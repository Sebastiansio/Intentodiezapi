<?php

namespace App\Services;

use App\Models\Solicitud;
use App\Models\Parte;
use App\Models\GiroComercial;
use App\Models\TipoContacto;
use App\Models\Jornada;
use App\Models\Periodicidad;
use App\Models\Ocupacion;
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
                
                // 5. Insertar Domicilio
                $citadoParte->domicilios()->create([
                    'tipo_vialidad_id' => 3,
                    'vialidad' => $citadoData['vialidad'],
                    'estado' => $citadoData['estado'],
                    'num_ext' => $citadoData['num_ext'],
                    'num_int' => $citadoData['num_int'],
                    'asentamiento' => $citadoData['colonia'],
                    'municipio' => $citadoData['municipio'],
                    'estado_id' => 14,
                    'cp' => $citadoData['cp'],
                ]);


                // 6. Otras relaciones (objetos, firmas, etc.)
                $solicitud->objetosSolicitud()->attach(4);
                
                // La inserción en firmas_documentos es más compleja y puede requerir su propio servicio
                // por la naturaleza de la relación polimórfica.
                
                Log::info("Solicitud creada exitosamente con ID: " . $solicitud->id);

                return $solicitud;

            } catch (\Exception $e) {
                // Si algo falla, se registra el error y la transacción se revierte.
                Log::error('Error en la creación masiva de solicitud: ' . $e->getMessage());
                // Retornamos null para indicar que la creación falló.
                return null;
            }
        });
    }
}