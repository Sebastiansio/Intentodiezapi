<?php

namespace App\Traits;

use App\Audiencia;
use App\AudienciaParte;
use App\Centro;
use App\ClasificacionArchivo;
use App\Compareciente;
use App\ConceptoPagoResolucion;
use App\DatoLaboral;
use App\Disponibilidad;
use App\Domicilio;
use App\Documento;
use App\EtapaResolucionAudiencia;
use App\Expediente;
use App\FirmaDocumento;
use App\Parte;
use App\Periodicidad;
use App\PlantillaDocumento;
use App\ResolucionPagoDiferido;
use App\ResolucionParteConcepto;
use App\ResolucionPartes;
use App\SalaAudiencia;
use App\SalarioMinimo;
use App\Services\StringTemplate;
use App\Services\Comparecientes;
use App\Solicitud;
use App\TipoDocumento;
use App\VacacionesAnio;
use App\User;
use App\Motivacion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use NumberFormatter;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Helper\Helper;
use Illuminate\Support\Facades\DB;
use Session;

trait GenerateDocument
{
    /**
     * Generar documento a partir de un modelo y de una plantilla
     * @return mixed
     */
    public function generarConstancia($idAudiencia, $idSolicitud, $clasificacion_id,$plantilla_id, $idSolicitante = null, $idSolicitado = null,$idDocumento = null,$idParteAsociada = null, $idPago = null)
    {
      $plantilla = PlantillaDocumento::find($plantilla_id);
      if($plantilla != null){
        if($idParteAsociada != ""){
          $padre = Parte::find($idParteAsociada);
          $directorio = 'expedientes/' . $padre->expediente_id . '/audiencias/' . $idAudiencia;
          $this->guardarDocumento($plantilla, $padre, $directorio, "audiencia", $idAudiencia, $idSolicitud, $clasificacion_id, $plantilla_id, $idSolicitante, $idSolicitado, $idDocumento, $idParteAsociada);
        }else if($idAudiencia != ""){
          $padre = Audiencia::find($idAudiencia);
          $directorio = 'expedientes/' . $padre->expediente_id . '/audiencias/' . $idAudiencia;
          $this->guardarDocumento($plantilla, $padre, $directorio, "audiencia", $idAudiencia, $idSolicitud, $clasificacion_id, $plantilla_id, $idSolicitante, $idSolicitado, $idDocumento, $idParteAsociada);
        }else{
          $padre = Solicitud::find($idSolicitud);
          $directorio = ($padre->expediente != null) ? ('expedientes/' . $padre->expediente->id . '/solicitud/' . $idSolicitud) : ('solicitudes/' . $idSolicitud);
          $this->guardarDocumento($plantilla, $padre, $directorio, "solicitud", $idAudiencia, $idSolicitud, $clasificacion_id, $plantilla_id, $idSolicitante, $idSolicitado, $idDocumento, $idParteAsociada);
        }
        return 'Guardado correctamente';
      }else{
        return 'No existe plantilla';
      }
    }

    public function guardarDocumento($plantilla, $padre, $directorio, $descripcion, $idAudiencia, $idSolicitud, $clasificacion_id, $plantilla_id, $idSolicitante, $idSolicitado, $idDocumento, $idParteAsociada){
      $algo = Storage::makeDirectory($directorio, 0775, true);
      $tipoArchivo = ClasificacionArchivo::find($clasificacion_id);
      //Creamos el registro
      $uuid = Str::uuid()->toString();
      $token = Str::random(64);
      if($idDocumento != null){
        $archivo = Documento::find($idDocumento);
        if(Storage::exists($archivo->ruta)){
          Storage::delete($archivo->ruta.".old");
          Storage::move($archivo->ruta, $archivo->ruta.".old");
        }
      }else{
        //qr_pulbico
        $archivo = $padre->documentos()->create(["descripcion" => "Documento de audiencia " . $tipoArchivo->nombre,"uuid"=>$uuid,"clasificacion_archivo_id" => $tipoArchivo->id, "qrtoken" => $token]);
      }
      //generamos html del archivo
      $start_debug = microtime(true);
      $html = $this->renderDocumento($idAudiencia,$idSolicitud, $plantilla->id, $idSolicitante, $idSolicitado,$archivo->id,$archivo->uuid, $archivo->qrtoken);
      $end_debug = microtime(true);
      $total_debug = $end_debug - $start_debug;
      Log::info("Tiempo de ejecución (Plantilla: " . $plantilla->id. "): " . $total_debug . " segundos. ");

      $firmantes = substr_count($html, 'class="qr"');
      $plantilla = PlantillaDocumento::find($plantilla->id);
      $nombreArchivo = $plantilla->nombre_plantilla;
      $nombreArchivo = $this->eliminar_acentos(str_replace(" ","",$nombreArchivo));
      $path = $directorio . "/".$nombreArchivo . $archivo->id . '.pdf';
      $fullPath = storage_path('app/' . $directorio) . "/".$nombreArchivo . $archivo->id . '.pdf';

      //Hacemos el render del pdf y lo guardamos en $fullPath
      $this->renderPDF($html, $plantilla->id, $fullPath);
      $archivo->update([
          "nombre" => str_replace($directorio . "/", '', $path),
          "nombre_original" => str_replace($directorio . "/", '', $path), //str_replace($directorio, '',$path->getClientOriginalName()),
          "descripcion" => "Documento de " . $descripcion ." " . $tipoArchivo->nombre,
          "ruta" => $path,
          "tipo_almacen" => "local",
          "uri" => $path,
          "longitud" => round(Storage::size($path) / 1024, 2),
          "firmado" => "false",
          "total_firmantes" => $firmantes,
      ]);
    }

    public function renderDocumento($idAudiencia,$idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado,$idDocumento, $uuid = null, $token = null)
    {
      $vars = [];
      $qrpublico = new Helper();
      $data = $this->getDataModelos($idAudiencia,$idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado,$idDocumento);
        if($data!=null){
            $count =0;
            foreach ($data as $key => $dato) { //solicitud
              if( gettype($dato) == 'array'){
                 $isArrAssoc = Arr::isAssoc($dato);
                 if($isArrAssoc){ //si es un array asociativo
                  foreach ($dato as $k => $val) { // folio
                    $val = ($val === null && $val != false)? "" : $val;
                    if(gettype($val)== "boolean"){
                      $val = ($val == false)? 'No' : 'Si';
                    }elseif(gettype($val)== 'array'){
                      $isArrayAssoc = Arr::isAssoc($val);
                      if( !$isArrayAssoc ){//objeto_solicitudes
                        $names =[];
                        foreach ($val as $i => $v) {
                          if( isset($v['nombre'] ) ){
                            array_push($names,$v['nombre']);
                            // array_push($names,$v['nombre']);
                          }
                        }
                        $val = implode (", ", $names);
                      }else{
                        if( isset($val['nombre']) && $k !='persona' && $k !='nombre_completo' ){
                          $val = $val['nombre'];
                        }elseif ($k == 'persona') {
                          foreach ($val as $n =>$v) {
                            $vars[strtolower($key.'_'.$n)] = $v;
                          }
                          $vars[strtolower($key.'_nombre_completo')] = $val['nombre'].' '.$val['primer_apellido'].' '.$val['segundo_apellido'];
                        }else{
                          foreach ($val as $n =>$v) {
                            $vars[strtolower($key.'_'.$k.'_'.$n)] =$v;//($v !== null)? $v :"-";
                          }
                        }
                      }
                    }elseif(gettype($val)== 'string'){
                      $pos = strpos($k,'fecha');
                      if ($pos !== false){
                        $val = $this->formatoFecha($val,1);
                      }
                    }
                    $vars[strtolower($key.'_'.$k)] = $val;
                  }
                }else{//Si no es un array assoc (n solicitados, n solicitantes)
                  foreach ($dato as $data) {//sol[0]...
                    foreach ($data as $k => $val) { // folio, domicilios n
                      $val = ($val === null && $val !== false)? "--" : $val;
                      if(gettype($val)== "boolean"){
                        $val = ($val == false)? 'No' : 'Si';
                      }elseif(gettype($val)== 'array'){
                        $isArrayAssoc = Arr::isAssoc($val);
                        if( !$isArrayAssoc ){ // with
                          if($k == 'domicilios'){
                            $val = Arr::except($val[0],['id','updated_at','created_at','deleted_at','domiciliable_type','domiciliable_id','hora_atencion_de','hora_atencion_a','georeferenciable','tipo_vialidad_id','tipo_asentamiento_id']);
                            foreach ($val as $n =>$v) {
                              // $vars[strtolower($key.'_'.$k.'_'.$n)] = $v;
                              $vars[strtolower($key.'_'.$k.'_'.$n)] = ($v === null)? "" : $v;
                            }
                          }else if($k =='contactos'){
                            foreach ($val as $n =>$v) {
                              $v = Arr::except($v,['id','updated_at','created_at','deleted_at','contactable_type','contactable_id']);
                              $vars[strtolower($key.'_'.$k.'_'.$v['tipo_contacto']['nombre'])] = ($v['contacto'] !== null)? $v['contacto'] :'-';
                              if($v['tipo_contacto_id'] == 3 && $data['correo_buzon'] == null){
                                $vars[$key.'_correo_buzon'] = $v['contacto'];
                                $vars[$key.'_password_buzon'] = '';
                              }
                            }
                          }else{
                            $names =[];
                            foreach ($val as $i => $v) {
                              if( isset($v['nombre'] ) ){
                                array_push($names,$v['nombre']);
                              }
                            }
                            $val = implode (", ", $names);
                          }
                        }else{
                          if( isset($val['nombre']) && $k !='persona' && $k !='datos_laborales' && $k !='representante_legal'){ //catalogos
                            $val = $val['nombre']; //catalogos
                           }elseif ($k == 'datos_laborales') {
                             foreach ($val as $n =>$v) {
                                if($n == "comida_dentro"){
                                  $vars[strtolower($key.'_'.$k.'_'.$n)] = ($v) ? 'dentro':'fuera';
                                }else{
                                  //$vars[strtolower($key.'_'.$k.'_'.$n)]  = $v;
                                  $vars[strtolower($key.'_'.$k.'_'.$n)]  = ($v!= null)?$v:"";
                                }
                                // $pos = strpos($n,'fecha');
                                // if ($pos !== false && $v != "--"){
                                //   $v = Carbon::createFromFormat('Y-m-d',$v)->format('d/m/Y');
                                // }
                             }
                          }elseif ($k == 'nombre_completo') {
                            $vars[strtolower($key.'_'.$k)] = $val;

                           }elseif ($k == 'representante_legal') {
                             foreach ($val as $n =>$v) {
                               $vars[strtolower($key.'_'.$k.'_'.$n)] = ($v!="") ? $v:'';
                             }
                           }
                        }
                      }elseif(gettype($val)== 'string'){
                        $pos = strpos($k,'fecha');
                        if ($pos !== false && $val != "--"){
                          $val = $this->formatoFecha($val,1);
                        }
                      // }else{
                      }
                      $vars[strtolower($key.'_'.$k)] = $val;
                    }
                  }
                }
              }else{
                $vars[strtolower('solicitud_'.$key)] = $dato;
              }
            }
            $vars[strtolower('fecha_actual')] = $this->formatoFecha(Carbon::now(),1);
            $vars[strtolower('hora_actual')] = $this->formatoFecha(Carbon::now(),2);
            $vars[strtolower('clave_nomenclatura')] = $this->nomenclaturaDocumento($idPlantilla);
            $vars[strtolower('qr_publico')] = '<p style="text-align: center;"><span style="font-size: 10pt;"><div style="text-align:center" class="qr">'.QrCode::generate($qrpublico->generarUrlQR($uuid, $token)).'</div></span></p>';
            $vars[strtolower('iterar_citados')] = self::obtenerCitados($idAudiencia, $idSolicitud);
          }
          
          $vars = Arr::except($vars, ['conciliador_persona']);
          $style = "<html xmlns=\"http://www.w3.org/1999/html\">
                  <head>
                  <meta http-equiv=”Content-Type” content=”text/html; charset=UTF-8″ />
                  <style>
                  thead { display: table-header-group }
                  tfoot { display: table-row-group }
                  tr { page-break-inside: avoid }
                  #contenedor-firma {height: 5px;}
                  .firma-llave-publica {text-align: center; font-size: xx-small; max-width: 1024px; overflow-wrap: break-word;}
                  body {
                        margin-left: 1cm;
                        margin-right: 1cm;
                  }
                  </style>
                  </head>
                  <body>
                  ";
          $end = "</body></html>";
          // $config = PlantillaDocumento::orderBy('created_at', 'desc')->first();
          $config = PlantillaDocumento::find($idPlantilla);
          if (!$config) {
              $body = view('documentos._body_documentos_default');
              $body = '<div class="body">' . $body . '</div>';
          } else {
              $body = '<div class="body">' . $config->plantilla_body . '</div>';
          }
          $blade = $style . $body . $end;
          $html = StringTemplate::renderPlantillaPlaceholders($blade, $vars);
          return $html;
    }

    /**
     * Dado un ID de plantilla obtiene el header del documento
     * @param $id
     * @return string
     * @throws \Symfony\Component\Debug\Exception\FatalThrowableError
     */
    public function getHeader($id)
    {
        $html = '';
        $config = PlantillaDocumento::find($id);
        $html = '<!DOCTYPE html> <html> <head> <meta charset="utf-8"> </head> <body>';
        if(!$config){
            $html .= view('documentos._header_documentos_default');
        }
        else{
            // $html = '<!DOCTYPE html> <html> <head> <meta charset="utf-8"> </head> <body>';
            $html .= $config->plantilla_header;
            // $html .= "</body></html>";
        }
        $html .= "</body></html>";
        return StringTemplate::renderPlantillaPlaceholders($html,[]);
    }

