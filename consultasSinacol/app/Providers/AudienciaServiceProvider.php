<?php

namespace App\Providers;

use DateTime;
use App\Audiencia;
use App\AudienciaParte;
use App\BitacoraBuzon;
use App\Compareciente;
use App\ConciliadorAudiencia;
use App\Disponibilidad;
use App\Documento;
use App\Expediente;
use App\Solicitud;
use App\EstatusSolicitud;
use App\Parte;
use App\ResolucionPagoDiferido;
use App\ResolucionParteConcepto;
use App\ResolucionPartes;
use App\Incidencia;
use App\Centro;
use App\Conciliador;
use App\ConciliadorHasSala;
use App\Sala;
use App\SalaAudiencia;
use App\Persona;
use App\Events\GenerateDocumentResolution;
use App\Events\RatificacionRealizada;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use App\Services\AudienciaService;
use App\TipoPersona;
use App\Mail\EnviarNotificacionBuzon;
use App\RolAtencion;
use Illuminate\Support\Facades\Mail;
use App\Services\Comparecientes;
use Exception;
use Illuminate\Support\Facades\Auth;

class AudienciaServiceProvider extends ServiceProvider
{

    /**
     * Instancia del request
     * @var Request
     */
    protected $request;
    protected $folioService;
    protected $contadorService;
    protected $dias_solicitud;

    public function __construct(Request $request, ContadorService $contadorService, FolioService $folioService, DiasVigenciaSolicitudService $dias)
    {
        // $this->middleware("auth");
        $this->request = $request;
        $this->dias_solicitud = $dias;
        $this->folioService = $folioService;
        $this->contadorService = $contadorService;
    }

