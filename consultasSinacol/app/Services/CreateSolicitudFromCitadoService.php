<?php

namespace App\Services;

use App\Solicitud;
use App\Parte;
use App\GiroComercial;
use App\TipoContacto;
use App\Jornada;
use App\Periodicidad;
use App\Ocupacion;
use App\TipoVialidad;
use App\Estado;    
use App\FirmaDocumento;
use App\Expediente;
use App\Audiencia;
use App\ConciliadorAudiencia;
use App\SalaAudiencia;
use App\AudienciaParte;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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

                Log::info('CreateSolicitud: Iniciando creación', ['citado' => $citadoData['nombre'] ?? 'UNKNOWN']);

                // 1. Insertar la Solicitud
                // Normalizar y convertir algunos campos antes de insertar
                $fechaConflicto = $this->parseDate($citadoData['fecha_conflicto'] ?? null);
                $salario = isset($citadoData['salario']) ? (float)$citadoData['salario'] : null;

                Log::debug('CreateSolicitud: Creando Solicitud', ['folio' => $max_folio, 'fecha_conflicto' => $fechaConflicto]);

                $solicitud = Solicitud::create([
                    'folio' => $max_folio,
                    'anio' => date('Y'),
                    'fecha_recepcion' => now(),
                    'fecha_conflicto' => $fechaConflicto,
                    'estatus_solicitud_id' => 1,
                    'centro_id' => 38,
                    // giro_comercial_id puede venir como ID o como nombre desde el formulario/import
                    'giro_comercial_id' => (is_numeric($citadoData['giro_comercial_id'] ?? null))
                        ? (int)$citadoData['giro_comercial_id']
                        : GiroComercial::where('nombre', $citadoData['giro_comercial_id'] ?? '')->value('id'),
                    'ratificada' => false,
                    'solicita_excepcion' => false,
                    'code_estatus' => 'sin_confirmar',
                    // aceptar tipo_solicitud_id del formulario si fue enviado
                    'tipo_solicitud_id' => isset($citadoData['tipo_solicitud_id']) ? (int)$citadoData['tipo_solicitud_id'] : 2,
                ]);

                Log::info('CreateSolicitud: Solicitud creada con ID ' . $solicitud->id);
                $solicitanteData = $citadoData['solicitante'] ?? [];

                // Determinar tipo_persona (1=fisica, 2=moral). Fallback a 1.
                $tipoPersona = isset($solicitanteData['tipo_persona_id']) ? (int)$solicitanteData['tipo_persona_id'] : 1;

                // Campos para crear la parte solicitante
                $parteFields = [
                    'tipo_parte_id' => 1, // 1 = Solicitante
                    'tipo_persona_id' => $tipoPersona,
                ];

                if ($tipoPersona === 2) {
                    // Persona moral
                    $parteFields['nombre_comercial'] = $solicitanteData['nombre_comercial'] ?? null;
                    $parteFields['rfc'] = $solicitanteData['rfc'] ?? null;
                } else {
                    // Persona física
                    $parteFields['nombre'] = $solicitanteData['nombre'] ?? null;
                    $parteFields['primer_apellido'] = $solicitanteData['primer_apellido'] ?? null;
                    $parteFields['segundo_apellido'] = $solicitanteData['segundo_apellido'] ?? null;
                    $parteFields['curp'] = $solicitanteData['curp'] ?? null;
                    // rfc para persona fisica (campo opcional en el formulario)
                    if (!empty($solicitanteData['rfc'])) {
                        $parteFields['rfc'] = $solicitanteData['rfc'];
                    } elseif (!empty($solicitanteData['rfc_fisica'])) {
                        $parteFields['rfc'] = $solicitanteData['rfc_fisica'];
                    }
                }

                // Crear la parte solicitante
                $parteSolicitante = $solicitud->partes()->create($parteFields);

                // Domicilio del solicitante (si viene)
                $domicilios = $solicitanteData['domicilios'] ?? [];
                if (!empty($domicilios) && is_array($domicilios)) {
                    $dom = $domicilios[0];
                    $tipoVialidadId = isset($dom['tipo_vialidad_id']) ? $dom['tipo_vialidad_id'] : (isset($dom['tipo_vialidad']) ? TipoVialidad::where('nombre', 'ilike', $dom['tipo_vialidad'])->value('id') : null);
                    $estadoId = isset($dom['estado_id']) ? $dom['estado_id'] : (isset($dom['estado']) ? Estado::where('nombre', 'ilike', $dom['estado'])->value('id') : null);
                    
                    // Obtener el nombre de tipo_vialidad si solo viene el ID
                    $tipoVialidadNombre = $dom['tipo_vialidad'] ?? null;
                    if (!$tipoVialidadNombre && $tipoVialidadId) {
                        $tipoVialidadNombre = TipoVialidad::find($tipoVialidadId)->nombre ?? 'CALLE';
                    }
                    if (!$tipoVialidadNombre) {
                        $tipoVialidadNombre = 'CALLE';
                    }
                    
                    // Obtener nombre de estado si solo viene el ID
                    $estadoNombre = $dom['estado'] ?? null;
                    if (!$estadoNombre && $estadoId) {
                        $estadoNombre = Estado::find($estadoId)->nombre ?? null;
                    }

                    $parteSolicitante->domicilios()->create([
                        'estado_id' => $estadoId ?? 14,
                        'municipio' => $dom['municipio'] ?? null,
                        'cp' => $dom['cp'] ?? null,
                        'tipo_vialidad_id' => $tipoVialidadId ?? 3,
                        'tipo_vialidad' => $tipoVialidadNombre,
                        'vialidad' => $dom['vialidad'] ?? null,
                        'num_ext' => $dom['num_ext'] ?? null,
                        'num_int' => $dom['num_int'] ?? null,
                        'asentamiento' => $dom['asentamiento'] ?? null,
                        'centro_id' => $solicitud->centro_id ?? null,
                        'estado' => $estadoNombre,
                    ]);
                }

                // Contactos del solicitante
                $contactos = $solicitanteData['contactos'] ?? [];
                if (!empty($contactos) && is_array($contactos)) {
                    foreach ($contactos as $cont) {
                        // Esperamos ['tipo_contacto_id'=>int, 'contacto'=>string]
                        if (!empty($cont['contacto'])) {
                            $parteSolicitante->contactos()->create([
                                'tipo_contacto_id' => $cont['tipo_contacto_id'] ?? 1,
                                'contacto' => $cont['contacto'],
                            ]);
                        }
                    }
                }

                // === FIN: Lógica del SOLICITANTE ===

                // 2. Insertar la Parte (Citado) usando la relación de Eloquent
                // Normalizar campos del citado
                $citadoNombre = $citadoData['nombre'] ?? null;
                $citadoPrimer = $citadoData['primer_apellido'] ?? null;
                $citadoSegundo = $citadoData['segundo_apellido'] ?? null;
                $citadoCurp = $citadoData['curp'] ?? null;

                $citadoParte = $solicitud->partes()->create([
                    'nombre' => $citadoNombre,
                    'primer_apellido' => $citadoPrimer,
                    'segundo_apellido' => $citadoSegundo,
                    'tipo_parte_id' => 2, // 2 = Citado
                    'tipo_persona_id' => 1, // 1 = Física
                    'curp' => $citadoCurp,
                ]);

                // 3. Insertar el Contacto usando relaciones polimórficas
                // Crear contacto del citado: aceptar 'correo' o 'contacto' o 'telefono'
                $contactValue = $citadoData['correo'] ?? ($citadoData['contacto'] ?? ($citadoData['telefono'] ?? null));
                $tipoContactoName = $citadoData['tipo_contacto'] ?? null;
                $tipoContactoId = $tipoContactoName ? TipoContacto::where('nombre', strtoupper($tipoContactoName))->value('id') : null;
                if ($contactValue) {
                    $citadoParte->contactos()->create([
                        'tipo_contacto_id' => $tipoContactoId ?? TipoContacto::where('nombre', 'TELEFONO')->value('id') ?? 1,
                        'contacto' => (string)$contactValue,
                    ]);
                }

                // 4. Insertar Datos Laborales
                Log::debug('CreateSolicitud: Creando DatosLaborales', ['puesto' => $citadoData['puesto'] ?? null]);
                
                $citadoParte->datosLaborales()->create([
                    'nss' => $citadoData['nss'] ?? null,
                    'puesto' => $citadoData['puesto'] ?? null,
                    'remuneracion' => $citadoData['salario'] ?? null,
                    'fecha_ingreso' => $this->parseDate($citadoData['fecha_ingreso'] ?? null),
                    'fecha_salida' => $this->parseDate($citadoData['fecha_salida'] ?? null),
                    'jornada_id' => ($citadoData['jornada'] ?? null) ? Jornada::where('nombre', strtoupper((string)$citadoData['jornada']))->value('id') : null,
                    'periodicidad_id' => ($citadoData['periocidad'] ?? null) ? Periodicidad::where('nombre', 'ilike', $citadoData['periocidad'])->value('id') : null,
                    'ocupacion_id' => ($citadoData['puesto'] ?? null) ? Ocupacion::where('nombre', 'ilike', '%' . $citadoData['puesto'] . '%')->value('id') : null,
                    'labora_actualmente' => true,
                    'horas_semanales' => $citadoData['horas_sem'] ?? null,
                    'horario_laboral' => $citadoData['horario_laboral'] ?? null,
                    'horario_comida' => $citadoData['horario_comida'] ?? null,
                    'comida_dentro' => true,
                    'dias_descanso' => $citadoData['dias_descanso'] ?? null,
                    'dias_vacaciones' => $citadoData['dias_vacaciones'] ?? null,
                    'dias_aguinaldo' => $citadoData['dias_aguinaldo'] ?? null,
                    'prestaciones_adicionales' => $citadoData['prestaciones_adicionales'] ?? null,
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
                // Si el formulario incluyó objetos de solicitud (IDs), los adjuntamos.
                if (!empty($citadoData['objeto_solicitudes']) && is_array($citadoData['objeto_solicitudes'])) {
                    $solicitud->objeto_solicitudes()->sync($citadoData['objeto_solicitudes']);
                } else {
                    $solicitud->objeto_solicitudes()->attach(4);
                }



                // Registrar documento de firma asociado al citado (firmable = Parte)
                FirmaDocumento::create([
                    'firmable_id'   => $citadoParte->id,  // Citado creado arriba
                    'firmable_type' => Parte::class,       // Clase fully-qualified para morph
                    'solicitud_id'  => $solicitud->id,
                ]);
                
                // La inserción en firmas_documentos es más compleja y puede requerir su propio servicio
                // por la naturaleza de la relación polimórfica.

                Log::info('CreateSolicitud: Proceso completado con éxito', ['solicitud_id' => $solicitud->id]);
                
                // Crear audiencia automáticamente después de crear la solicitud
                try {
                    Log::info('CreateSolicitud: Iniciando creación de audiencia', ['solicitud_id' => $solicitud->id]);
                    $this->createAudiencia($solicitud);
                    Log::info('CreateSolicitud: Audiencia creada exitosamente', ['solicitud_id' => $solicitud->id]);
                } catch (\Exception $e) {
                    Log::error('Error al crear audiencia para solicitud: ' . $e->getMessage(), [
                        'solicitud_id' => $solicitud->id,
                        'trace' => $e->getTraceAsString()
                    ]);
                    // No lanzamos la excepción para que la solicitud se cree aunque falle la audiencia
                }
                
                return $solicitud;
                
            } catch (\Exception $e) {
                // Si algo falla, se registra el error y la transacción se revierte.
                Log::error('Error en la creación de solicitud: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                    'data' => $citadoData
                ]);
                // Retornamos null para indicar que la creación falló.
                return null;
            }
        });
    }

    /**
     * Intenta parsear una fecha en varios formatos y devolver YYYY-mm-dd o null.
     */
    private function parseDate($value)
    {
        if (empty($value)) {
            return null;
        }

        $formats = [
            'd/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i', 'd-m-Y H:i:s'
        ];

        foreach ($formats as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, trim($value));
                if ($dt) {
                    return $dt->toDateString();
                }
            } catch (\Exception $e) {
                // intentar siguiente formato
            }
        }

        // Último recurso: intentar que Carbon lo autodetecte
        try {
            $dt = new Carbon($value);
            return $dt->toDateString();
        } catch (\Exception $e) {
            Log::warning('parseDate: no se pudo parsear fecha', ['value' => $value]);
            return null;
        }
    }

    /**
     * Crea una audiencia para una solicitud específica
     * Valores hardcodeados para pruebas
     */
    private function createAudiencia(Solicitud $solicitud)
    {
        DB::beginTransaction();
        try {
            Log::info('CreateAudiencia: Iniciando creación de audiencia', ['solicitud_id' => $solicitud->id]);
            
            // Refrescar la solicitud para obtener todas las relaciones
            $solicitud = Solicitud::with('partes')->find($solicitud->id);
            
            // Crear el expediente de la solicitud
            $anio = date("Y");
            
            // Sistema robusto para generar folio único
            $expediente = null;
            $intentos = 0;
            $max_intentos = 20;
            
            while ($expediente === null && $intentos < $max_intentos) {
                try {
                    // Obtener el max dentro del loop para tener el valor más actualizado
                    $max_consecutivo = \App\Expediente::where('anio', $anio)->max('consecutivo');
                    $consecutivo = ($max_consecutivo ?? 0) + 1 + $intentos; // Agregar intentos para evitar colisiones
                    $folio = "EXP-" . $anio . "-" . str_pad($consecutivo, 5, "0", STR_PAD_LEFT);
                    
                    Log::info('CreateAudiencia: Intentando crear expediente', [
                        'intento' => $intentos + 1,
                        'consecutivo' => $consecutivo,
                        'folio' => $folio,
                        'max_consecutivo_actual' => $max_consecutivo
                    ]);

                    // Verificar si el folio ya existe antes de crear (doble verificación)
                    if (\App\Expediente::where('folio', $folio)->exists()) {
                        $intentos++;
                        Log::warning('CreateAudiencia: Folio ya existe (verificación previa), reintentando', [
                            'folio' => $folio,
                            'intento' => $intentos
                        ]);
                        usleep(50000); // Esperar 50ms antes de reintentar
                        continue;
                    }

                    $expediente = \App\Expediente::create([
                        "solicitud_id" => $solicitud->id,
                        "folio" => $folio,
                        "anio" => $anio,
                        "consecutivo" => $consecutivo
                    ]);
                    
                    Log::info('CreateAudiencia: Expediente creado exitosamente', [
                        'expediente_id' => $expediente->id,
                        'folio' => $folio,
                        'consecutivo' => $consecutivo
                    ]);
                    
                } catch (\App\Exceptions\FolioExpedienteExistenteException $e) {
                    $intentos++;
                    Log::warning('CreateAudiencia: FolioExpedienteExistenteException capturada, reintentando', [
                        'intento' => $intentos,
                        'mensaje' => $e->getMessage()
                    ]);
                    usleep(50000); // Esperar 50ms antes de reintentar
                } catch (\Exception $e) {
                    $intentos++;
                    Log::warning('CreateAudiencia: Error general al crear expediente, reintentando', [
                        'intento' => $intentos,
                        'error' => $e->getMessage()
                    ]);
                    usleep(50000); // Esperar 50ms antes de reintentar
                }
            }
            
            if ($expediente === null) {
                throw new \Exception('No se pudo generar un folio único para el expediente después de ' . $max_intentos . ' intentos');
            }

            // Marcar partes como ratificadas si no tienen documentos
            foreach ($solicitud->partes as $parte) {
                if (count($parte->documentos) == 0) {
                    $parte->ratifico = true;
                    $parte->save();
                }
            }

            // Obtener domicilio del citado
            $domicilio_citado = null;
            foreach ($solicitud->partes as $parte) {
                if ($parte->tipo_parte_id == 2) {
                    $domicilio_citado = $parte->domicilios->last();
                    break;
                }
            }

            // Actualizar la solicitud con ratificación
            $fecha_ratificacion = now();
            
            // HARDCODED: fecha_vigencia para pruebas (45 días desde hoy)
            $fecha_vigencia = Carbon::now()->addDays(45)->toDateString();
            
            // HARDCODED: user_id para pruebas
            $user_id = 1;
            
            $solicitud->update([
                "estatus_solicitud_id" => 2,
                "user_id" => $user_id,
                "ratificada" => true,
                "fecha_ratificacion" => $fecha_ratificacion,
                "fecha_vigencia" => $fecha_vigencia,
                "inmediata" => false
            ]);

            // Obtener el siguiente consecutivo disponible para audiencias
            $max_folio_audiencia = \App\Audiencia::where('anio', $anio)->max('folio');
            $consecutivo_audiencia = ($max_folio_audiencia ?? 0) + 1;
            
            Log::info('CreateAudiencia: Generando folio de audiencia', [
                'consecutivo_audiencia' => $consecutivo_audiencia,
                'max_folio_anterior' => $max_folio_audiencia
            ]);
            
            // HARDCODED: Valores para la audiencia
            $fecha_audiencia = Carbon::now()->addDays(15)->toDateString(); // 15 días desde hoy
            $hora_inicio = "10:00:00";
            $hora_fin = "12:00:00";
            $conciliador_id = 1; // ID de conciliador hardcodeado
            $sala_id = 1; // ID de sala hardcodeada
            $multiple = false;
            $fecha_cita = null;

            // Crear el registro de la audiencia
            $audiencia = \App\Audiencia::create([
                "expediente_id" => $expediente->id,
                "multiple" => $multiple,
                "fecha_audiencia" => $fecha_audiencia,
                "fecha_limite_audiencia" => null,
                "hora_inicio" => $hora_inicio,
                "hora_fin" => $hora_fin,
                "conciliador_id" => $conciliador_id,
                "numero_audiencia" => 1,
                "reprogramada" => false,
                "anio" => $anio,
                "folio" => $consecutivo_audiencia,
                "encontro_audiencia" => true,
                "fecha_cita" => $fecha_cita
            ]);
            
            Log::info('CreateAudiencia: Audiencia creada', ['audiencia_id' => $audiencia->id]);

            // Crear relación conciliador-audiencia
            \App\ConciliadorAudiencia::create([
                "audiencia_id" => $audiencia->id,
                "conciliador_id" => $conciliador_id,
                "solicitante" => true
            ]);

            // Crear relación sala-audiencia
            \App\SalaAudiencia::create([
                "audiencia_id" => $audiencia->id,
                "sala_id" => $sala_id,
                "solicitante" => true
            ]);

            // Guardar todas las Partes en la audiencia
            foreach ($solicitud->partes as $parte) {
                \App\AudienciaParte::create([
                    "audiencia_id" => $audiencia->id,
                    "parte_id" => $parte->id
                ]);
            }

            DB::commit();
            
            Log::info('CreateAudiencia: Proceso completado exitosamente', [
                'audiencia_id' => $audiencia->id,
                'solicitud_id' => $solicitud->id
            ]);
            
            return $audiencia;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en createAudiencia: ' . $e->getMessage(), [
                'solicitud_id' => $solicitud->id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; // Re-lanzar para que sea capturado en el try-catch de create()
        }
    }
}