    /**
     * Dado un ID de plantilla obtiene el footer del documento
     * @param $id
     * @return string
     * @throws \Symfony\Component\Debug\Exception\FatalThrowableError
     */
    public function getFooter($id)
    {
        $config = PlantillaDocumento::find($id);
        $html = '<!DOCTYPE html> <html> <head> <meta charset="utf-8"> <style>body{border: thin solid white;} .clave-nomenclatura{ position:absolute; top:0px; right:0px; margin-right:1cm; font-size: small;}</style> </head> <body>';
        if(!$config){
            $html .= view('documentos._footer_documentos_default');
        }
        else{
            $html .= $config->plantilla_footer;
          }
        $html .= "</body></html>";
        $vars = [];
        $vars[strtolower('clave_nomenclatura')] = $this->nomenclaturaDocumento($id);
        return StringTemplate::renderPlantillaPlaceholders($html,$vars);
    }

    private function getDataModelos($idAudiencia,$idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado,$idDocumento)
    {
      try {
            Log::info('getDataModelos: Iniciando', [
                'idAudiencia' => $idAudiencia,
                'idSolicitud' => $idSolicitud,
                'idPlantilla' => $idPlantilla,
                'idSolicitante' => $idSolicitante,
                'idSolicitado' => $idSolicitado,
                'idDocumento' => $idDocumento
            ]);
            
            $plantilla = PlantillaDocumento::find($idPlantilla);
            $tipo_plantilla = TipoDocumento::find($plantilla->tipo_documento_id);

            $objetos = explode (",", $tipo_plantilla->objetos);
            $path = base_path('database/datafiles');
            $jsonElementos = json_decode(file_get_contents($path . "/elemento_documentos.json"),true);
            $idBase = "";
            $audienciaId = $idAudiencia;
            $data = [];
            $solicitud = new Solicitud();
            $solicitudVirtual = "";
            $conciliadorId = "";
            $centroId = "";
            $tipoSolicitud = "";
            $resolucionAudienciaId="";
            $existeContrasenaSolicitante = null;
            $existeContrasenaCitado = null;
            $helper = new Helper();
            $list_users_ids = array();
            $collectElementos = collect($jsonElementos['datos']);

            foreach ($objetos as $objeto) {
                $element = $collectElementos->where('id', $objeto)->first();
                if($element['id']==$objeto){
                  $model_name = 'App\\' . $element['objeto'];
                  $model = $element['objeto'];
                  $model_name = 'App\\' .$model;
                  if($model == 'Solicitud' ){
                    /**
                     * Model: Solicitud
                     */
                    $solicitud = $model_name::with('estatusSolicitud','objeto_solicitudes')->find($idSolicitud);
                    $solicitudVirtual = $solicitud->virtual;
                    $tipoSolicitud = $solicitud->tipo_solicitud_id;
                    $objeto = new JsonResponse($solicitud);
                    $obj = json_decode($objeto->content(),true);
                    $idBase = intval($obj['id']);
                    $centroId = ($solicitud->resuelveOficinaCentral() && $idPlantilla != 6) ? (Centro::where('central',true)->first()->id) : (intval($obj['centro_id']));
                    $obj['tipo_solicitud'] =  mb_strtoupper(($obj['tipo_solicitud_id'] == 1) ? "Conciliación Prejudicial Individual " :  (($obj['tipo_solicitud_id'] == 2) ? "Conciliación Prejudicial Patronal Individual" : "Conciliación Colectiva"));
                    $obj['prescripcion'] = $this->calcularPrescripcion($solicitud->objeto_solicitudes, $solicitud->fecha_conflicto,$solicitud->fecha_ratificacion);
                    $obj['fecha_maxima_ratificacion'] = $this->calcularFechaMaximaRatificacion($solicitud->fecha_recepcion,$centroId);
                    $obj = Arr::except($obj, ['id','updated_at','created_at','deleted_at']);
                    $data = ['solicitud' => $obj];

                  }elseif ($model == 'Parte') {
                    /**
                     * Model: Parte
                     */
                    if($idSolicitante != "" && $idSolicitado != ""){
                      $partes = $model_name::with(['nacionalidad','domicilios'=>function($q){$q->orderByDesc('id');},'lenguaIndigena','tipoDiscapacidad','documentos.clasificacionArchivo.entidad_emisora','contactos.tipo_contacto','tipoParte','compareciente','bitacoras_buzon'])->where('solicitud_id',intval($idBase))->whereIn('id',[$idSolicitante,$idSolicitado])->get();
                    }else if($idSolicitante != "" && $idSolicitado == ""){
                      $partes = $model_name::with(['nacionalidad','domicilios'=>function($q){$q->orderByDesc('id');},'lenguaIndigena','tipoDiscapacidad','documentos.clasificacionArchivo.entidad_emisora','contactos.tipo_contacto','tipoParte','compareciente','bitacoras_buzon'])->where('solicitud_id',intval($idBase))->whereRaw('(id=? or tipo_parte_id<>?)',[$idSolicitante,1])->get();
                    }else if($idSolicitante == "" && $idSolicitado != ""){
                      $partes = $model_name::with(['nacionalidad','domicilios'=>function($q){$q->orderByDesc('id');},'lenguaIndigena','tipoDiscapacidad','documentos.clasificacionArchivo.entidad_emisora','contactos.tipo_contacto','tipoParte','compareciente','bitacoras_buzon'])->where('solicitud_id',intval($idBase))->whereRaw('(id=? or tipo_parte_id=?)',[$idSolicitado,1])->get();
                    }else{
                      $partes = $model_name::with(['nacionalidad','domicilios'=>function($q){$q->orderByDesc('id');},'lenguaIndigena','tipoDiscapacidad','documentos.clasificacionArchivo.entidad_emisora','contactos.tipo_contacto','tipoParte','compareciente','bitacoras_buzon'])->where('solicitud_id',intval($idBase))->get();
                    }
                    if($idDocumento){
                      foreach($partes as $parteaFirma){
                        if($idAudiencia){
                          $existe = $parteaFirma->firmas()->where('audiencia_id',$idAudiencia)->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                        }else{
                          $existe = $parteaFirma->firmas()->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                        }
                        
                        if($existe == null){
                          if($idAudiencia){
                            $parteaFirma->firmas()->create(['audiencia_id'=>$idAudiencia,'solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                          }else{
                            $parteaFirma->firmas()->create(['solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                          }
                        }
                      }
                    }

                    $objeto = new JsonResponse($partes);
                    $obj = json_decode($objeto->content(),true);
                    $parte2 = [];
                    $parte1 = [];
                    $countSolicitante = 0;
                    $countSolicitado = 0;
                    $nombresSolicitantes = [];
                    $nombresSolicitados = [];
                    $solicitantesNSS = [];
                    $solicitantesCURP = [];
                    $solicitantesIdentificaciones = [];
                    $datoLaboral="";
                    $solicitanteIdentificacion = "";
                    $firmasPartesQR="";
                    $nss="";
                    $curp="";

                    foreach ($obj as $key=>$parte ) {
                      if( sizeof($parte['documentos']) > 0 ){
                        /*
                        * Código anterior
                        *
                        foreach ($parte['documentos'] as $k => $docu) {
                          if($docu['clasificacion_archivo']['tipo_archivo_id'] == 1){ //tipo identificacion
                            $parte['identificacion_documento'] = ($docu['clasificacion_archivo']['nombre'] != null ) ? $docu['clasificacion_archivo']['nombre']: "--";
                            $parte['identificacion_expedida_por'] = ($docu['clasificacion_archivo']['entidad_emisora']['nombre']!= null ) ? $docu['clasificacion_archivo']['entidad_emisora']['nombre']: "---";
                          }
                        }
                        */
                        $collect_documentos = collect($parte['documentos']);
                        $element_documentos = $collect_documentos->where('clasificacion_archivo.tipo_archivo_id', 1)->last();
                        if($element_documentos){
                          $parte['identificacion_documento'] = ($element_documentos['clasificacion_archivo']['nombre'] != null ) ? $element_documentos['clasificacion_archivo']['nombre']: "--";
                          $parte['identificacion_expedida_por'] = ($element_documentos['clasificacion_archivo']['entidad_emisora']['nombre']!= null ) ? $element_documentos['clasificacion_archivo']['entidad_emisora']['nombre']: "---";
                        }

                      }else{
                        $parte['identificacion_documento'] = "---";
                        $parte['identificacion_expedida_por'] = "---";
                      }
                      
                      $parteId = $parte['id'];
                      $curp = $parte['curp'];
                      $parte = Arr::add($parte, 'password_buzon' , '');
                      $parte['comparecio'] = Comparecientes::comparecio($idAudiencia, $parteId);
                      $parte = Arr::except($parte, ['id','updated_at','created_at','deleted_at']);
                      $parte['datos_laborales'] = $datoLaboral;
                      if($parte['tipo_persona_id'] == 1){ //fisica
                        $parte['nombre_completo'] = $parte['nombre'].' '.$parte['primer_apellido'].' '.$parte['segundo_apellido'];
                      }else{//moral
                        $parte['nombre_completo'] = $parte['nombre_comercial'];
                      }

                      //$idAudiencia,$idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado,$idConciliador
                      $tipoParte = ($parte['tipo_parte_id'] == 1) ? 'solicitante':'citado';

                      //Se trae el usuario buzón de la tabla users
                      //Para cambiar el pwd y dejarlo como el último que se generó
                      $users = User::where('email', $parte['correo_buzon'])->where('remember_token', '0000000000')->whereDate('created_at', now()->today())->first();
                      if(isset($users)) {
                        //New user
                        if (!session()->has($users->id)) {
                          $password = strtolower($helper->getPassword());
                          $parte['password_buzon'] = $password;
                          User::where('id', $users->id)->update([
                            'email_verified_at' => now()->format('Y-m-d H:i:s'),
                            'password' => bcrypt($parte['password_buzon']),
                            'remember_token' => Str::random(10)
                          ]);
                          session([$users->id => $password]);
                        }
                      }else{
                        //Old user
                        $parte['password_buzon'] = 'Tu contraseña no ha cambiado';
                      }

                      $firmaDocumento = null;
                      if($idDocumento){
                        if($idAudiencia =="" ){
                          $firmaDocumento = FirmaDocumento::where('firmable_id',$parteId)->where('firmable_type','App\Parte')->where('plantilla_id',$idPlantilla)->where('solicitud_id',$idBase)->where('documento_id',$idDocumento)->first();
                        }else{
                          $firmaDocumento = FirmaDocumento::where('firmable_id',$parteId)->where('firmable_type','App\Parte')->where('plantilla_id',$idPlantilla)->where('audiencia_id',$idAudiencia)->where('documento_id',$idDocumento)->first();
                        }
                      }
                      if($solicitudVirtual && $solicitudVirtual!="" && $idDocumento){
                        if((substr_count($plantilla->plantilla_body,'SOLICITANTE_QR_FIRMA') > 0 && $parte['tipo_parte_id'] == 1) || (substr_count($plantilla->plantilla_body,'SOLICITADO_QR_FIRMA') > 0 && $parte['tipo_parte_id'] == 2)){
                          $firmaDocumento->update(['firma_electronicamente'=>true]);
                        }
                        if($firmaDocumento && $firmaDocumento->firma != null && $firmaDocumento->tipo_firma == 'autografa'){
                          $parte['qr_firma'] = '<div style="text-align:center" class="qr"> <img style="max-height:80px" src="'.$firmaDocumento->firma.'" /></div>';
                        } elseif ($firmaDocumento && $firmaDocumento->firma != null && ($firmaDocumento->tipo_firma == 'llave-publica' || $firmaDocumento->tipo_firma == '' )){
                          $parte['qr_firma'] = '<div style="text-align:center" class="firma-llave-publica">Firma Digital: '.$this->splitFirma($firmaDocumento->firma).'</div>';
                        } else{
                          if($firmaDocumento){
                            $parte['qr_firma'] = '<div style="text-align:center" class="qr">'.QrCode::errorCorrection('H')->size(100)->generate($parteId."/".$tipoParte."/".urlencode($parte['nombre_completo'])."/".$audienciaId."/".$idSolicitud."/".$idPlantilla."/".$idDocumento ."/".$idSolicitante ."/".$idSolicitado."/".$firmaDocumento->id).'</div>';
                          }else{
                            $parte['qr_firma'] = "";
                          }
                        }
                        if($parte['tipo_persona_id']==1 && count($parte['compareciente']) > 0){
                          $siFirma= true;
                          if($idPlantilla == 2 && $parte['tipo_parte_id']!=1){
                            $parte_solicitada = $parteId;
                            if($parte['tipo_parte_id'] == 3){
								              $parte_solicitada = $parte['parte_representada_id'];
								              $firmaDocumentoRepresentada = FirmaDocumento::where('firmable_id',$parte_solicitada)->where('firmable_type','App\Parte')->where('plantilla_id',$idPlantilla)->where('solicitud_id',$idBase)->where('documento_id',$idDocumento)->first();
								              if((substr_count($plantilla->plantilla_body,'SOLICITUD_FIRMAS_PARTES_QR') > 0)){
									              $firmaDocumentoRepresentada->update(['firma_electronicamente'=>true]);
								              }
                            }
                            $resolucionParteRepresentada = ResolucionPartes::where('audiencia_id',$audienciaId)->where('parte_solicitada_id',$parte_solicitada)->first();
                            if($resolucionParteRepresentada && $resolucionParteRepresentada->terminacion_bilateral_id !=3){
                              $siFirma=false;
                            }
                          }
                          if($siFirma){
                            if((substr_count($plantilla->plantilla_body,'SOLICITUD_FIRMAS_PARTES_QR') > 0)){
                              $firmaDocumento->update(['firma_electronicamente'=>true]);
                            }
                            $firmasPartesQR .= '<p style="text-align: center;"><span style="font-size: 10pt;">'.$parte['qr_firma'].' </span></p>';
                            $firmasPartesQR .= '<p style="text-align: center;"><span style="font-size: 10pt;">_________________________________________</span></p>';
                            $firmasPartesQR .= '<p style="text-align: center;"><strong><span style="font-size: 10pt;">'.mb_strtoupper($parte['nombre_completo']).'</span></strong></p>';
                            $firmasPartesQR .= '<p style="text-align: center;">&nbsp;</p>';
                          }
                        }
                      }else{
                        $parte['qr_firma'] = "";
                      }
                      //domicilio de partes, excepto representante
                      if($parte['tipo_parte_id'] != 3 ){
                        $dom_parte = $parte['domicilios'][0];
                        $tipo_vialidad =  ($dom_parte['tipo_vialidad'] !== null)? $dom_parte['tipo_vialidad'] :"";
                        $vialidad =  ($dom_parte['vialidad'] !== null)? $dom_parte['vialidad'] :"";
                        $num_ext =  ($dom_parte['num_ext'] !== null)? "No. " . $dom_parte['num_ext'] :"";
                        $num_int =  ($dom_parte['num_int'] !== null)? " Int. " . $dom_parte['num_int'] :"";
                        $num =  $num_ext.$num_int;
                        $municipio =  ($dom_parte['municipio'] !== null)? $dom_parte['municipio'] :"";
                        $cp =  ($dom_parte['cp'] !== null)? " CP. " . $dom_parte['cp'] :"";

                        $estado =  ($dom_parte['estado'] !== null)? $dom_parte['estado'] :"";
                        $colonia =  ($dom_parte['asentamiento'] !== null)? $dom_parte['tipo_asentamiento']." ". $dom_parte['asentamiento']." "  :"";
                        $parte['domicilios_completo'] = mb_strtoupper($tipo_vialidad.' '.$vialidad.' '.$num.', '.$colonia.', '.$municipio.', ' .$estado. ', '. $cp);
                      }

                      // if($parte['tipo_parte_id'] == 1 ){//Solicitante
                        //datos laborales del solicitante
                        $datoLaborales = DatoLaboral::with('jornada','ocupacion')->where('parte_id', $parteId)->get();
                        $hayDatosLaborales = count($datoLaborales);
                        if($hayDatosLaborales>1){
                          $datoLaborales =$datoLaborales->where('resolucion',true)->first();
                        }else{
                          $datoLaborales =$datoLaborales->first();
                        }
                        if($hayDatosLaborales >0){
                          $domicilioLaboral = Domicilio::where('domiciliable_id',$datoLaborales->id)->where('domiciliable_type','App\DatoLaboral')->first();
                          if($domicilioLaboral != null ){
                            $parte['domicilios_laboral'] = mb_strtoupper($domicilioLaboral->tipo_vialidad.' '.$domicilioLaboral->vialidad.' '.$domicilioLaboral->num_ext.', '.$domicilioLaboral->asentamiento.', '.$domicilioLaboral->municipio.', '.$domicilioLaboral->estado.', '.$domicilioLaboral->cp);
                          }else{
                            $domicilioLaboral = "";
                            $primeraResolucion =null;
                            if($audienciaId){
                              $primeraResolucion = ResolucionPartes::where('audiencia_id',$audienciaId)->first();
                            }
                            if($primeraResolucion != null){
                              //Si trabajador es solicitante buscar dom de citado o viceversa
                              $tipoParteDom = ($parte['tipo_parte_id'] == 1 )? $primeraResolucion->parte_solicitada_id : $primeraResolucion->parte_solicitante_id ;
                              $contraparte = Parte::with(['domicilios'=>function($q){$q->orderBy('id');}])->find($tipoParteDom);
                              if($contraparte->tipo_parte_id == 3){//si es representante buscar parte
                                $contraparte = Parte::with(['domicilios'=>function($q){$q->orderBy('id');}])->find($contraparte->parte_representada_id);
                              }
                              $doms_parte = $contraparte->domicilios;
                              foreach ($doms_parte as $key => $dom_parte) {
                                $tipo_vialidad =  ($dom_parte->tipo_vialidad !== null)? $dom_parte->tipo_vialidad :"";
                                $vialidad =  ($dom_parte->vialidad !== null)? $dom_parte->vialidad :"";
                                $num_ext =  ($dom_parte->num_ext !== null)? "No. " . $dom_parte->num_ext :"";
                                $num_int =  ($dom_parte->num_int !== null)? " Int. " . $dom_parte->num_int :"";
                                $num =  $num_ext.$num_int;
                                $municipio =  ($dom_parte->municipio !== null)? $dom_parte->municipio :"";
                                $estado =  ($dom_parte->estado !== null)? $dom_parte->estado :"";
                                $colonia =  ($dom_parte->asentamiento !== null)? $dom_parte->tipo_asentamiento." ". $dom_parte->asentamiento." "  :"";
                              }
                              $domicilioLaboral = mb_strtoupper($tipo_vialidad.' '.$vialidad.' '.$num.', '.$colonia.', '.$municipio.', '.$estado);
                            }
                            $parte['domicilios_laboral'] = $domicilioLaboral;
                          }

                          $nss = $datoLaborales->nss;
                          $salarioMensual = round( (($datoLaborales->remuneracion / $datoLaborales->periodicidad->dias)*30),2);
                          $salarioMensual =number_format($salarioMensual, 2, '.', '');
                          $salario = explode('.', $salarioMensual);
                          $intSalarioMensual = $salario[0];
                          $decSalarioMensual = $salario[1];
                          $intSalarioMensualTextual = (new NumberFormatter("es", NumberFormatter::SPELLOUT))->format((float)$intSalarioMensual);
                          $intSalarioMensualTextual = str_replace("uno","un",$intSalarioMensualTextual);
                          $salarioMensualTextual = $intSalarioMensualTextual.' pesos '. $decSalarioMensual.'/100';
                          $objeto = new JsonResponse($datoLaborales);
                          $datoLaboral = json_decode($objeto->content(),true);
                          $datoLaboral = Arr::except($datoLaboral, ['id','updated_at','created_at','deleted_at']);
                          $parte['datos_laborales'] = $datoLaboral;
                          $parte['datos_laborales_salario_mensual'] = $salarioMensual;
                          $parte['datos_laborales_salario_mensual_letra'] = $salarioMensualTextual;
                        }
                        $parte['identificacion_documento']= ( isset($parte['identificacion_documento'])) ?$parte['identificacion_documento'] : "";
                        $parte['identificacion_expedida_por']= ( isset($parte['identificacion_expedida_por'])) ?$parte['identificacion_expedida_por'] : "";
                        $solicitanteIdentificacion = $parte['nombre_completo'] ." quien se identifica con " .$parte['identificacion_documento']." expedida a su favor por ". $parte['identificacion_expedida_por'];
                      // }elseif ($parte['tipo_parte_id'] == 2 ) {//Citado
                        //representante legal solicitado
                        if($audienciaId != "" && $audienciaId != null){
                          $representanteLegal = Parte::with('documentos.clasificacionArchivo.entidad_emisora','compareciente')->whereHas('compareciente',function($q)use($idAudiencia){$q->where('audiencia_id',$idAudiencia);})->where('parte_representada_id', $parteId)->where('tipo_parte_id',3)->get();
                          if(count($representanteLegal) > 0){
                            $parte['asistencia'] =  'Si';
                            $objeto = new JsonResponse($representanteLegal);
                            $representanteLegal = json_decode($objeto->content(),true);
                            $representanteLegal = Arr::except($representanteLegal[0], ['id','updated_at','created_at','deleted_at']);
                            $representanteLegal['nombre_completo'] = $representanteLegal['nombre'].' '.$representanteLegal['primer_apellido'].' '.$representanteLegal['segundo_apellido'];
                            if( sizeof($representanteLegal['documentos']) > 0 ){
                              foreach ($representanteLegal['documentos'] as $k => $docu) {
                                if($docu['clasificacion_archivo']['tipo_archivo_id'] == 1){ //tipo identificacion
                                  $representanteLegal['identificacion_documento'] = ($docu['clasificacion_archivo']['nombre'] != null ) ? $docu['clasificacion_archivo']['nombre']: "--";
                                  $representanteLegal['identificacion_expedida_por'] = ($docu['clasificacion_archivo']['entidad_emisora']['nombre']!= null ) ? $docu['clasificacion_archivo']['entidad_emisora']['nombre']: "---";
                                }
                              }
                            }else{
                              $representanteLegal['identificacion_documento'] = "---";
                              $representanteLegal['identificacion_expedida_por'] = "---";
                            }
                            $parte['representante_legal'] = $representanteLegal;
                            $parte['nombre_compareciente'] = $representanteLegal['nombre_completo'] .' C. REPRESENTANTE LEGAL DE '. $parte['nombre_completo'];
                          }else{
                            $countParteAsistencia = Compareciente::where('parte_id', $parteId)->where('audiencia_id',$audienciaId)->count();
                            $parte['asistencia'] =  ($countParteAsistencia >0) ? 'Si':'No';
                            $parte['nombre_compareciente'] = $parte['nombre_completo'];
                          }
                        }

                          //tipoNotificacion solicitado
                        if($audienciaId!=""){
                          $audienciaParte = AudienciaParte::with('tipo_notificacion')->where('audiencia_id',$audienciaId)->where('parte_id',$parteId)->get();
                          if(count($audienciaParte)>0){
                            $parte['finalizado'] = isset($audienciaParte[0]->finalizado) ? (str_contains($audienciaParte[0]->finalizado, 'NO EXITOSO') ? 'No' : 'Si') : 'No';
                            $parte['notificacion_exitosa'] = $parte['finalizado'];
                            $parte['tipo_notificacion'] = $audienciaParte[0]->tipo_notificacion_id;
                            $parte['fecha_notificacion'] = $audienciaParte[0]->fecha_notificacion;
                            $parte['fecha_confirmacion_audiencia'] = $audienciaParte[0]->created_at;
                          }else{
                            $parte['finalizado'] = isset($audienciaParte[0]->finalizado) ? (str_contains($audienciaParte[0]->finalizado, 'NO EXITOSO') ? 'No' : 'Si') : 'No';
                            $parte['notificacion_exitosa'] = $parte['finalizado'];
                            $parte['tipo_notificacion'] = null;
                            $parte['fecha_notificacion'] = "";
                            $parte['fecha_confirmacion_audiencia'] = "";
                          }
                        }
                        $tablaConsultaBuzon = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                        if( sizeof($parte['bitacoras_buzon']) > 0 ){
                          $tablaConsultaBuzon .= '<table class="tbl">';
                          $tablaConsultaBuzon .= '<tbody>';
                          foreach ($parte['bitacoras_buzon'] as $k => $bitacora) {
                            $tablaConsultaBuzon .= '<tr><td> '. Carbon::createFromFormat('Y-m-d H:i:s',$bitacora['created_at'])->format('d/m/Y h:i').' </td><td>'.$bitacora['descripcion'].'</tr>';
                          }
                          $tablaConsultaBuzon .= '</tbody>';
                          $tablaConsultaBuzon .= '</table>';
                        }else{
                          $tablaConsultaBuzon .= 'No hay registros en la bitácora';
                        }
                        $parte['bitacora_consulta_buzon']=$tablaConsultaBuzon;

                        if($parte['tipo_parte_id'] == 1 ){//Solicitante
                          array_push($parte1, $parte);
                          array_push($nombresSolicitantes, $parte['nombre_completo'] );
                          array_push($solicitantesIdentificaciones, $solicitanteIdentificacion);
                          $countSolicitante += 1;
                        }

                        if ($parte['tipo_parte_id'] == 2 ) {//Citado
                          $countSolicitado += 1;
                          array_push($nombresSolicitados, $parte['nombre_completo'] );
                          array_push($parte2, $parte);
                        }
                    }
                    $partesGral = Parte::where('solicitud_id',intval($idBase))->get();
                    $countSolicitado = 0;
                    $countSolicitante = 0;
                    $nombresSolicitantes = [];
                    $nombresSolicitados = [];
                    $nombresSolicitantesConfirmaron = [];
                    
                    foreach($partesGral as $parteGral){
                      if($parteGral->tipo_persona_id == 1){ //fisica
                        $nombre_completo = $parteGral->nombre.' '.$parteGral->primer_apellido.' '.$parteGral->segundo_apellido;
                        $curp = $parteGral->curp;
                        if(count($parteGral->dato_laboral)>0){
                          foreach($parteGral->dato_laboral as $dato_laboral){
                            $nss = $dato_laboral->nss;
                          }
                        }
                      }else{//moral
                        $nombre_completo = $parteGral->nombre_comercial;
                      }
                      if($parteGral->tipo_parte_id == 1){//Solicitante
                        array_push($nombresSolicitantes, $nombre_completo );
                        $countSolicitante += 1;
                        if($parteGral->ratifico){
                          array_push($solicitantesNSS, $nss);
                          array_push($solicitantesCURP, $curp);
                          array_push($nombresSolicitantesConfirmaron, $nombre_completo );
                        }
                      }else if($parteGral->tipo_parte_id == 2){//Citado
                        array_push($nombresSolicitados, $nombre_completo );
                        $countSolicitado += 1;
                      }else{//representante

                      }
                    }
                    $data = Arr::add( $data, 'solicitante', $parte1 );
                    $data = Arr::add( $data, 'solicitado', $parte2 );
                    $data = Arr::add( $data, 'total_solicitantes', $countSolicitante );
                    $data = Arr::add( $data, 'total_solicitados', $countSolicitado );
                    $data = Arr::add( $data, 'nombres_solicitantes', implode(", ",$nombresSolicitantes));
                    $data = Arr::add( $data, 'nombres_solicitados', implode(", ",$nombresSolicitados));
                    $data = Arr::add( $data, 'nombres_solicitantes_confirmados', implode(", ",$nombresSolicitantesConfirmaron));
                    $data = Arr::add( $data, 'nss_solicitantes', implode(", ",$solicitantesNSS));
                    $data = Arr::add( $data, 'curp_solicitantes', implode(", ",$solicitantesCURP));
                    $data = Arr::add( $data, 'solicitantes_identificaciones', implode(", ",$solicitantesIdentificaciones));
                    $data = Arr::add( $data, 'firmas_partes_qr', $firmasPartesQR);

                  }elseif ($model == 'Expediente') {
                    /**
                     * Model: Expediente
                     */
                    $expediente = Expediente::where('solicitud_id', $idBase)->get();
                    $expedienteId = $expediente[0]->id;
                    $objeto = new JsonResponse($expediente);
                    $expediente = json_decode($objeto->content(),true);
                    $expediente = Arr::except($expediente[0], ['id','updated_at','created_at','deleted_at']);
                    $data = Arr::add( $data, 'expediente', $expediente );

                  }elseif ($model == 'Audiencia') {
                    /**
                     * Model: Audiencia
                     */
                    if($solicitud!="" && $solicitud->estatus_solicitud_id != 1){
                      $expediente = Expediente::where('solicitud_id', $idBase)->get();
                      $expedienteId = $expediente[0]->id;

                      $objeto = new JsonResponse($expediente);
                      $expediente = json_decode($objeto->content(),true);
                      $expediente = Arr::except($expediente[0], ['id','updated_at','created_at','deleted_at']);
                      $data = Arr::add( $data, 'expediente', $expediente );

                      
                      $audiencia = $model_name::where('id',$audienciaId)->get();
                      $conciliadorId = $audiencia[0]->conciliador_id;
                      $objeto = new JsonResponse($audiencia);
                      $audiencias = json_decode($objeto->content(),true);
                      $Audiencias = [];
                      foreach ($audiencias as $audiencia ) {
                        if($audienciaId == ""){
                          $audienciaId = $audiencia['id'];
                        }
                        $resolucionAudienciaId = $audiencia['resolucion_id'];
                        $audiencia = Arr::except($audiencia, ['id','updated_at','created_at','deleted_at']);
                        array_push($Audiencias,$audiencia);
                      }

                      $data = Arr::add( $data, 'audiencia', $Audiencias );
                      $salaAudiencia = SalaAudiencia::with('sala')->where('audiencia_id',$audienciaId)->get();
                      $objSala = new JsonResponse($salaAudiencia);
                      $salaAudiencia = json_decode($objSala->content(),true);
                      $salas = [];
                      foreach ($salaAudiencia as $sala ) {
                        $sala['nombre'] = $sala['sala']['sala'];
                        $sala = Arr::except($sala, ['id','updated_at','created_at','deleted_at','sala']);
                        array_push($salas,$sala);
                      }

                      //Se agrega para cuando la sala viene vacía 29-01-2024
                      if (empty($salas)) {
                        $sala['nombre'] = " - ";
                        $sala = Arr::except($sala, ['id','updated_at','created_at','deleted_at','sala']);
                        array_push($salas,$sala);
                      }
                    
                      $data = Arr::add( $data, 'sala', $salas );

                    }

                  }elseif ($model == 'Conciliador') {
                    /**
                     * Model: Conciliador
                     */
                    if($conciliadorId != ""){
                      $objeto = $model_name::with('persona')->find($conciliadorId);
                      if($idDocumento){
                        if($idAudiencia){
                          $existe = $objeto->firmas()->where('audiencia_id',$idAudiencia)->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                        }else{
                          $existe = $objeto->firmas()->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                        }
                        if($existe == null){
                          if($idAudiencia){
                            $objeto->firmas()->create(['audiencia_id'=>$idAudiencia,'solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                          }else{
                            $objeto->firmas()->create(['solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                          }
                        }
                      }
                      $objeto = new JsonResponse($objeto);
                      $conciliador = json_decode($objeto->content(),true);
                      $conciliador = Arr::except($conciliador, ['id','updated_at','created_at','deleted_at']);
                      $conciliador['persona'] = Arr::except($conciliador['persona'], ['id','updated_at','created_at','deleted_at']);
                      $nombreConciliador = $conciliador['persona']['nombre']." ".$conciliador['persona']['primer_apellido']." ".$conciliador['persona']['segundo_apellido'];
                      if($solicitudVirtual && $solicitudVirtual!="" && $idDocumento){
                        $firmaDocumento = FirmaDocumento::where('firmable_id',$conciliadorId)->where('firmable_type','App\Conciliador')->where('plantilla_id',$idPlantilla)->where('audiencia_id',$idAudiencia)->where('documento_id',$idDocumento)->first();
                          if(substr_count($plantilla->plantilla_body,'CONCILIADOR_QR_FIRMA') > 0){
                            $firmaDocumento->update(['firma_electronicamente'=>true]);
                          }
                          if($firmaDocumento != null && $firmaDocumento->firma != null && $firmaDocumento->tipo_firma == 'autografa'){
                            $conciliador['qr_firma'] = '<div style="text-align:center" class="qr"> <img style="max-height:80px" src="'.$firmaDocumento->firma.'" /></div>';
                          } elseif ($firmaDocumento != null && $firmaDocumento->firma != null && ($firmaDocumento->tipo_firma == 'llave-publica' || $firmaDocumento->tipo_firma == '' )){
                            $conciliador['qr_firma'] = '<div style="text-align:center" class="firma-llave-publica">Firma Digital: '.$this->splitFirma($firmaDocumento->firma).'</div>';
                          }else{
                            if($firmaDocumento){
                              $conciliador['qr_firma'] = '<div style="text-align:center" class="qr">'.QrCode::errorCorrection('H')->size(100)->generate($conciliadorId."/conciliador/".urlencode($nombreConciliador)."/".$audienciaId."/".$idSolicitud."/".$idPlantilla."/".$idDocumento ."/".$idSolicitante ."/".$idSolicitado."/".$firmaDocumento->id).'</div>';
                            }else{
                              $conciliador['qr_firma'] = '';
                            }
                          }
                      }else{
                        $conciliador['qr_firma'] = '';
                      }
                      $data = Arr::add( $data, 'conciliador', $conciliador );

                    }

                  }elseif ($model == 'Centro') {
                    /**
                     * Model: Centro
                     */
                    $objeto = $model_name::with('domicilio','disponibilidades','contactos')->find($centroId);
                    $dom_centro = $objeto->domicilio;
                    $usuarios_centro = $objeto->user()->role('Administrador del centro')->orderBy('id', 'desc')->first();
                    $contacto_centro = $objeto->contactos;
                    $disponibilidad_centro = $objeto->disponibilidades;
                    $objeto = new JsonResponse($objeto);
                    $centro = json_decode($objeto->content(),true);
                    $centro = Arr::except($centro, ['id','updated_at','created_at','deleted_at']);
                    $dom_centro = new JsonResponse($dom_centro);
                    $dom_centro = json_decode($dom_centro->content(),true);
                    $centro['domicilio'] = Arr::except($dom_centro, ['id','updated_at','created_at','deleted_at','domiciliable_id','domiciliable_type']);
                    $tipo_vialidad =  ($dom_centro['tipo_vialidad'] !== null)? $dom_centro['tipo_vialidad'] :"";
                    $vialidad =  ($dom_centro['vialidad'] !== null)? $tipo_vialidad." ". $dom_centro['vialidad'] :"";
                    $num_ext =  ($dom_centro['num_ext'] !== null)? " No. " . $dom_centro['num_ext'] :"";
                    $num_int =  ($dom_centro['num_int'] !== null)? " Int. " . $dom_centro['num_int'] :"";
                    $num = $num_ext . $num_int;
                    $colonia =  ($dom_centro['asentamiento'] !== null)? $dom_centro['tipo_asentamiento']." ". $dom_centro['asentamiento']." "  :"";
                    $municipio =  ($dom_centro['municipio'] !== null)? $dom_centro['municipio'] :"";
                    $cp =  ($dom_centro['cp'] !== null)? 'C.P. '.$dom_centro['cp'] :"";
                    $referencias =  ($dom_centro['referencias'] !== null)? $dom_centro['referencias'] :"";
                    $estado =  ($dom_centro['estado'] !== null)? $dom_centro['estado'] :"";
                    $centro['domicilio_completo'] = mb_strtoupper($vialidad. $num.', '.$colonia.', '.$municipio.', '.$estado.', '.$cp);
                    $contacto_centro = new JsonResponse($contacto_centro);
                    $contacto_centro = json_decode($contacto_centro->content(),true);
                    foreach ($contacto_centro as $contacto ) {
                      if($contacto['tipo_contacto_id'] == 1 || $contacto['tipo_contacto_id'] == 2 ){
                        $centro['telefono'] = $contacto['contacto'];
                      }else{
                        $centro['telefono'] = '--- -- -- ---';
                      }
                    }
                    $nombreAdministrador = "";
                    $personaId = "";
                    $userAdmin = null;

                    if(isset($usuarios_centro->id)){
                        $userAdmin = $usuarios_centro->persona;
                        $personaId= $userAdmin->id;
                        $nombreAdministrador = $userAdmin['nombre'].' '.$userAdmin['primer_apellido'].' '.$userAdmin['segundo_apellido'];
                    }
                    $centro['nombre_administrador'] = $nombreAdministrador;
                    //Firma conciliador generico
                    $solicitudFirma = Solicitud::find($idSolicitud);
                    if($idDocumento){
                      if($idAudiencia){
                        $existe = $solicitudFirma->firmas()->where('audiencia_id',$idAudiencia)->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                      }else{
                        $existe = $solicitudFirma->firmas()->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                      }
                      if($existe == null){
                        if($idAudiencia){
                          $solicitudFirma->firmas()->create(['audiencia_id'=>$idAudiencia,'solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                        }else{
                          $solicitudFirma->firmas()->create(['solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                        }
                      }
                    }
                    if($solicitudVirtual && $solicitudVirtual!="" && $idDocumento){
                      if($idAudiencia){
                        $firmaDocumento = FirmaDocumento::where('firmable_id',$idSolicitud)->where('firmable_type','App\Conciliador')->where('plantilla_id',$idPlantilla)->where('audiencia_id',$idAudiencia)->where('documento_id',$idDocumento)->first();
                      }else{
                        $firmaDocumento = FirmaDocumento::where('firmable_id',$idSolicitud)->where('firmable_type','App\Conciliador')->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                      }
                      if($firmaDocumento != null && $firmaDocumento->firma != null && $firmaDocumento->tipo_firma == 'autografa'){
                          $centro['conciliador_generico_qr_firma'] = '<div style="text-align:center" class="qr"> <img style="max-height:80px" src="'.$firmaDocumento->firma.'" /></div>';
                        } elseif ($firmaDocumento != null && $firmaDocumento->firma != null && ($firmaDocumento->tipo_firma == 'llave-publica' || $firmaDocumento->tipo_firma == '' )){
                          $centro['conciliador_generico_qr_firma'] = '<div style="text-align:center" class="firma-llave-publica">Firma Digital: '.$this->splitFirma($firmaDocumento->firma).'</div>';
                        }else{
                          if($firmaDocumento){
                            $centro['conciliador_generico_qr_firma'] = '<div style="text-align:center" class="qr">'.QrCode::errorCorrection('H')->size(100)->generate($idSolicitud."/conciliador//".$audienciaId."/".$idSolicitud."/".$idPlantilla."/".$idDocumento ."/".$idSolicitante ."/".$idSolicitado."/".$firmaDocumento->id).'</div>';
                          }else{
                            $centro['conciliador_generico_qr_firma'] = '';
                          }
                        }
                    }else{
                      $centro['conciliador_generico_qr_firma'] = '';
                    }

                    //Firma administrador centro
                    if($idDocumento && $userAdmin!=null){
                      if($idAudiencia){
                        $existe = $userAdmin->firmas()->where('audiencia_id',$idAudiencia)->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                      }else{
                        $existe = $userAdmin->firmas()->where('solicitud_id',$idSolicitud)->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                      }
                      if($existe == null){
                        if($idAudiencia){
                          $userAdmin->firmas()->create(['audiencia_id'=>$idAudiencia,'solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                        }else{
                          $userAdmin->firmas()->create(['solicitud_id'=>$idSolicitud,'plantilla_id'=>$idPlantilla,'documento_id'=>$idDocumento]);
                        }
                      }
                    }
                    if($solicitudVirtual && $solicitudVirtual!="" && $idDocumento){
                      if($idAudiencia){
                        $firmaDocumento = FirmaDocumento::where('firmable_id',$personaId)->where('firmable_type','App\Persona')->where('plantilla_id',$idPlantilla)->where('audiencia_id',$idAudiencia)->where('documento_id',$idDocumento)->first();
                      }else{
                        $firmaDocumento = FirmaDocumento::where('firmable_id',$personaId)->where('firmable_type','App\Persona')->where('plantilla_id',$idPlantilla)->where('documento_id',$idDocumento)->first();
                      }
                      if(substr_count($plantilla->plantilla_body,'CENTRO_ADMINISTRADOR_QR_FIRMA') > 0 ){
                        $firmaDocumento->update(['firma_electronicamente'=>true]);
                      }
                      if($firmaDocumento != null && $firmaDocumento->firma != null && $firmaDocumento->tipo_firma == 'autografa'){
                          $centro['administrador_qr_firma'] = '<div style="text-align:center" class="qr"> <img style="max-height:80px" src="'.$firmaDocumento->firma.'" /></div>';
                        } elseif ($firmaDocumento != null && $firmaDocumento->firma != null && ($firmaDocumento->tipo_firma == 'llave-publica' || $firmaDocumento->tipo_firma == '' )){
                          $centro['administrador_qr_firma'] = '<div style="text-align:center" class="firma-llave-publica">Firma Digital: '.$this->splitFirma($firmaDocumento->firma).'</div>';
                        }else{
                          if($firmaDocumento){
                            $centro['administrador_qr_firma'] = '<div style="text-align:center" class="qr">'.QrCode::errorCorrection('H')->size(100)->generate($personaId."/administrador/".urlencode($nombreAdministrador)."/".$audienciaId."/".$idSolicitud."/".$idPlantilla."/".$idDocumento ."/".$idSolicitante ."/".$idSolicitado."/".$firmaDocumento->id).'</div>';
                          }else{
                            $centro['administrador_qr_firma'] = '';
                          }
                        }
                    }else{
                      $centro['administrador_qr_firma'] = '';
                    }
                    //Disponibilidad del centro horarios y dias
                    $disponibilidad_centro = new JsonResponse($disponibilidad_centro);
                    $disponibilidad_centro = json_decode($disponibilidad_centro->content(),true);
                    $centro['hora_inicio']= $this->formatoFecha($disponibilidad_centro[0]['hora_inicio'],3);
                    $centro['hora_fin']= $this->formatoFecha($disponibilidad_centro[0]['hora_fin'],3);
                    $data = Arr::add( $data, 'centro', $centro );

                  }elseif ($model == 'Resolucion') {
                    /**
                     * Model: Resolucion
                     */
                    $objetoResolucion = $model_name::find($resolucionAudienciaId);
                    $datosResolucion=[];
                    $etapas_resolucion = EtapaResolucionAudiencia::where('audiencia_id',$audienciaId)->whereIn('etapa_resolucion_id',[3,4,5,6])->get();
                    $objeto = new JsonResponse($etapas_resolucion);
                    $etapas_resolucion = json_decode($objeto->content(),true);
                    $datosResolucion['resolucion']= $objetoResolucion->nombre ?? '';
                    $audiencia_partes = Audiencia::find($audienciaId)->audienciaParte;
                    foreach ($etapas_resolucion as $asd => $etapa ) {
                      if($etapa['etapa_resolucion_id'] == 3){
                        $datosResolucion['primera_manifestacion']= $etapa['evidencia'];
                      }else if($etapa['etapa_resolucion_id'] == 4){
                        $datosResolucion['justificacion_propuesta']= $etapa['evidencia'];
                        $tablaConceptos = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                        $tablaConceptosConvenio = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                        $tablaRetencionesConvenio = '';
                        $tablaConceptosActa = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                        $totalPercepciones = 0;
                        $parteID= "";
                        $totalPagosDiferidos = 0;
                        $tablaPagosDiferidos = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                        $hayConceptosPago = false;
                        $resumenPagos='<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                        $infoPago  = "";
                        $fechaCumplimientoPago="";
                        foreach ($audiencia_partes as $key => $audiencia_parte) {
                          if ($audiencia_parte->parte->tipo_parte_id != 3) {
                            $parteID = $audiencia_parte->parte->id;

                            //datos laborales del solicitante
                            $datoLaborales = DatoLaboral::with('jornada','ocupacion')->where('parte_id', $parteID)->get();
                            $hayDatosLaborales = count($datoLaborales);
                            if($hayDatosLaborales>1){
                              $datoLaborales =$datoLaborales->where('resolucion',true)->first();
                            }else{
                              $datoLaborales =$datoLaborales->first();
                            }

                            if($hayDatosLaborales >0){
                              $remuneracionDiaria = $datoLaborales->remuneracion / $datoLaborales->periodicidad->dias;
                              $anios_antiguedad = Carbon::parse($datoLaborales->fecha_ingreso)->floatDiffInYears($datoLaborales->fecha_salida);
                              $propVacaciones = $anios_antiguedad - floor($anios_antiguedad);
                              $salarios = SalarioMinimo::get('salario_minimo');
                              $salarioMinimo = $salarios[0]->salario_minimo;
                              $anioSalida = Carbon::parse($datoLaborales->fecha_salida)->startOfYear();
                              $propAguinaldo = Carbon::parse($anioSalida)->floatDiffInYears($datoLaborales->fecha_salida);
                              $vacacionesPorAnio = VacacionesAnio::all();
                              $diasVacaciones = 0;
                              foreach ($vacacionesPorAnio as $key => $vacaciones) {
                                  if($vacaciones->anios_laborados >= $anios_antiguedad ){
                                      $diasVacaciones = $vacaciones->dias_vacaciones;
                                      break;
                                  }
                              }
                              $pagoVacaciones = $propVacaciones * $diasVacaciones * $remuneracionDiaria;
                              $salarioTopado = ($remuneracionDiaria > (2*$salarioMinimo) ? (2*$salarioMinimo) : $remuneracionDiaria);

                              //Propuesta de convenio al 100% y 50%
                              $prouestas = [];
                              array_push($prouestas,array("concepto_pago"=> 'Indemnización constitucional', "montoCompleta"=>round($remuneracionDiaria * 90,2), "montoAl50"=>round($remuneracionDiaria * 45,2) )); //Indemnizacion constitucional = gratificacion A
                              array_push($prouestas,array("concepto_pago"=> 'Aguinaldo', "montoCompleta"=>round($remuneracionDiaria * 15 * $propAguinaldo,2) ,  "montoAl50"=>round($remuneracionDiaria * 15 * $propAguinaldo,2) )); //Aguinaldo = dias de aguinaldo
                              array_push($prouestas,array("concepto_pago"=> 'Vacaciones', "montoCompleta"=>round($pagoVacaciones,2), "montoAl50"=>round($pagoVacaciones,2))); //Vacaciones = dias vacaciones
                              array_push($prouestas,array("concepto_pago"=> 'Prima vacacional', "montoCompleta"=>round($pagoVacaciones * 0.25,2), "montoAl50"=>round($pagoVacaciones * 0.25,2) )); //Prima Vacacional
                              array_push($prouestas,array("concepto_pago"=> 'Prima antigüedad', "montoCompleta"=>round($salarioTopado * $anios_antiguedad *12,2), "montoAl50"=>round($salarioTopado * $anios_antiguedad *6,2) )); //Prima antiguedad = gratificacion C

                              // $tablaConceptos = '<h4>Propuestas</h4>';
                              $tablaConceptos .= '<table  class="tbl">';
                              $tablaConceptos .= '<thead><tr><th>Prestación</th><th>Propuesta completa</th><th>Propuesta 45 días</th></tr></thead>';
                              $tablaConceptos .= '<tbody >';
                              $total50 = 0;
                              $total100 = 0;
                              foreach ($prouestas as $concepto ) {
                                $tablaConceptos .= '<tr><td class="tbl">'.$concepto['concepto_pago'].'</td><td class="amount"> $'.$concepto['montoCompleta'].'</td><td class="amount"> $'.$concepto['montoAl50'].'</td> </tr>';
                                $total100 += floatval($concepto['montoCompleta'] );
                                $total50 += floatval($concepto['montoAl50'] );
                              }
                              $tablaConceptos .= '<tr ><th class="tbl"> TOTAL </th><td class="amount"> $'.$total100.'</td><td class="amount"> $'.$total50.'</td> </tr>';
                              $tablaConceptos .= '</tbody>';
                              $tablaConceptos .= '</table>';

                              //Conceptos resolucion
                              // $tablaConceptos .= '<h4>Propuesta Configurada </h4>';
                              $resolucion_conceptos = ResolucionParteConcepto::where('audiencia_parte_id',$audiencia_parte->id)->get();
                              $tablaConceptosEConvenio = '';
                              $tablaConceptosRConvenio = '';
                              $tablaConceptosEActa = '';
                              //$tablaConceptosConvenio = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                              $tablaRetencionesConvenio = '<tr><td colspan="2" style="text-align: center;font-weight:bold;"> RETENCIONES </td></tr>';
                              $tablaConceptosConvenio .= '<table class="tbl">';
                              $tablaConceptosConvenio .= '<tbody>';
                              $tablaConceptosActa .= '';
                              $hayRetenciones = false;
                              $hayConceptosPago = false;
                              $conceptosEspecie = [];
                              $conceptosDerechos = [];
                              $parte = Parte::find($parteID);
                              if(sizeof($parte->compareciente)>0){
                                $nombreParte = $parte['nombre'].' '.$parte['primer_apellido'].' '.$parte['segundo_apellido'];
                                $tablaConceptosActa .= ' Propuesta para '.$nombreParte;
                                $tablaConceptosActa .= '<table class="tbl">';
                                $tablaConceptosActa .= '<tbody>';
                                $tablaRetencionesActa = '<tr><td colspan="2" style="text-align: center;font-weight:bold;"> RETENCIONES </td></tr>';
                              }

                              $totalPercepciones = 0;
                              $totalDeducciones = 0;
                              foreach ($resolucion_conceptos as $concepto ) {
                                $conceptoName = ConceptoPagoResolucion::select('nombre')->find($concepto->concepto_pago_resoluciones_id);
                                if($concepto->concepto_pago_resoluciones_id != 9 && $concepto->concepto_pago_resoluciones_id != 11){//en especie
                                  if($concepto->concepto_pago_resoluciones_id == 12 || $concepto->concepto_pago_resoluciones_id == 13){//otro pago o deduccion
                                    $conceptoName->nombre = $concepto->otro;
                                    if($concepto->concepto_pago_resoluciones_id == 13){
                                      $totalDeducciones += ($concepto->monto!= null ) ? floatval($concepto->monto) : 0;
                                    }else{
                                      $totalPercepciones += ($concepto->monto!= null ) ? floatval($concepto->monto) : 0;
                                    }
                                  }else{
                                    $totalPercepciones += ($concepto->monto!= null ) ? floatval($concepto->monto) : 0;
                                  }
                                  if($tipoSolicitud == 1){ //solicitud individual
                                    if($parteID == $idSolicitante){ //si resolucion pertenece al solicitante
                                      if($concepto['concepto_pago_resoluciones_id'] == 13){
                                        $tablaRetencionesConvenio .= '<tr><td class="tbl"> '.$conceptoName->nombre.' </td><td style="text-align:right;">     $'.number_format($concepto->monto, 2, '.', ',').'</td></tr>';
                                        $hayRetenciones = true;
                                      }else{
                                        $tablaConceptosConvenio .= '<tr><td class="tbl"> '.$conceptoName->nombre.' </td><td style="text-align:right;">     $'.number_format($concepto->monto, 2, '.', ',').'</td></tr>';
                                        $hayConceptosPago = true;
                                      }
                                    }
                                  }else{
                                    if($parteID == $idSolicitado){ //si resolucion pertenece al citado
                                      if($concepto['concepto_pago_resoluciones_id'] == 13){
                                        $tablaRetencionesConvenio .= '<tr><td class="tbl"> '.$conceptoName->nombre.' </td><td style="text-align:right;">     $'.number_format($concepto->monto, 2, '.', ',').'</td></tr>';
                                        $hayRetenciones = true;
                                      }else{
                                        $tablaConceptosConvenio .= '<tr><td class="tbl"> '.$conceptoName->nombre.' </td><td style="text-align:right;">     $'.number_format($concepto->monto, 2, '.', ',').'</td></tr>';
                                        $hayConceptosPago = true;
                                      }
                                    }
                                  }
                                  if($concepto['concepto_pago_resoluciones_id'] == 13){
                                    $tablaRetencionesActa .= '<tr><td class="tbl"> '.$conceptoName->nombre.' </td><td style="text-align:right;">     $'.number_format($concepto->monto, 2, '.', ',').'</td></tr>';
                                    $hayRetenciones = true;
                                  }else{
                                    $tablaConceptosActa .= '<tr><td class="tbl"> '.$conceptoName->nombre.' </td><td style="text-align:right;">     $'.number_format($concepto->monto, 2, '.', ',').'</td></tr>';
                                  }
                                }else{ //9 y 11
                                  if($tipoSolicitud == 1){ //solicitud individual
                                    if($parteID == $idSolicitante){ //si resolucion pertenece al solicitante
                                      if($concepto['concepto_pago_resoluciones_id'] == 9){
                                        array_push($conceptosEspecie, $concepto['otro'] );
                                      }else{//11
                                        $tablaConceptosRConvenio = $concepto['otro'];
                                      }
                                      // $tablaConceptosEConvenio .= $concepto->otro.' ';
                                      // $hayConceptosPago = ($concepto->concepto_pago_resoluciones_id != 11) ? true : $hayConceptosPago;
                                    }
                                  }else{
                                    if($parteID == $idSolicitado){ //si resolucion pertenece al citado
                                      if($concepto['concepto_pago_resoluciones_id'] == 9){
                                        array_push($conceptosEspecie, $concepto['otro'] );
                                      }else{//11
                                        $tablaConceptosRConvenio = $concepto['otro'];
                                      }
                                      //$tablaConceptosEConvenio .= $concepto->otro.' ';
                                      // $hayConceptosPago = ($concepto->concepto_pago_resoluciones_id != 11) ? true : $hayConceptosPago;
                                    }
                                  }
                                  ($concepto['concepto_pago_resoluciones_id'] == 9 && $idPlantilla == 3 ) ? array_push($conceptosEspecie, $concepto['otro'] ): array_push($conceptosDerechos, $concepto['otro'] );
                                  //$tablaConceptosEActa .= $concepto->otro.' ';
                                }
                              }
                              $tablaConceptosEActa .= implode(", ",$conceptosEspecie);
                              $totalPercepciones = $totalPercepciones - $totalDeducciones;
                              if($tipoSolicitud == 1){ //solicitud individual
                                $tablaConceptosConvenio .= ($parteID == $idSolicitante && $hayRetenciones)? $tablaRetencionesConvenio:"";
                                $tablaConceptosConvenio .= ($parteID == $idSolicitante)?'<tr><td> Total de percepciones </td><td>     $'.number_format($totalPercepciones, 2, '.', ',').'</td></tr>':"";
                              }else{
                                $tablaConceptosConvenio .= ($parteID == $idSolicitado && $hayRetenciones)? $tablaRetencionesConvenio:"";
                                $tablaConceptosConvenio .= ($parteID == $idSolicitado)?'<tr><td> Total de percepciones </td><td>     $'.number_format($totalPercepciones, 2, '.', ',').'</td></tr>':"";
                              }
                              $tablaConceptosConvenio .= '</tbody>';
                              $tablaConceptosConvenio .= '</table>';
                              if($tipoSolicitud == 1){ //solicitud individual
                                if($parteID == $idSolicitante){ //si resolucion pertenece al solicitante
                                  $tablaConceptosConvenio .= $tablaConceptosRConvenio;
                                  $tablaConceptosEConvenio = implode(", ",$conceptosEspecie);
                                  $tablaConceptosConvenio .= ($tablaConceptosEConvenio!='') ? '<p>Adicionalmente las partes acordaron que la parte&nbsp;<b> EMPLEADORA</b> entregar&aacute; a la parte <b>TRABAJADORA</b> '.$tablaConceptosEConvenio.'.</p>':'';
                                }
                              }else{
                                if($parteID == $idSolicitado){ //si resolucion pertenece al citado
                                  $tablaConceptosConvenio .= $tablaConceptosRConvenio;
                                  $tablaConceptosEConvenio = implode(", ",$conceptosEspecie);
                                  $tablaConceptosConvenio .= ($tablaConceptosEConvenio!='') ? '<p>Adicionalmente las partes acordaron que la parte&nbsp;<b> EMPLEADORA</b> entregar&aacute; a la parte <b>TRABAJADORA</b> '.$tablaConceptosEConvenio.'.</p>':'';
                                }
                              }
                              if(sizeof($parte->compareciente)>0){
                                $tablaConceptosActa .= ($hayRetenciones)?$tablaRetencionesActa:"";
                                $tablaConceptosActa .= '<tr><td> Total de percepciones </td><td>     $'.number_format($totalPercepciones, 2, '.', ',').'</td></tr>';
                                $tablaConceptosActa .= '</tbody>';
                                $tablaConceptosActa .= '</table>';
                                $tablaConceptosActa .= implode(", ",$conceptosDerechos);
                                $tablaConceptosActa .= ($tablaConceptosEActa!='') ? '<p>Adicionalmente las partes acordaron que la parte&nbsp;<b> EMPLEADORA</b> entregar&aacute; a la parte <b>TRABAJADORA</b> '.$nombreParte.' '.$tablaConceptosEActa.'.</p>':'';
                                $tablaConceptosActa .= '<br>';
                              }

                              $totalPercepciones = number_format($totalPercepciones, 2, '.', '');
                              $totalPercepcion = explode('.', $totalPercepciones);
                              $intTotalPercepciones = $totalPercepcion[0];
                              $decTotalPercepciones = $totalPercepcion[1];
                              $intTotalPercepciones = (new NumberFormatter("es", NumberFormatter::SPELLOUT))->format((float)$intTotalPercepciones);
                              $intTotalPercepciones = str_replace("uno","un",$intTotalPercepciones);
                              $cantidadTextual = $intTotalPercepciones.' pesos '. $decTotalPercepciones.'/100';
                              if($tipoSolicitud == 1){ //solicitud individual
                                if($parteID == $idSolicitante ){
                                  $datosResolucion['total_percepciones']= number_format($totalPercepciones, 2, '.', ',');//$totalPercepciones;
                                  $datosResolucion['total_percepciones_letra']= $cantidadTextual;
                                  $datosResolucion['pagos']= $hayConceptosPago;
                                }
                              }else{
                                if($parteID == $idSolicitado ){
                                  $datosResolucion['total_percepciones']= number_format($totalPercepciones, 2, '.', ',');//$totalPercepciones;
                                  $datosResolucion['total_percepciones_letra']= $cantidadTextual;
                                  $datosResolucion['pagos']= $hayConceptosPago;
                                }
                              }
                            }
                            //Fechas pago resolucion
                            $tablaPagosDiferidos .= '<table class="tbl">';
                            $tablaPagosDiferidos .= '<tbody>';
                            $resolucion_pagos = ResolucionPagoDiferido::where('audiencia_id',$audienciaId)->orderBy('id')->get();

                            if(count($resolucion_pagos) > 0  && (($parteID == $idSolicitante && $tipoSolicitud == 1) || ($parteID == $idSolicitado && $tipoSolicitud == 2)) ) {
                              $resumenPagos .= '<table class="tbl">';
                              $resumenPagos .= '<theader>';
                              $resumenPagos .= '<th>Fecha cumplimiento</th><th>Concepto</th><th>Monto</th><th>Descripción</th>';
                              $resumenPagos .= '</theader>';
                              $resumenPagos .= '<tbody>';
                            }
                            foreach ($resolucion_pagos as $pago ) {
                              if($tipoSolicitud == 1){
                                if(($parteID == $pago->solicitante_id) && ($parteID == $idSolicitante)){
                                  $enPago =($pago->monto != null)?'   $'.number_format($pago->monto, 2, '.', ',') : "$0.00";
                                  $tablaPagosDiferidos .= '<tr><td class="tbl"> '.Carbon::createFromFormat('Y-m-d H:i:s',$pago->fecha_pago)->format('d/m/Y h:i').' horas </td><td> '.$pago->descripcion_pago.'</td><td style="text-align:right;"> '.$enPago.'</td></tr>';
                                  if($pago->diferido){
                                    $totalPagosDiferidos +=1;
                                  }
                                  if($pago->pagado){
                                    $infoPago = $pago->informacion_pago;
                                    // $fechaCumplimientoPago = Carbon::createFromFormat('Y-m-d H:i:s',$pago->fecha_cumplimiento)->format('d/m/Y');
                                    $fechaCumplimientoPago = $pago->fecha_cumplimiento;
                                    // $resumenPagos .= $pago->informacion_pago . " <br>";
                                    $fechaC = ($pago->fecha_cumplimiento != "" && $pago->fecha_cumplimiento != null)? Carbon::createFromFormat('Y-m-d H:i:s',$pago->fecha_cumplimiento)->format('d/m/Y') : "N/A";
                                    $resumenPagos .= '<tr><td class="tbl"> '. $fechaC.' </td><td> '.$pago->descripcion_pago.'</td><td style="text-align:right;">  '. $enPago .'</td><td style="text-align:justify;">  '. $pago->informacion_pago .'</td></tr>';
                                  }
                                }
                              }else{
                                if(($parteID == $pago->solicitante_id) && ($parteID == $idSolicitado)){
                                  $enPago =($pago->monto != null)?'   $'.number_format($pago->monto, 2, '.', ',') : "$0.00";
                                  $tablaPagosDiferidos .= '<tr><td class="tbl"> '.Carbon::createFromFormat('Y-m-d H:i:s',$pago->fecha_pago)->format('d/m/Y h:i').' horas </td><td> '.$pago->descripcion_pago.'</td><td style="text-align:right;">  '. $enPago .'</td></tr>';
                                  if($pago->diferido){
                                    $totalPagosDiferidos +=1;
                                  }
                                  if($pago->pagado){
                                    $infoPago = $pago->informacion_pago;
                                    $fechaCumplimientoPago = $pago->fecha_cumplimiento;
                                    $resumenPagos .= '<tr><td class="tbl"> '.Carbon::createFromFormat('Y-m-d H:i:s',$pago->fecha_cumplimiento)->format('d/m/Y').' </td><td> '.$pago->descripcion_pago.'</td><td style="text-align:right;">  '. $enPago .'</td><td style="text-align:justify;">  '. $pago->informacion_pago .'</td></tr>';
                                  }
                                }
                              }
                            }
                            $tablaPagosDiferidos .= '</tbody>';
                            $tablaPagosDiferidos .= '</table>';
                            $resumenPagos .= '</tbody>';
                            $resumenPagos .= '</table>';
                            $datosResolucion['informacion_pago']= $infoPago;
                            $datosResolucion['fecha_cumplimiento_pago']= $fechaCumplimientoPago;
                            $datosResolucion['resumen_pagos']= $resumenPagos;
                            $datosResolucion['total_diferidos']= $totalPagosDiferidos;
                            $datosResolucion['pagos_diferidos']= $tablaPagosDiferidos;
                          }
                        }
                        
                        $datosResolucion['propuestas_conceptos']= $tablaConceptos;
                        $datosResolucion['propuestas_trabajadores']= $tablaConceptosActa;
                        $datosResolucion['propuesta_configurada']= $tablaConceptosConvenio;
                        $datosResolucion['propuestas_acta']= $tablaConceptosActa;
                      }else if($etapa['etapa_resolucion_id'] == 5){
                        $datosResolucion['segunda_manifestacion']= $etapa['evidencia'];
                      }else if($etapa['etapa_resolucion_id'] == 6){
                        $datosResolucion['descripcion_pagos']= $etapa['evidencia'];
                      }
                    }

                    // citados que convinieron comparecieron
                    $partes_convenio = Compareciente::where('audiencia_id',$audienciaId)->get();
                    $hayPartesConvenio = count($partes_convenio);
                    $nombreSolicitanteComparecientes = "";
                    $nombreSolicitantesConvenio = "";

                    if($hayPartesConvenio > 0){
                      $citadosConvenio = [];
                      $solictantesConvenio = [];
                      $clausulacitadosConvenio = [];
                      $clausulasolicitantesConvenio = [];
                      $solicitantesComparecientes = [];
                      $citadosComparecientes = [];
                      $nombreCitadoConvenio = "";
                      $nombreSolicitanteConvenio = "";
                      $nombreCitadoComparecientes = "";
                      $nombreSolicitanteComparecientes = "";
                      $idParteCitada = "";
                      $clausula2citadosConvenio = "";
                      $clausula2solicitantesConvenio = "";

                      foreach ($partes_convenio as $key => $parteConvenio) {
                        $nombreCitadoComparecientes = "";
                        $nombreSolicitanteComparecientes = "";
                        $nombreCitadoConvenio = "";
                        $clausulaSolicitanteConvenio = "";
                        $clausulaCitadoConvenio = "";
                        //citados convenio
                        $parteC = $parteConvenio->parte;
                        if($parteC->id != $idParteCitada){
                          $idParteCitada = $parteC->id;
                          if($parteC->tipo_persona_id == 1){//fisica
                            if($parteC->tipo_parte_id == 3){//OTRO (representante)
                              $representanteLegalC = $parteC;
                              $parteRepresentada = Parte::find($representanteLegalC->parte_representada_id);
                              $segundo_apellido_representante = ($representanteLegalC['segundo_apellido']!="")?' '.$representanteLegalC['segundo_apellido']:"";
                              $nombreRepresentanteLegal = $representanteLegalC['nombre'].' '.$representanteLegalC['primer_apellido'].$segundo_apellido_representante;
                              $representanteIdentificacion = "--";
                              $documentoRep = $representanteLegalC->documentos;
                              $representanteInstrumento="";
                              $representantePoder="";
                              if( sizeof($documentoRep) > 0 ){
                                foreach ($documentoRep as $k => $docu) {

                                  if($docu->clasificacionArchivo->tipo_archivo_id == 1){ //tipo identificacion
                                    $representanteIdentificacion = ($docu->clasificacionArchivo->nombre != null ) ? " quien se identifica con " .$docu->clasificacionArchivo->nombre: "";
                                  }else if($docu->clasificacionArchivo->tipo_archivo_id == 9){
                                    $representantePoder = ($docu->clasificacionArchivo->nombre != null ) ? " en términos de " .$docu->clasificacionArchivo->nombre . ', poder que a la fecha de este convenio no le ha sido revocado. ' : "";
                                    $representanteInstrumento = ($docu->clasificacionArchivo->nombre != null ) ? " circunstancia que se acredita con " .$docu->clasificacionArchivo->nombre ." ". $representanteLegalC->detalle_instrumento : "";
                                  }
                                }
                              }
                              $nombreRepresentada = ($parteRepresentada['tipo_persona_id']== 2)? $parteRepresentada['nombre_comercial']: $parteRepresentada['nombre'].' '.$parteRepresentada['primer_apellido'] .' '.$parteRepresentada['segundo_apellido'];
                              $resolucionDe = ($tipoSolicitud == 1) ? 'parte_solicitada_id' : 'parte_solicitante_id';
                              $resolucionParteRepresentada = ResolucionPartes::where('audiencia_id',$audienciaId)->where($resolucionDe,$parteRepresentada['id'])->first();
                              if($resolucionParteRepresentada && $resolucionParteRepresentada->terminacion_bilateral_id ==3){
                                if($parteRepresentada->tipo_parte_id == 2){ //si representante de citado
                                  $nombreCitadoConvenio = $nombreRepresentada .' representada por '.$nombreRepresentanteLegal .' en carácter de apoderado legal';
                                  $clausulaCitadoConvenio = $nombreRepresentanteLegal. $representanteIdentificacion .', que es apoderado legal de '. $nombreRepresentada .' y que cuenta con facultades suficientes para convenir a nombre de su representada'. $representantePoder ;
                                }else{
                                  $nombreSolicitanteConvenio = $nombreRepresentada.' representada por '.$nombreRepresentanteLegal .' en carácter de apoderado legal';
                                  $clausulaSolicitanteConvenio = $nombreRepresentanteLegal. $representanteIdentificacion .', que es apoderado legal de '. $nombreRepresentada .' y que cuenta con facultades suficientes para convenir a nombre de su representada'. $representantePoder ;
                                }
                              }
                              //$nombreCitadoComparecientes = $parteRepresentada['nombre_comercial'].' representada por '.$nombreRepresentanteLegal .' en carácter de apoderado legal';
                              $nombreCitadoComparecientes = ($parteRepresentada->tipo_parte_id == 2)? $nombreRepresentanteLegal .', en su carácter de representante legal de '. $nombreRepresentada . $representanteInstrumento .", ".$representanteIdentificacion:"";
                              $nombreSolicitanteComparecientes = ($parteRepresentada->tipo_parte_id == 1)? $nombreRepresentanteLegal .', en su carácter de representante legal de '. $nombreRepresentada . $representanteInstrumento .", ".$representanteIdentificacion:"";
                            }else{ // Solicitante o Citado
                              //if($parteC->tipo_parte_id == 2){
                                foreach ($parteC->documentos as $k => $docu) {
                                  if($docu->clasificacionArchivo->tipo_archivo_id == 1){ //tipo identificacion
                                    //$parteIdentificacion = ($docu->clasificacionArchivo->nombre != null ) ? " quien se identifica con " .$docu->clasificacionArchivo->nombre: "";
                                    $parteIdentificacion = ($docu->clasificacionArchivo->nombre != null ) ? " quien se identifica con " .$docu->clasificacionArchivo->nombre . " expedida a su favor por ". $docu->clasificacionArchivo->entidad_emisora->nombre: "";
                                  }
                                }
                                $segundo_apellido = ($parteC['segundo_apellido']!="")?' '.$parteC['segundo_apellido']:"";
                                $resolucionParteRepresentada = ResolucionPartes::where('audiencia_id',$audienciaId)->where('parte_solicitada_id',$parteC->id)->first();
                              if($parteC->tipo_parte_id == 2){//citados
                                if($resolucionParteRepresentada && $resolucionParteRepresentada->terminacion_bilateral_id ==3){
                                  $nombreCitadoConvenio = $parteC['nombre'].' '.$parteC['primer_apellido'].$segundo_apellido;
                                  $clausulaCitadoConvenio = $parteC['nombre'].' '.$parteC['primer_apellido'].$segundo_apellido . $parteIdentificacion . '  tener plenas capacidades de goce y ejercicio para convenir el presente instrumento. ';
                                }
                                  $nombreCitadoComparecientes = $parteC['nombre'].' '.$parteC['primer_apellido'].$segundo_apellido. $parteIdentificacion;
                              }else{
                                if($resolucionParteRepresentada && $resolucionParteRepresentada->terminacion_bilateral_id ==3){
                                  $nombreSolicitanteConvenio = $parteC['nombre'].' '.$parteC['primer_apellido'].$segundo_apellido;
                                  $clausulaSolicitanteConvenio = $parteC['nombre'].' '.$parteC['primer_apellido'].$segundo_apellido . $parteIdentificacion . '  tener plenas capacidades de goce y ejercicio para convenir el presente instrumento. ';
                                }
                                $nombreSolicitanteComparecientes = $parteC['nombre'].' '.$parteC['primer_apellido'].$segundo_apellido . $parteIdentificacion;
                              }
                            }
                          }else{ //moral compareciente
                            $representanteLegalC = Parte::with('documentos.clasificacionArchivo.entidad_emisora')->where('parte_representada_id', $parteC->id)->where('tipo_parte_id',3)->get();
                            $representanteLegalC = $representanteLegalC[0];
                            $segundo_apellido_representante = ($representanteLegalC['segundo_apellido']!="")?' '.$representanteLegalC['segundo_apellido']:"";
                            $nombreRepresentanteLegal = $representanteLegalC['nombre'].' '.$representanteLegalC['primer_apellido'].$segundo_apellido_representante;
                            $representanteIdentificacion = "--";
                            if( sizeof($representanteLegalC['documentos']) > 0 ){
                              foreach ($representanteLegalC['documentos'] as $k => $docu) {
                                if($docu->clasificacionArchivo->tipo_archivo_id == 1){ //tipo identificacion
                                  $representanteIdentificacion = ($docu->clasificacionArchivo->nombre != null ) ? " quien se identifica con " .$docu->clasificacionArchivo->nombre: "";
                                }else if($docu->clasificacionArchivo->tipo_archivo_id == 9){
                                  $representantePoder = ($docu->clasificacionArchivo->nombre != null ) ? " en términos de " .$docu->clasificacionArchivo->nombre . ', poder que a la fecha de este convenio no le ha sido revocado. ' : "";
                                }
                              }
                            }
                            $resolucionParteRepresentada = ResolucionPartes::where('audiencia_id',$audienciaId)->where('parte_solicitada_id',$parteC['id'])->first();
                            if($resolucionParteRepresentada && $resolucionParteRepresentada->terminacion_bilateral_id ==3){
                              $nombreCitadoConvenio = $parteC['nombre_comercial'].' representada por '.$nombreRepresentanteLegal .' en carácter de apoderado legal';
                              $clausulaCitadoConvenio = $nombreRepresentanteLegal. $representanteIdentificacion .', que es apoderado legal de '. $parteC['nombre_comercial'] .' y que cuenta con facultades suficientes para convenir a nombre de su representada'. $representantePoder ;
                            }
                            $nombreCitadoComparecientes = $parteC['nombre_comercial'].' representada por '.$nombreRepresentanteLegal .' en carácter de apoderado legal' ; //$parteIdentificacion
                          }
                          if($clausulaCitadoConvenio != ""){
                            array_push($clausulacitadosConvenio, $clausulaCitadoConvenio );
                          }
                          if($clausulaSolicitanteConvenio != ""){
                            array_push($clausulasolicitantesConvenio, $clausulaSolicitanteConvenio );
                          }
                          if($nombreCitadoConvenio != ""){
                            array_push($citadosConvenio, $nombreCitadoConvenio );
                          }
                          if($nombreSolicitanteConvenio != ""){
                            array_push($solictantesConvenio, $nombreSolicitanteConvenio );
                          }
                          if($nombreCitadoComparecientes != ""){
                              array_push($citadosComparecientes, $nombreCitadoComparecientes );
                          }
                          if($nombreSolicitanteComparecientes != ""){
                            array_push($solicitantesComparecientes, $nombreSolicitanteComparecientes );
                          }
                        }
                      }
                      if($hayPartesConvenio > 1){
                        $clausulacitadosConvenioA =  implode(", ",$clausulacitadosConvenio);
                        $clausula2citadosConvenio = $clausulacitadosConvenioA;//$this->lreplace(',', ' y', $citadosConvenioA);

                        $clausulasolicitantesConvenioA =  implode(", ",$clausulasolicitantesConvenio);
                        $clausula2solicitantesConvenio = $clausulasolicitantesConvenioA;

                        $citadosConvenioA =  implode(", ",$citadosConvenio);
                        $nombreCitadosConvenio = $citadosConvenioA;//$this->lreplace(',', ' y', $citadosConvenioA);

                        $solicitantesConvenioA =  implode(", ",$solictantesConvenio);
                        $nombreSolicitantesConvenio = $solicitantesConvenioA;//$this->lreplace(',', ' y', $citadosConvenioA);

                        $citadosConvenioB =  implode(", ",$citadosComparecientes);
                        $nombreCitadosComparecientes = $citadosConvenioB;//$this->lreplace(',', ' y', $citadosConvenioA);

                        $solicitantesB =  implode(", ",$solicitantesComparecientes);
                        $nombreSolicitanteComparecientes = $solicitantesB;//$this->lreplace(',', ' y', $citadosConvenioA);
                      }else{
                        $nombreCitadosConvenio = $nombreCitadoConvenio;
                        $nombreCitadosComparecientes = $nombreCitadoComparecientes;
                      }
                    }else{
                      $nombreCitadosConvenio = "-";
                      $nombreCitadosComparecientes = "";
                      $clausula2citadosConvenio = "";
                      $clausula2solicitantesConvenio = "";
					          }

                    $datosResolucion['citados_comparecientes'] = $nombreCitadosComparecientes;
                    $datosResolucion['solicitantes_comparecientes'] = $nombreSolicitanteComparecientes;
                    $datosResolucion['citados_convenio'] = $nombreCitadosConvenio;
                    $datosResolucion['solicitantes_convenio'] = $nombreSolicitantesConvenio;
                    $datosResolucion['segunda_declaracion_convenio_patronal'] = $clausula2solicitantesConvenio;
                    $datosResolucion['segunda_declaracion_convenio'] = $clausula2citadosConvenio;
                    $datosResolucion['primera_manifestacion'] = (isset($datosResolucion['primera_manifestacion']))? $datosResolucion['primera_manifestacion'] :"";
                    $datosResolucion['segunda_manifestacion'] = (isset($datosResolucion['segunda_manifestacion']))? $datosResolucion['segunda_manifestacion'] :"";
                    $datosResolucion['total_percepciones'] = (isset($datosResolucion['total_percepciones']))? $datosResolucion['total_percepciones'] :"";
                    $datosResolucion['propuestas_conceptos'] = (isset($datosResolucion['propuestas_conceptos']))? $datosResolucion['propuestas_conceptos'] :"";
                    $datosResolucion['propuesta_configurada'] = (isset($datosResolucion['propuesta_configurada']))? $datosResolucion['propuesta_configurada'] :"";
                    $datosResolucion['pagos_diferidos'] = (isset($datosResolucion['pagos_diferidos']))? $datosResolucion['pagos_diferidos'] :"";
                    $datosResolucion['total_diferidos'] = (isset($datosResolucion['total_diferidos']))? $datosResolucion['total_diferidos'] :"";
                    $datosResolucion['informacion_pago'] = (isset($datosResolucion['informacion_pago']))? $datosResolucion['informacion_pago'] :"";
                    $datosResolucion['resumen_pagos'] = (isset($datosResolucion['resumen_pagos']))? $datosResolucion['resumen_pagos'] :"";
                    $data = Arr::add( $data, $model, $datosResolucion );

                  }else{
                    $objeto = $model_name::first();
                    $objeto = new JsonResponse($objeto);
                    $otro = json_decode($objeto->content(),true);
                    $otro = Arr::except($otro, ['id','updated_at','created_at','deleted_at']);
                    $data = Arr::add( $data, $model , $otro );
                  }
                }
              
            }
            return $data;
          } catch (\Throwable $e) {
            Log::error('En script:'.$e->getFile()." En línea: ".$e->getLine().
                       " Se emitió el siguiente mensaje: ". $e->getMessage().
                       " Con código: ".$e->getCode()." La traza es: ". $e->getTraceAsString());
            return []; // Retornar array vacío en lugar de $data que no existe
          }
    }
    function lreplace($search, $replace, $original){
      $pos = strrpos($original, $search);
      if($pos !== false){
          $subject = substr_replace($original, $replace, $pos, strlen($search));
      }
      return $subject;
    }

    /*
    Calcular posible prescripcion de derechos
      */
    private function calcularPrescripcion($objetoSolicitud,$fechaConflicto,$fechaRatificacion)
    {
      try {
        $prescripcion = 'N/A';
        foreach ($objetoSolicitud as $key => $objeto) {
          if($objeto->tipo_objeto_solicitudes_id == 1){
            $prescripcion = 'No';
            if($objeto->id == 1 || $objeto->id == 4) {//Despido o derechos de preferencia
                $meses = Carbon::parse($fechaConflicto)->diffInMonths($fechaRatificacion);
                $prescripcion = ($meses > 2) ? 'Si' : $prescripcion;
            }else if ($objeto->id == 2 || $objeto->id == 5 || $objeto->id == 6){//Pago prestaciones o derecho de antiguiedad o derecho de acenso
                $anios = Carbon::parse($fechaConflicto)->floatDiffInYears($fechaRatificacion);
                $prescripcion = ($anios > 1) ? 'Si': $prescripcion;
            }else if($objeto->id == 3){//Resicion de relacion laboral
                $meses = Carbon::parse($fechaConflicto)->diffInMonths($fechaRatificacion);
                $prescripcion = ($meses > 1) ? 'Si': $prescripcion;
            }
          }
        }
        return $prescripcion;
      } catch (\Throwable $th) {
        return "";
      }
    }
    /*
    Calcular la fecha m'axima para ratificar la solicitud (3 dias maximo)
      */
    private function calcularFechaMaximaRatificacion($fechaRecepcion,$centroId)
    {
      try {
        $ndia=0;
        $diasDisponibilidad = [];
        $centro = Centro::find($centroId);
        $disponibilidad_centro = $centro->disponibilidades;
        $incidencias_centro = $centro->incidencias;
        foreach ($disponibilidad_centro as $disponibilidad) { //dias de disponibilidad del centro
          array_push($diasDisponibilidad,$disponibilidad->dia);
        }
        while ($ndia <= 3) {
          $fechaRecepcion = Carbon::parse($fechaRecepcion);
          if($ndia<3){
            $fechaRecepcion = $fechaRecepcion->addDay();//sumar dia a fecha recepcion
            $dayOfTheWeek = $fechaRecepcion->dayOfWeek; //dia de la semana de la fecha de recepcion
          }
          $diaHabil = array_search($dayOfTheWeek, $diasDisponibilidad);
          foreach ($incidencias_centro as $incidencia) {
            $diaConIncidencia = $fechaRecepcion->between($incidencia->fecha_inicio,$incidencia->fecha_fin);
          }
          if (false !== $diaHabil && !$diaConIncidencia) { //si dia agregado es dia disponble en centro y no tiene incidencia
            $ndia+=1;
          }
        }
        //Do,lu,ma,mi,ju,vi,sa
        // 0,1,2,3,4,5,6
        return $fechaRecepcion->toDateTimeString();
      } catch (\Throwable $th) {
        return "";
      }
    }

    function eliminar_acentos($cadena){

		//Reemplazamos la A y a
		$cadena = str_replace(
		array('Á', 'À', 'Â', 'Ä', 'á', 'à', 'ä', 'â', 'ª'),
		array('A', 'A', 'A', 'A', 'a', 'a', 'a', 'a', 'a'),
		$cadena
		);

		//Reemplazamos la E y e
		$cadena = str_replace(
		array('É', 'È', 'Ê', 'Ë', 'é', 'è', 'ë', 'ê'),
		array('E', 'E', 'E', 'E', 'e', 'e', 'e', 'e'),
		$cadena );

		//Reemplazamos la I y i
		$cadena = str_replace(
		array('Í', 'Ì', 'Ï', 'Î', 'í', 'ì', 'ï', 'î'),
		array('I', 'I', 'I', 'I', 'i', 'i', 'i', 'i'),
		$cadena );

		//Reemplazamos la O y o
		$cadena = str_replace(
		array('Ó', 'Ò', 'Ö', 'Ô', 'ó', 'ò', 'ö', 'ô'),
		array('O', 'O', 'O', 'O', 'o', 'o', 'o', 'o'),
		$cadena );

		//Reemplazamos la U y u
		$cadena = str_replace(
		array('Ú', 'Ù', 'Û', 'Ü', 'ú', 'ù', 'ü', 'û'),
		array('U', 'U', 'U', 'U', 'u', 'u', 'u', 'u'),
		$cadena );

		//Reemplazamos la N, n, C y c
		$cadena = str_replace(
		array('Ñ', 'ñ', 'Ç', 'ç'),
		array('N', 'n', 'C', 'c'),
		$cadena
		);

		return $cadena;
	}

    /**
     * Devuelve la cadena de clave de nomenclatura para un documento. Esto para el "control" documental
     * del CFCRL
     *
     * @param $plantilla_id
     * @return string
     */
	public function nomenclaturaDocumento($plantilla_id){

        $plantilla = Cache::remember('plantilla_'.$plantilla_id, 600, function () use ($plantilla_id) {
            return PlantillaDocumento::find($plantilla_id);
        });

        if(!$plantilla->clave_nomenclatura){
            return '';
        }

        $timestamp = date("YmdHis");
        $nomenclatura = $plantilla->clave_nomenclatura;
        $visto_veces = Cache::increment($nomenclatura.$timestamp);

        if($visto_veces > 1) {
            $timestamp =  $timestamp.($visto_veces -1);
        }

        return sprintf('<div class="clave-nomenclatura">%s/%s</div>', $nomenclatura, $timestamp);
  }

  /*
  Convertir fechas yyyy-mm-dd hh to dd de Monthname de yyyy
  */
  private function formatoFecha($fecha,$tipo=null)
  {
    try {
      if($tipo!=3){ //no es hora
        $monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio","Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        $hh= "";
        if(strpos($fecha, " ") ){
          $date = explode(' ', $fecha);
          $fecha = $date[0];
          $hr = explode(':', $date[1]);
          $hh = $hr[0].':'.$hr[1];
        }
        $fecha = explode('-', $fecha);
        $dd = $fecha[2];
        $mm = $fecha[1];
        $yy = $fecha[0];
        if($tipo == 1){ //fecha sin hr
          $ddmmyy = $dd.' de '. $monthNames[intval($mm)-1]. ' de ' . $yy;
        }else if($tipo == 2){ //hr
          $ddmmyy = $hh;
        }else{ //fecha y hora
          $ddmmyy = $dd.' de '. $monthNames[intval($mm)-1]. ' de ' . $yy .' '. $hh;
        }
        // $ddmmyy = $dd.' de '. $monthNames[intval($mm)-1]. ' de ' . $yy .' '. $hh ;
        // return $ddmmyy;
      }else{//recibe HH:mm:ss: devuelve hh:mm hr
        $hr = explode(':', $fecha);
        $hh = $hr[0].':'.$hr[1];
        $ddmmyy = $hh;
      }
      return $ddmmyy;
    } catch (\Throwable $th) {
      return "";
    }
  }

  public function splitFirma($firma)
  {
      $aFirma = str_split($firma,150);
      $firma = implode("\n", $aFirma);
      return $firma;
  }

  /**
   * Genera el archivo PDF.
   * @param $html string HTML fuente para generar el PDF
   * @param $plantilla_id integer ID de la plantilla en la BD
   * @param $path string Ruta del archivo a guardar. Si no existe entonces regresa el PDF inline para mostrar en browser
   * @ToDo  Agregar opciones desde variable de ambiente como tamaño de página, margen, etc.
   * @return mixed
   * @throws \Symfony\Component\Debug\Exception\FatalThrowableError
   */
  public function renderPDF($html, $plantilla_id, $path=null){
      $pdf = App::make('snappy.pdf.wrapper');
      $pdf->loadHTML($html);
      $pdf->setOption('page-size', 'Letter')
          ->setOption('margin-top', '25mm')
          ->setOption('margin-bottom', '20mm')
          ->setOption('header-html', $this->getHeader($plantilla_id))
          ->setOption('footer-html', $this->getFooter($plantilla_id))
      ;
      if($path){
          return $pdf->generateFromHtml($html, $path);
      }
      return $pdf->inline();
  }

  /**
  * Se obtiene el domicilio 
  */
  public function obtenerDomicilio($item){
    $dom_parte = $item['domicilios'][0];
    $tipo_vialidad =  ($dom_parte['tipo_vialidad'] !== null)? $dom_parte['tipo_vialidad'] :"";
    $vialidad =  ($dom_parte['vialidad'] !== null)? $dom_parte['vialidad'] :"";
    $num_ext =  ($dom_parte['num_ext'] !== null)? "No. " . $dom_parte['num_ext'] :"";
    $num_int =  ($dom_parte['num_int'] !== null)? " Int. " . $dom_parte['num_int'] :"";
    $num =  $num_int.$num_ext;
    $municipio =  ($dom_parte['municipio'] !== null)? $dom_parte['municipio'] :"";
    $cp =  ($dom_parte['cp'] !== null)? " CP. " . $dom_parte['cp'] :"";
    $estado =  ($dom_parte['estado'] !== null)? $dom_parte['estado'] :"";
    $colonia =  ($dom_parte['asentamiento'] !== null)? $dom_parte['tipo_asentamiento']." ". $dom_parte['asentamiento']." "  :"";
    return mb_strtoupper($tipo_vialidad.' '.$vialidad.' '.$num.', '.$colonia.', '.$municipio.', '.$estado.', '. $cp);
  }

  /**
   * Obtener la iteración de los citados
   */
  public function obtenerCitados($idAudiencia, $idSolicitud){
    if(!$idAudiencia) return "";

    $citados = "";
    $audiencia_partes = Audiencia::find($idAudiencia)->audienciaParte;
    $solicitud = Solicitud::find($idSolicitud);
    $solicitud_tipo = $solicitud->tipo_solicitud_id;

    foreach($audiencia_partes as $parte){
      $parte_info = Parte::with('domicilios')->where("id", $parte->parte_id)->first();
      if($parte_info->tipo_parte_id == 2){
                                
        if($solicitud_tipo == 1){
          //Citado
          $citado = ($parte_info->tipo_persona_id == 1) ? $parte_info->nombre.' '.$parte_info->primer_apellido.' '.$parte_info->segundo_apellido : $parte_info->nombre_comercial;
          $notificado = $parte->finalizado !== null && strpos($parte->finalizado, "NO EXITOSO") === false;
          $notificacion_html = $notificado ? "Sí" : "No";

			$parte_citados = Parte::where("solicitud_id", $parte_info->solicitud_id)->where("representante", true)->where("parte_representada_id", $parte_info->id)->pluck("id");
			$parte_citados->push($parte_info->id);
          	//Se busca sí comparecio algún representante legal o la persona moral/persona fisica
			$parte_representante_comparecio = Compareciente::whereIn('parte_id', $parte_citados)->where('audiencia_id', $idAudiencia)->exists();
			$comparecio = $parte_representante_comparecio ? true : false;
			$comparecio_html = $comparecio ? "Sí" : "No";

			$domicilios_completo = self::obtenerDomicilio($parte_info);
			$motivos = Motivacion::where('centro_id', auth()->user()->centro_id)->where('comparecio_citado', $comparecio)->where('notificado', $notificado)->first();

			$citados .='<table border="1" cellspacing="3" cellpadding="3" style="border-collapse: collapse !important;">';
			$citados .= '<tr><td style="width: 210px !important;"><b>Citado</b></td><td>' .$citado.'</td></tr>';
			$citados .= '<tr><td style="width: 210px !important;"><b>Compareció</b></td><td>'.$comparecio_html.'</td></tr>';
			$citados .= '<tr><td style="width: 210px !important;"><b>Notificado</b></td><td>'.$notificacion_html.'</td></tr>';
			$citados .= '<tr><td style="width: 210px !important;"><b>Domicilio de la parte citada</b></td><td>' .$domicilios_completo.'</td></tr>';
			$citados .= '<tr><td style="width: 210px !important;"><b>Motivación</b></td><td>'.$motivos['motivacion_citado'].'</td></tr>';

			if($motivos['fundamento'] != NULL || $motivos['fundamento'] != "")
				$citados .= '<tr><td style="width: 210px !important;"><b>Fundamento</b></td><td>'.$motivos['fundamento'].'</td></tr>';
			$citados .='</table><br /><br />';
        }
        //
        
        //Patronal
        if($solicitud_tipo == 2 && Comparecientes::comparecio($idAudiencia, $parte_info->id)){

          if(ResolucionPartes::where('audiencia_id', $idAudiencia)->where(function($query) use ($parte_info) {
                $query->where('parte_solicitada_id', $parte_info->id)
                      ->orWhere('parte_solicitante_id', $parte_info->id);
            })->whereNotNull('tipo_propuesta_pago_id')->min('terminacion_bilateral_id') == 5){

            //Citado
            $citado = ($parte_info->tipo_persona_id == 1) ? $parte_info->nombre.' '.$parte_info->primer_apellido.' '.$parte_info->segundo_apellido : $parte_info->nombre_comercial;
            $notificado = $parte->finalizado !== null && strpos($parte->finalizado, "NO EXITOSO") === false;
            $notificacion_html = $notificado ? "Sí" : "No";

            $parte_citados = Parte::where("solicitud_id", $parte_info->solicitud_id)->where("representante", true)->where("parte_representada_id", $parte_info->id)->pluck("id");
			$parte_citados->push($parte_info->id); 
            //Se busca sí comparecio algún representante legal o la persona moral/persona fisica
            $parte_representante_comparecio = Compareciente::whereIn('parte_id', $parte_citados)->where('audiencia_id', $idAudiencia)->exists();
            $comparecio = $parte_representante_comparecio ? true : false;
            $comparecio_html = $comparecio ? "Sí" : "No";

            $domicilios_completo = self::obtenerDomicilio($parte_info);
            //$motivos = Motivacion::where('centro_id', auth()->user()->centro_id)->where('comparecio_citado', $comparecio)->where('notificado', $notificado)->first();
            $motivos = Motivacion::where('centro_id', $parte_info->solicitud->centro_id)->where('comparecio_citado', $comparecio)->where('notificado', $notificado)->first();

            $citados .='<table border="1" cellspacing="3" cellpadding="3" style="border-collapse: collapse !important;">';
            $citados .= '<tr><td style="width: 210px !important;"><b>Citado</b></td><td>' .$citado.'</td></tr>';
            $citados .= '<tr><td style="width: 210px !important;"><b>Compareció</b></td><td>'.$comparecio_html.'</td></tr>';
            $citados .= '<tr><td style="width: 210px !important;"><b>Notificado</b></td><td>'.$notificacion_html.'</td></tr>';
            $citados .= '<tr><td style="width: 210px !important;"><b>Domicilio de la parte citada</b></td><td>' .$domicilios_completo.'</td></tr>';
            $citados .= '<tr><td style="width: 210px !important;"><b>Motivación</b></td><td>'.$motivos['motivacion_citado'].'</td></tr>';

            if($motivos['fundamento'] != NULL || $motivos['fundamento'] != "")
              $citados .= '<tr><td style="width: 210px !important;"><b>Fundamento</b></td><td>'.$motivos['fundamento'].'</td></tr>';
            $citados .='</table><br /><br />';
          }

        }
        //
      }
    }
    return $citados;
  }
}