    /**
     * Funcion para guardar las resoluciones individuales de las audiencias
     * @param Audiencia $audiencia
     * @param type $arrayRelaciones
     */
    public static function guardarRelaciones(Audiencia $audiencia, $arrayRelaciones = array(), $listaConceptos = array(), $listaFechasPago = array(), $listaTipoPropuestas = array())
    {
        $partes = $audiencia->audienciaParte;
        $solicitantes = self::getSolicitantes($audiencia);
        $solicitados = self::getSolicitados($audiencia);
        $huboConvenio = false;
        $totalPagoC = [];
        $totalDeduccionC = [];
        $solicitante_documento = true;
        $generar_documento_no_conciliacion = false;
        foreach ($solicitados as $solicitado) {
            foreach ($solicitantes as $solicitante) {
                $bandera = true;
                if ($arrayRelaciones != null) {
                    foreach ($arrayRelaciones as $relacion) {
                        //
                        $parte_solicitante = Parte::find($relacion["parte_solicitante_id"]);
                        if ($parte_solicitante->tipo_parte_id == 3) {
                            $parte_solicitante = Parte::find($parte_solicitante->parte_representada_id);
                        }
                        //
                        $parte_solicitado = Parte::find($relacion["parte_solicitado_id"]);
                        if ($parte_solicitado->tipo_parte_id == 3) {
                            $parte_solicitado = Parte::find($parte_solicitado->parte_representada_id);
                        }

                        if ($solicitante->parte_id == $parte_solicitante->id && $solicitado->parte_id == $parte_solicitado->id) {
                            $terminacion = 3;
                            $huboConvenio = true;
                        } else {
                            $terminacion = 5;
                        }
                        $bandera = false;
                        $resolucionParte = ResolucionPartes::create([
                            "audiencia_id" => $audiencia->id,
                            "parte_solicitante_id" => $solicitante->parte_id,
                            "parte_solicitada_id" => $solicitado->parte_id,
                            "terminacion_bilateral_id" => $terminacion
                        ]);
                    }
                }

                if ($bandera) {
                    //Se consulta comparecencia de solicitante
                    $parteS = $solicitante->parte;
                    $comparecienteSol = null;
                    if ($parteS->tipo_persona_id == 2) { //solicitante moral
                        $compareciente_partes = Parte::where("parte_representada_id", $parteS->id)->get();
                        foreach ($compareciente_partes as $key => $compareciente_parte) {
                            $comparecienteSolicitud = Compareciente::where('parte_id', $compareciente_parte->id)->where('audiencia_id', $audiencia->id)->first();
                            if ($comparecienteSolicitud != null) {
                                $comparecienteSol = $comparecienteSolicitud;
                            }
                        }
                    } else { //solicitante fisica
                        $comparecienteSol = Compareciente::where('parte_id', $solicitante->parte_id)->where('audiencia_id', $audiencia->id)->first();
                        if ($comparecienteSol == null) {
                            $compareciente_partes = Parte::where("parte_representada_id", $parteS->id)->get();
                            if (count($compareciente_partes) > 0) {
                                foreach ($compareciente_partes as $key => $compareciente_parte) {
                                    $comparecienteSol = Compareciente::where('parte_id', $compareciente_parte->id)->where('audiencia_id', $audiencia->id)->first();
                                }
                            }
                        }
                    }
                    //Se consulta comparecencia de citado
                    $comparecienteCit = null;
                    $comparecienteCit = Compareciente::where('parte_id', $solicitado->parte_id)->where('audiencia_id', $audiencia->id)->first();
                    if ($comparecienteCit == null) {
                        $compareciente_partes = Parte::where("parte_representada_id", $solicitado->parte_id)->get();
                        foreach ($compareciente_partes as $key => $compareciente_parte) {
                            $comparecienteCitado = Compareciente::where('parte_id', $compareciente_parte->id)->where('audiencia_id', $audiencia->id)->first();
                            if ($comparecienteCitado != null) {
                                $comparecienteCit = $comparecienteCitado;
                            }
                        }
                    }

                    $terminacion = 1;

                    if ($audiencia->resolucion_id == 3) { //no hubo convenio, guarda resolucion para todas las partes
                        $terminacion = 5;
                        // start - verify
                        $citado_comparece = null;
                        $solicitante_parte = null;
                        $solicitante_parte = $solicitante->parte;
                        if ($solicitante_parte->tipo_persona_id == 2) {
                            $solicitante_parte = Parte::where('parte_representada_id', $solicitante_parte->id)->first();
                        }
                        if ($solicitante_parte) {
                            $solicitante_comparece = $solicitante_parte->compareciente()->where('audiencia_id', $audiencia->id)->first();
                        }

                        $citado_parte = $solicitado->parte;
                        if ($citado_parte->tipo_persona_id == 2) {
                            $citado_parte = Parte::where('parte_representada_id', $citado_parte->id)->first();
                        }
                        if ($citado_parte) {
                            $citado_comparece = $citado_parte->compareciente()->where('audiencia_id', $audiencia->id)->first();
                        }
                        if ($solicitante_comparece && $citado_comparece && $solicitante_documento) {
                            //se genera el acta de no conciliacion para todos los casos
                            $solicitante_documento = false;
                            //event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 17, 1, $solicitante->parte_id));
                        }
                        // end - verify

                        $parte = $solicitado->parte;
                        if ($parte->tipo_persona_id == 2) {
                            $compareciente_parte = Parte::where("parte_representada_id", $parte->id)->first();
                            if ($compareciente_parte != null) {
                                $compareciente = Compareciente::where('parte_id', $compareciente_parte->id)->where('audiencia_id', $audiencia->id)->first();
                            } else {
                                $compareciente = null;
                            }
                        } else {
                            $compareciente_parte = Parte::where("parte_representada_id", $parte->id)->first();
                            if ($compareciente_parte != null) {
                                $compareciente = Compareciente::where('parte_id', $compareciente_parte->id)->where('audiencia_id', $audiencia->id)->first();
                            } else {
                                $compareciente = Compareciente::where('parte_id', $solicitado->parte_id)->where('audiencia_id', $audiencia->id)->first();
                            }
                        }
                    } else if ($audiencia->resolucion_id == 1) { // Hubo convenio
                        if ($comparecienteSol != null && $comparecienteCit != null) {
                            $terminacion = 3;
                            $huboConvenio = true;
                        } else if ($comparecienteSol != null) {
                            $terminacion = 5;
                        } else {
                            $terminacion = 1;
                        }
                    } else if ($audiencia->resolucion_id == 2) {
                        //no hubo convenio pero se agenda nueva audiencia, guarda para todos las partes
                        $terminacion = 2;
                    }
                    $resolucionParte = ResolucionPartes::create([
                        "audiencia_id" => $audiencia->id,
                        "parte_solicitante_id" => $solicitante->parte_id,
                        "parte_solicitada_id" => $solicitado->parte_id,
                        "terminacion_bilateral_id" => $terminacion
                    ]);
                }
                //guardar conceptos de pago para Convenio
                if (isset($resolucionParte)) { //Hubo conciliacion
                    if ($terminacion == 3) {
                        $huboConvenio = true;
                        //Se consulta comparecencia de citado
                        $parte = $solicitado->parte;
                        if ($parte->tipo_persona_id == 2) {
                            $compareciente_parte = Parte::where("parte_representada_id", $parte->id)->first();
                            if ($compareciente_parte != null) {
                                $comparecienteCit = Compareciente::where('parte_id', $compareciente_parte->id)->first();
                            } else {
                                $comparecienteCit = null;
                            }
                        } else {
                            $comparecienteCit = Compareciente::where('parte_id', $solicitado->parte_id)->first();
                        }
                        // Termina consulta de comparecencia de citado
                        //Se consulta comparecencia de solicitante
                        $parteS = $solicitante->parte;
                        if ($parteS->tipo_persona_id == 2) {
                            $compareciente_parte = Parte::where("parte_representada_id", $parteS->id)->first();
                            if ($compareciente_parte != null) {
                                $comparcomparecienteSoleciente = Compareciente::where('parte_id', $compareciente_parte->id)->first();
                            } else {
                                $comparecienteSol = null;
                            }
                        } else {
                            $comparecienteSol = Compareciente::where('parte_id', $solicitante->parte_id)->first();
                        }
                    }
                }
            }

            $solicitanteComparecio = $solicitado->parte->compareciente->where('audiencia_id', $audiencia->id)->first();
            if ($solicitanteComparecio != null) {
                if (isset($listaConceptos)) {
                    if (count($listaConceptos) > 0) {
                        $totalPagoC[$solicitado->parte_id] = 0;
                        $totalDeduccionC[$solicitado->parte_id] = 0.0;
                        foreach ($listaConceptos as $key => $conceptosSolicitante) { //solicitantes
                            if ($key == $solicitado->parte_id) {
                                foreach ($conceptosSolicitante as $k => $concepto) {
                                    ResolucionParteConcepto::create([
                                        "resolucion_partes_id" => null, //$resolucionParte->id,
                                        "audiencia_parte_id" => $solicitado->id,
                                        "concepto_pago_resoluciones_id" => $concepto["concepto_pago_resoluciones_id"],
                                        "conciliador_id" => $audiencia->conciliador_id,
                                        "dias" => intval($concepto["dias"]),
                                        "monto" => $concepto["monto"],
                                        "otro" => $concepto["otro"]
                                    ]);
                                    if ($concepto["concepto_pago_resoluciones_id"] == 13) {
                                        $totalDeduccionC[$solicitado->parte_id] = floatval($totalDeduccionC[$solicitado->parte_id])  + floatval($concepto["monto"]);
                                    } else {
                                        $totalPagoC[$solicitado->parte_id] =  floatval($totalPagoC[$solicitado->parte_id])  + floatval($concepto["monto"]);
                                    }
                                }
                            }
                        }
                        foreach ($listaTipoPropuestas as $key => $listaPropuesta) { //solicitantes
                            if ($key == $solicitado->parte_id) {
                                $resolucionParte = ResolucionPartes::where("audiencia_id", $audiencia->id)->where("parte_solicitada_id", $solicitado->parte_id)->where("parte_solicitante_id", $solicitante->parte_id)->get();
                                foreach ($resolucionParte as $resolucion) { //solicitantes
                                    $resolucion->update([
                                        "tipo_propuesta_pago_id" => $listaPropuesta
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }
        // Termina consulta de comparecencia de solicitante
        if ($huboConvenio) {
            if (isset($listaFechasPago)) { //se registran pagos diferidos
                if (count($listaFechasPago) > 0) {
                    foreach ($listaFechasPago as $key => $fechaPago) {
                        ResolucionPagoDiferido::create([
                            "audiencia_id" => $audiencia->id,
                            "solicitante_id" => $fechaPago["idCitado"],
                            "monto" => $fechaPago["monto_pago"],
                            "conciliador_id" => $audiencia->conciliador_id,
                            "descripcion_pago" => $fechaPago["descripcion_pago"],
                            "fecha_pago" => Carbon::createFromFormat('d/m/Y H:i', $fechaPago["fecha_pago"])->format('Y-m-d H:i'),
                            "code_estatus" => ResolucionPagoDiferido::PENDIENTE,
                            "diferido" => true
                        ]);
                    }
                }
            }
            foreach ($solicitados as $solicitado) {
                foreach ($solicitantes as $solicitante) {
                    $part = Parte::find($solicitado->parte_id);
                    $datoLaboral_citado = $part->dato_laboral()->orderBy('id', 'desc')->first();
                    if ($datoLaboral_citado->labora_actualmente) {
                        $date = Carbon::now();
                        $datoLaboral_citado->fecha_salida = $date;
                        $datoLaboral_citado->save();
                    }
                    $convenio = ResolucionPartes::where('parte_solicitante_id', $solicitante->parte_id)->where('parte_solicitada_id', $solicitado->parte_id)->where('terminacion_bilateral_id', 3)->first();
                    if ($convenio != null) {
                        if (!isset($listaFechasPago)) { //si no se registraron pagos diferidos crear pago NO diferido
                            $totalPagoC[$solicitado->parte_id] = $totalPagoC[$solicitado->parte_id] - $totalDeduccionC[$solicitado->parte_id];
                            ResolucionPagoDiferido::create([
                                "audiencia_id" => $audiencia->id,
                                "solicitante_id" => $solicitado->parte_id,
                                "monto" => $totalPagoC[$solicitado->parte_id],
                                "descripcion_pago" => "Convenio",
                                "conciliador_id" => $audiencia->conciliador_id,
                                "fecha_pago" => $audiencia->fecha_audiencia . " " . $audiencia->hora_fin,
                                "code_estatus" => ResolucionPagoDiferido::PENDIENTE,
                                "diferido" => false
                            ]);
                        }

                        if ($convenio->tipo_propuesta_pago_id == 4) {
                            //generar convenio reinstalacion
                            event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 43, 15, $solicitante->parte_id, $solicitado->parte_id));
                        } elseif ($convenio->tipo_propuesta_pago_id == 5) {
                            //generar convenio de prestaciones
                            event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 16, 17, $solicitante->parte_id, $solicitado->parte_id));
                        } else {
                            //generar convenio
                            event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 52, 14, $solicitante->parte_id, $solicitado->parte_id)); //15
                        }
                    }

                    $noComparecioSolicitado =  Comparecientes::comparecio($audiencia->id,  $solicitado->parte_id);
                    if ($noComparecioSolicitado == null) {
                        //event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 41, 8, $solicitante->parte_id, $solicitado->parte_id));
                    }
                }
            }
        }

        foreach ($solicitantes as $solicitante) {
            $noConciliacion = ResolucionPartes::where('parte_solicitante_id', $solicitante->parte_id)->where('terminacion_bilateral_id', 5)->first();
            if ($noConciliacion != null) {
                //una Constancia de No Conciliador por solicitante (contiene todos los solicitados(citado) )
                $citado_comparece = null;
                $solicitante_parte = null;
                $solicitante_parte = $solicitante->parte;

                if ($solicitante_parte->tipo_persona_id == 2) {
                    $solicitante_parte = Parte::where('parte_representada_id', $solicitante_parte->id)
                        ->where('representante', true)
                        ->orderBy('id', 'desc')
                        ->first();
                }
                if ($solicitante_parte) {
                    $solicitante_comparece = $solicitante_parte->compareciente()->where('audiencia_id', $audiencia->id)->first();
                }
                if ($solicitante_comparece) {
                    event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 17, 1, $solicitante->parte->id));
                }
            }
        }

        $solicitud = $audiencia->expediente->solicitud();
        $solicitud->update(['url_virtual' => null]);
        //generar acta de audiencia
        event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 15, 3));
    }

    /**
     * Funcion para obtener las partes involucradas en una audiencia de tipo solicitante
     * @param Audiencia $audiencia
     * @return AudienciaParte $solicitante
     */
    public static function getSolicitantes(Audiencia $audiencia)
    {
        $solicitantes = [];
        foreach ($audiencia->audienciaParte as $parte) {
            if ($parte->parte->tipo_parte_id == 1) {
                $solicitantes[] = $parte;
            }
        }
        return $solicitantes;
    }

    /**
     * Funcion para obtener las partes involucradas en una audiencia de tipo solicitado
     * @param Audiencia $audiencia
     * @return AudienciaParte $solicitado
     */
    public static function getSolicitados(Audiencia $audiencia)
    {
        $solicitados = [];
        foreach ($audiencia->audienciaParte as $parte) {
            if ($parte->parte->tipo_parte_id == 2) {
                $solicitados[] = $parte;
            }
        }
        return $solicitados;
    }
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Algoritmo V3
     */

    public static function GeneracionAudiencia($solicitud, $request, $folios, $generar_documentos = TRUE)
    {

        set_time_limit(1200);
        $start_algoritmo_debug = microtime(true);
        $user = Auth::user();
        // Se obtienen variables de la Solicitud
        $centro_id = $user->centro_id ?? null;
        $request_solicitud_id = $request->id ?? null;
        $tipo_notificacion_id = $request->tipo_notificacion_id ?? null;
        $inmediata = filter_var($request->inmediata, FILTER_VALIDATE_BOOLEAN) ?? false;
        $fecha_cita = $request->fecha_cita ?? null;     //B) Agendar cita con el notificador para entrega de citatorio
        $url_virtual = $request->url_virtual ?? null;
        $duracion_audiencia = $request->duracion_audiencia ?? null;
        $conciliador_ligado_sala = $request->conciliador_ligado_sala ?? null;
        $automatica = $request->automatica ?? false;
        $conciliador = $request->conciliador ?? null;
        $requiere_salas = $request->requiere_salas ?? null;
        $motivos_requiere_salas = $request->motivos_requiere_salas ?? null;
        $err = false;
        $err_message = '';
        $conciliador_disponibilidad_sala = [];
        $hasrole_personal_conciliador = $user->hasRole('Personal conciliador');
        $personal_conciliador = $user->persona->conciliador;
        $user_id = $user->id;

        $ultima_audiencia_ejecutada = null;
        $primera_audiencia = null;

        if (isset($solicitud->expediente)) {
            $primera_audiencia = Audiencia::with('audienciaParte')->where('expediente_id', $solicitud->expediente->id)->orderBy('id', 'desc')->first();
        }

        // Se obtienen dias máximos y mínimos
        if ((int) $tipo_notificacion_id == 1) {
            $minimo_dia_habil = env('MINIMO_HABIL_SOLICITANTE', 5);
            $maximo_dia_habil = env('MAXIMO_HABIL_SOLICITANTE', 15);
        } else {
            $minimo_dia_habil = env('MINIMO_HABIL_NOTIFICADOR', 15);
            $maximo_dia_habil = env('MAXIMO_HABIL_NOTIFICADOR', 25);
        }
        $dias_vigencia = env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45);

        /**
         * Valores Iniciales
         **/

        // Disponibilidad del centro L - V
        $centro_disponibilidad = Incidencia::disponibilidadRegistrada($centro_id, "App\Centro", "");
        
        $centro_dias = collect($centro_disponibilidad)->pluck('dia')->toArray();
        $fechaUltimaAudiencia = Carbon::now();
        
        if ($solicitud->expediente) {
            $ultimaAudiencia = $solicitud->expediente->audiencia()->whereNotNull('fecha_audiencia')->orderBy("created_at", "DESC")->first();
            $ultima_audiencia_ejecutada = $solicitud->expediente->ultima_audiencia_ejecutada;
            $fechaUltimaAudiencia = is_null($ultimaAudiencia->fecha_audiencia) ? Carbon::now() : $ultimaAudiencia->fecha_audiencia;
            $fechaUltimaAudiencia = Carbon::now()->greaterThan(Carbon::parse($fechaUltimaAudiencia)) ? Carbon::now() : $ultimaAudiencia->fecha_audiencia;
        }

        $resultados = DB::select('SELECT * FROM calcular_periodo_general(?, ?, ?, ?, ?, ?)', [$fechaUltimaAudiencia, $centro_id, $minimo_dia_habil, $maximo_dia_habil, 30, 'habiles']);
     
        $fecha_minima = Carbon::parse($resultados[0]->fecha_minima);
        $fecha_maxima = Carbon::parse($resultados[0]->fecha_max);
        $fecha_audiencia = Carbon::parse($resultados[0]->fecha_minima);
        $fecha_vigencia_naturales  = Carbon::parse($solicitud["fecha_recepcion"])->addDays(intval($dias_vigencia));



        /**
         * Valores asignación de salas al centro
         **/
        $centro_info = Centro::where("id", $centro_id)->first();
        $conciliador_con_sala = false;
        $conciliador_remoto_salas = $centro_info["tipo_atencion_centro_id"] != "1" ? FALSE : TRUE;
        
        try {
            if ($inmediata) {
                if (!isset($personal_conciliador)) {
                    $_solicitud = Solicitud::find($solicitud->id);
                    $_solicitud->update(['code_estatus' => 'sin_confirmar', 'modified_user_id' => null]);
                    return [TRUE, "El usuario no es un conciliador, por lo cual no puede realizar la confirmación con convenio.", null];
                } else {
                    list($err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador) = self::GeneracionAudienciaInmediata($centro_id, $hasrole_personal_conciliador, $personal_conciliador, $solicitud, $automatica, $inmediata, $folios, $duracion_audiencia, $motivos_requiere_salas);
                }
            } else {

                $rol_conciliadores = RolAtencion::where("nombre", $solicitud->virtual ? "Conciliador virtual" : "Conciliador en sala")->first();
                if ($automatica) {
                    $conciliadores = [$conciliador];
                } else {
                    $conciliadores = $centro_info->conciliadores()->whereHas('rolesConciliador', function ($q) use ($rol_conciliadores) {
                        return $q->where('rol_atencion_id', $rol_conciliadores->id);
                    })
                        ->with('horario_comida')
                        ->get();
                }

                // Disponibilidad de los conciliadores L - V
                list($hours, $minutes) = explode(':', $duracion_audiencia);
                $total_minutos = ($hours * 60) + $minutes;
                $conciliadorIds = collect($conciliadores)->pluck('id')->toArray();
                $conciliadores_disponibilidad = Disponibilidad::where("disponibilidad_type", "=", "App\Conciliador")
                    ->whereIn('disponibilidad_id', $conciliadorIds)
                    ->whereNull('deleted_at')
                    ->get()
                    ->filter(function ($disponibilidad) use ($total_minutos) {
                        $horaInicio = Carbon::createFromFormat('H:i:s', $disponibilidad->hora_inicio);
                        $horaFin = Carbon::createFromFormat('H:i:s', $disponibilidad->hora_fin);
                        //$availableMinutes = $horaFin->diffInMinutes($horaInicio); // Se cambia por el upgrade a laravel 11
                        $availableMinutes = ($horaFin->timestamp - $horaInicio->timestamp) / 60;
                        return $total_minutos <= $availableMinutes;
                    });

                if ($centro_info["asignar_salas"]) {
                    //Buscar salas disponibles con conciliador
                    if (!$centro_info["conciliador_ligado_sala"]) {
                        // Buscar sala y conciliador - Flujo Normal
                        Log::alert("Buscar sala y conciliador");

                        // Disponibilidad de la sala L - V
                        $salas = Sala::where("centro_id", "=", $centro_id)
                            ->whereNull('deleted_at')
                            ->where("virtual", false)
                            ->get();
                        $salasIds = collect($salas)->pluck('id')->toArray();

                        /**
                         * Cargar incidencias
                         */
                        $incidencias = Incidencia::where(function ($query) use ($centro_id, $conciliadorIds, $salasIds) {
                            $query->where(function ($subquery) use ($centro_id) {
                                $subquery->where('incidenciable_type', 'App\Centro')
                                    ->where('incidenciable_id', $centro_id);
                            })
                                ->orWhere(function ($subquery) use ($salasIds) {
                                    $subquery->where('incidenciable_type', 'App\Sala')
                                        ->whereIn('incidenciable_id', $salasIds);
                                })
                                ->orWhere(function ($subquery) use ($conciliadorIds) {
                                    $subquery->where('incidenciable_type', 'App\Conciliador')
                                        ->whereIn('incidenciable_id', $conciliadorIds);
                                });
                        })
                            ->where('fecha_fin', '>=', $fecha_minima->startOfDay())
                            ->whereNull('deleted_at')
                            ->select('id', 'incidenciable_type', 'incidenciable_id', 'fecha_inicio', 'fecha_fin', 'suspende_terminos', 'justificacion_suspende_terminos')
                            ->get();

                        $salas_disponibilidad = Disponibilidad::where("disponibilidad_type", "=", "App\Sala")
                            ->whereIn('disponibilidad_id', $salasIds)
                            ->whereNull('deleted_at')
                            ->get();

                        $conciliador_con_sala = FALSE;
                        list($err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador) = self::GeneracionAudienciaAlgoritmoConSalas($solicitud, $folios, $incidencias, $inmediata, $duracion_audiencia, $requiere_salas, $motivos_requiere_salas, $fecha_audiencia, $fecha_maxima, $fecha_cita, $conciliador_con_sala, $centro_id, $centro_dias, $salas, $salas_disponibilidad, $conciliadores, $conciliadores_disponibilidad, $automatica);
                        if ($err != 200) return [$err, $err_message, $audiencia];
                    } else {
                        return [TRUE, "Opción no valida.", NULL];
                    }
                } else {
                    /**
                     * Cargar incidencias
                     */
                    $incidencias = Incidencia::where(function ($query) use ($centro_id, $conciliadorIds) {
                        $query->where(function ($subquery) use ($centro_id) {
                            $subquery->where('incidenciable_type', 'App\Centro')
                                ->where('incidenciable_id', $centro_id);
                        })
                            ->orWhere(function ($subquery) use ($conciliadorIds) {
                                $subquery->where('incidenciable_type', 'App\Conciliador')
                                    ->whereIn('incidenciable_id', $conciliadorIds);
                            });
                    })
                        ->where('fecha_fin', '>=', $fecha_minima->startOfDay())
                        ->whereNull('deleted_at')
                        ->select('id', 'incidenciable_type', 'incidenciable_id', 'fecha_inicio', 'fecha_fin', 'suspende_terminos', 'justificacion_suspende_terminos')
                        ->get();

                    if ($centro_info["conciliador_ligado_sala"]) {
                        // Buscar conciliador ligado a sala
                        Log::alert("El conciliador tiene sala asignada");
                        $conciliador_con_sala = TRUE;
                        if (ConciliadorHasSala::where("centro_id", $centro_id)->get()->count() < 1) {
                            return [true, 'No existen salas con conciliador configurados, Es necesario contactar al administrador.', null];
                        }
                        if ($automatica) {
                            $sala = $conciliador->tieneSala->sala;
                            $salas_disponibles = self::buscarDisponibilidadSalaConciliador($centro_disponibilidad, $centro_info, $sala);
                            if (!$salas_disponibles->isNotEmpty()) {
                                $solicitud->update(['code_estatus' => 'err_confirmar']);
                                return [TRUE, 'No existen salas con disponibilidad suficiente para agendar la audiencia.', NULL];
                            }
                        }
                    } else {
                        // No buscar salas disponibles pero si conciliador
                        Log::alert("No se asignan salas y si conciliador");
                        $conciliador_con_sala = FALSE;
                    }

                    list($err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador) = self::GeneracionAudienciaAlgoritmoSinSalas($solicitud, $folios, $incidencias, $inmediata, $duracion_audiencia, $requiere_salas, $motivos_requiere_salas, $fecha_audiencia, $fecha_maxima, $fecha_cita, $conciliador_con_sala, $centro_id, $centro_dias, $conciliadores, $conciliadores_disponibilidad, $automatica);
                    if ($err != 200) return [$err, $err_message, $audiencia];
                }
            }
            if ($fecha_audiencia > $fecha_maxima) {
                list($err, $err_message, $audiencia) = DB::transaction(function () use ($folios, $solicitud, $tipo_notificacion_id, $automatica, $inmediata, $requiere_salas, $duracion_audiencia, $motivos_requiere_salas, $fecha_audiencia, $request, $fecha_maxima) {
                    $exists = DB::table('expedientes')
                        ->where('folio', $folios['expediente'])
                        ->where('anio', date('Y'))
                        ->where('consecutivo', $folios['consecutivo_expediente'])
                        ->lockForUpdate()
                        ->exists();

                    if ($exists) {
                        Solicitud::find($solicitud->id)->update(['code_estatus' => 'err_confirmar']);
                        return [TRUE, "Error al generar el folio de la solicitud, favor de volverlo a intentar.", NULL];
                    }

                    list($err, $err_message, $audiencia) = self::GeneracionHerramientaForzada($tipo_notificacion_id, $solicitud, $automatica, $inmediata, $folios, $requiere_salas, $duracion_audiencia, $motivos_requiere_salas, $fecha_audiencia, $request, $fecha_maxima);
                    return [$err, $err_message, $audiencia];
                });
                return [$err, $err_message, $audiencia];
            }

            $end_algoritmo_debug = microtime(true);
            $total_algoritmo_debug = $end_algoritmo_debug - $start_algoritmo_debug;
            Log::info("Tiempo de ejecución total(Algoritmo " . $user_id . "): " . $total_algoritmo_debug . " segundos. ");
            Log::alert("Minimo dia habil: " . $minimo_dia_habil);
            Log::alert("Maximo dia habil: " . $maximo_dia_habil);
            Log::alert("Dias Vigencia: " . $dias_vigencia);
            Log::alert("Centro:" . $centro_info);
            Log::alert("Sala: " . $salas_disponibles);
            Log::alert("Conciliador: " . $conciliador);            
            $conciliador_persona = Persona::where('id', $conciliador['persona_id'])->first();
            Log::alert("Persona: " . $conciliador_persona);
            Log::alert("Fecha minima: " . $fecha_minima);
            Log::alert("Fecha vigencia: " . $fecha_vigencia_naturales);
            Log::alert("Horario Encontrado: " . $hora_inicio_encontrada . " - " . $hora_fin_encontrada . " / " . $fecha_audiencia->format('Y-m-d'));
            Log::alert("Audiencia: " . $audiencia);
            // guardamos la sala y el conciliador a la audiencia
            SalaAudiencia::create(["audiencia_id" => $audiencia->id, "sala_id" => $var_sala_id, "solicitante" => true]);
            ConciliadorAudiencia::create(["audiencia_id" => $audiencia->id, "conciliador_id" => $conciliador["id"], "solicitante" => true]);
            //Creamos los registros de Audiencias Partes
            $err = false;
            $err_message = '';
            $partes = $solicitud->partes()->whereNull('archivado')->orderby('tipo_parte_id', 'asc')->get();

            foreach ($partes as $parte) {

                $tipo_notificacion = null;
                $comparecientes = collect([]);
                $comparecio = collect([]);
                if ($ultima_audiencia_ejecutada) {
                    $comparecientes = $ultima_audiencia_ejecutada->comparecientes;
                    $comparecio = $comparecientes->where('parte_id', $parte->id);
                }
                //Generamos AudienciaParte Para todos, menos representantes
                //Se valida si comparecio con la linea !$comparecio->isEmpty() para validar que genere Audiencias partes unicamente para comparecientes
                //Se valida $comparecientes->isEmpty() && $n_audiencia == 1 && $parte->ratifico para especificar que no
                //existan comparecientes, que sea la primer audiencia y que hayan ratificado (Esto para la edicion de audiencias)
                if ($parte->tipo_parte_id == 2 || $parte->tipo_parte_id == 1) {
                    $audiencia_parte = AudienciaParte::where('parte_id', $parte->id)->orderBy('id', 'desc')->first();

                    //Para determinar si comparecio la parte principal
                    //Si no comparecio se avalúa si tiene Representante legal
                    //Si tiene RL se evalúa si compareció
                    //Si no compareció el RL ni la parte principal se va por los otros tipos de notificaciones
                    //Aplica para RL de solicitantes y citados morales o fisicos
                    $comparecienteParte = null;
                    if (isset($audiencia_parte->id)) {
                        if (isset($primera_audiencia->id)) {
                            $comparecienteParte = Compareciente::where('parte_id', $audiencia_parte->parte->id)->where('audiencia_id', $primera_audiencia->id)->first();
                            if (!isset($comparecienteParte->id)) {
                                $parte_representante = Parte::where('parte_representada_id', $parte->id)->where('representante', true)->first();
                                if (isset($parte_representante->id)) {
                                    $comparecienteParte = Compareciente::where('parte_id', $parte_representante->id)->where('audiencia_id', $primera_audiencia->id)->first();
                                }
                            }
                        }
                    }

                    if (isset($comparecienteParte->id)) {
                        $tipo_notificacion = 7; //G) Notificado al comparecer
                    } else {
                        if (filter_var($parte->notificacion_buzon, FILTER_VALIDATE_BOOLEAN)) {
                            $tipo_notificacion = 4; //D) Notificado por buzón electrónico
                        } else {
                            $tipo_notificacion = isset($request->tipo_notificacion_id) ? $request->tipo_notificacion_id : 2; //B) El notificador del centro entrega citatorio a citaados
                        }
                    }
                    try{
                        AudienciaParte::create(["audiencia_id" => $audiencia->id, "parte_id" => $parte->id, "tipo_notificacion_id" => $tipo_notificacion, 'finalizado' => ($tipo_notificacion == 7 ? 'FINALIZADO EXITOSAMENTE' : null), 'fecha_notificacion' => ($tipo_notificacion == 7 ? now() : null)]);
                    } catch (\Exception $e) {
                        Log::error('AudienciaParte (Algoritmo).', [
                            'audiencia_id' => $audiencia->id,
                            'parte_id' => $parte->id,
                            'tipo_notificacion_id' => $tipo_notificacion,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            foreach ($audiencia->salasAudiencias as $sala) {
                $sala->sala;
            }
            foreach ($audiencia->conciliadoresAudiencias as $conciliador_audiencia) {
                $conciliador_audiencia->conciliador->persona;
            }
            $audiencia->tipo_solicitud_id = $solicitud->tipo_solicitud_id;

            if ($generar_documentos) {
                $start_algoritmo_debug = microtime(true);
                list($err, $err_message, $audiencia) = self::GeneracionDocumentosAudiencia($solicitud, $audiencia, $request, $partes, $tipo_notificacion_id, $automatica);
                $end_algoritmo_debug = microtime(true);
                $total_algoritmo_debug = $end_algoritmo_debug - $start_algoritmo_debug;
                Log::info("Tiempo de ejecución total(Documentos " . $user_id . "): " . $total_algoritmo_debug . " segundos. ");
            }
            return [$err, $err_message, $audiencia];
        } catch (Exception $e) {
            $err_message = $e->getMessage();
            Log::error('Error en ejemploMetodo: ' . $err_message);
            return [TRUE, "Favor de intentar de nuevo.", null];
        }
    }

    public static function GeneracionAudienciaInmediata($centro_id, $hasrole_personal_conciliador, $personal_conciliador, $solicitud, $automatica, $inmediata, $folios, $duracion_audiencia, $motivos_requiere_salas)
    {
        $err = false;
        $err_message = '';
        $sala = Sala::where("centro_id", $centro_id)->where("virtual", true)->first();
        if ($sala == null) return [TRUE, 'No hay salas virtuales disponibles.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL];

        $var_sala_id = $sala->id;
        // Validamos que el que ratifica sea conciliador
        if (!$hasrole_personal_conciliador) {
            return [TRUE, 'La solicitud con convenio solo puede ser confirmada por personal conciliador.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL];
        } else {
            //Buscamos el conciliador del usuario
            if (isset($personal_conciliador)) {
                $conciliador_id = $personal_conciliador->id;
            } else {
                return [TRUE, 'El usuario no esta dado de alta en la lista de conciliadores.', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL];
            }
        }
        $conciliador = $personal_conciliador;
        $fecha_audiencia = Carbon::now();
        $hora_inicio_encontrada = now()->format('H:i:s');
        $duracion_audiencia_explode = explode(':', $duracion_audiencia);
        $hora_fin_encontrada = Carbon::now()
            ->addHours((int)$duracion_audiencia_explode[0])
            ->addMinutes((int)$duracion_audiencia_explode[1])
            ->format('H:i:s');
        $dias_disponibles_sala = collect();
        $dias_disponibles_sala["id"] = 0;
        $dias_disponibles_sala["sala_id"] = 0;
        $dias_disponibles_sala["dia"] = Carbon::now()->dayOfWeek;
        $dias_disponibles_sala["hora_inicio"] = $hora_inicio_encontrada;
        $dias_disponibles_sala["hora_fin"] =   $hora_fin_encontrada;
        $dias_disponibles_sala["sala_nombre"] = "-";
        list($solicitud, $expediente, $audiencia) = self::generaModeloAudiencia($solicitud, $automatica, $inmediata, $folios, false, $duracion_audiencia, $motivos_requiere_salas, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $conciliador_id);
        return [$err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $dias_disponibles_sala, $var_sala_id, $conciliador];
    }

    public static function generaModeloAudiencia($solicitud = null, $automatica = null, $inmediata = null, $folios = null, $requiere_salas = null, $duracion_audiencia = null, $motivos_requiere_salas = null, $fecha_audiencia = null, $hora_inicio_encontrada = null, $hora_fin_encontrada = null, $conciliador_id = null, $user_id = null)
    {
        if (!$automatica || $inmediata) {
            $fecha_ratificacion = now();
            $resultado = DB::select('SELECT * FROM calcular_periodo_general(?, ?, ?, ?, ?, ?)', [now(), $solicitud->centro_id, env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45), env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45), env("DIAS_CALCULAR_PERIODO_GENERAL", 45), 'naturales']);
            $fecha_vigencia = $resultado[0]->fecha_minima;
            $solicitud->update([
                "estatus_solicitud_id" => $inmediata ? 2 : EstatusSolicitud::where("nombre", "PENDIENTE DE AUDIENCIA")->first()->id,
                "url_virtual" => null,
                "ratificada" => true,
                "fecha_ratificacion" => $fecha_ratificacion,
                "fecha_vigencia" => $fecha_vigencia,
                "inmediata" => filter_var($inmediata, FILTER_VALIDATE_BOOLEAN),
                "user_id" => $user_id ,
                'code_estatus' => $inmediata ? 'completado' : 'pendiente_audiencia',
                'modified_user_id' => NULL
            ]);

            //Creamos el registro del expediente
            $expediente = Expediente::create([
                "solicitud_id" => $solicitud->id,
                "folio" => $folios['expediente'],
                "anio" => date('Y'),
                "consecutivo" => $folios["consecutivo_expediente"]
            ]);
        } else {
            $solicitud->update([
                "estatus_solicitud_id" => EstatusSolicitud::where("nombre", "PENDIENTE DE AUDIENCIA")->first()->id,
                'code_estatus' => 'pendiente_audiencia',
                'modified_user_id' => NULL
            ]);
            $expediente = $solicitud->expediente;
        }

        try{

            $audienciaExistente = Audiencia::where('expediente_id', $expediente->id)
                ->whereNull('deleted_at')
                ->where('finalizada', false)
                ->select('id')        
                ->first();

            if ($audienciaExistente) {
                return [TRUE, $solicitud, $expediente, NULL, "No se puede generar una nueva audiencia, debido a que existe una audiencia ya generada con estatus de 'Pendiente'"];
            }

            $audiencia = Audiencia::create([
                "expediente_id" => $expediente->id,
                "multiple" => $requiere_salas,
                "fecha_limite_audiencia" => Carbon::parse(Carbon::now())->format('Y-m-d'),
                "fecha_audiencia" => $inmediata ? $fecha_audiencia->format("Y-m-d") : null,
                "hora_inicio" => $inmediata ? $hora_inicio_encontrada :  null,
                "hora_fin" => $inmediata ? $hora_fin_encontrada : null,
                "conciliador_id" => $inmediata ? $conciliador_id : null,
                "numero_audiencia" => Audiencia::where('expediente_id', $expediente->id)->count() + 1,
                "reprogramada" => false,
                "anio" => date('Y'),
                "folio" => $folios["audiencia"],
                "encontro_audiencia" => $inmediata ? True : False,
                "fecha_cita" => isset($fecha_cita) ? Carbon::createFromFormat('d/m/Y', $fecha_cita)->format('Y-m-d') : NULL,
                "etapa_notificacion_id" => \App\EtapaNotificacion::where("etapa", "ilike", "%Ratificación%")->first()->id,
                "duracion_audiencia" => $duracion_audiencia,
                "motivos_salas" => is_array($motivos_requiere_salas) ? implode(', ', $motivos_requiere_salas) : NULL
            ]);
            Log::alert("Audiencia (generaModeloAudiencia): " . $audiencia);
            if (is_null($audiencia)) {
                Log::alert('Audiencia (generaModeloAudiencia):', [
                    'solicitud' => $solicitud,
                    'mensaje' => 'La audiencia es NULL',
                ]);
            }  
        } catch (\Exception $e) {
            Log::error('Error al crear la audiencia (generaModeloAudiencia)', [
                'solicitud' => $solicitud,
                'expediente_id' => $expediente->id,
                'mensaje' => $e->getMessage(),
            ]);
        }      

        //Modificamos las partes que confirman
        foreach ($solicitud->partes as $key => $parte) {
            if (count($parte->documentos) > 0 || $parte->tipo_parte_id == 3) {
                if ($parte->tipo_parte_id == 3) {
                    $parteRep = Parte::find($parte->parte_representada_id);
                    if ($parteRep->tipo_parte_id == 1) $parte = $parteRep;
                }
                $parte->update(["ratifico" => true]);
            }
        }
        $solicitud = Solicitud::find($solicitud->id);
        foreach ($solicitud->partes as $key => $parte) {
            if(!$parte->ratifico && $parte->tipo_parte_id == 1){
                Parte::find($parte->id)->update(["archivado"=>true]);
            }
        }

        return [$solicitud, $expediente, $audiencia];
    }

    public static function GeneracionHerramientaForzada($tipo_notificacion_id, $solicitud, $automatica, $inmediata, $folios, $requiere_salas, $duracion_audiencia, $motivos_requiere_salas, $fecha_audiencia, $request, $fecha_maxima)
    {

        Log::alert('Ingresa a HF -> Fechas: ', [
            'solicitud' => $solicitud,
            'fecha_audiencia' => $fecha_audiencia,
            'fecha_maxima' => $fecha_maxima,
        ]);        

        $primera_audiencia = null;

        if (isset($solicitud->expediente)) {
            $primera_audiencia = Audiencia::with('audienciaParte')->where('expediente_id', $solicitud->expediente->id)->orderBy('id', 'desc')->first();
        }

        if ((int) $tipo_notificacion_id == 1) {
            return [true, 'La fecha de audiencia obtenida rebasa la fecha límite para agendarla. Es necesario cambiar el tipo de notificación por "Un notificador del centro entrega el citatorio al citado(s)" o "Agendar cita con el notificador para entrega de citatorio"', null];
        }

        list($solicitud, $expediente, $audiencia) = self::generaModeloAudiencia($solicitud, $automatica, $inmediata, $folios, $requiere_salas, $duracion_audiencia, $motivos_requiere_salas);

        if (is_null($audiencia)) {
            Log::alert('HF (return generaModeloAudiencia): ', [
                'solicitud' => $solicitud,
                'mensaje' => 'audiencia es NULL'
            ]);
        }        

        //Creamos los acuses y actas de archivado
        foreach ($audiencia->salasAudiencias as $sala) {
            $sala->sala;
        }
        foreach ($audiencia->conciliadoresAudiencias as $conciliador_audiencia) {
            $conciliador_audiencia->conciliador->persona;
        }

        //Creamos los registros de Audiencias Partes
        $partes = $solicitud->partes()->whereNull('archivado')->orderby('tipo_parte_id', 'asc')->get();
        $tipo_notificacion_id = null;

        $ultima_audiencia_ejecutada = $expediente->ultima_audiencia_ejecutada;

        Log::info('HF (partes): ', [
            'solicitud' => $solicitud,
            'cantidad' => $partes->count()
        ]);

        foreach ($partes as $parte) {

            $tipo_notificacion = null;
            $comparecientes = collect([]);
            $comparecio = collect([]);
            if ($ultima_audiencia_ejecutada) {
                $comparecientes = $ultima_audiencia_ejecutada->comparecientes;
                $comparecio = $comparecientes->where('parte_id', $parte->id);
            }

            Log::info("Parte migrada ante de entrar" . $parte->nombre);
            //Generamos AudienciaParte Para todos, menos representantes
            //Se valida si comparecio con la linea !$comparecio->isEmpty() para validar que genere Audiencias partes unicamente para comparecientes
            //Se valida $comparecientes->isEmpty() && $n_audiencia == 1 && $parte->ratifico para especificar que no
            //existan comparecientes, que sea la primer audiencia y que hayan ratificado (Esto para la edicion de audiencias)
            if ($parte->tipo_parte_id == 2 || $parte->tipo_parte_id == 1 ) {
                $audiencia_parte = AudienciaParte::where('parte_id', $parte->id)->orderBy('id', 'desc')->first();
                //Para determinar si comparecio la parte principal
                //Si no comparecio se avalúa si tiene Representante legal
                //Si tiene RL se evalúa si compareció
                //Si no compareció el RL ni la parte principal se va por los otros tipos de notificaciones
                //Aplica para RL de solicitantes y citados morales o fisicos
                $comparecienteParte = null;
                if (isset($audiencia_parte->id)) {
                    if (isset($primera_audiencia->id)) {
                        $comparecienteParte = Compareciente::where('parte_id', $audiencia_parte->parte->id)->where('audiencia_id', $primera_audiencia->id)->first();
                        if (!isset($comparecienteParte->id)) {
                            $parte_representante = Parte::where('parte_representada_id', $parte->id)->where('representante', true)->first();
                            if (isset($parte_representante->id)) {
                                $comparecienteParte = Compareciente::where('parte_id', $parte_representante->id)->where('audiencia_id', $primera_audiencia->id)->first();
                            }
                        }
                    }
                }

                if (isset($comparecienteParte->id)) {
                    $tipo_notificacion = 7; //G) Notificado al comparecer
                } else {
                    if (filter_var($parte->notificacion_buzon, FILTER_VALIDATE_BOOLEAN)) {
                        $tipo_notificacion = 4; //D) Notificado por buzón electrónico
                    } else {
                        $tipo_notificacion = isset($request->tipo_notificacion_id) ? $request->tipo_notificacion_id : 2; //B) El notificador del centro entrega citatorio a citaados
                    }
                }

                try {
                    AudienciaParte::create(["audiencia_id" => $audiencia->id, "parte_id" => $parte->id, "tipo_notificacion_id" => $tipo_notificacion, 'finalizado' => ($tipo_notificacion == 7 ? 'FINALIZADO EXITOSAMENTE' : null), 'fecha_notificacion' => ($tipo_notificacion == 7 ? now() : null)]);
                } catch (\Exception $e) {
                    Log::error('HF: No se pudo crear el registro en AudienciaParte.', [
                        'solicitud' => $solicitud,
                        'audiencia_id' => $audiencia->id,
                        'parte_id' => $parte->id,
                        'tipo_notificacion_id' => $tipo_notificacion,
                        'error' => $e->getMessage(),
                    ]);
                }
            
            }else{
                Log::alert('HF (tipo_parte_id): ', [
                    'solicitud' => $solicitud,
                    'parte' => $parte,
                    'mensaje' => 'No se generan la AudienciaParte'
                ]);
            }
        }

        $acuse = Documento::where('documentable_type', 'App\Solicitud')->where('documentable_id', $solicitud->id)->where('clasificacion_archivo_id', 40)->first();
        if ($acuse != null) {
            $acuse->delete();
        }

        foreach ($solicitud->partes()->get() as $parte) {
            if ($parte->tipo_parte_id == 1) {
                if ($parte->ratifico == true) {
                    event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud_id, 65, 31, $parte->id, null, null, $parte->id));
                } else {
                    event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud_id, 66, 30, $parte->id, null, null, $parte->id));
                    Parte::find($parte->id)->update(["archivado"=>true]);
                }
            }
        }

        event(new GenerateDocumentResolution("", $solicitud->id, 40, 6));
        $audiencia->tipo_solicitud_id = $solicitud->tipo_solicitud_id;

        return [true, 'La fecha "' . $fecha_audiencia->format('d/m/Y') . '" disponible para la generación de la audiencia es mayor a los días de vigencia para el proceso de audiencias, siendo la fecha limite "' . $fecha_maxima->format('d/m/Y') . '", El administrador de su centro le ayudará con la generación de la audiencia.', $audiencia];
    }

    public static function GeneracionAudienciaAlgoritmoSinSalas($solicitud, $folios, $incidencias, $inmediata, $duracion_audiencia, $requiere_salas, $motivos_requiere_salas, $fecha_audiencia, $fecha_maxima, $fecha_cita, $conciliador_con_sala, $centro_id, $centro_disponibilidad, $conciliadores, $conciliadores_disponibilidad, $automatica)
    {
        /**
         * Centro: Analizar Fecha
         */
        while ($fecha_audiencia <= $fecha_maxima) {
            //No analizar Sabados y Domingos
            if ($fecha_audiencia->isWeekend() || !in_array(strval($fecha_audiencia->dayOfWeek), $centro_disponibilidad)) {
                $fecha_audiencia->addDay();
                continue;
            }

            //Se buscan incidencias de suspenden terminos del Centro
            $incidencia_centro = $incidencias->filter(function ($incidencia) use ($fecha_audiencia) {
                $fecha_inicio = substr($incidencia['fecha_inicio'], 0, 10); // Obtiene solo año-mes-día
                $fecha_fin = substr($incidencia['fecha_fin'], 0, 10); // Obtiene solo año-mes-día
                return $fecha_inicio <= $fecha_audiencia->format('Y-m-d') && $fecha_fin >= $fecha_audiencia->format('Y-m-d') && $incidencia['incidenciable_type'] == 'App\Centro';
            })->first();

            if ($incidencia_centro && $incidencia_centro["suspende_terminos"]) {
                //No hay disponibilidad en el día
                $fecha_audiencia->addDay();
                continue;
            }

            //Analizar la siguiente fecha
            $salas_disponibles = collect();
            $sala_disponibilidad["id"] = 0;
            $sala_disponibilidad["sala_id"] = "0";
            $sala_disponibilidad["disponibilidad_id"] = "0";
            $sala_disponibilidad["dia"] = strval($fecha_audiencia->dayOfWeek);
            $sala_disponibilidad["hora_inicio"] = "08:00:00";
            $sala_disponibilidad["hora_fin"] = "18:00:00";
            $sala_disponibilidad["sala_nombre"] = "NA-0";
            $salas_disponibles->push($sala_disponibilidad);

            $sala_asignada = $salas_disponibles->first();
            $hora_inicio_sala = Carbon::parse($sala_asignada["hora_inicio"]);
            $hora_fin_sala = Carbon::parse($sala_asignada["hora_fin"]);
            $horarios = self::generarHorario($hora_inicio_sala, $hora_fin_sala, $duracion_audiencia);

            /**
             * Centro: Analizar Fecha/Hora
             */
            $horasTrabajadas = [];
            if (count($conciliadores) > 0) {
                $horasTrabajadas = Audiencia::whereIn('conciliador_id', collect($conciliadores)->pluck('id')->toArray())
                    ->where('fecha_audiencia', $fecha_audiencia->format('Y-m-d'))
                    ->selectRaw('conciliador_id, SUM(EXTRACT(EPOCH FROM hora_fin - hora_inicio) / 3600) AS horas_trabajadas')
                    ->groupBy('conciliador_id')
                    ->orderBy('horas_trabajadas')
                    ->pluck('conciliador_id')
                    ->toArray();

                // Convertir el array a una colección
                $conciliadores_datos = collect($conciliadores);
                // Ordenar la colección de acuerdo al orden específico
                $conciliadores_soft = $conciliadores_datos->sortBy(function ($item) use ($horasTrabajadas) {
                    return array_search($item['id'], $horasTrabajadas);
                });
                // Obtener los datos ordenados como un array
                $conciliadores = $conciliadores_soft->values()->all();
            }

            //validar horario y conciliador start - v1
            $filtrado_audiencias = Audiencia::whereIn('conciliador_id', collect($conciliadores)->pluck('id')->toArray())
                ->where('fecha_audiencia', $fecha_audiencia->format('Y-m-d'))
                ->whereNull('deleted_at')
                ->select('id', 'conciliador_id', 'fecha_audiencia', 'hora_inicio', 'hora_fin')
                ->get();
            //end v-1

            for ($i = 0; $i < count($horarios) - 1; $i++) {
                $hora_inicio_encontrada = $horarios[$i];
                $hora_fin_encontrada = date('H:i', strtotime($horarios[$i]) + strtotime($duracion_audiencia, 0) - strtotime('00:00', 0));
                $rango = ['fecha_inicio' => $fecha_audiencia->format('Y-m-d') . " " . $hora_inicio_encontrada, 'fecha_fin' => $fecha_audiencia->format('Y-m-d') . " " . $hora_fin_encontrada];
                $incidencia_centro = $incidencias->filter(function ($incidencia) use ($rango) {
                    // Convertir las fechas a objetos DateTime para facilitar la comparación
                    $fecha_inicio_incidencia = new DateTime($incidencia['fecha_inicio']);
                    $fecha_fin_incidencia = new DateTime($incidencia['fecha_fin']);
                    $fecha_inicio_rango = new DateTime($rango['fecha_inicio']);
                    $fecha_fin_rango = new DateTime($rango['fecha_fin']);

                    // Comprobar si hay superposición de horarios
                    return $fecha_inicio_incidencia < $fecha_fin_rango && $fecha_fin_incidencia > $fecha_inicio_rango && $incidencia['incidenciable_type'] == 'App\Centro';
                })->isNotEmpty();
                if ($incidencia_centro) continue;

                //validar horario y conciliador start - v1
                $hora_inicio_encontrada_timestamp = strtotime($hora_inicio_encontrada);
                $hora_fin_encontrada_timestamp = strtotime($hora_fin_encontrada);
                $audiencias_filtradas = $filtrado_audiencias->filter(function ($audiencia) use ($hora_inicio_encontrada_timestamp, $hora_fin_encontrada_timestamp) {
                    $hora_inicio_audiencia_timestamp = strtotime($audiencia->hora_inicio);
                    $hora_fin_audiencia_timestamp = strtotime($audiencia->hora_fin);
                    return ($hora_inicio_encontrada_timestamp < $hora_fin_audiencia_timestamp) && ($hora_fin_encontrada_timestamp > $hora_inicio_audiencia_timestamp);
                });
                //end v1

                list($err, $err_message, $audiencia, $expediente, $solicitud, $salas_disponibles, $var_sala_id, $conciliador) = self::AnalizarConciliadores($conciliadores, $conciliador_con_sala, $centro_id, $sala_asignada, $conciliadores_disponibilidad, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $incidencias, $solicitud, $folios, $inmediata, $fecha_maxima, $duracion_audiencia, $requiere_salas, $fecha_cita, $motivos_requiere_salas, $salas_disponibles, $automatica, $audiencias_filtradas);

                if ($err == 200) {
                    return [$err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador];
                } elseif ($err) {
                    return [$err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador];
                }
            }
            $fecha_audiencia->addDay();
        }
        return [200, "Herramienta forzada", NULL, NULL, $solicitud, $fecha_audiencia, NULL, NULL, NULL, NULL, NULL];
    }

    public static function GeneracionAudienciaAlgoritmoConSalas($solicitud, $folios, $incidencias, $inmediata, $duracion_audiencia, $requiere_salas, $motivos_requiere_salas, $fecha_audiencia, $fecha_maxima, $fecha_cita, $conciliador_con_sala, $centro_id, $centro_disponibilidad, $salas, $salas_disponibilidad, $conciliadores, $conciliadores_disponibilidad, $automatica)
    {
        /**
         * Centro: Analizar Fecha
         */
        while ($fecha_audiencia <= $fecha_maxima) {
            //No analizar Sabados y Domingos
            if ($fecha_audiencia->isWeekend() || !in_array(strval($fecha_audiencia->dayOfWeek), $centro_disponibilidad)) {
                $fecha_audiencia->addDay();
                continue;
            }

            //Se buscan incidencias de suspenden terminos del Centro
            $incidencia_centro = $incidencias->filter(function ($incidencia) use ($fecha_audiencia) {
                $fecha_inicio = substr($incidencia['fecha_inicio'], 0, 10); // Obtiene solo año-mes-día
                $fecha_fin = substr($incidencia['fecha_fin'], 0, 10); // Obtiene solo año-mes-día
                return $fecha_inicio <= $fecha_audiencia->format('Y-m-d') && $fecha_fin >= $fecha_audiencia->format('Y-m-d') && $incidencia['incidenciable_type'] == 'App\Centro';
            })->first();

            if ($incidencia_centro && $incidencia_centro["suspende_terminos"]) {
                //No hay disponibilidad en el día
                $fecha_audiencia->addDay();
                continue;
            }

            $dia_buscado = strval($fecha_audiencia->dayOfWeek);
            $dias_disponibles_salas = $salas_disponibilidad->filter(function ($item) use ($dia_buscado) {
                return strval($item['dia']) === strval($dia_buscado);
            });

            $resultados_fechas_disponibles_salas = DB::table('audiencias as a')
                ->leftJoin('salas_audiencias as s', 's.audiencia_id', '=', 'a.id')
                ->select('a.id', 'a.expediente_id', 'a.conciliador_id', 'a.fecha_audiencia', 'a.hora_inicio', 'a.hora_fin', 's.sala_id')
                ->where('a.fecha_audiencia', $fecha_audiencia->format('Y-m-d'))
                ->get();

            foreach ($dias_disponibles_salas as $sala_asignada) {
                $sala_asignada_disponible = $salas_disponibilidad->where('id', $sala_asignada["id"])->first();
                //Se obtiene la disponibilidad en minutos de la sala               
                $hora_inicio_sala = Carbon::parse($sala_asignada_disponible["hora_inicio"]);
                $hora_fin_sala = Carbon::parse($sala_asignada_disponible["hora_fin"]);
                $horarios = self::generarHorario($hora_inicio_sala, $hora_fin_sala, $duracion_audiencia);

                /**
                 * Centro: Analizar Fecha/Hora
                 */
                $horasTrabajadas = [];
                if (count($conciliadores) > 0) {
                    $horasTrabajadas = Audiencia::whereIn('conciliador_id', collect($conciliadores)->pluck('id')->toArray())
                        ->where('fecha_audiencia', $fecha_audiencia->format('Y-m-d'))
                        ->selectRaw('conciliador_id, COALESCE(SUM(EXTRACT(EPOCH FROM hora_fin - hora_inicio) / 3600), 0) AS horas_trabajadas')
                        ->groupBy('conciliador_id')
                        ->pluck('horas_trabajadas', 'conciliador_id') // Genera un array [conciliador_id => horas_trabajadas]
                        ->toArray();
                
                    $conciliadores = collect($conciliadores)->map(function ($conciliador) use ($horasTrabajadas) {
                        $conciliador['horas_trabajadas'] = $horasTrabajadas[$conciliador['id']] ?? 0; 
                        return $conciliador;
                    });
                    
                    // Ordenar por horas trabajadas
                    $conciliadores = $conciliadores->sortBy('horas_trabajadas')->values()->all();
                }

                //validar horario y conciliador start - v1
                $filtrado_audiencias = Audiencia::whereIn('conciliador_id', collect($conciliadores)->pluck('id')->toArray())
                    ->where('fecha_audiencia', $fecha_audiencia->format('Y-m-d'))
                    ->whereNull('deleted_at')
                    ->select('id', 'conciliador_id', 'fecha_audiencia', 'hora_inicio', 'hora_fin')
                    ->get();
                //end v-1

                for ($i = 0; $i < count($horarios) - 1; $i++) {
                    $hora_inicio_encontrada = $horarios[$i];
                    $hora_fin_encontrada = date('H:i', strtotime($horarios[$i]) + strtotime($duracion_audiencia, 0) - strtotime('00:00', 0));
                    $rango = ['fecha_inicio' => $fecha_audiencia->format('Y-m-d') . " " . $hora_inicio_encontrada, 'fecha_fin' => $fecha_audiencia->format('Y-m-d') . " " . $hora_fin_encontrada];

                    $incidencia_centro = $incidencias->filter(function ($incidencia) use ($rango) {
                        $fecha_inicio_incidencia = new DateTime($incidencia['fecha_inicio']);
                        $fecha_fin_incidencia = new DateTime($incidencia['fecha_fin']);
                        $fecha_inicio_rango = new DateTime($rango['fecha_inicio']);
                        $fecha_fin_rango = new DateTime($rango['fecha_fin']);
                        return $fecha_inicio_incidencia < $fecha_fin_rango && $fecha_fin_incidencia > $fecha_inicio_rango && ($incidencia['incidenciable_type'] == 'App\Centro' || $incidencia['incidenciable_type'] == 'App\Sala');
                    })->isNotEmpty();
                    // Comprobar si hay superposición de horarios
                    if ($incidencia_centro) continue;

                    $resultados_disponibles_salas = $resultados_fechas_disponibles_salas->filter(function ($registro) use ($sala_asignada, $hora_inicio_encontrada, $hora_fin_encontrada) {
                        return $registro->sala_id == $sala_asignada["disponibilidad_id"] && !(Carbon::parse($registro->hora_fin) <= Carbon::parse($hora_inicio_encontrada) || Carbon::parse($registro->hora_inicio) >= Carbon::parse($hora_fin_encontrada));

                    })->isNotEmpty();                    

                    // Comprobar si hay superposición de horarios
                    if ($resultados_disponibles_salas) continue;

                    //validar horario y conciliador start - v1
                    $hora_inicio_encontrada_timestamp = strtotime($hora_inicio_encontrada);
                    $hora_fin_encontrada_timestamp = strtotime($hora_fin_encontrada);
                    $audiencias_filtradas = $filtrado_audiencias->filter(function ($audiencia) use ($hora_inicio_encontrada_timestamp, $hora_fin_encontrada_timestamp) {
                        $hora_inicio_audiencia_timestamp = strtotime($audiencia->hora_inicio);
                        $hora_fin_audiencia_timestamp = strtotime($audiencia->hora_fin);
                        return ($hora_inicio_encontrada_timestamp < $hora_fin_audiencia_timestamp) && ($hora_fin_encontrada_timestamp > $hora_inicio_audiencia_timestamp);
                    });
                    //end v1

                    list($err, $err_message, $audiencia, $expediente, $solicitud, $salas_disponibles, $var_sala_id, $conciliador) = self::AnalizarConciliadores($conciliadores, $conciliador_con_sala, $centro_id, $sala_asignada, $conciliadores_disponibilidad, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $incidencias, $solicitud, $folios, $inmediata, $fecha_maxima, $duracion_audiencia, $requiere_salas, $fecha_cita, $motivos_requiere_salas, $sala_asignada_disponible, $automatica, $audiencias_filtradas);
                    if ($err == 200) {
                        return [$err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador];
                    } elseif ($err) {
                        return [$err, $err_message, $audiencia, $expediente, $solicitud, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $salas_disponibles, $var_sala_id, $conciliador];
                    }
                }
            }
            $fecha_audiencia->addDay();
        }
        return [200, "Herramienta forzada", NULL, NULL, $solicitud, $fecha_audiencia, NULL, NULL, NULL, NULL, NULL];
    }

    public static function AnalizarConciliadores($conciliadores, $conciliador_con_sala, $centro_id, $sala_asignada, $conciliadores_disponibilidad, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $incidencias, $solicitud, $folios, $inmediata, $fecha_maxima, $duracion_audiencia, $requiere_salas, $fecha_cita, $motivos_requiere_salas, $salas_disponibles, $automatica, $audiencias_filtradas)
    {
        /**
         * Centro/Conciliadores: Analizar Fecha/Hora
         */
        foreach ($conciliadores as $conciliador) {
            if ($conciliador_con_sala) {
                $conciliador_sala = ConciliadorHasSala::where("centro_id", $centro_id)
                    ->where("conciliador_id", $conciliador->id)
                    ->first();

                //El conciliador no tiene sala
                if (!$conciliador_sala) continue;
            }

            if (!isset($conciliador['persona_id'])) continue;

            $dia_disponibilidad = $sala_asignada["dia"];
            $conciliador_disponibilidad = $conciliador->id;

            $conciliador_disponibilidad = $conciliadores_disponibilidad->filter(function ($incidencia) use ($dia_disponibilidad, $conciliador_disponibilidad) {
                return (int)$dia_disponibilidad == (int)$incidencia['dia'] && (int)$conciliador_disponibilidad == (int)$incidencia['disponibilidad_id']  && $incidencia['disponibilidad_type'] == 'App\Conciliador';
            })->first();

            //validar horario y conciliador start - v1
            $hora_inicio_encontrada_timestamp = strtotime($hora_inicio_encontrada);
            $hora_fin_encontrada_timestamp = strtotime($hora_fin_encontrada);
            $audiencias_conciliador = $audiencias_filtradas->filter(function ($audiencia) use ($hora_inicio_encontrada_timestamp, $hora_fin_encontrada_timestamp, $conciliador) {
                $hora_inicio_audiencia_timestamp = strtotime($audiencia->hora_inicio);
                $hora_fin_audiencia_timestamp = strtotime($audiencia->hora_fin);
                return ($hora_inicio_encontrada_timestamp < $hora_fin_audiencia_timestamp) && ($hora_fin_encontrada_timestamp > $hora_inicio_audiencia_timestamp);
            })->filter(function ($audiencia) use ($conciliador) {
                return $audiencia->conciliador_id == $conciliador->id;
            });
            if ($audiencias_conciliador->isNotEmpty()) continue;
            //end - v1

            if ($conciliador_disponibilidad) {
                $rango = ['fecha_inicio' => $fecha_audiencia->format('Y-m-d') . " " . $hora_inicio_encontrada, 'fecha_fin' => $fecha_audiencia->format('Y-m-d') . " " . $hora_fin_encontrada, 'incidenciable_id' => $conciliador->id];

                if (!self::verificaDisponibilidadConciliador($hora_inicio_encontrada, $hora_fin_encontrada, $conciliador_disponibilidad["hora_inicio"], $conciliador_disponibilidad["hora_fin"])) {
                    //Buscar Siguiente conciliador
                    continue;
                }

                $incidencia_conciliadores = $incidencias->filter(function ($incidencia) use ($rango) {
                    // Convertir las fechas a objetos DateTime para facilitar la comparación
                    $fecha_inicio_incidencia = new DateTime($incidencia['fecha_inicio']);
                    $fecha_fin_incidencia = new DateTime($incidencia['fecha_fin']);
                    $fecha_inicio_rango = new DateTime($rango['fecha_inicio']);
                    $fecha_fin_rango = new DateTime($rango['fecha_fin']);
                    return $fecha_inicio_incidencia < $fecha_fin_rango && $fecha_fin_incidencia > $fecha_inicio_rango && (int)$incidencia['incidenciable_id'] == (int)$rango['incidenciable_id'] && $incidencia['incidenciable_type'] == 'App\Conciliador';
                });

                if (count($incidencia_conciliadores) > 0) {
                    //Buscar Siguiente conciliador
                    continue;
                }
                //Se encontro Fecha/hora audicencia
                if (isset($conciliador['horario_comida']) && !empty($conciliador['horario_comida'])) {
                    $incidencia_hora_comida = [
                        "fecha_inicio" => $fecha_audiencia->format('Y-m-d') . " " . $conciliador['horario_comida']["hora_inicio"],
                        "fecha_fin" => $fecha_audiencia->format('Y-m-d') . " " . $conciliador['horario_comida']["hora_fin"]
                    ];
                    $fecha_audiencia_encontrada = ['fecha_inicio' => $fecha_audiencia->format('Y-m-d') . ' ' . $hora_inicio_encontrada, 'fecha_fin' => $fecha_audiencia->format('Y-m-d') . ' ' . $hora_fin_encontrada];
                    if (strtotime($incidencia_hora_comida["fecha_inicio"]) < strtotime($fecha_audiencia_encontrada["fecha_fin"]) && strtotime($incidencia_hora_comida["fecha_fin"]) > strtotime($fecha_audiencia_encontrada["fecha_inicio"])) {
                        //Buscar Siguiente conciliador
                        continue;
                    }
                }
                list($err, $solicitudes, $expediente, $audiencia, $err_message) = self::GuardadoAudienciaEncontrada($conciliador, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $solicitud, $folios, $inmediata, $fecha_maxima, $duracion_audiencia, $requiere_salas, $fecha_cita, $motivos_requiere_salas, $automatica);
                if ($err == 200) {
                    if ($conciliador_con_sala) {
                        $conciliador_disponibilidad_sala = Sala::where("centro_id", $centro_id)
                            ->where("id", $conciliador_sala["sala_id"])
                            ->first();
                        $var_sala_id = $conciliador_disponibilidad_sala->id;
                        $salas_disponibles = $conciliador_disponibilidad_sala;
                    } else {
                        $var_sala_id = $sala_asignada["disponibilidad_id"];
                    }
                    return [$err, $err_message, $audiencia, $expediente, $solicitud, $salas_disponibles, $var_sala_id, $conciliador];
                } elseif ($err) {
                    return [$err, $err_message, $audiencia, $expediente, $solicitud, $salas_disponibles, $var_sala_id, $conciliador];
                }
            }
        }
        return [NULL, 'No existen conciliadores disponibles.', NULL, NULL, $solicitud, $salas_disponibles, NULL, NULL];
    }

    public static function GuardadoAudienciaEncontrada($conciliador, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $solicitud, $folios, $inmediata, $fecha_maxima, $duracion_audiencia, $requiere_salas, $fecha_cita, $motivos_requiere_salas, $automatica)
    {
        return DB::transaction(function () use ($conciliador, $fecha_audiencia, $hora_inicio_encontrada, $hora_fin_encontrada, $solicitud, $folios, $inmediata, $fecha_maxima, $duracion_audiencia, $requiere_salas, $fecha_cita, $motivos_requiere_salas, $automatica) {
            // Bloquear la fila para evitar que otros procesos la actualicen al mismo tiempo
            $audiencias = Audiencia::where("conciliador_id", $conciliador["id"])
                ->where("fecha_audiencia", '=', $fecha_audiencia->format('Y-m-d'))
                ->where(function ($query) use ($hora_inicio_encontrada, $hora_fin_encontrada) {
                    $query->where(function ($q) use ($hora_inicio_encontrada, $hora_fin_encontrada) {
                        $q->where(function ($q) use ($hora_inicio_encontrada, $hora_fin_encontrada) {
                            $q->where("hora_inicio", "<", $hora_fin_encontrada)
                                ->where("hora_fin", ">", $hora_inicio_encontrada);
                        })->orWhere(function ($q) use ($hora_inicio_encontrada, $hora_fin_encontrada) {
                            $q->where("hora_inicio", "=", $hora_inicio_encontrada)
                                ->orWhere("hora_fin", "=", $hora_fin_encontrada);
                        });
                    });
                })
                ->whereNull("deleted_at")
                ->lockForUpdate()
                ->first();

            // Verificar si ya existe una cita en la misma fecha y hora
            if ($audiencias) return [NULL, $solicitud, NULL, NULL, "Existe audiencia."];
            //Validar que exista información
            if (empty($folios["folios"]) || $folios["expediente"] == "" || !is_numeric($folios["audiencia"]) || !is_numeric($folios["consecutivo_expediente"])) {
                Solicitud::find($solicitud->id)->update(['code_estatus' => 'err_confirmar']);
                return [TRUE, $solicitud, NULL, NULL, "Error al generar los folios de la solicitud, favor de volverlo a intentar."];
            }
            if (!$automatica) {
                if (!is_null(Expediente::where("solicitud_id", $solicitud->id)->first())) {
                    $solicitud->update(['code_estatus' => 'pendiente_audiencia']);
                    return [TRUE, $solicitud, NULL, NULL, "Existe un expediente para esta solicitud. Es necesario contactar al administrador."];
                }
                $fecha_ratificacion = now();
                $resultado = DB::select('SELECT * FROM calcular_periodo_general(?, ?, ?, ?, ?, ?)', [now(), $solicitud->centro_id, env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45), env("DIAS_VIGENCIA_SOLICITUD_FEDERAL", 45), env("DIAS_CALCULAR_PERIODO_GENERAL", 45), 'naturales']);
                $fecha_vigencia = $resultado[0]->fecha_minima;
                $solicitud->update([
                    "estatus_solicitud_id" => 2,
                    "url_virtual" => null,
                    "ratificada" => true,
                    "fecha_ratificacion" => $fecha_ratificacion,
                    "fecha_vigencia" => $fecha_vigencia,
                    "inmediata" => filter_var($inmediata, FILTER_VALIDATE_BOOLEAN),
                    "user_id" => auth()->user()->id,
                    'code_estatus' => 'completado',
                    'modified_user_id' => NULL
                ]);

                //Modificamos las partes que confirman
                foreach ($solicitud->partes as $key => $parte) {
                    if (count($parte->documentos) > 0 || $parte->tipo_parte_id == 3) {
                        if ($parte->tipo_parte_id == 3) {
                            $parteRep = Parte::find($parte->parte_representada_id);
                            if ($parteRep->tipo_parte_id == 1) $parte = $parteRep;
                        }
                        $parte->update(["ratifico" => true]);
                    }
                }
                $solicitud = Solicitud::find($solicitud->id);
                foreach ($solicitud->partes as $key => $parte) {
                    if(!$parte->ratifico && $parte->tipo_parte_id == 1 ){
                        Parte::find($parte->id)->update(["archivado"=>true]);
                    }
                }
                //Creamos el registro del expediente
                $expediente = Expediente::create([
                    "solicitud_id" => $solicitud->id,
                    "folio" => $folios['expediente'],
                    "anio" => date('Y'),
                    "consecutivo" => $folios["consecutivo_expediente"]
                ]);
            } else {
                $expediente = $solicitud->expediente;
            }

            $existingAudiencia = Audiencia::where('conciliador_id', $conciliador["id"])
                ->where('fecha_audiencia', $fecha_audiencia->format('Y-m-d'))
                ->where('hora_inicio', $hora_inicio_encontrada)
                ->first();

            // Creamos el registro de la audiencia
            if (!$existingAudiencia) {
                try {

                    $audienciaExistente = Audiencia::where('expediente_id', $expediente->id)
                        ->whereNull('deleted_at')
                        ->where('finalizada', false)
                        ->select('id')             
                        ->lockForUpdate()          
                        ->first();

                    if ($audienciaExistente) {
                        return [TRUE, $solicitud, $expediente, NULL, "No se puede generar una nueva audiencia, debido a que existe una audiencia ya generada con estatus de 'Pendiente'"];
                    }

                    $audiencia = Audiencia::create([
                        "expediente_id" => $expediente->id,
                        "multiple" => $requiere_salas,
                        "fecha_audiencia" => $fecha_audiencia->format('Y-m-d'),
                        "fecha_limite_audiencia" => $fecha_maxima,
                        "hora_inicio" => $hora_inicio_encontrada,
                        "hora_fin" => $hora_fin_encontrada,
                        "conciliador_id" => $conciliador["id"],
                        "numero_audiencia" => Audiencia::where('expediente_id', $expediente->id)->count() + 1,
                        "reprogramada" => false,
                        "anio" => date('Y'),
                        "folio" => $folios["audiencia"],
                        "encontro_audiencia" => TRUE,
                        "fecha_cita" => isset($fecha_cita) ? Carbon::createFromFormat('d/m/Y', $fecha_cita)->format('Y-m-d') : NULL,
                        "etapa_notificacion_id" => \App\EtapaNotificacion::where("etapa", "ilike", "%Ratificación%")->first()->id,
                        "duracion_audiencia" => $duracion_audiencia,
                        "motivos_salas" => is_array($motivos_requiere_salas) ? implode(', ', $motivos_requiere_salas) : NULL
                    ]);

                    Log::alert("Audiencia (GuardadoAudienciaEncontrada): " . $audiencia);
                    
                    if (is_null($audiencia)) {
                        Log::alert('Audiencia (GuardadoAudienciaEncontrada):', [
                            'solicitud' => $solicitud,
                            'mensaje' => 'La audiencia es NULL',
                        ]);
                    } 
                } catch (\Exception $e) {
                    Log::error('Error al crear la audiencia (GuardadoAudienciaEncontrada)', [
                        'solicitud' => $solicitud,
                        'expediente_id' => $expediente->id,
                        'mensaje' => $e->getMessage(),
                    ]);
                }

            } else {
                return [NULL, $solicitud, NULL, NULL, "Siguiente conciliador"];
            }
            return [200, $solicitud, $expediente, $audiencia, "SUCCESS"];
        });
    }

    public static function GeneracionDocumentosAudiencia($solicitud, $audiencia, $request, $partes, $tipo_notificacion_id, $automatica = FALSE)
    {
        set_time_limit(0);
        $err = false;
        $err_message = '';
        //Creamos las actas de aceptación y no aceptación del buzón
        if (!$automatica) {
            foreach ($partes as $key => $parte) {
                if ($parte->tipo_parte_id != 3 && $parte->ratifico) {
                    if (filter_var($parte->notificacion_buzon, FILTER_VALIDATE_BOOLEAN)) {
                        $identificador = $parte->rfc;
                        $tipo = TipoPersona::whereNombre("FISICA")->first();
                        if ($parte->tipo_persona_id == $tipo->id) {
                            $identificador = $parte->curp;
                        }
                        $array_comparecen[] = $parte->id;
                        //Genera acta de aceptacion de buzón
                        if ($parte->tipo_parte_id == 1) {
                            event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 62, 19, $parte->id, null, null, $parte->id));
                        } else if ($parte->tipo_parte_id == 2) {
                        } else {
                            $representado = Parte::find($parte->parte_representada_id);
                            if ($representado->tipo_parte_id == 1) {
                                event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 62, 19, $representado->id, null, null, $representado->id));
                            }
                        }
                        BitacoraBuzon::create(['parte_id' => $parte->id, 'descripcion' => 'Se genera el documento de aceptación de buzón electrónico', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $identificador]);
                    } else {
                        //Genera acta de no aceptacion de buzón
                        if ($parte->tipo_parte_id == 1) {
                            event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 60, 22, $parte->id, null, null, $parte->id));
                        } else if ($parte->tipo_parte_id == 2) {
                        } else {
                            $representado = Parte::find($parte->parte_representada_id);
                            if ($representado->tipo_parte_id == 1) {
                                event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 60, 22, $representado->id, null, null, $representado->id));
                            }
                        }
                    }
                }
            }
        }
        // Creamos los citatorios
        foreach ($audiencia->audienciaParte as $parte_audiencia) {
            if ($parte_audiencia->parte->tipo_parte_id == 2) {
                if ($parte_audiencia->parte->tipo_persona_id == 1) {
                    $busqueda = $parte_audiencia->parte->curp;
                } else {
                    $busqueda = $parte_audiencia->parte->rfc;
                }
                BitacoraBuzon::create(['parte_id' => $parte_audiencia->parte_id, 'descripcion' => 'Se crea citatorio', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 14, 4, null, $parte_audiencia->parte_id));
            } elseif ($parte_audiencia->parte->tipo_parte_id == 1) {
                if ($parte_audiencia->parte->tipo_persona_id == 1) {
                    $busqueda = $parte_audiencia->parte->curp;
                } else {
                    $busqueda = $parte_audiencia->parte->rfc;
                }
                BitacoraBuzon::create(['parte_id' => $parte_audiencia->parte_id, 'descripcion' => 'Se crea notificación del solicitante', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 64, 29, $parte_audiencia->parte_id, null));
            }

            if ($parte_audiencia->parte->correo_buzon != null && !Str::contains($parte_audiencia->parte->correo_buzon, 'mibuzonlaboral.gob.mx')) {
                Mail::to($parte_audiencia->parte->correo_buzon)->send(new EnviarNotificacionBuzon($audiencia, $parte_audiencia->parte));
            }
            if (($solicitud->tipo_solicitud_id == 1 && $parte_audiencia->parte->tipo_parte_id == 2)) {
                $ultima_audiencia_ejecutada = $audiencia->expediente->ultima_audiencia_ejecutada;
                if ($ultima_audiencia_ejecutada && $automatica) {
                    $comparecienteParte = null;
                    $comparecienteParte = Compareciente::where('parte_id', $parte_audiencia->parte->id)->where('audiencia_id', $ultima_audiencia_ejecutada->id)->first();
                    if (!isset($comparecienteParte->id)) {
                        $parte_representante = Parte::where('parte_representada_id', $parte_audiencia->parte->id)->first();
                        if (isset($parte_representante->id)) {
                            $comparecienteParte = Compareciente::where('parte_id', $parte_representante->id)->where('audiencia_id', $ultima_audiencia_ejecutada->id)->first();
                        }
                    }
                    if (isset($comparecienteParte->id)) {
                        $tipo_notificacion = 7;
                        AudienciaParte::find($parte_audiencia->id)->update(["tipo_notificacion_id" => $tipo_notificacion, 'finalizado' => 'FINALIZADO EXITOSAMENTE', 'fecha_notificacion' => now()]);
                        BitacoraBuzon::create(['parte_id' => $parte_audiencia->parte_id, 'descripcion' => 'Se crea notificación por comparecencia', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                        event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 56, 18, null, $parte_audiencia->parte_id));
                    }
                }
            }
        }

        //Creamos los acuses y actas de archivado
        $acuse = Documento::where('documentable_type', 'App\Solicitud')->where('documentable_id', $solicitud->id)->where('clasificacion_archivo_id', 40)->first();
        if ($acuse != null) {
            $acuse->delete();
        }
        if (!$automatica) {
            foreach ($solicitud->partes()->get() as $parte) {
                if ($parte->tipo_parte_id == 1) {
                    if ($parte->ratifico == true) {
                        event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud_id, 65, 31, $parte->id, null, null, $parte->id));
                    } else {
                        event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud_id, 66, 30, $parte->id, null, null, $parte->id));
                        Parte::find($parte->id)->update(["archivado"=>true]);
                    }
                }
            }
        }
        //Al no haber más inserts y updates se cierra la transaccion
        /**
         * Notificación a Signo
         */
        if ($request->inmediata != "true" && $audiencia->encontro_audiencia && ($tipo_notificacion_id != 1 && $tipo_notificacion_id != null)) {
            foreach ($audiencia->audienciaParte as $audiencia_parte) {
                if ($audiencia_parte->parte->tipo_parte_id == 2) {
                    event(new RatificacionRealizada($audiencia->id, "citatorio", false, $audiencia_parte->id));
                }
            }
        }
        event(new GenerateDocumentResolution("", $solicitud->id, 40, 6));

        return [$err, $err_message, $audiencia];
    }

    public static function generarHorario($hora_inicio, $hora_fin, $duracion_audiencia)
    {
        $horario = [];
        $horaInicio = strtotime($hora_inicio);
        $horaFin = strtotime($hora_fin);
        //$intervalo = strtotime(Carbon::parse($duracion_audiencia)->format('H:i'), 0) - strtotime('00:00', 0);
        $intervalo = strtotime("00:15", 0) - strtotime('00:00', 0);
        while ($horaInicio <= $horaFin) {
            $hora = date('H:i', $horaInicio);
            $horario[] = $hora;
            $horaInicio += $intervalo;
        }
        return $horario;
    }

    public static function buscarDisponibilidadSalaConciliador($centro_disponibilidad, $centro_info, $sala = null)
    {
        $salas_disponibles = collect();
        if ($sala) {
            $salas[] = $sala;
        } else {
            $salas = $centro_info->salas()->where("virtual", $centro_info["apoyo_virtual"])->select("id", "sala")->get();
        }
        foreach ($salas as $sala) {
            $salas_disponibilidad = Incidencia::disponibilidadRegistrada($sala->id, "App\Sala", $centro_disponibilidad);
            foreach ($salas_disponibilidad as $sala_disponibilidad) {
                $sala_disponibilidad["sala_id"] = $sala["id"];
                $sala_disponibilidad["sala_nombre"] = $sala["sala"];
                $salas_disponibles->push($sala_disponibilidad);
            }
        }
        return $salas_disponibles;
    }

    public static function parseDuration($duration)
    {
        $formats = ['H:i:s', 'H:i', 'G:i'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $duration)->format('H:i:s');
            } catch (\Exception $e) {
                // Continúa
            }
        }
        return "01:30:00";
    }

    public static function verificaDisponibilidadConciliador($inicio, $fin, $rango_inicio, $rango_fin)
    {

        $inicio_time = self::convertirTiempo($inicio);
        $fin_time = self::convertirTiempo($fin);

        $rango_inicio_time = self::convertirTiempo($rango_inicio);
        $rango_fin_time = self::convertirTiempo($rango_fin);

        return ($inicio_time >= $rango_inicio_time && $inicio_time <= $rango_fin_time && $fin_time >= $rango_inicio_time && $fin_time <= $rango_fin_time);
    }

    public static function convertirTiempo($hora)
    {
        $formatos = ['H:i', 'H:i:s', 'H:i a', 'H:i:s a'];
        foreach ($formatos as $formato) {
            $hora_time = DateTime::createFromFormat($formato, $hora);
            if ($hora_time !== false) {
                return $hora_time;
            }
        }
        throw new Exception("Formato de hora no válido: $hora");
    }

    public static function generaDocumentoBuzon($solicitud, $audiencia)
    {
        $partes = $solicitud->partes()->orderby('tipo_parte_id', 'asc')->get();
        foreach ($partes as $key => $parte) {
            if (($parte->ratifico && $parte->tipo_parte_id == 1) || $parte->tipo_parte_id == 3) {
                if ($parte->tipo_parte_id == 3) {
                    $parteRep = Parte::find($parte->parte_representada_id);
                    if ($parteRep->tipo_parte_id == 1) {
                        $parte = $parteRep;
                    }
                }
                if (filter_var($parte->notificacion_buzon, FILTER_VALIDATE_BOOLEAN)) {
                    $identificador = $parte->rfc;
                    $tipo = TipoPersona::whereNombre("FISICA")->first();
                    if ($parte->tipo_persona_id == $tipo->id) {
                        $identificador = $parte->curp;
                    }
                    $array_comparecen[] = $parte->id;
                    //Genera acta de aceptacion de buzón
                    if ($parte->tipo_parte_id == 1) {
                        event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 62, 19, $parte->id, null, null, $parte->id));
                    } else if ($parte->tipo_parte_id == 2) {
                    } else {
                        $representado = Parte::find($parte->parte_representada_id);
                        if ($representado->tipo_parte_id == 1) {
                            event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 62, 19, $representado->id, null, null, $representado->id));
                        }
                    }
                    BitacoraBuzon::create(['parte_id' => $parte->id, 'descripcion' => 'Se genera el documento de aceptación de buzón electrónico', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $identificador]);
                } else {
                    //Genera acta de no aceptacion de buzón
                    if ($parte->tipo_parte_id == 1) {
                        event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 60, 22, $parte->id, null, null, $parte->id));
                    } else if ($parte->tipo_parte_id == 2) {
                    } else {
                        $representado = Parte::find($parte->parte_representada_id);
                        if ($representado->tipo_parte_id == 1) {
                            event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 60, 22, $representado->id, null, null, $representado->id));
                        }
                    }
                }
            }
        }
        return false;
    }

    public static function generaDocumentoCitatorios($solicitud, $audiencia, $edicion = true, $ultimaAudiencia = null)
    {
        $partes = $solicitud->partes()->orderby('tipo_parte_id', 'asc')->get();
        $expediente = Expediente::where('solicitud_id', $solicitud->id)->first();
        $n_audiencia = (int)$expediente->audiencia->count();
        $ultimaAudiencia = $solicitud->expediente->audiencia()->whereNotNull('fecha_audiencia')->where('finalizada', true)->orderBy("created_at", "DESC")->first();

        // Creamos los citatorios
        if ($n_audiencia == 1) {
            //Si es la primera audiencia
            //Desde la confirmación
            foreach ($partes as $parte) {
                $busqueda = null;
                if ($parte->tipo_persona_id == 1) {
                    $busqueda = $parte->curp;
                } else {
                    $busqueda = $parte->rfc;
                }

                if ($parte->tipo_parte_id == 1 and $parte->ratifico == true) {
                    BitacoraBuzon::create(['parte_id' => $parte->id, 'descripcion' => 'Se crea notificación del solicitante', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                    event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 64, 29, $parte->id, null));
                }

                if ($parte->tipo_parte_id == 2) {
                    $audienciaParte = AudienciaParte::where('audiencia_id', $audiencia->id)->where('parte_id', $parte->id)->first();

                    if (isset($audienciaParte->id)) {
                        if ($audienciaParte->tipo_notificacion_id == 1) {
                            event(new RatificacionRealizada($audiencia->id, "citatorio", false, $audienciaParte->id));
                        }
                        if ($audienciaParte->tipo_notificacion_id == 2 || $audienciaParte->tipo_notificacion_id == 3) {
                            event(new RatificacionRealizada($audiencia->id, "citatorio", false, $audienciaParte->id));
                        }

                        if ($audienciaParte->tipo_notificacion_id == 7) {
                            BitacoraBuzon::create(['parte_id' => $parte->parte_id, 'descripcion' => 'Se crea notificación por comparecencia', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                            event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 56, 18, null, $parte->id));
                        }
                    }

                    BitacoraBuzon::create(['parte_id' => $parte->id, 'descripcion' => 'Se crea citatorio', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                    event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 14, 4, null, $parte->id));
                }
            }
        } else {
            foreach ($partes as $parte) {
                $busqueda = null;
                $comparecio = null;
                //$comparecio = Compareciente::where('parte_id', $parte->id)->where('audiencia_id', $ultimaAudiencia->id)->first();
                $audienciaParteNueva = AudienciaParte::where("audiencia_id", $audiencia->id)->where("parte_id", $parte->id)->first();

                if ($parte->tipo_persona_id == 1) {
                    $busqueda = $parte->curp;
                } else {
                    $busqueda = $parte->rfc;
                }

                if (isset($audienciaParteNueva->id)) {
                    if ($parte->tipo_parte_id == 1) {
                        BitacoraBuzon::create(['parte_id' => $parte->id, 'descripcion' => 'Se crea notificación del solicitante', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                        event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 64, 29, $parte->id, null));
                    }

                    //No acepta buzón
                    //No comparece
                    //Que sea diferente a la edición
                    if ($parte->tipo_parte_id == 2 && $parte->notificacion_buzon == false && $edicion == false) {
                        //Actualizar los registros de Audiencias Partes
                        $audienciaParte = AudienciaParte::where('audiencia_id', $audiencia->id)->where('parte_id', $parte->id)->first();
                        if (isset($audienciaParte->id)) {
                            event(new RatificacionRealizada($audiencia->id, "citatorio", false, $audienciaParte->id));
                        }
                    }

                    if ($parte->tipo_parte_id == 2) {
                        if ($audienciaParteNueva->tipo_notificacion_id == 7) {
                            BitacoraBuzon::create(['parte_id' => $parte->parte_id, 'descripcion' => 'Se crea notificación por comparecencia', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                            event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 56, 18, null, $parte->id));
                        }

                        BitacoraBuzon::create(['parte_id' => $parte->id, 'descripcion' => 'Se crea citatorio', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
                        event(new GenerateDocumentResolution($audiencia->id, $solicitud->id, 14, 4, null, $parte->id));
                    }
                }
            }
        }
        return false;
    }

    public static function generaDocumentoAcusesActasArchivado($solicitud, $audiencia)
    {
        //Creamos los acuses y actas de archivado
        foreach ($audiencia->salasAudiencias as $sala) {
            $sala->sala;
        }
        foreach ($audiencia->conciliadoresAudiencias as $conciliador) {
            $conciliador->conciliador->persona;
        }
        $acuse = Documento::where('documentable_type', 'App\Solicitud')->where('documentable_id', $solicitud->id)->where('clasificacion_archivo_id', 40)->first();
        if ($acuse != null) {
            $acuse->delete();
        }

        foreach ($solicitud->partes()->get() as $parte) {
            if ($parte->tipo_parte_id == 1) {
                if ($parte->ratifico == true) {
                    event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud_id, 65, 31, $parte->id, null, null, $parte->id));
                } else {
                    event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud_id, 66, 30, $parte->id, null, null, $parte->id));
                    Parte::find($parte->id)->update(["archivado"=>true]);
                }
            }
        }
        return false;
    }

    public static function generaDocumentoNotificacionSIGNO($solicitud, $audiencia, $tipo_notificacion_id, $request = null)
    {
        //Al no haber más inserts y updates se cierra la transaccion
        /**
         * Notificación a Signo
         */
        if ($request->inmediata != "true" && $audiencia->encontro_audiencia && ($tipo_notificacion_id != 1 && $tipo_notificacion_id != null)) {
            foreach ($audiencia->audienciaParte as $audiencia_parte) {
                if ($audiencia_parte->parte->tipo_parte_id == 2) {
                    event(new RatificacionRealizada($audiencia->id, "citatorio", false, $audiencia_parte->id));
                }
            }
        }
        event(new GenerateDocumentResolution("", $solicitud->id, 40, 6));
        $audiencia->tipo_solicitud_id = $solicitud->tipo_solicitud_id;
        return false;
    }
}
