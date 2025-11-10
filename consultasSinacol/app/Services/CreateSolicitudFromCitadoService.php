<?php

namespace App\Services;

use App\Solicitud;
use App\Parte;
use App\GiroComercial;
use App\TipoContacto;
use App\Jornada;
use App\Periodicidad;
use App\Ocupacion;
use App\TipoVialidad; // Asegúrate de importar tus modelos
use App\Estado;    
use App\FirmaDocumento;
  // Asegúrate de importar tus modelos
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateSolicitudFromCitadoService
{
    /**
     * Crea una solicitud completa y todos sus registros relacionados
     * a partir de los datos de la tabla temporal 'citado'.
     *
     * @param array $citadoData Los datos de una fila de la tabla 'citado'.
     * @return Solicitud|null El modelo de la solicitud creada o null si falla.
     */
    public function create(array $citadoData): ?Solicitud
    {
        // Envolvemos todo el proceso en una transacción de base de datos.
        // Si algo falla, todos los cambios se revierten automáticamente.
        // Esta es la MEJOR PRÁCTICA (transacción por fila).
        // Asegúrate de que el script que llama a este método NO tenga su propia transacción.
        return DB::transaction(function () use ($citadoData) {
            try {
                // Obtener el siguiente folio de manera segura
                $max_folio = Solicitud::where('anio', date('Y'))->max('folio') + 1;


                // 1. Insertar la Solicitud
                $solicitud = Solicitud::create([
                    'folio' => $max_folio,
                    'anio' => date('Y'),
                    'fecha_recepcion' => now(),
                    'fecha_conflicto' => $citadoData['fecha_conflicto'],
                    'estatus_solicitud_id' => 1,
                    'centro_id' => 38,
                    'giro_comercial_id' => GiroComercial::where('nombre', $citadoData['giro_comercial_id'])->value('id'),
                    'ratificada' => false,
                    'solicita_excepcion' => false,
                    'code_estatus' => 'sin_confirmar',
                    'tipo_solicitud_id' => 2,
                ]);


                // === INICIO: Lógica del SOLICITANTE (Patrón) [HARDCODEADO] ===

                // Reemplaza: INSERT INTO partes (tipo_parte_id = 1) ...
                $parteSolicitante = $solicitud->partes()->create([
                    'nombre_comercial' => 'EMPRESA DE ROPA MODERNA SA DE CV (Prueba)',
                    'rfc' => 'AASS890220QC3',
                    'tipo_parte_id' => 1, // 1 = Solicitante
                    'tipo_persona_id' => 2, // 2 = Moral
                ]);
                
                // Reemplaza: INSERT INTO domicilios (solicitante) ...
                $parteSolicitante->domicilios()->create([
                    'estado_id' => 14, // ID de Estado
                    'municipio' => 'IZTACALCO', 
                    'cp' => '52371', 
                    'tipo_vialidad_id' => 3, // ID Tipo Vialidad (CALLE)
                    'tipo_vialidad' => 'CALLE', // <-- Esta es la columna 'tipo_vialidad' de texto
                    'vialidad' => 'CALLE', // Nombre Vialidad (de tu SQL)
                    'num_ext' => '5510', 
                    'num_int' => '25-A', 
                    'asentamiento' => 'CONDESA', 
                    'centro_id' => 38, 
                    'estado' => 'jalisco', 
                ]);

                
                // Reemplaza: INSERT INTO contactos (solicitante) ...
                $parteSolicitante->contactos()->create([
                    'tipo_contacto_id' => 1, // Asumiendo 1 = Celular
                    'contacto' => '1234567890', // Teléfono (de tu SQL)
                ]);
                $parteSolicitante->contactos()->create([
                    'tipo_contacto_id' => 3, // Asumiendo 3 = Email
                    'contacto' => 'citado@gmail.com', // Email (de tu SQL)
                ]);

                // === FIN: Lógica del SOLICITANTE (Patrón) [HARDCODEADO] ===

                // 2. Insertar la Parte (Citado) usando la relación de Eloquent
                $citadoParte = $solicitud->partes()->create([
                    'nombre' => $citadoData['nombre'],
                    'primer_apellido' => $citadoData['primer_apellido'],
                    'segundo_apellido' => $citadoData['segundo_apellido'],
                    'tipo_parte_id' => 2, // 2 = Citado
                    'tipo_persona_id' => 1, // 1 = Física
                    'curp' => $citadoData['curp'],
                ]);

                // 3. Insertar el Contacto usando relaciones polimórficas
                $citadoParte->contactos()->create([
                    'tipo_contacto_id' => TipoContacto::where('nombre', strtoupper($citadoData['tipo_contacto']))->value('id'),
                    'contacto' => $citadoData['correo'],
                ]);

                // 4. Insertar Datos Laborales
                $citadoParte->datosLaborales()->create([
                    'nss' => $citadoData['nss'],
                    'puesto' => $citadoData['puesto'],
                    'remuneracion' => $citadoData['salario'],
                    'fecha_ingreso' => $citadoData['fecha_ingreso'],
                    'fecha_salida' => $citadoData['fecha_salida'],
                    'jornada_id' => Jornada::where('nombre', strtoupper($citadoData['jornada']))->value('id'),
                    'periodicidad_id' => Periodicidad::where('nombre', 'ilike', $citadoData['periocidad'])->value('id'),
                    'ocupacion_id' => Ocupacion::where('nombre', 'ilike', '%' . $citadoData['puesto'] . '%')->value('id'),
                    'labora_actualmente' => true,
                    'horas_semanales' => $citadoData['horas_sem'],
                    'horario_laboral' => $citadoData['horario_laboral'],
                    'horario_comida' => $citadoData['horario_comida'],
                    'comida_dentro' => true,
                    'dias_descanso' => $citadoData['dias_descanso'],
                    'dias_vacaciones' => $citadoData['dias_vacaciones'],
                    'dias_aguinaldo' => $citadoData['dias_aguinaldo'],
                    'prestaciones_adicionales' => $citadoData['prestaciones_adicionales'],
                ]);
                
                // 5. Insertar Domicilio (SECCIÓN MEJORADA - normaliza claves y asegura valores no nulos)
                // Aceptamos variantes de nombre de columna que pueden venir del Excel/CSV
                $tipoVialidadTexto = isset($citadoData['tipo_vialidad']) ? trim((string)$citadoData['tipo_vialidad']) : null;
                if (empty($tipoVialidadTexto)) {
                    $tipoVialidadTexto = isset($citadoData['domicilio_tipo_vialidad']) ? trim((string)$citadoData['domicilio_tipo_vialidad']) : null;
                }
                // Si aún está vacío, usamos un fallback claro
                if (empty($tipoVialidadTexto)) {
                    $tipoVialidadTexto = 'OTRO';
                    Log::warning('Domicilio: tipo_vialidad ausente en datos del citado, aplicando fallback OTRO', ['curp' => $citadoData['curp'] ?? null, 'data' => $citadoData]);
                }

                // Localizamos el ID del tipo de vialidad (si existe), si no, usamos 3 (CALLE) por compatibilidad
                $tipoVialidadId = TipoVialidad::where('nombre', 'ilike', $tipoVialidadTexto)->value('id') ?? 3;

                // Normalizamos estado / municipio / vialidad con posibles variantes de nombres
                $estadoTexto = isset($citadoData['estado']) ? trim((string)$citadoData['estado']) : null;
                if (empty($estadoTexto)) {
                    $estadoTexto = isset($citadoData['domicilio_estado']) ? trim((string)$citadoData['domicilio_estado']) : null;
                }
                $estadoId = Estado::where('nombre', 'ilike', $estadoTexto)->value('id') ?? 14;

                $vialidad = isset($citadoData['vialidad']) ? trim((string)$citadoData['vialidad']) : (isset($citadoData['domicilio_vialidad']) ? trim((string)$citadoData['domicilio_vialidad']) : null);
                $num_ext = $citadoData['num_ext'] ?? ($citadoData['domicilio_num_ext'] ?? null);
                $num_int = $citadoData['num_int'] ?? ($citadoData['domicilio_num_int'] ?? null);
                $colonia = $citadoData['colonia'] ?? ($citadoData['domicilio_asentamiento'] ?? ($citadoData['asentamiento'] ?? null));
                $municipio = $citadoData['municipio'] ?? ($citadoData['domicilio_municipio'] ?? null);
                $cp = $citadoData['cp'] ?? ($citadoData['domicilio_cp'] ?? null);

                // Creamos el domicilio asegurando que 'tipo_vialidad' nunca sea nulo (DB lo exige)
                $citadoParte->domicilios()->create([
                    'tipo_vialidad_id' => $tipoVialidadId,
                    'vialidad' => $vialidad,
                    'estado' => $estadoTexto,
                    'num_ext' => $num_ext,
                    'num_int' => $num_int,
                    'asentamiento' => $colonia,
                    'municipio' => $municipio,
                    'tipo_vialidad' => $tipoVialidadTexto,
                    'estado_id' => $estadoId,
                    'cp' => $cp,
                ]);


                // 6. Otras relaciones (objetos, firmas, etc.)
                $solicitud->objeto_solicitudes()->attach(4);



                // Registrar documento de firma asociado al citado (firmable = Parte)
                FirmaDocumento::create([
                    'firmable_id'   => $citadoParte->id,  // Citado creado arriba
                    'firmable_type' => Parte::class,       // Clase fully-qualified para morph
                    'solicitud_id'  => $solicitud->id,
                ]);
                
                // La inserción en firmas_documentos es más compleja y puede requerir su propio servicio
                // por la naturaleza de la relación polimórfica.
                
                Log::info("Solicitud creada exitosamente con ID: " . $solicitud->id);

                return $solicitud;

            } catch (\Exception $e) {
                // Si algo falla, se registra el error y la transacción se revierte.
                Log::error('Error en la creación masiva de solicitud: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(), // Agregamos más contexto al log
                    'data' => $citadoData // Registramos los datos que fallaron
                ]);
                // Retornamos null para indicar que la creación falló.
                return null;
            }
        });
    }
}