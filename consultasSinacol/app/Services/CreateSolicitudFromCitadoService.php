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
use App\Centro;
use App\TipoParte;
use App\Sala;
use App\Compareciente;
use App\ConceptoPagoResolucion;
use App\EtapaResolucionAudiencia;
use App\ResolucionPartes;
use App\ResolucionParteConcepto;
use App\ResolucionPagoDiferido;
use App\Documento;
use App\Events\GenerateDocumentResolution;
use App\Http\Controllers\ContadorController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CreateSolicitudFromCitadoService
{
    /**
     * El concepto de pago de deducciones es el ID 13 en el catálogo concepto_pago_resoluciones
     */
    const CONCEPTO_PAGO_DEDUCCION_ID = 13;

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
                    $this->createAudiencia($solicitud, $citadoData);
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
     * Obtiene las fechas para la audiencia
     * Retorna un array con [fecha_audiencia, hora_inicio, hora_fin, fecha_resolucion]
     */
    private function getDatosFechasAudiencia()
    {
        // Por defecto: audiencia para hoy a las 10:00
        $fecha_audiencia = Carbon::now(); // Retornar objeto Carbon, no string
        $hora_inicio = '10:00:00';
        $hora_fin = '11:00:00'; // Duración de 1 hora (era 12:00:00)
        $fecha_resolucion = Carbon::now()->addDays(1)->format('Y-m-d H:i:00');
        
        return [$fecha_audiencia, $hora_inicio, $hora_fin, $fecha_resolucion];
    }

    /**
     * Crea el representante legal del solicitante
     * 
     * @param Solicitud $solicitud
     * @param Audiencia $audiencia
     * @param array $datosRepresentante Datos del representante legal desde el formulario
     * @return Parte|null
     */
    private function crearRepresentanteLegal(Solicitud $solicitud, Audiencia $audiencia, array $datosRepresentante = []): ?Parte
    {
        try {
            Log::info('CrearRepresentante: Iniciando', [
                'solicitud_id' => $solicitud->id,
                'tiene_datos' => !empty($datosRepresentante)
            ]);
            
            // Si no hay datos de representante, no crear
            if (empty($datosRepresentante)) {
                Log::info('CrearRepresentante: No se proporcionaron datos de representante');
                return null;
            }
            
            // Buscar la parte representada (solicitante)
            $parteRepresentada = null;
            foreach ($solicitud->partes as $parte) {
                if ($parte->tipo_parte_id == 1) { // 1 = Solicitante
                    $parteRepresentada = $parte->id;
                    break;
                }
            }
            
            if (!$parteRepresentada) {
                Log::warning('CrearRepresentante: No se encontró solicitante');
                return null;
            }
            
            // Crear representante con los datos del formulario
            $representante = Parte::create([
                'solicitud_id' => $solicitud->id,
                'tipo_parte_id' => 3, // 3 = Representante
                'tipo_persona_id' => 1, // 1 = Física
                'rfc' => $datosRepresentante['rfc'] ?? '',
                'curp' => $datosRepresentante['curp'] ?? '',
                'nombre' => strtoupper($datosRepresentante['nombre'] ?? 'REPRESENTANTE'),
                'primer_apellido' => strtoupper($datosRepresentante['primer_apellido'] ?? 'LEGAL'),
                'segundo_apellido' => strtoupper($datosRepresentante['segundo_apellido'] ?? ''),
                'fecha_nacimiento' => $this->parseDate($datosRepresentante['fecha_nacimiento'] ?? null),
                'genero_id' => $datosRepresentante['genero_id'] ?? 1,
                'clasificacion_archivo_id' => null,
                'detalle_instrumento' => 'Poder General para Pleitos y Cobranzas',
                'feha_instrumento' => now()->format('Y-m-d'),
                'parte_representada_id' => $parteRepresentada,
                'representante' => true,
            ]);
            
            // Crear contacto del representante si se proporcionó
            if (!empty($datosRepresentante['telefono']) || !empty($datosRepresentante['correo_electronico'])) {
                try {
                    // Buscar tipo de contacto por defecto (teléfono móvil)
                    $tipoContacto = TipoContacto::where('nombre', 'Teléfono móvil')->first();
                    
                    if (!empty($datosRepresentante['telefono']) && $tipoContacto) {
                        DB::table('contactos')->insert([
                            'parte_id' => $representante->id,
                            'tipo_contacto_id' => $tipoContacto->id,
                            'contacto' => $datosRepresentante['telefono'],
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    
                    // Email
                    if (!empty($datosRepresentante['correo_electronico'])) {
                        $tipoEmail = TipoContacto::where('nombre', 'Correo electrónico')->first();
                        if ($tipoEmail) {
                            DB::table('contactos')->insert([
                                'parte_id' => $representante->id,
                                'tipo_contacto_id' => $tipoEmail->id,
                                'contacto' => $datosRepresentante['correo_electronico'],
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('CrearRepresentante: Error al crear contactos', ['error' => $e->getMessage()]);
                }
            }
            
            // Crear compareciente para el representante
            Compareciente::create([
                'parte_id' => $representante->id,
                'audiencia_id' => $audiencia->id,
                'presentado' => true
            ]);
            
            Log::info('CrearRepresentante: Representante creado exitosamente', [
                'representante_id' => $representante->id,
                'nombre' => $representante->nombre . ' ' . $representante->primer_apellido,
                'curp' => $representante->curp
            ]);
            
            return $representante;
            
        } catch (\Exception $e) {
            Log::error('CrearRepresentante: Error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'solicitud_id' => $solicitud->id
            ]);
            return null;
        }
    }

    /**
     * Crea las comparecencias para todas las partes de la audiencia
     * 
     * @param Solicitud $solicitud
     * @param Audiencia $audiencia
     */
    private function crearComparecencias(Solicitud $solicitud, Audiencia $audiencia): void
    {
        try {
            Log::info('CrearComparecencias: Iniciando', ['audiencia_id' => $audiencia->id]);
            
            foreach ($solicitud->partes as $parte) {
                // Solo crear comparecencia para personas físicas (no representantes ya creados)
                if ($parte->tipo_persona_id == 1 && $parte->tipo_parte_id != 3) {
                    $existeComparecencia = Compareciente::where('parte_id', $parte->id)
                        ->where('audiencia_id', $audiencia->id)
                        ->exists();
                    
                    if (!$existeComparecencia) {
                        Compareciente::create([
                            'parte_id' => $parte->id,
                            'audiencia_id' => $audiencia->id,
                            'presentado' => true
                        ]);
                        
                        Log::debug('CrearComparecencias: Compareciente creado', [
                            'parte_id' => $parte->id,
                            'tipo_parte_id' => $parte->tipo_parte_id
                        ]);
                    }
                }
            }
            
            Log::info('CrearComparecencias: Completado', ['audiencia_id' => $audiencia->id]);
            
        } catch (\Exception $e) {
            Log::error('CrearComparecencias: Error', [
                'error' => $e->getMessage(),
                'audiencia_id' => $audiencia->id
            ]);
        }
    }

    /**
     * Crea las manifestaciones (etapas de resolución) de la audiencia
     * 
     * @param Audiencia $audiencia
     */
    private function crearManifestaciones(Audiencia $audiencia): void
    {
        try {
            Log::info('CrearManifestaciones: Iniciando', ['audiencia_id' => $audiencia->id]);
            
            // Obtener las etapas de resolución que existen en la base de datos
            $etapas_existentes = DB::table('etapa_resoluciones')
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
            
            if (empty($etapas_existentes)) {
                Log::warning('CrearManifestaciones: No se encontraron etapas de resolución en la BD');
                return;
            }
            
            Log::debug('CrearManifestaciones: Etapas encontradas', ['etapas' => $etapas_existentes]);
            
            // Crear manifestaciones solo para las etapas que existen
            foreach ($etapas_existentes as $etapa_id) {
                \App\EtapaResolucionAudiencia::create([
                    'etapa_resolucion_id' => $etapa_id,
                    'audiencia_id' => $audiencia->id,
                    'evidencia' => 'true', // Valor por defecto para todas
                ]);
            }
            
            Log::info('CrearManifestaciones: Completado', [
                'audiencia_id' => $audiencia->id,
                'total_etapas' => count($etapas_existentes)
            ]);
            
        } catch (\Exception $e) {
            Log::error('CrearManifestaciones: Error', [
                'error' => $e->getMessage(),
                'audiencia_id' => $audiencia->id,
                'linea' => $e->getLine()
            ]);
            // No lanzar excepción para que continúe el proceso
        }
    }

    /**
     * Crea la resolución de partes (terminación bilateral)
     * 
     * @param Solicitud $solicitud
     * @param Audiencia $audiencia
     */
    private function crearResolucionPartes(Solicitud $solicitud, Audiencia $audiencia): void
    {
        try {
            Log::info('CrearResolucionPartes: Iniciando', ['audiencia_id' => $audiencia->id]);
            
            $solicitante = $solicitud->partes()->where('tipo_parte_id', 1)->first();
            $citado = $solicitud->partes()->where('tipo_parte_id', 2)->first();
            
            if (!$solicitante || !$citado) {
                Log::warning('CrearResolucionPartes: Falta solicitante o citado');
                return;
            }
            
            ResolucionPartes::create([
                'audiencia_id' => $audiencia->id,
                'parte_solicitante_id' => $solicitante->id,
                'parte_solicitada_id' => $citado->id,
                'terminacion_bilateral_id' => 3, // 3 = Convenio
            ]);
            
            Log::info('CrearResolucionPartes: Completado', ['audiencia_id' => $audiencia->id]);
            
        } catch (\Exception $e) {
            Log::error('CrearResolucionPartes: Error', [
                'error' => $e->getMessage(),
                'audiencia_id' => $audiencia->id
            ]);
        }
    }

    /**
     * Crea los conceptos de pago de la resolución
     * 
     * @param Solicitud $solicitud
     * @param Audiencia $audiencia
     * @param array $citadoData Datos del citado que pueden incluir conceptos
     */
    private function crearConceptosPago(Solicitud $solicitud, Audiencia $audiencia, array $citadoData): void
    {
        try {
            Log::info('CrearConceptosPago: Iniciando', ['audiencia_id' => $audiencia->id]);
            
            $citado = $solicitud->partes()->where('tipo_parte_id', 2)->first();
            $solicitante = $solicitud->partes()->where('tipo_parte_id', 1)->first();
            
            if (!$citado || !$solicitante) {
                Log::warning('CrearConceptosPago: Falta citado o solicitante');
                return;
            }
            
            $audiencia_parte = $citado->audienciaParte->first();
            if (!$audiencia_parte) {
                Log::warning('CrearConceptosPago: No se encontró audiencia_parte');
                return;
            }
            
            // Conceptos por defecto (puedes modificar según datos del citado)
            // Si vienen conceptos en $citadoData, se usan; si no, valores por defecto
            $conceptos = $citadoData['conceptos'] ?? [
                ['concepto_id' => 1, 'monto' => 5000.00, 'dias' => null], // Salarios caídos
                ['concepto_id' => 2, 'monto' => 2000.00, 'dias' => null], // Indemnización
            ];
            
            $montoTotal = 0;
            
            foreach ($conceptos as $concepto) {
                if (empty($concepto['concepto_id'])) {
                    continue;
                }
                
                $monto = floatval($concepto['monto'] ?? 0);
                
                ResolucionParteConcepto::create([
                    'resolucion_partes_id' => null,
                    'audiencia_parte_id' => $audiencia_parte->id,
                    'concepto_pago_resoluciones_id' => $concepto['concepto_id'],
                    'conciliador_id' => $audiencia->conciliador_id,
                    'dias' => $concepto['dias'] ?? null,
                    'monto' => $monto,
                    'otro' => $concepto['otro'] ?? ''
                ]);
                
                // Si es deducción, restar; si no, sumar
                if ($concepto['concepto_id'] == self::CONCEPTO_PAGO_DEDUCCION_ID) {
                    $montoTotal -= $monto;
                } else {
                    $montoTotal += $monto;
                }
                
                Log::debug('CrearConceptosPago: Concepto creado', [
                    'concepto_id' => $concepto['concepto_id'],
                    'monto' => $monto
                ]);
            }
            
            // Crear pago diferido
            if ($montoTotal > 0) {
                $fecha_audiencia = $audiencia->fecha_audiencia ?? Carbon::now()->format('Y-m-d');
                
                ResolucionPagoDiferido::create([
                    'audiencia_id' => $audiencia->id,
                    'solicitante_id' => $solicitante->id,
                    'monto' => $montoTotal,
                    'conciliador_id' => $audiencia->conciliador_id,
                    'code_estatus' => 'pendiente', // ResolucionPagoDiferido::PENDIENTE
                    'fecha_pago' => Carbon::createFromFormat('Y-m-d', $fecha_audiencia)
                        ->setTime(9, 0)
                        ->format('Y-m-d H:i')
                ]);
                
                Log::info('CrearConceptosPago: Pago diferido creado', ['monto_total' => $montoTotal]);
            }
            
            Log::info('CrearConceptosPago: Completado', ['audiencia_id' => $audiencia->id]);
            
        } catch (\Exception $e) {
            Log::error('CrearConceptosPago: Error', [
                'error' => $e->getMessage(),
                'audiencia_id' => $audiencia->id,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Genera los documentos de la audiencia (citatorios, acuse, etc.)
     * 
     * @param Solicitud $solicitud
     * @param Audiencia $audiencia
     */
    private function generarDocumentos(Solicitud $solicitud, Audiencia $audiencia): void
    {
        try {
            Log::info('GenerarDocumentos: Iniciando', [
                'solicitud_id' => $solicitud->id,
                'audiencia_id' => $audiencia->id
            ]);
            
            // Generar citatorio de conciliación para el citado (tipo_parte_id = 2)
            foreach ($solicitud->partes as $parte) {
                if ($parte->tipo_parte_id == 2) { // Citado
                    event(new GenerateDocumentResolution(
                        $audiencia->id,
                        $solicitud->id,
                        14, // clasificacion_archivo_id: Citatorio
                        4,  // tipo_documento_id: Citatorio de conciliación
                        null,
                        $parte->id
                    ));
                    
                    Log::debug('GenerarDocumentos: Citatorio generado', ['parte_id' => $parte->id]);
                }
            }
            
            // Eliminar el acuse anterior si existe
            $acuse = Documento::where('documentable_type', \App\Solicitud::class)
                ->where('documentable_id', $solicitud->id)
                ->where('clasificacion_archivo_id', 40)
                ->first();
            
            if ($acuse != null) {
                $acuse->delete();
                Log::debug('GenerarDocumentos: Acuse anterior eliminado');
            }
            
            // Crear el nuevo acuse
            event(new GenerateDocumentResolution(
                '',
                $solicitud->id,
                40, // clasificacion_archivo_id: Acuse
                6   // tipo_documento_id: Acuse de ratificación
            ));
            
            Log::info('GenerarDocumentos: Acuse generado');
            Log::info('GenerarDocumentos: Completado', ['audiencia_id' => $audiencia->id]);
            
        } catch (\Exception $e) {
            Log::error('GenerarDocumentos: Error', [
                'error' => $e->getMessage(),
                'audiencia_id' => $audiencia->id
            ]);
        }
    }

    /**
     * Actualiza los datos laborales del citado con información adicional
     * 
     * @param Solicitud $solicitud
     */
    private function actualizarDatosLaborales(Solicitud $solicitud): void
    {
        try {
            Log::info('ActualizarDatosLaborales: Iniciando', ['solicitud_id' => $solicitud->id]);
            
            $citado = $solicitud->partes()->where('tipo_parte_id', 2)->first();
            
            if (!$citado) {
                Log::warning('ActualizarDatosLaborales: No se encontró citado');
                return;
            }
            
            $dato_laboral = $citado->datosLaborales->first();
            
            if ($dato_laboral) {
                // Actualizar campos que puedan faltar
                // Estos valores ya deberían estar, pero por si acaso
                $dato_laboral->update([
                    'horario_laboral' => $dato_laboral->horario_laboral ?? 'Lunes a Viernes de 9:00 a 18:00',
                    'horario_comida' => $dato_laboral->horario_comida ?? '14:00 a 15:00',
                    'comida_dentro' => $dato_laboral->comida_dentro ?? true,
                    'dias_descanso' => $dato_laboral->dias_descanso ?? 'Sábado y Domingo',
                    'dias_vacaciones' => $dato_laboral->dias_vacaciones ?? 6,
                    'dias_aguinaldo' => $dato_laboral->dias_aguinaldo ?? 15,
                    'prestaciones_adicionales' => $dato_laboral->prestaciones_adicionales ?? 'Ninguna'
                ]);
                
                Log::info('ActualizarDatosLaborales: Completado', ['parte_id' => $citado->id]);
            } else {
                Log::warning('ActualizarDatosLaborales: No se encontraron datos laborales');
            }
            
        } catch (\Exception $e) {
            Log::error('ActualizarDatosLaborales: Error', [
                'error' => $e->getMessage(),
                'solicitud_id' => $solicitud->id
            ]);
        }
    }

    /**
     * Crea una audiencia para una solicitud específica con lógica robusta de generación de folios
     * Similar al proceso de ConveniosMasivos
     * 
     * @param Solicitud $solicitud
     * @param array $citadoData Datos del citado que pueden incluir conceptos de pago
     */
    private function createAudiencia(Solicitud $solicitud, array $citadoData = [])
    {
        DB::beginTransaction();
        try {
            Log::info('CreateAudiencia: Iniciando creación de audiencia', ['solicitud_id' => $solicitud->id]);
            
            // Refrescar la solicitud para obtener todas las relaciones
            $solicitud = Solicitud::with('partes')->find($solicitud->id);
            
            // Validar que existe solicitud
            if (!$solicitud) {
                throw new \Exception('Solicitud no encontrada');
            }

            // === PASO 0: OBTENER CONTADORES Y DATOS BASE ===
            // Obtenemos los folios usando ContadorController (si está disponible)
            try {
                $ContadorController = new ContadorController;
                $folioC = $ContadorController->getContador(1, $solicitud->centro->id);
                $folioAudiencia = $ContadorController->getContador(3, 15);
                Log::info('CreateAudiencia: Contadores obtenidos', [
                    'folioC' => $folioC,
                    'folioAudiencia' => $folioAudiencia
                ]);
            } catch (\Exception $e) {
                // Si falla, continuamos con la lógica de max() + 1
                Log::warning('CreateAudiencia: No se pudo usar ContadorController, usando lógica de max()', [
                    'error' => $e->getMessage()
                ]);
                $folioC = null;
                $folioAudiencia = null;
            }

            // Colocamos los parámetros en variables
            $tipoParte = \App\TipoParte::whereNombre('SOLICITANTE')->first();

            // Obtenemos las fechas para la audiencia
            [$fecha_audiencia_base, $hora_inicio_default, $hora_fin_default, $fecha_resolucion] = $this->getDatosFechasAudiencia();

            // Obtenemos la sala virtual del centro
            $sala = Sala::where('centro_id', $solicitud->centro_id)->where('virtual', true)->first();
            if ($sala == null) {
                // Fallback: usar sala hardcodeada
                Log::warning('CreateAudiencia: No se encontró sala virtual, usando sala_id = 1');
                $sala_id = 1;
            } else {
                $sala_id = $sala->id;
            }

            // Obtener conciliador desde citadoData o usar el hardcodeado como fallback
            $conciliador_id = isset($citadoData['conciliador_id']) && !empty($citadoData['conciliador_id']) 
                ? (int)$citadoData['conciliador_id'] 
                : 248; // Fallback por defecto
            
            Log::info('CreateAudiencia: Conciliador asignado', ['conciliador_id' => $conciliador_id]);

            // === PASO 1: CREAR EXPEDIENTE CON LÓGICA ROBUSTA ===
            // Validar que la solicitud no tenga expediente ya (similar a ConveniosMasivos)
            if ($solicitud->expediente != null) {
                Log::warning('CreateAudiencia: La solicitud ya tiene un expediente', [
                    'solicitud_id' => $solicitud->id,
                    'expediente_id' => $solicitud->expediente->id
                ]);
                throw new \Exception('La solicitud ya tiene un expediente asociado');
            }

            $anio = date("Y");
            $centro_id = $solicitud->centro_id ?? 38;
            $edo_folio = $solicitud->centro->abreviatura;
            
            Log::info('CreateAudiencia: Iniciando creación de expediente', [
                'solicitud_id' => $solicitud->id,
                'anio' => $anio,
                'centro_id' => $centro_id,
                'abreviatura' => $edo_folio
            ]);
            
            // Obtener el último expediente REAL de este año y abreviatura
            // IMPORTANTE: Los folios son GLOBALES por abreviatura, NO por centro
            // Ejemplo: AMG/CI/2025/010000 es único en toda la abreviatura AMG, independientemente del centro
            // CRÍTICO: Usar CAST para ordenar numéricamente, no alfabéticamente
            $ultimo_expediente = \App\Expediente::where('anio', $anio)
                ->where('folio', 'like', $edo_folio . '/CI/' . $anio . '/%')
                ->orderByRaw('CAST(consecutivo AS INTEGER) DESC')
                ->first();
            
            // Si existe un último expediente, usar su consecutivo + 1, si no empezar en 1
            $consecutivo_inicial = $ultimo_expediente ? ($ultimo_expediente->consecutivo + 1) : 1;
            
            Log::info('CreateAudiencia: Consecutivo calculado desde último expediente', [
                'ultimo_consecutivo' => $ultimo_expediente ? $ultimo_expediente->consecutivo : 'ninguno',
                'consecutivo_inicial' => $consecutivo_inicial,
                'ultimo_folio' => $ultimo_expediente ? $ultimo_expediente->folio : 'ninguno'
            ]);
            
            // Sistema robusto: intentar crear con incrementos secuenciales
            $expediente = null;
            $intentos = 0;
            $max_intentos = 10;
            $consecutivo = $consecutivo_inicial;
            
            while ($expediente === null && $intentos < $max_intentos) {
                try {
                    // Generar el folio con el consecutivo actual
                    $folio = $edo_folio . '/CI/' . $anio . '/' . sprintf('%06d', $consecutivo);
                    
                    Log::info('CreateAudiencia: Intentando crear expediente', [
                        'intento' => $intentos + 1,
                        'consecutivo' => $consecutivo,
                        'folio' => $folio
                    ]);

                    // Verificación previa: si el folio ya existe, incrementar y continuar
                    if (\App\Expediente::where('folio', $folio)->exists()) {
                        $consecutivo++;
                        $intentos++;
                        Log::warning('CreateAudiencia: Folio ya existe, incrementando consecutivo', [
                            'folio' => $folio,
                            'nuevo_consecutivo' => $consecutivo
                        ]);
                        continue;
                    }

                    // Crear el expediente
                    $expediente = \App\Expediente::create([
                        'solicitud_id' => $solicitud->id,
                        'folio' => $folio,
                        'anio' => $anio,
                        'consecutivo' => $consecutivo
                    ]);
                    
                    Log::info('CreateAudiencia: Expediente creado exitosamente', [
                        'expediente_id' => $expediente->id,
                        'folio' => $folio,
                        'consecutivo' => $consecutivo
                    ]);
                    
                } catch (\Illuminate\Database\QueryException $e) {
                    // Si hay error de duplicado (23505), incrementar consecutivo
                    if ($e->getCode() == '23505') {
                        $consecutivo++;
                        $intentos++;
                        Log::warning('CreateAudiencia: Violación UNIQUE, incrementando consecutivo', [
                            'intento' => $intentos,
                            'nuevo_consecutivo' => $consecutivo
                        ]);
                    } else {
                        throw $e;
                    }
                } catch (\Exception $e) {
                    $intentos++;
                    Log::error('CreateAudiencia: Error al crear expediente', [
                        'intento' => $intentos,
                        'error' => $e->getMessage()
                    ]);
                    $consecutivo++;
                }
            }
            
            // Validar que se creó el expediente
            if ($expediente === null) {
                throw new \Exception('No se pudo generar un folio único para el expediente después de ' . $max_intentos . ' intentos');
            }

            // === PASO 2: ACTUALIZAR PARTES Y SOLICITUD ===
            Log::info('CreateAudiencia: Actualizando partes y solicitud', ['expediente_id' => $expediente->id]);
            
            // Indicamos que el solicitante está ratificando
            foreach ($solicitud->partes as $key => $parte) {
                if ($tipoParte->id == $parte->tipo_parte_id) {
                    $parte->update(['ratifico' => true]);
                    Log::debug('CreateAudiencia: Parte SOLICITANTE marcada como ratificada', ['parte_id' => $parte->id]);
                }
            }

            // Modificamos la solicitud para indicar que ya se ratificó
            $fecha_ratificacion = now();
            
            // Calcular fecha_vigencia
            try {
                $resultado = DB::select('SELECT * FROM calcular_periodo_general(?, ?, ?, ?, ?, ?)', [
                    now(),
                    $solicitud->centro_id,
                    env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45),
                    env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45),
                    env("DIAS_CALCULAR_PERIODO_GENERAL", 45),
                    'naturales'
                ]);
                $fecha_vigencia = $resultado[0]->fecha_minima;
            } catch (\Exception $e) {
                $fecha_vigencia = Carbon::now()->addDays(45)->toDateString();
                Log::warning('CreateAudiencia: calcular_periodo_general no disponible, usando fallback');
            }
            
            // Actualizar solicitud
            $solicitud->update([
                "estatus_solicitud_id" => 3,
                "url_virtual" => null,
                "ratificada" => true,
                "fecha_ratificacion" => $fecha_ratificacion,
                "fecha_vigencia" => $fecha_vigencia,
                "inmediata" => true
            ]);
            
            Log::info('CreateAudiencia: Solicitud actualizada', ['solicitud_id' => $solicitud->id]);

            // === PASO 3: CREAR AUDIENCIA CON LÓGICA ROBUSTA ===
            Log::info('CreateAudiencia: Iniciando creación de audiencia', ['expediente_id' => $expediente->id]);
            
            // Obtener la última audiencia REAL de este año
            $ultima_audiencia = \App\Audiencia::where('anio', $anio)
                ->orderBy('folio', 'desc')
                ->first();
            
            // Si existe una última audiencia, usar su folio + 1, si no empezar en 1
            $folio_audiencia_inicial = $ultima_audiencia ? ($ultima_audiencia->folio + 1) : 1;
            
            Log::info('CreateAudiencia: Folio audiencia calculado desde última audiencia', [
                'ultimo_folio' => $ultima_audiencia ? $ultima_audiencia->folio : 'ninguno',
                'folio_inicial' => $folio_audiencia_inicial
            ]);
            
            // Sistema robusto para generar folio único de audiencia
            $audiencia = null;
            $intentos_audiencia = 0;
            $max_intentos_audiencia = 200; // Aumentado de 20 a 200 intentos
            $folio_audiencia = $folio_audiencia_inicial;
            
            // Fecha base que puede cambiar si hay muchos intentos fallidos
            $fecha_audiencia_actual = clone $fecha_audiencia_base;
            
            while ($audiencia === null && $intentos_audiencia < $max_intentos_audiencia) {
                try {
                    // Cambiar de día cada 50 intentos
                    if ($intentos_audiencia > 0 && $intentos_audiencia % 50 == 0) {
                        $fecha_audiencia_actual->addDay();
                        Log::info('CreateAudiencia: Cambiando a siguiente día', [
                            'nueva_fecha' => $fecha_audiencia_actual->format('Y-m-d'),
                            'intento' => $intentos_audiencia
                        ]);
                    }
                    
                    // Variar la hora de inicio en cada intento para evitar restricción UNIQUE
                    $hora_base = 8; // Hora inicial: 08:00
                    $minutos_offset = ($intentos_audiencia % 50) * 15; // Reinicia cada día
                    $hora_inicio_audiencia = Carbon::createFromTime($hora_base, 0, 0)
                        ->addMinutes($minutos_offset)
                        ->format('H:i:s');
                    $hora_fin_audiencia = Carbon::createFromTime($hora_base, 0, 0)
                        ->addMinutes($minutos_offset + 60) // Duración de 1 hora (era 120)
                        ->format('H:i:s');
                    
                    // Si la hora pasa de las 19:00, saltar al siguiente día
                    if (Carbon::parse($hora_inicio_audiencia)->hour >= 19) {
                        $intentos_audiencia++;
                        continue; // Salta este intento
                    }
                    
                    // Verificación previa: evitar violación de restricción UNIQUE
                    $audiencia_existe = \App\Audiencia::where('conciliador_id', $conciliador_id)
                        ->where('fecha_audiencia', $fecha_audiencia_actual->format('Y-m-d'))
                        ->where('hora_inicio', $hora_inicio_audiencia)
                        ->exists();
                    
                    if ($audiencia_existe) {
                        $intentos_audiencia++;
                        if ($intentos_audiencia % 10 == 0) { // Log cada 10 intentos para no saturar
                            Log::info('CreateAudiencia: Hora ya ocupada, probando siguiente slot', [
                                'hora_ocupada' => $hora_inicio_audiencia,
                                'intento' => $intentos_audiencia
                            ]);
                        }
                        continue;
                    }
                    
                    Log::info('CreateAudiencia: Intentando crear audiencia', [
                        'intento' => $intentos_audiencia + 1,
                        'folio_audiencia' => $folio_audiencia,
                        'hora_inicio' => $hora_inicio_audiencia
                    ]);
                    
                    // Verificar si el folio ya existe
                    $folio_existe = \App\Audiencia::where('anio', $anio)
                        ->where('folio', $folio_audiencia)
                        ->exists();
                    
                    if ($folio_existe) {
                        $folio_audiencia++;
                        $intentos_audiencia++;
                        Log::warning('CreateAudiencia: Folio de audiencia ya existe, incrementando', [
                            'folio' => $folio_audiencia - 1,
                            'nuevo_folio' => $folio_audiencia
                        ]);
                        continue;
                    }

                    // Crear la audiencia
                    $audiencia = \App\Audiencia::create([
                        'expediente_id' => $expediente->id,
                        'multiple' => false,
                        'fecha_audiencia' => $fecha_audiencia_actual->format('Y-m-d'),
                        'hora_inicio' => $hora_inicio_audiencia,
                        'hora_fin' => $hora_fin_audiencia,
                        'conciliador_id' => $conciliador_id,
                        'numero_audiencia' => 1,
                        'reprogramada' => false,
                        'anio' => $anio,
                        'folio' => $folio_audiencia,
                        'fecha_cita' => null,
                        'finalizada' => true,
                        'solicitud_cancelacion' => false,
                        'cancelacion_atendida' => false,
                        'encontro_audiencia' => true,
                        'tipo_terminacion_audiencia' => 1,
                        'audiencia_creada' => false,
                        'fecha_resolucion' => $fecha_resolucion,
                        'resolucion_id' => 1,
                        'fecha_limite_audiencia' => null
                    ]);
                    
                    Log::info('CreateAudiencia: Audiencia creada exitosamente', [
                        'audiencia_id' => $audiencia->id,
                        'folio' => $folio_audiencia,
                        'hora_inicio' => $hora_inicio_audiencia
                    ]);
                    
                } catch (\Illuminate\Database\QueryException $e) {
                    $intentos_audiencia++;
                    
                    if ($e->getCode() == '25P02') {
                        Log::error('CreateAudiencia: Transacción abortada (25P02), imposible continuar');
                        break;
                    } elseif ($e->getCode() == '23505') {
                        Log::warning('CreateAudiencia: Violación UNIQUE (23505), incrementando folio');
                        $folio_audiencia++;
                    } elseif ($e->getCode() == '23503') {
                        Log::error('CreateAudiencia: Foreign Key violation (23503)');
                        break;
                    } else {
                        Log::warning('CreateAudiencia: QueryException al crear audiencia', [
                            'codigo' => $e->getCode()
                        ]);
                    }
                } catch (\Exception $e) {
                    $intentos_audiencia++;
                    Log::warning('CreateAudiencia: Error general al crear audiencia', [
                        'error' => $e->getMessage()
                    ]);
                    $folio_audiencia++;
                }
            }
            
            // Validar que se creó la audiencia
            if ($audiencia === null) {
                throw new \Exception('No se pudo generar un folio único para la audiencia después de ' . $max_intentos_audiencia . ' intentos');
            }

            // === PASO 4: CREAR RELACIONES DE AUDIENCIA ===
            Log::info('CreateAudiencia: Creando relaciones de audiencia', ['audiencia_id' => $audiencia->id]);
            
            \App\ConciliadorAudiencia::create([
                'audiencia_id' => $audiencia->id,
                'conciliador_id' => $conciliador_id,
                'solicitante' => true
            ]);

            \App\SalaAudiencia::create([
                'audiencia_id' => $audiencia->id,
                'sala_id' => $sala_id,
                'solicitante' => true
            ]);

            foreach ($solicitud->partes as $parte) {
                \App\AudienciaParte::create([
                    'audiencia_id' => $audiencia->id,
                    'parte_id' => $parte->id,
                    'tipo_notificacion_id' => null
                ]);
            }

            // === PASO 5: PROCESO COMPLETO DE CONFIRMACIÓN ===
            Log::info('CreateAudiencia: Iniciando proceso de confirmación completo', ['audiencia_id' => $audiencia->id]);
            
            // 5.1 Crear representante legal (si se proporcionaron datos)
            $datosRepresentante = $citadoData['representante'] ?? [];
            $this->crearRepresentanteLegal($solicitud, $audiencia, $datosRepresentante);
            
            // 5.2 Crear manifestaciones (etapas de resolución)
            $this->crearManifestaciones($audiencia);
            
            // 5.3 Crear resolución de partes (terminación bilateral)
            $this->crearResolucionPartes($solicitud, $audiencia);
            
            // 5.4 Actualizar datos laborales del citado
            $this->actualizarDatosLaborales($solicitud);
            
            // 5.5 Crear conceptos de pago (usa datos de citadoData si están disponibles)
            $this->crearConceptosPago($solicitud, $audiencia, $citadoData);
            
            // 5.6 Crear comparecencias para todas las partes
            $this->crearComparecencias($solicitud, $audiencia);
            
            // 5.7 Generar documentos (citatorios, acuse)
            $this->generarDocumentos($solicitud, $audiencia);
            
            Log::info('CreateAudiencia: Proceso de confirmación completado', ['audiencia_id' => $audiencia->id]);

            // === PASO 6: COMMIT Y RETORNO ===
            DB::commit();
            
            Log::info('CreateAudiencia: Proceso completado exitosamente', [
                'audiencia_id' => $audiencia->id,
                'expediente_id' => $expediente->id,
                'solicitud_id' => $solicitud->id
            ]);
            
            return $audiencia;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error en createAudiencia: ' . $e->getMessage(), [
                'solicitud_id' => $solicitud->id ?? 'N/A',
                'linea' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}