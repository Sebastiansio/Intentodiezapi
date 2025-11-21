<?php

namespace App\Http\Controllers;

use App\Audiencia;
use App\AudienciaParte;
use App\BitacoraBuzon;
use App\Centro;
use App\Compareciente;
use App\ConceptoPagoResolucion;
use App\Conciliador;
use App\Configuracion;
use App\DatoLaboral;
use App\Disponibilidad;
use App\Domicilio;
use App\EtapaResolucionAudiencia;
use App\Expediente;
use App\Filters\CatalogoFilter;
use App\Filters\PlantillaDocumentoFilter;
use App\Events\GenerateDocumentResolution;
use App\FirmaDocumento;
use App\Http\Requests\ConfiguracionResponsivasRequest;
use App\Parte;
use App\PlantillaDocumento;
use App\ResolucionPagoDiferido;
use App\ResolucionParteConcepto;
use App\SalaAudiencia;
use App\SalarioMinimo;
use App\Services\StringTemplate;
use Illuminate\Support\Str;
use App\Services\Comparecientes;
use App\Solicitud;
use App\TipoDocumento;
use App\Documento;
use App\VacacionesAnio;
use App\Motivacion;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use NumberFormatter;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Helper\Helper;
use App\Helper\ParteHelper;
use App\Persona;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PlantillasDocumentosController extends Controller
{
  protected $request;
  protected $qrpublico;
  protected $helper;

  public function __construct(Request $request)
  {
    $this->request = $request;
    $this->qrpublico =  new Helper();
    $this->helper = new Helper();
  }
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    $plantillas = (new PlantillaDocumentoFilter(PlantillaDocumento::query(), $this->request))
      ->searchWith(PlantillaDocumento::class)
      ->filter();

    if (!$this->request->exists('sort_by')) {
      $plantillas->orderBy('id');
    }

    $plantillas = $plantillas->paginate($this->request->get('per_page', 10));

    if ($this->request->wantsJson()) {
      return $this->sendResponse($plantillas, 'SUCCESS');
    }
    return view('documentos.index', compact('plantillas'));
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create()
  {
    $tipo_plantillaDoc = TipoDocumento::all();
    $tipo_plantilla = $tipo_plantillaDoc->pluck('nombre', 'id')->toArray();
    $objetoDocumento = [];
    $path = base_path('database/datafiles');
    $json = json_decode(file_get_contents($path . "/elemento_documentos.json"));
    foreach ($json->datos as $key => $value) {
      $columnNames = Schema::getColumnListing($value->tabla);
      $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
      foreach ($guarded as $guard) {
        $k = array_search($guard, $columnNames);
        if (false !== $k) {
          unset($columnNames[$k]);
        }
      }
      $objetoDocumento[] =
        [
          'objeto' => $value->objeto,
          'nombre' => $value->nombre,
          'tabla' => $value->tabla,
          'campos' => $columnNames
        ];
    }
    $condicionales = $this->getCondicionales();
    return view('documentos.create', compact('objetoDocumento', 'tipo_plantilla', 'condicionales'));
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    $datos = $request->all();
    if ($datos["plantilla-body"] == null) {
      $header = view('documentos._header_documentos_default');
      $body = view('documentos._body_documentos_default');
      $footer = view('documentos._footer_documentos_default');
    } else {
      $header = $datos["plantilla-header"];
      $body = $datos["plantilla-body"];
      $footer = $datos["plantilla-footer"];
    }
    // $user = Auth::user();
    // $user_id = $user->id;

    $datos["nombre-plantilla"] = $datos["nombre-plantilla"] == "" ? "Plantilla default" : $datos["nombre-plantilla"];
    $datosP['nombre_plantilla'] = $datos["nombre-plantilla"];
    $datosP['clave_nomenclatura'] = isset($datos['clave_nomenclatura']) ? mb_strtoupper($datos['clave_nomenclatura']) : '';
    $datosP['tipo_documento_id'] = $datos['tipo-plantilla-id'];
    $datosP['plantilla_header'] = $header;
    $datosP['plantilla_body'] = $body;
    $datosP['plantilla_footer'] = $footer;
    // $datos['user_id'] = $user_id;
    PlantillaDocumento::create($datosP);
    // return response('OK',200);
    return redirect('plantilla-documentos')->with('success', 'Se ha guardado exitosamente');
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id) {}

  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id)
  {
    $objetoDocumento = [];
    $tipo_plantillaDoc = TipoDocumento::all();
    $tipo_plantilla = collect($tipo_plantillaDoc)->pluck('nombre', 'id')->toArray();

    $plantillaDocumento = PlantillaDocumento::find($id);
    // $config = PlantillaDocumento::orderBy('created_at', 'desc')->first();
    if (!$plantillaDocumento) {
      $header = view('documentos._header_documentos_default');
      $body = view('documentos._body_documentos_default');
      $footer = view('documentos._footer_documentos_default');

      $plantilla['plantilla_body'] = $body;
    } else {
      $tipo_plantillaDoc = $tipo_plantillaDoc->where('id', $plantillaDocumento->tipo_documento_id)->first()->getAttributes();
      $objetos = explode(",", $tipo_plantillaDoc['objetos']);
      $header = $plantillaDocumento->plantilla_header;
      $body = $plantillaDocumento->plantilla_body;
      $footer = $plantillaDocumento->plantilla_footer;
      $nombre = $plantillaDocumento->nombre_plantilla;
      $objetoDocumento = $this->getObjetoDocumento($objetos);
    }

    //  $objetoDocumento = [];
    //  //Se llena el catalogo desde el arvhivo json elemento_documentos.json
    //  $path = base_path('database/datafiles');
    //  $jsonElementos = json_decode(file_get_contents($path . "/elemento_documentos.json"));
    //  foreach ($objetos as $key => $obj){
    //    foreach ($jsonElementos->datos as $key => $value){
    //      if($value->id == $obj){
    //        $columnNames = Schema::getColumnListing($value->tabla);
    //        $guarded = ['id','updated_at','created_at','deleted_at'];
    //        foreach ( $guarded as $guard ){
    //          $k = array_search($guard, $columnNames);
    //          if (false !== $k) {
    //             unset($columnNames[$k]);
    //          }
    //        }
    //        if($value->nombre =='Solicitud'){
    //          array_push($columnNames,'total_solicitados');
    //          array_push($columnNames,'total_solicitantes');
    //          array_push($columnNames,'objeto_solicitudes');
    //        }
    //        if($value->nombre =='Conciliador'){
    //          $columnPersona = Schema::getColumnListing('personas');
    //          $guarded = ['id','updated_at','created_at','deleted_at'];
    //          foreach ( $guarded as $guard ){
    //            $k = array_search($guard, $columnPersona);
    //            if (false !== $k) {
    //               unset($columnPersona[$k]);
    //            }
    //          }
    //          foreach ($columnPersona as $k => $valPersona) {
    //            array_push($columnNames,$valPersona);
    //          }
    //        }
    //        $objetoDocumento [] =
    //            [
    //                'objeto' => $value->objeto,
    //                'nombre' => $value->nombre,
    //                'tabla' => $value->tabla,
    //                'campos' =>$columnNames
    //            ];
    //      }
    //    }
    //  }
    $condicionales = $this->getCondicionales();
    return view('documentos.edit', compact('plantillaDocumento', 'objetoDocumento', 'tipo_plantilla', 'condicionales'));
    // return view('documentos.edit')->with('plantillaDocumento', $plantilla);
    // return view('documentos.edit',compact('header','body', 'footer','nombre'))->with('plantillaDocumento', $config);
    // return view('documentos.edit', compact('header','body', 'footer','nombre'));
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id)
  {
    $plantilla = PlantillaDocumento::find($id);
    $datos = $request->all();
    $datos["nombre-plantilla"] = $datos["nombre-plantilla"] == "" ? "Plantilla default" : $datos["nombre-plantilla"];
    $datosP['nombre_plantilla'] = $datos["nombre-plantilla"];
    $datosP['plantilla_header'] = $datos["plantilla-header"];
    $datosP['plantilla_body'] = $datos["plantilla-body"];
    $datosP['plantilla_footer'] = $datos["plantilla-footer"];
    $datosP['tipo_documento_id'] = $datos['tipo-plantilla-id'];
    $datosP['clave_nomenclatura'] = isset($datos['clave_nomenclatura']) ? mb_strtoupper($datos['clave_nomenclatura']) : '';
    $plantilla->update($datosP);
    return redirect('plantilla-documentos/' . $id . '/edit')->with('success', 'Se ha actualizado exitosamente');
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    PlantillaDocumento::find($id)->delete();
    if ($this->request->wantsJson()) {
      return $this->sendResponse($id, 'SUCCESS');
    }
    return redirect()->route('plantilla-documentos.index')->with('success', 'Se ha eliminado exitosamente');
  }

  /**
   * Cargar html default para plantillas.
   *
   * @param
   * @return \Illuminate\Http\Response
   */
  public function cargarDefault()
  {
    // $tipo_plantillaDoc = TipoDocumento::all();
    $tipo_plantilla = TipoDocumento::pluck('nombre', 'id')->toArray();

    $plantillaDocumento = new PlantillaDocumento();
    $plantillaDocumento->plantilla_header = view('documentos._header_documentos_default');
    $plantillaDocumento->plantilla_body = view('documentos._body_documentos_default');
    $plantillaDocumento->plantilla_footer = view('documentos._footer_documentos_default');

    $objetoDocumento = [];
    $path = base_path('database/datafiles');
    $json = json_decode(file_get_contents($path . "/elemento_documentos.json"));
    //Se llena el catalogo desde el arvhivo json elemento_documentos.json
    foreach ($json->datos as $key => $value) {
      $columnNames = Schema::getColumnListing($value->tabla);
      $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
      foreach ($guarded as $guard) {
        $k = array_search($guard, $columnNames);
        if (false !== $k) {
          unset($columnNames[$k]);
        }
      }
      $objetoDocumento[] =
        [
          'objeto' => $value->objeto,
          'nombre' => $value->nombre,
          'tabla' => $value->tabla,
          'campos' => $columnNames
        ];
    }
    $condicionales = $this->getCondicionales();
    return view('documentos.create', compact('plantillaDocumento', 'objetoDocumento', 'tipo_plantilla', 'condicionales'));
  }
  /**
   * Funcion para obtener variables de condiciones en el editor
   *
   * @param
   * @return \Illuminate\Http\Response
   */
  private function getCondicionales()
  {
    $condicionales = [];
    $path = base_path('database/datafiles');
    $json = json_decode(file_get_contents($path . "/condiciones_documento.json"));
    $condicionales = $json->datos;
    return $condicionales;
  }
  /**
   * Obtener las variables de los modelos usados en las plantillas
   * @param
   * @return \Illuminate\Http\Response
   */

  private function getObjetoDocumento($objetos)
  {

    $objetoDocumento = [];
    //Se llena el catalogo desde el arvhivo json elemento_documentos.json
    $path = base_path('database/datafiles');
    $jsonElementos = json_decode(file_get_contents($path . "/elemento_documentos.json"));
    foreach ($objetos as $key => $obj) {
      foreach ($jsonElementos->datos as $key => $value) {
        if ($value->id == $obj) {
          $columnNames = Schema::getColumnListing($value->tabla);
          $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
          foreach ($guarded as $guard) {
            $k = array_search($guard, $columnNames);
            if (false !== $k) {
              unset($columnNames[$k]);
            }
          }
          if ($value->nombre == 'Solicitud') {
            array_push($columnNames, 'total_solicitados');
            array_push($columnNames, 'total_solicitantes');
            array_push($columnNames, 'nombres_solicitados');
            array_push($columnNames, 'nombres_solicitantes');
            array_push($columnNames, 'nombres_solicitantes_confirmados');
            array_push($columnNames, 'nss_solicitantes');
            array_push($columnNames, 'curp_solicitantes');
            array_push($columnNames, 'objeto_solicitudes');
            array_push($columnNames, 'prescripcion');
            array_push($columnNames, 'fecha_maxima_ratificacion');
            array_push($columnNames, 'tipo_solicitud');
            array_push($columnNames, 'firmas_partes_qr');
          }
          if ($value->objeto == 'Parte') {
            //if($value->id_tipo  =='1'){ // campos de datos laborales de solicitante
            $columnDatosLaborales = Schema::getColumnListing('datos_laborales');
            $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
            foreach ($guarded as $guard) {
              $k = array_search($guard, $columnDatosLaborales);
              if (false !== $k) {
                unset($columnDatosLaborales[$k]);
              }
            }
            array_push($columnDatosLaborales, 'salario_mensual');
            array_push($columnDatosLaborales, 'salario_mensual_letra');
            //datos laborales de solicitante
            array_push($columnNames, ['nombre' => 'datos_laborales', 'columns' => $columnDatosLaborales]);
            //}
            //representante legal de partes
            $columnPersona = Schema::getColumnListing('personas');
            $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
            foreach ($guarded as $guard) {
              $k = array_search($guard, $columnPersona);
              if (false !== $k) {
                unset($columnPersona[$k]);
              }
            }
            array_push($columnPersona, 'nombre_completo'); //nombre completo representante legal
            //domicilios partes
            $columnDomicilio = Schema::getColumnListing('domicilios');
            $exclude = ['id', 'updated_at', 'created_at', 'deleted_at', 'domiciliable_type', 'domiciliable_id', 'hora_atencion_de', 'hora_atencion_a', 'georeferenciable', 'tipo_vialidad_id', 'tipo_asentamiento_id'];
            foreach ($exclude as $exclu) {
              $k = array_search($exclu, $columnDomicilio);
              if (false !== $k) {
                unset($columnDomicilio[$k]);
              }
            }
            array_push($columnDomicilio, 'completo');
            array_push($columnDomicilio, 'laboral');
            //documentos de identificacion parte
            $columnDocumento = [];
            array_push($columnDocumento, 'documento');
            array_push($columnDocumento, 'numero');
            array_push($columnDocumento, 'expedida_por');
            array_push($columnNames, ['nombre' => 'identificacion', 'columns' => $columnDocumento]);

            // representante_legal de citado
            $colDocumentoRepresentante = [];
            array_push($colDocumentoRepresentante, 'identificacion_documento');
            array_push($colDocumentoRepresentante, 'identificacion_numero');
            array_push($colDocumentoRepresentante, 'identificacion_expedida_por');
            array_push($colDocumentoRepresentante, 'detalle_instrumento');
            array_push($columnNames, ['nombre' => 'representante_legal', 'columns' => array_merge($columnPersona, $colDocumentoRepresentante)]);

            // array_push($columnNames,['nombre'=>'representante_legal', 'columns'=>$columnDocumento]);
            //domicilio de partes
            array_push($columnNames, ['nombre' => 'domicilios', 'columns' => $columnDomicilio]);
            // $representante = Parte::where("parte_representada_id",$id)->where("representante",true)->get();
            array_push($columnNames, 'nombre_completo');
            array_push($columnNames, 'nombre_compareciente'); //nombre completo representante legal o fisico
            array_push($columnNames, 'qr_firma');
            array_push($columnNames, 'fecha_notificacion');
            array_push($columnNames, 'asistencia');
            array_push($columnNames, 'bitacora_consulta_buzon');
            array_push($columnNames, 'fecha_confirmacion_audiencia');
            array_push($columnNames, 'password_buzon');
          }
          if ($value->nombre == 'Conciliador') {
            $columnPersona = Schema::getColumnListing('personas');
            $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
            foreach ($guarded as $guard) {
              $k = array_search($guard, $columnPersona);
              if (false !== $k) {
                unset($columnPersona[$k]);
              }
            }
            foreach ($columnPersona as $k => $valPersona) {
              array_push($columnNames, $valPersona);
            }
            array_push($columnNames, 'nombre_completo');
            array_push($columnNames, 'qr_firma');
          }
          if ($value->nombre == 'Resolucion') {
            array_push($columnNames, 'total_percepciones');
            array_push($columnNames, 'total_percepciones_letra');
            array_push($columnNames, 'propuestas_conceptos');
            array_push($columnNames, 'propuesta_configurada');
            array_push($columnNames, 'propuestas_trabajadores');
            array_push($columnNames, 'descripcion_pagos');
            array_push($columnNames, 'pagos_diferidos');
            array_push($columnNames, 'total_diferidos');
            array_push($columnNames, 'informacion_pago');
            array_push($columnNames, 'fecha_cumplimiento_pago');
            array_push($columnNames, 'resumen_pagos');
            array_push($columnNames, 'justificacion_propuesta');
            array_push($columnNames, 'primera_manifestacion');
            array_push($columnNames, 'segunda_manifestacion');

            array_push($columnNames, 'citados_convenio');
            array_push($columnNames, 'solicitantes_convenio');
            array_push($columnNames, 'citados_comparecientes');
            array_push($columnNames, 'solicitantes_comparecientes');
            array_push($columnNames, 'segunda_declaracion_convenio');
            array_push($columnNames, 'segunda_declaracion_convenio_patronal');
            // array_push($columnNames,'firmas_partes_qr');
          }

          if ($value->nombre == 'Sala') {
            array_push($columnNames, 'nombre');
          }
          if ($value->nombre == 'Audiencia') {
            array_push($columnNames, 'comparecen_interesados');
          }
          if ($value->nombre == 'Autoridad') {
            array_push($columnNames, 'ejecutora_de_multa');
          }
          if ($value->nombre == 'Centro') {
            $columnDomicilio = Schema::getColumnListing('domicilios');
            $exclude = ['id', 'updated_at', 'created_at', 'deleted_at', 'domiciliable_type', 'domiciliable_id', 'georeferenciable', 'tipo_vialidad_id', 'tipo_asentamiento_id'];
            foreach ($exclude as $exclu) {
              $k = array_search($exclu, $columnDomicilio);
              if (false !== $k) {
                unset($columnDomicilio[$k]);
              }
            }
            array_push($columnNames, 'hora_inicio');
            array_push($columnNames, 'hora_fin');
            array_push($columnNames, 'dias');
            array_push($columnNames, 'domicilio_completo');
            array_push($columnNames, 'telefono');
            array_push($columnNames, 'nombre_administrador');
            array_push($columnNames, 'administrador_qr_firma');
            array_push($columnNames, 'conciliador_generico_qr_firma');
            array_push($columnNames, ['nombre' => 'domicilio', 'columns' => $columnDomicilio]);
          }
          $objetoDocumento[] =
            [
              'objeto' => $value->objeto,
              'nombre' => $value->nombre,
              'tabla' => $value->tabla,
              'campos' => $columnNames
            ];
        }
      }
    }
    return $objetoDocumento;
  }

  /**
   * display PDF file.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function imprimirPDF($id)
  {
    $solicitud = Solicitud::where('ratificada', true)->first();
    $idSolicitud = $solicitud->id;
    if ($solicitud) {
      if (isset($solicitud->expediente->audiencia->first()->id)) {
        $idAudiencia = $solicitud->expediente->audiencia->first()->id;
      }
      $idSolicitante = $solicitud->solicitantes->first()->id;
      $idSolicitado = $solicitud->solicitados->first()->id;
    }
    $html = $this->renderDocumento($idAudiencia, $idSolicitud, $id, $idSolicitante, $idSolicitado, null, null);
    return $this->renderPDF($html, $id);
    // $pdf = new Dompdf();
    // //  $pdf->set_option('defaultFont', 'Montserrat');
    // $pdf->loadHtml($html);
    // $pdf->setPaper('A4');
    // $pdf->render();
    // // return $pdf->stream('carta.pdf');
    // //  return $pdf->stream('carta.pdf');
    // $pdf->stream("carta.pdf", array("Attachment" => false));
    // exit(0);
    //  return $pdf->download('constancia.pdf');

    // $pdf = App::make('dompdf.wrapper');
  }

  /**
   * display PDF preview.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function previewDocumento(Request $request)
  {
    $idAudiencia = $request->audiencia_id;
    $idSolicitud = $request->solicitud_id;
    $plantilla_id = $request->plantilla_id;
    $idSolicitante = $request->solicitante_id;
    $idSolicitado = $request->citado_id;
    $resolucion_id = $request->resolucion_id;
    $conceptos_pago = $request->listaConceptos;
    $resolucion_pagos = $request->listaFechasPago;
    $resolucionesIndividuales = $request->listaRelacion;
    $descripcionPagos = $request->descripcion_pagos;

    $html = $this->renderDocumento($idAudiencia, $idSolicitud, $plantilla_id, $idSolicitante, $idSolicitado, $conceptos_pago, $resolucion_pagos, $resolucion_id, $resolucionesIndividuales, $descripcionPagos);
    // $html = file_get_contents(env('APP_URL').'/header/'.$plantilla_id) . $html . file_get_contents(env('APP_URL').'/footer/'.$plantilla_id);
    return $this->sendResponse($html, "Correcto");
    //return $this->renderPDF($html, $plantilla_id);
    //return $html;
  }

  /**
   * Genera el archivo PDF.
   * @param $html string HTML fuente para generar el PDF
   * @param $plantilla_id integer ID de la plantilla en la BD
   * @param $path string Ruta del archivo a guardar. Si no existe entonces regresa el PDF inline para mostrar en browser
   * @ToDo  Agregar opciones desde variable de ambiente como tamaño de página, margen, etc.
   * @return mixed
   */
  public function renderPDF($html, $plantilla_id, $path = null)
  {
    $pdf = App::make('snappy.pdf.wrapper');
    $pdf->loadHTML($html);
    $pdf->setOption('page-size', 'Letter')
      ->setOption('margin-top', '25mm')
      ->setOption('margin-bottom', '11mm')
      ->setOption('header-html', env('APP_URL') . '/header/' . $plantilla_id)
      ->setOption('footer-html', env('APP_URL') . '/footer/' . $plantilla_id);
    if ($path) {
      return $pdf->generateFromHtml($html, $path);
    }
    return $pdf->inline();
  }

  private function getDataModelos($idAudiencia, $idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado, $conceptos_pago = null, $resolucion_pagos = null, $resolucion_id = null, $resolucionesIndividuales = null, $descripcionPagos = null)
  {
    try {
      $plantilla = PlantillaDocumento::find($idPlantilla);
      $tipo_plantilla = TipoDocumento::find($plantilla->tipo_documento_id);
      $objetos = explode(",", $tipo_plantilla->objetos);
      $path = base_path('database/datafiles');
      $jsonElementos = json_decode(file_get_contents($path . "/elemento_documentos.json"), true);
      $idBase = "";
      $audienciaId = $idAudiencia;
      $data = [];
      $solicitud = "";
      $solicitudVirtual = "";
      $tipoSolicitud = "";
      foreach ($objetos as $objeto) {
        foreach ($jsonElementos['datos'] as $key => $element) {
          if ($element['id'] == $objeto) {
            $model_name = 'App\\' . $element['objeto'];
            $model = $element['objeto'];
            $model_name = 'App\\' . $model;
            if ($model == 'Solicitud') {
              $solicitud = $model_name::with('estatusSolicitud', 'objeto_solicitudes')->find($idSolicitud);
              $solicitudVirtual = isset($solicitud->virtual) ?? "";
              $tipoSolicitud = $solicitud->tipo_solicitud_id;
              // $solicitud = $model_name::with('estatusSolicitud','objeto_solicitudes')->first();
              $objeto = new JsonResponse($solicitud);
              $obj = json_decode($objeto->content(), true);
              $idBase = intval($obj['id']);
              if ($solicitud->resuelveOficinaCentral() && $idPlantilla != 6) {
                $centroId = Centro::where('central', true)->first()->id;
              } else {
                $centroId = intval($obj['centro_id']);
              }
              $obj = Arr::except($obj, ['id', 'updated_at', 'created_at', 'deleted_at']);
              $obj['tipo_solicitud'] =  mb_strtoupper(($obj['tipo_solicitud_id'] == 1) ? "Individual" : (($obj['tipo_solicitud_id'] == 2) ? "Patronal Individual" : (($obj['tipo_solicitud_id'] == 3) ? "Patronal Colectiva" : "Sindical")));
              $obj['prescripcion'] = $this->calcularPrescripcion($solicitud->objeto_solicitudes, $solicitud->fecha_conflicto, $solicitud->fecha_ratificacion);
              $obj['fecha_maxima_ratificacion'] = $this->calcularFechaMaximaRatificacion($solicitud->fecha_recepcion, $centroId);
              $data = ['solicitud' => $obj];
            } elseif ($model == 'Parte') {
              if ($idSolicitante != "" && $idSolicitado != "") {
                $partes = $model_name::with('nacionalidad', 'domicilios', 'lenguaIndigena', 'tipoDiscapacidad', 'documentos.clasificacionArchivo.entidad_emisora', 'contactos.tipo_contacto', 'tipoParte', 'compareciente', 'bitacoras_buzon')->where('solicitud_id', intval($idBase))->whereIn('id', [$idSolicitante, $idSolicitado])->get();
                // $partes = $model_name::with(['nacionalidad','domicilios','lenguaIndigena','tipoDiscapacidad','documentos.clasificacionArchivo.entidad_emisora','contactos.tipo_contacto','tipoParte','compareciente','bitacoras_buzon'=>function($q){$q->where('tipo_movimiento','Consulta');}])->where('solicitud_id',intval($idBase))->whereIn('id',[$idSolicitante,$idSolicitado])->get();
              } else if ($idSolicitante != "" && $idSolicitado == "") {
                $partes = $model_name::with('nacionalidad', 'domicilios', 'lenguaIndigena', 'tipoDiscapacidad', 'documentos.clasificacionArchivo.entidad_emisora', 'contactos.tipo_contacto', 'tipoParte', 'compareciente', 'bitacoras_buzon')->where('solicitud_id', intval($idBase))->whereRaw('(id=? or tipo_parte_id<>?)', [$idSolicitante, 1])->get();
              } else if ($idSolicitante == "" && $idSolicitado != "") {
                $partes = $model_name::with('nacionalidad', 'domicilios', 'lenguaIndigena', 'tipoDiscapacidad', 'documentos.clasificacionArchivo.entidad_emisora', 'contactos.tipo_contacto', 'tipoParte', 'compareciente', 'bitacoras_buzon')->where('solicitud_id', intval($idBase))->whereRaw('(id=? or tipo_parte_id=?)', [$idSolicitado, 1])->get();
              } else {
                $partes = $model_name::with('nacionalidad', 'domicilios', 'lenguaIndigena', 'tipoDiscapacidad', 'documentos.clasificacionArchivo.entidad_emisora', 'contactos.tipo_contacto', 'tipoParte', 'compareciente', 'bitacoras_buzon')->where('solicitud_id', intval($idBase))->get();
              }
              $objeto = new JsonResponse($partes);
              $obj = json_decode($objeto->content(), true);
              $parte2 = [];
              $parte1 = [];
              $countSolicitante = 0;
              $countSolicitado = 0;
              $nombresSolicitantes = [];
              $nombresSolicitados = [];
              $solicitantesNSS = [];
              $solicitantesRFC = [];
              $solicitantesCURP = [];
              $solicitantesIdentificaciones = [];
              $datoLaboral = "";
              $solicitanteIdentificacion = "";
              $firmasPartesQR = "";
              $nss = "";
              $curp = "";
              $rfc = "";
              // $partes = $model_name::with('nacionalidad','domicilios','lenguaIndigena','tipoDiscapacidad')->findOrFail(1);
              foreach ($obj as $key => $parte) {
                if (sizeof($parte['documentos']) > 0) {
                  foreach ($parte['documentos'] as $k => $docu) {
                    if ($docu['clasificacion_archivo']['tipo_archivo_id'] == 1) { //tipo identificacion
                      $parte['identificacion_documento'] = ($docu['clasificacion_archivo']['nombre'] != null) ? $docu['clasificacion_archivo']['nombre'] : "--";
                      $parte['identificacion_expedida_por'] = ($docu['clasificacion_archivo']['entidad_emisora']['nombre'] != null) ? $docu['clasificacion_archivo']['entidad_emisora']['nombre'] : "---";
                    }
                  }
                } else {
                  $parte['identificacion_documento'] = "---";
                  $parte['identificacion_expedida_por'] = "---";
                }

                $parteId = $parte['id'];
                $curp = $parte['curp'];
                //$rfc = $parte['rfc'];

                $parte['comparecio'] = Comparecientes::comparecio($idAudiencia, $parteId);

                $parte = Arr::except($parte, ['id', 'updated_at', 'created_at', 'deleted_at']);
                $parte['datos_laborales'] = $datoLaboral;
                if ($parte['tipo_persona_id'] == 1) { //fisica
                  $parte['nombre_completo'] = $parte['nombre'] . ' ' . $parte['primer_apellido'] . ' ' . $parte['segundo_apellido'];
                } else { //moral
                  $parte['nombre_completo'] = $parte['nombre_comercial'];
                }

                //$idAudiencia,$idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado,$idConciliador
                $tipoParte = ($parte['tipo_parte_id'] == 1) ? 'solicitante' : 'citado';

                if ($idAudiencia == "") {
                  $firmaDocumento = FirmaDocumento::where('firmable_id', $parteId)->where('plantilla_id', $idPlantilla)->where('solicitud_id', $idBase)->first();
                } else {
                  $firmaDocumento = FirmaDocumento::where('firmable_id', $parteId)->where('plantilla_id', $idPlantilla)->where('audiencia_id', $idAudiencia)->first();
                }
                if ($solicitudVirtual && $solicitudVirtual != "") {
                  if ($firmaDocumento && $firmaDocumento->firma != null) {
                    $parte['qr_firma'] = '<div style="text-align:center" class="qr"> <img style="max-height:80px" src="' . $firmaDocumento->firma . '" /></div>';
                  } elseif ($firmaDocumento && $firmaDocumento->firma != null && ($firmaDocumento->tipo_firma == 'llave-publica' || $firmaDocumento->tipo_firma == '')) {
                    $parte['qr_firma'] = '<div style="text-align:center" class="firma-llave-publica">Firma Digital: ' . $this->splitFirma($firmaDocumento->firma) . '</div>';
                  } else {
                    $parte['qr_firma'] = '<div style="text-align:center" class="qr">' . QrCode::errorCorrection('H')->size(100)->generate($parteId . "/" . $tipoParte . "/" . urlencode($parte['nombre_completo']) . "/" . $audienciaId . "/" . $idSolicitud . "/" . $idPlantilla . "//" . $idSolicitante . "/" . $idSolicitado . "/") . '</div>';
                  }
                  if ($parte['tipo_persona_id'] == 1 && count($parte['compareciente']) > 0) {
                    $siFirma = true;
                    if ($idPlantilla == 2 && $parte['tipo_parte_id'] != 1) {
                      $resolucionParteRepresentada = false;
                      if ($resolucionesIndividuales != null && sizeof($resolucionesIndividuales) > 0) {
                        foreach ($resolucionesIndividuales as $k => $resolucion) {
                          if ($resolucion['parte_solicitado_id'] == $parteId) {
                            $resolucionParteRepresentada = true;
                          }
                        }
                      } else {
                        $resolucionParteRepresentada = true;
                      }
                      if (!$resolucionParteRepresentada) {
                        $siFirma = false;
                      }
                    }
                    if ($siFirma) {
                      $firmasPartesQR .= '<p style="text-align: center;"><span style="font-size: 10pt;">' . $parte['qr_firma'] . ' </span></p>';
                      $firmasPartesQR .= '<p style="text-align: center;"><span style="font-size: 10pt;">_________________________________________</span></p>';
                      $firmasPartesQR .= '<p style="text-align: center;"><strong><span style="font-size: 10pt;">' . mb_strtoupper($parte['nombre_completo']) . '</span></strong></p>';
                      $firmasPartesQR .= '<p style="text-align: center;">&nbsp;</p>';
                    }
                  }
                } else {
                  $parte['qr_firma'] = "";
                }
                //domicilio de partes, excepto representante
                if ($parte['tipo_parte_id'] != 3) {
                  $dom_parte = $parte['domicilios'][0];
                  $tipo_vialidad =  ($dom_parte['tipo_vialidad'] !== null) ? $dom_parte['tipo_vialidad'] : "";
                  $vialidad =  ($dom_parte['vialidad'] !== null) ? $dom_parte['vialidad'] : "";
                  $num_ext =  ($dom_parte['num_ext'] !== null) ? "No. " . $dom_parte['num_ext'] : "";
                  $num_int =  ($dom_parte['num_int'] !== null) ? " Int. " . $dom_parte['num_int'] : "";
                  $num =  $num_int . $num_ext;
                  $municipio =  ($dom_parte['municipio'] !== null) ? $dom_parte['municipio'] : "";
                  $cp =  ($dom_parte['cp'] !== null) ? " CP. " . $dom_parte['cp'] : "";
                  $estado =  ($dom_parte['estado'] !== null) ? $dom_parte['estado'] : "";
                  $colonia =  ($dom_parte['asentamiento'] !== null) ? $dom_parte['tipo_asentamiento'] . " " . $dom_parte['asentamiento'] . " "  : "";
                  $parte['domicilios_completo'] = mb_strtoupper($tipo_vialidad . ' ' . $vialidad . ' ' . $num . ', ' . $colonia . ', ' . $municipio . ', ' . $estado . ', ' . $cp);
                }
                // if($parte['tipo_parte_id'] == 1 ){//Solicitante
                //datos laborales del solicitante
                $datoLaborales = DatoLaboral::with('jornada', 'ocupacion')->where('parte_id', $parteId)->get();
                $hayDatosLaborales = count($datoLaborales);
                if ($hayDatosLaborales > 1) {
                  $datoLaborales = $datoLaborales->where('resolucion', true)->first();
                } else {
                  $datoLaborales = $datoLaborales->first();
                }
                // $datoLaboral = DatoLaboral::with('jornada','ocupacion')->where('parte_id', $parteId)->get();
                if ($hayDatosLaborales > 0) {
                  $domicilioLaboral = Domicilio::where('domiciliable_id', $datoLaborales->id)->where('domiciliable_type', 'App\DatoLaboral')->first();
                  if ($domicilioLaboral != null) {
                    $parte['domicilios_laboral'] = mb_strtoupper($domicilioLaboral->tipo_vialidad . ' ' . $domicilioLaboral->vialidad . ' ' . $domicilioLaboral->num_ext . ', ' . $domicilioLaboral->asentamiento . ', ' . $domicilioLaboral->municipio . ', ' . $domicilioLaboral->estado);
                  } else { //obtener domicilio del patron
                    $tipoParteDom = ($parte['tipo_parte_id'] == 1) ? 2 : 1;
                    //si no se convino con todos y hay resoluciones 1-1
                    if ($resolucionesIndividuales != null && sizeof($resolucionesIndividuales) > 0) {
                      $contraparte = Parte::with('domicilios')->find($resolucionesIndividuales[0]['parte_solicitado_id']);
                      if ($contraparte->tipo_parte_id == 3) { //si es representante buscar parte
                        $contraparte = Parte::with('domicilios')->find($contraparte->parte_representada_id);
                      }
                    } else {
                      $contraparte = Parte::with('domicilios')->where('solicitud_id', $idBase)->where('tipo_parte_id', $tipoParteDom)->first();
                    }
                    $doms_parte = $contraparte->domicilios;
                    foreach ($doms_parte as $key => $dom_parte) {
                      $tipo_vialidad =  ($dom_parte->tipo_vialidad !== null) ? $dom_parte->tipo_vialidad : "";
                      $vialidad =  ($dom_parte->vialidad !== null) ? $dom_parte->vialidad : "";
                      $num_ext =  ($dom_parte->num_ext !== null) ? "No. " . $dom_parte->num_ext : "";
                      $num_int =  ($dom_parte->num_int !== null) ? " Int. " . $dom_parte->num_int : "";
                      $num =  $num_int . $num_ext;
                      $municipio =  ($dom_parte->municipio !== null) ? $dom_parte->municipio : "";
                      $estado =  ($dom_parte->estado !== null) ? $dom_parte->estado : "";
                      $colonia =  ($dom_parte->asentamiento !== null) ? $dom_parte->tipo_asentamiento . " " . $dom_parte->asentamiento . " "  : "";
                    }
                    $domicilioLaboral = mb_strtoupper($tipo_vialidad . ' ' . $vialidad . ' ' . $num . ', ' . $colonia . ', ' . $municipio . ', ' . $estado);
                    $parte['domicilios_laboral'] = $domicilioLaboral;
                  }

                  $salarioMensual = round((($datoLaborales->remuneracion / $datoLaborales->periodicidad->dias) * 30), 2);
                  $salarioMensual = number_format($salarioMensual, 2, '.', '');
                  $salario = explode('.', $salarioMensual);
                  $intSalarioMensual = $salario[0];
                  $decSalarioMensual = $salario[1];
                  $intSalarioMensualTextual = (new NumberFormatter("es", NumberFormatter::SPELLOUT))->format((float)$intSalarioMensual);
                  $intSalarioMensualTextual = str_replace("uno", "un", $intSalarioMensualTextual);
                  $salarioMensualTextual = $intSalarioMensualTextual . ' pesos ' . $decSalarioMensual . '/100';

                  $objeto = new JsonResponse($datoLaborales);
                  $datoLaboral = json_decode($objeto->content(), true);
                  $datoLaboral = Arr::except($datoLaboral, ['id', 'updated_at', 'created_at', 'deleted_at']);
                  $parte['datos_laborales'] = $datoLaboral;
                  $parte['datos_laborales_salario_mensual'] = $salarioMensual;
                  $parte['datos_laborales_salario_mensual_letra'] = $salarioMensualTextual;
                  $nss = $datoLaborales->nss;
                }
                $parte['identificacion_documento'] = (isset($parte['identificacion_documento'])) ? $parte['identificacion_documento'] : "";
                $parte['identificacion_expedida_por'] = (isset($parte['identificacion_expedida_por'])) ? $parte['identificacion_expedida_por'] : "";
                $solicitanteIdentificacion = $parte['nombre_completo'] . " quien se identifica con " . $parte['identificacion_documento'] . " expedida a su favor por " . $parte['identificacion_expedida_por'];
                //}elseif ($parte['tipo_parte_id'] == 2 ) {//Citado
                //representante legal de parte
                //$representanteLegal = Parte::with('documentos.clasificacionArchivo.entidad_emisora')->where('parte_representada_id', $parteId)->where('tipo_parte_id',3)->get();
                $representanteLegal = Parte::with('documentos.clasificacionArchivo.entidad_emisora', 'compareciente')->whereHas('compareciente', function ($q) use ($idAudiencia) {
                  $q->where('audiencia_id', $idAudiencia);
                })->where('parte_representada_id', $parteId)->where('tipo_parte_id', 3)->get();
                if (count($representanteLegal) > 0) {
                  //$comparecenciaAudiencia = $representanteLegal[0]->compareciente()->where('audiencia_id',$idAudiencia)->get();
                  $parte['asistencia'] = 'Si';
                  $objeto = new JsonResponse($representanteLegal);
                  $representanteLegal = json_decode($objeto->content(), true);
                  $representanteLegal = Arr::except($representanteLegal[0], ['id', 'updated_at', 'created_at', 'deleted_at']);
                  $representanteLegal['nombre_completo'] = $representanteLegal['nombre'] . ' ' . $representanteLegal['primer_apellido'] . ' ' . $representanteLegal['segundo_apellido'];
                  if (sizeof($representanteLegal['documentos']) > 0) {
                    foreach ($representanteLegal['documentos'] as $k => $docu) {
                      if ($docu['clasificacion_archivo']['tipo_archivo_id'] == 1) { //tipo identificacion
                        $representanteLegal['identificacion_documento'] = ($docu['clasificacion_archivo']['nombre'] != null) ? $docu['clasificacion_archivo']['nombre'] : "--";
                        $representanteLegal['identificacion_expedida_por'] = ($docu['clasificacion_archivo']['entidad_emisora']['nombre'] != null) ? $docu['clasificacion_archivo']['entidad_emisora']['nombre'] : "---";
                      }
                    }
                  } else {
                    $representanteLegal['identificacion_documento'] = "---";
                    $representanteLegal['identificacion_expedida_por'] = "---";
                  }
                  $parte['representante_legal'] = $representanteLegal;
                  $parte['nombre_compareciente'] = $representanteLegal['nombre_completo'] . ' C. REPRESENTANTE LEGAL DE ' . $parte['nombre_completo'];
                } else {
                  $countParteAsistencia = Compareciente::where('parte_id', $parteId)->where('audiencia_id', $audienciaId)->count();
                  $parte['asistencia'] =  ($countParteAsistencia > 0) ? 'Si' : 'No';
                  $parte['nombre_compareciente'] = $parte['nombre_completo'];
                }
                //tipoNotificacion solicitado
                if ($audienciaId != "") {
                  $audienciaParte = AudienciaParte::with('tipo_notificacion')->where('audiencia_id', $audienciaId)->where('parte_id', $parteId)->get();
                  if (count($audienciaParte) > 0) {
                    $parte['finalizado'] = isset($audienciaParte[0]->finalizado) ? (str_contains($audienciaParte[0]->finalizado, 'NO EXITOSO') ? 'No' : 'Si') : 'No';
                    $parte['notificacion_exitosa'] = $parte['finalizado'];
                    $parte['tipo_notificacion'] = $audienciaParte[0]->tipo_notificacion_id;
                    $parte['fecha_notificacion'] = $audienciaParte[0]->fecha_notificacion;
                    $parte['fecha_confirmacion_audiencia'] = $audienciaParte[0]->created_at;
                  } else {
                    $parte['finalizado'] = isset($audienciaParte[0]->finalizado) ? (str_contains($audienciaParte[0]->finalizado, 'NO EXITOSO') ? 'No' : 'Si') : 'No';
                    $parte['notificacion_exitosa'] = $parte['finalizado'];
                    $parte['tipo_notificacion'] = null;
                    $parte['fecha_notificacion'] = "";
                    $parte['fecha_confirmacion_audiencia'] = "";
                  }
                }
                $tablaConsultaBuzon = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                if (sizeof($parte['bitacoras_buzon']) > 0) {
                  $tablaConsultaBuzon .= '<table class="tbl">';
                  $tablaConsultaBuzon .= '<tbody>';
                  foreach ($parte['bitacoras_buzon'] as $k => $bitacora) {
                    $tablaConsultaBuzon .= '<tr><td class="tbl"> ' . Carbon::parse($bitacora['created_at'])->format('d/m/Y h:i') . ' </td><td>' . $bitacora['descripcion'] . '</td></tr>';
                  }
                  $tablaConsultaBuzon .= '</tbody>';
                  $tablaConsultaBuzon .= '</table>';
                } else {
                  $tablaConsultaBuzon .= 'No hay registros en la bitácora';
                }
                $parte['bitacora_consulta_buzon'] = $tablaConsultaBuzon;

                if ($parte['tipo_parte_id'] == 1) { //Solicitante
                  array_push($parte1, $parte);
                  array_push($nombresSolicitantes, $parte['nombre_completo']);
                  array_push($solicitantesIdentificaciones, $solicitanteIdentificacion);
                  //array_push($solicitantesNSS, $nss);
                  //if ($curp!= "" && $curp!= null ){
                  //array_push($solicitantesCURP, $curp);
                  //}
                  $countSolicitante += 1;
                }
                if ($parte['tipo_parte_id'] == 2) { //Citado
                  $countSolicitado += 1;
                  array_push($nombresSolicitados, $parte['nombre_completo']);
                  array_push($parte2, $parte);
                }
              }

              $partesGral = Parte::where('solicitud_id', intval($idBase))->get();
              $countSolicitado = 0;
              $countSolicitante = 0;
              $nombresSolicitantes = [];
              $nombresSolicitados = [];
              $nombresSolicitantesConfirmaron = [];
              foreach ($partesGral as $parteGral) {
                if ($parteGral->tipo_persona_id == 1) { //fisica
                  if (count($parteGral->dato_laboral) > 0) {
                    foreach ($parteGral->dato_laboral as $dato_laboral) {
                      $nss = $dato_laboral->nss;
                    }
                  }
                  $nombre_completo = $parteGral->nombre . ' ' . $parteGral->primer_apellido . ' ' . $parteGral->segundo_apellido;
                } else { //moral
                  $nombre_completo = $parteGral->nombre_comercial;
                }
                if ($parteGral->tipo_parte_id == 1) { //Solicitante
                  array_push($nombresSolicitantes, $nombre_completo);
                  $countSolicitante += 1;
                  if ($parteGral->ratifico) { //Si el solicitante confirmo su solicitud
                    array_push($nombresSolicitantesConfirmaron, $nombre_completo);
                    array_push($solicitantesNSS, $nss);
                    array_push($solicitantesCURP, $parteGral->curp);
                  }
                } else if ($parteGral->tipo_parte_id == 2) { //Citado
                  array_push($nombresSolicitados, $nombre_completo);
                  $countSolicitado += 1;
                } else { //representante
                }
              }
              $data = Arr::add($data, 'solicitante', $parte1);
              $data = Arr::add($data, 'solicitado', $parte2);
              $data = Arr::add($data, 'total_solicitantes', $countSolicitante);
              $data = Arr::add($data, 'total_solicitados', $countSolicitado);
              $data = Arr::add($data, 'nombres_solicitantes', implode(", ", $nombresSolicitantes));
              $data = Arr::add($data, 'nombres_solicitados', implode(", ", $nombresSolicitados));
              $data = Arr::add($data, 'nombres_solicitantes_confirmados', implode(", ", $nombresSolicitantesConfirmaron));
              $data = Arr::add($data, 'nss_solicitantes', implode(", ", $solicitantesNSS));
              $data = Arr::add($data, 'curp_solicitantes', implode(", ", $solicitantesCURP));
              $data = Arr::add($data, 'solicitantes_identificaciones', implode(", ", $solicitantesIdentificaciones));
              $data = Arr::add($data, 'firmas_partes_qr', $firmasPartesQR);
              $data = Arr::add($data, 'bitacora_consulta_buzon', $tablaConsultaBuzon);
            } elseif ($model == 'Expediente') {
              $expediente = Expediente::where('solicitud_id', $idBase)->get();
              $expedienteId = $expediente[0]->id;
              $objeto = new JsonResponse($expediente);
              $expediente = json_decode($objeto->content(), true);
              $expediente = Arr::except($expediente[0], ['id', 'updated_at', 'created_at', 'deleted_at']);
              $data = Arr::add($data, 'expediente', $expediente);
            } elseif ($model == 'Audiencia') {
              if ($solicitud != "" && $solicitud->estatus_solicitud_id != 1) {
                $expediente = Expediente::where('solicitud_id', $idBase)->get();
                $expedienteId = $expediente[0]->id;

                $objeto = new JsonResponse($expediente);
                $expediente = json_decode($objeto->content(), true);
                $expediente = Arr::except($expediente[0], ['id', 'updated_at', 'created_at', 'deleted_at']);
                $data = Arr::add($data, 'expediente', $expediente);
                // $objeto = $model_name::with('conciliador')->findOrFail(1);
                //$audiencias = $model_name::where('expediente_id',$expedienteId)->get();
                $audiencias = $model_name::where('id', $audienciaId)->get();
                $conciliadorId = $audiencias[0]->conciliador_id;
                $objeto = new JsonResponse($audiencias);
                $audiencias = json_decode($objeto->content(), true);
                $Audiencias = [];
                foreach ($audiencias as $audiencia) {
                  if ($audienciaId == "") {
                    $audienciaId = $audiencia['id'];
                  }
                  $resolucionAudienciaId = $audiencia['resolucion_id'];
                  $audiencia = Arr::except($audiencia, ['id', 'updated_at', 'created_at', 'deleted_at']);
                  array_push($Audiencias, $audiencia);
                }

                $data = Arr::add($data, 'audiencia', $Audiencias);
                $salaAudiencia = SalaAudiencia::with('sala')->where('audiencia_id', $audienciaId)->get();
                $objSala = new JsonResponse($salaAudiencia);
                $salaAudiencia = json_decode($objSala->content(), true);
                $salas = [];
                foreach ($salaAudiencia as $sala) {
                  $sala['nombre'] = $sala['sala']['sala'];
                  $sala = Arr::except($sala, ['id', 'updated_at', 'created_at', 'deleted_at', 'sala']);
                  array_push($salas, $sala);
                }

                //Se agrega para cuando la sala viene vacía 29-01-2024
                if (empty($salas)) {
                  $sala['nombre'] = " - ";
                  $sala = Arr::except($sala, ['id', 'updated_at', 'created_at', 'deleted_at', 'sala']);
                  array_push($salas, $sala);
                }

                $data = Arr::add($data, 'sala', $salas);
              }
            } elseif ($model == 'Conciliador') {
              $objeto = $model_name::with('persona')->find($conciliadorId);
              $objeto = new JsonResponse($objeto);
              $conciliador = json_decode($objeto->content(), true);
              $conciliador = Arr::except($conciliador, ['id', 'updated_at', 'created_at', 'deleted_at']);
              $conciliador['persona'] = Arr::except($conciliador['persona'], ['id', 'updated_at', 'created_at', 'deleted_at']);
              $nombreConciliador = $conciliador['persona']['nombre'] . " " . $conciliador['persona']['primer_apellido'] . " " . $conciliador['persona']['segundo_apellido'];
              $firmaDocumento = FirmaDocumento::where('firmable_id', $conciliadorId)->where('plantilla_id', $idPlantilla)->where('audiencia_id', $idAudiencia)->first();
              if ($firmaDocumento != null) {
                $conciliador['qr_firma'] = '<div style="text-align:center" class="qr"> <img style="max-height:80px" src="' . $firmaDocumento->firma . '" /></div>';
              } else {
                $conciliador['qr_firma'] = '<div style="text-align:center" class="qr">' . QrCode::size(100)->generate($conciliadorId . "|conciliador|" . $nombreConciliador . "|" . $audienciaId . "|" . $idSolicitud . "|" . $idPlantilla . "|" . $idSolicitante . "|" . $idSolicitado) . '</div>';
              }
              $data = Arr::add($data, 'conciliador', $conciliador);
            } elseif ($model == 'Centro') {
              $objeto = $model_name::with('domicilio', 'disponibilidades', 'contactos')->find($centroId);
              $dom_centro = $objeto->domicilio;
              //$usuarios_centro = $objeto->user;
              $usuarios_centro = $objeto->user()->role('Administrador del centro')->orderBy('id', 'desc')->first();
              $contacto_centro = $objeto->contactos;
              $disponibilidad_centro = $objeto->disponibilidades;
              $centro['autoridad_ejecuta_multa'] = $objeto->autoridad_ejecuta_multa ?? '';
              $objeto = new JsonResponse($objeto);
              $centro = json_decode($objeto->content(), true);
              $centro = Arr::except($centro, ['id', 'updated_at', 'created_at', 'deleted_at']);
              $dom_centro = new JsonResponse($dom_centro);
              $dom_centro = json_decode($dom_centro->content(), true);
              $centro['domicilio'] = Arr::except($dom_centro, ['id', 'updated_at', 'created_at', 'deleted_at', 'domiciliable_id', 'domiciliable_type']);

              $tipo_vialidad =  ($dom_centro['tipo_vialidad'] !== null) ? $dom_centro['tipo_vialidad'] : "";
              $vialidad =  ($dom_centro['vialidad'] !== null) ? $dom_centro['tipo_vialidad'] . " " . $dom_centro['vialidad'] : "";
              $num_ext =  ($dom_centro['num_ext'] !== null) ? " No. " . $dom_centro['num_ext'] : "";
              $num_int =  ($dom_centro['num_int'] !== null) ? " Int. " . $dom_centro['num_int'] : "";
              $num = $num_ext . $num_int;
              $colonia =  ($dom_centro['asentamiento'] !== null) ? $dom_centro['tipo_asentamiento'] . " " . $dom_centro['asentamiento'] . " "  : "";
              $municipio =  ($dom_centro['municipio'] !== null) ? $colonia . $dom_centro['municipio'] : "";
              $estado =  ($dom_centro['estado'] !== null) ? $dom_centro['estado'] : "";
              $centro['domicilio_completo'] = mb_strtoupper($tipo_vialidad . ' ' . $vialidad . $num . ', ' . $colonia . ', ' . $municipio . ', ' . $estado);
              $contacto_centro = new JsonResponse($contacto_centro);
              $contacto_centro = json_decode($contacto_centro->content(), true);
              foreach ($contacto_centro as $contacto) {
                if ($contacto['tipo_contacto_id'] == 1 || $contacto['tipo_contacto_id'] == 2) {
                  $centro['telefono'] = $contacto['contacto'];
                } else {
                  $centro['telefono'] = '--- -- -- ---';
                }
              }
              $nombreAdministrador = "";
              $userAdmin = null;
              if (isset($usuarios_centro->id)) {
                $userAdmin = $usuarios_centro->persona;
                $nombreAdministrador = $userAdmin['nombre'] . ' ' . $userAdmin['primer_apellido'] . ' ' . $userAdmin['segundo_apellido'];
              }

              $centro['nombre_administrador'] = $nombreAdministrador;
              //Disponibilidad del centro horarios y dias
              $disponibilidad_centro = new JsonResponse($disponibilidad_centro);
              $disponibilidad_centro = json_decode($disponibilidad_centro->content(), true);
              $centro['hora_inicio'] = $this->formatoFecha($disponibilidad_centro[0]['hora_inicio'], 3);
              $centro['hora_fin'] = $this->formatoFecha($disponibilidad_centro[0]['hora_fin'], 3);
              $data = Arr::add($data, 'centro', $centro);
            } elseif ($model == 'Resolucion') {
              //$objetoResolucion = $model_name::find($resolucionAudienciaId);
              $datosResolucion = [];
              $etapas_resolucion = EtapaResolucionAudiencia::where('audiencia_id', $audienciaId)->whereIn('etapa_resolucion_id', [3, 4, 5, 6])->get();
              $objeto = new JsonResponse($etapas_resolucion);
              $etapas_resolucion = json_decode($objeto->content(), true);
              //$datosResolucion['resolucion']= $objetoResolucion->nombre;
              $audiencia_partes = Audiencia::find($audienciaId)->audienciaParte;
              //$resoluciones_partes = ResolucionPartes::where('audiencia_id',$audienciaId)->get();
              foreach ($etapas_resolucion as $asd => $etapa) {
                if ($etapa['etapa_resolucion_id'] == 3) {
                  $datosResolucion['primera_manifestacion'] = $etapa['evidencia'];
                } else if ($etapa['etapa_resolucion_id'] == 4) {
                  $datosResolucion['justificacion_propuesta'] = $etapa['evidencia'];
                  $tablaConceptos = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                  $tablaConceptosConvenio = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                  $tablaRetencionesConvenio = '';
                  $tablaConceptosActa = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                  $totalPercepciones = 0;
                  $parteID = "";
                  $totalPagosDiferidos = 0;
                  $tablaPagosDiferidos = '<style> .tbl, .tbl th, .tbl td {border: .5px dotted black; border-collapse: collapse; padding:3px;} .amount{ text-align:right} </style>';
                  $hayConceptosPago = false;
                  $resumenPagos = "";
                  $infoPago  = "";
                  foreach ($audiencia_partes as $key => $audiencia_parte) {
                    if ($audiencia_parte->parte->tipo_parte_id != 3) {
                      $parteID = $audiencia_parte->parte->id;
                      //datos laborales del solicitante
                      $datoLaborales = DatoLaboral::with('jornada', 'ocupacion')->where('parte_id', $parteID)->get();
                      $hayDatosLaborales = count($datoLaborales);
                      if ($hayDatosLaborales > 1) {
                        $datoLaborales = $datoLaborales->where('resolucion', true)->first();
                      } else {
                        $datoLaborales = $datoLaborales->first();
                      }
                      // $datoLaboral = DatoLaboral::with('jornada','ocupacion')->where('parte_id', $parteId)->get();
                      if ($hayDatosLaborales > 0) {
                        // $diasPeriodicidad = Periodicidad::where('id', $datoLaborales->periodicidad_id)->first();
                        // $remuneracionDiaria = $datoLaborales->remuneracion / $diasPeriodicidad->dias;
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
                          if ($vacaciones->anios_laborados >= $anios_antiguedad) {
                            $diasVacaciones = $vacaciones->dias_vacaciones;
                            break;
                          }
                        }
                        $pagoVacaciones = $propVacaciones * $diasVacaciones * $remuneracionDiaria;
                        $salarioTopado = ($remuneracionDiaria > (2 * $salarioMinimo) ? (2 * $salarioMinimo) : $remuneracionDiaria);

                        //Propuesta de convenio al 100% y 50%
                        $prouestas = [];
                        array_push($prouestas, array("concepto_pago" => 'Indemnización constitucional', "montoCompleta" => round($remuneracionDiaria * 90, 2), "montoAl50" => round($remuneracionDiaria * 45, 2))); //Indemnizacion constitucional = gratificacion A
                        array_push($prouestas, array("concepto_pago" => 'Aguinaldo', "montoCompleta" => round($remuneracionDiaria * 15 * $propAguinaldo, 2),  "montoAl50" => round($remuneracionDiaria * 15 * $propAguinaldo, 2))); //Aguinaldo = dias de aguinaldo
                        array_push($prouestas, array("concepto_pago" => 'Vacaciones', "montoCompleta" => round($pagoVacaciones, 2), "montoAl50" => round($pagoVacaciones, 2))); //Vacaciones = dias vacaciones
                        array_push($prouestas, array("concepto_pago" => 'Prima vacacional', "montoCompleta" => round($pagoVacaciones * 0.25, 2), "montoAl50" => round($pagoVacaciones * 0.25, 2))); //Prima Vacacional
                        array_push($prouestas, array("concepto_pago" => 'Prima antigüedad', "montoCompleta" => round($salarioTopado * $anios_antiguedad * 12, 2), "montoAl50" => round($salarioTopado * $anios_antiguedad * 6, 2))); //Prima antiguedad = gratificacion C
                        // $tablaConceptos = '<h4>Propuestas</h4>';
                        $tablaConceptos .= '<table  class="tbl">';
                        $tablaConceptos .= '<thead><tr><th>Prestación</th><th>Propuesta completa</th><th>Propuesta 45 días</th></tr></thead>';
                        $tablaConceptos .= '<tbody >';
                        $total50 = 0;
                        $total100 = 0;
                        foreach ($prouestas as $concepto) {
                          $tablaConceptos .= '<tr><td class="tbl">' . $concepto['concepto_pago'] . '</td><td class="amount"> $' . $concepto['montoCompleta'] . '</td><td class="amount"> $' . $concepto['montoAl50'] . '</td> </tr>';
                          $total100 += floatval($concepto['montoCompleta']);
                          $total50 += floatval($concepto['montoAl50']);
                        }
                        $tablaConceptos .= '<tr ><th class="tbl"> TOTAL </th><td class="amount"> $' . $total100 . '</td><td class="amount"> $' . $total50 . '</td> </tr>';
                        $tablaConceptos .= '</tbody>';
                        $tablaConceptos .= '</table>';

                        //Conceptos resolucion
                        if (sizeof($conceptos_pago) > 0) {
                          if (array_key_exists($parteID, $conceptos_pago)) {
                            $resolucion_conceptos = $conceptos_pago[$parteID];
                          } else {
                            $resolucion_conceptos = array();
                          }
                        } else {
                          $resolucion_conceptos = ResolucionParteConcepto::where('audiencia_parte_id', $audiencia_parte->id)->get();
                        }
                        $tablaConceptosEConvenio = '';
                        $tablaConceptosRConvenio = '';
                        $tablaConceptosEActa = '';
                        $tablaConceptosConvenio .= '<table class="tbl">';
                        $tablaConceptosConvenio .= '<tbody>';
                        // $tablaConceptosConvenio .= '<tr><td colspan="2" style="text-align: center;font-weight:bold;"> PAGOS </td></tr>';
                        $tablaRetencionesConvenio = '<tr><td colspan="2" style="text-align: center;font-weight:bold;"> RETENCIONES </td></tr>';
                        $tablaConceptosActa .= '';
                        $hayRetenciones = false;
                        $hayConceptosPago = false;
                        $conceptosEspecie = [];
                        $conceptosDerechos = [];
                        $parte = Parte::find($parteID);
                        if (sizeof($parte->compareciente) > 0) {
                          $nombreParte = $parte['nombre'] . ' ' . $parte['primer_apellido'] . ' ' . $parte['segundo_apellido'];
                          $tablaConceptosActa .= ' Propuesta para ' . $nombreParte;
                          $tablaConceptosActa .= '<table class="tbl">';
                          $tablaConceptosActa .= '<tbody>';
                          $tablaRetencionesActa = '<tr><td colspan="2" style="text-align: center;font-weight:bold;"> RETENCIONES </td></tr>';
                        }
                        $totalPercepciones = 0;
                        $totalDeducciones = 0;
                        foreach ($resolucion_conceptos as $concepto) {
                          if (!is_array($concepto)) {
                            $concepto = collect($concepto)->toArray();
                          }
                          //foreach ($conceptos as $concepto ) {
                          $conceptoName = ConceptoPagoResolucion::select('nombre')->find($concepto['concepto_pago_resoluciones_id']);
                          if ($concepto['concepto_pago_resoluciones_id'] != 9 && $concepto['concepto_pago_resoluciones_id'] != 11) { //no pago en especie ni reconocimiento
                            if ($concepto['concepto_pago_resoluciones_id'] == 12 || $concepto['concepto_pago_resoluciones_id'] == 13) { //otro pago o deduccion
                              $conceptoName->nombre = $concepto['otro'];
                              if ($concepto['concepto_pago_resoluciones_id'] == 13) {
                                $totalDeducciones += ($concepto['monto'] != null) ? floatval($concepto['monto']) : 0;
                              } else {
                                $totalPercepciones += ($concepto['monto'] != null) ? floatval($concepto['monto']) : 0;
                              }
                            } else {
                              $totalPercepciones += ($concepto['monto'] != null) ? floatval($concepto['monto']) : 0;
                            }
                            if ($tipoSolicitud == 1) { //solicitud individual
                              if ($parteID == $idSolicitante && $parteID == $concepto['idSolicitante']) { //si resolucion pertenece al solicitante
                                if ($concepto['concepto_pago_resoluciones_id'] == 13) {
                                  $tablaRetencionesConvenio .= '<tr><td class="tbl"> ' . $conceptoName->nombre . ' </td><td style="text-align:right;">     $' . number_format($concepto['monto'], 2, '.', ',') . '</td></tr>';
                                  $hayRetenciones = true;
                                } else {
                                  $tablaConceptosConvenio .= '<tr><td class="tbl"> ' . $conceptoName->nombre . ' </td><td style="text-align:right;">     $' . number_format($concepto['monto'], 2, '.', ',') . '</td></tr>';
                                  $hayConceptosPago = true;
                                }
                              }
                            } else {
                              if ($parteID == $idSolicitado && $parteID == $concepto['idSolicitante']) { //si resolucion pertenece al solicitante
                                if ($concepto['concepto_pago_resoluciones_id'] == 13) {
                                  $tablaRetencionesConvenio .= '<tr><td class="tbl"> ' . $conceptoName->nombre . ' </td><td style="text-align:right;">     $' . number_format($concepto['monto'], 2, '.', ',') . '</td></tr>';
                                  $hayRetenciones = true;
                                } else {
                                  $tablaConceptosConvenio .= '<tr><td class="tbl"> ' . $conceptoName->nombre . ' </td><td style="text-align:right;">     $' . number_format($concepto['monto'], 2, '.', ',') . '</td></tr>';
                                  $hayConceptosPago = true;
                                }
                              }
                            }
                            if ($concepto['concepto_pago_resoluciones_id'] == 13) {
                              $tablaRetencionesActa .= '<tr><td class="tbl"> ' . $conceptoName->nombre . ' </td><td style="text-align:right;">     $' . number_format($concepto['monto'], 2, '.', ',') . '</td></tr>';
                              $hayRetenciones = true;
                            } else {
                              $tablaConceptosActa .= '<tr><td class="tbl"> ' . $conceptoName->nombre . ' </td><td style="text-align:right;">     $' . number_format($concepto['monto'], 2, '.', ',') . '</td></tr>';
                            }
                          } else { // 9 y 11
                            if ($tipoSolicitud == 1) { //solicitud individual
                              if ($parteID == $idSolicitante && $parteID == $concepto['idSolicitante']) { //si resolucion pertenece al solicitante
                                if ($concepto['concepto_pago_resoluciones_id'] == 9) {
                                  array_push($conceptosEspecie, $concepto['otro']);
                                } else { //11
                                  $tablaConceptosRConvenio = $concepto['otro'];
                                }
                                // $hayConceptosPago = ($concepto['concepto_pago_resoluciones_id'] == 9) ? true : $hayConceptosPago;
                              }
                            } else {
                              if ($parteID == $idSolicitado && $parteID == $concepto['idSolicitante']) { //si resolucion pertenece al solicitado
                                if ($concepto['concepto_pago_resoluciones_id'] == 9) {
                                  array_push($conceptosEspecie, $concepto['otro']);
                                } else { //11
                                  $tablaConceptosRConvenio = $concepto['otro'];
                                }
                                // $hayConceptosPago = ($concepto['concepto_pago_resoluciones_id'] == 9) ? true : $hayConceptosPago;
                              }
                            }
                            ($concepto['concepto_pago_resoluciones_id'] == 9 && $idPlantilla == 3) ? array_push($conceptosEspecie, $concepto['otro']) : array_push($conceptosDerechos, $concepto['otro']);
                            //$tablaConceptosEActa .= $concepto['otro'].' ';
                          }
                        }
                        $tablaConceptosEActa .= implode(", ", $conceptosEspecie);
                        $totalPercepciones = $totalPercepciones - $totalDeducciones;
                        if ($tipoSolicitud == 1) {
                          $tablaConceptosConvenio .= ($parteID == $idSolicitante && $hayRetenciones) ? $tablaRetencionesConvenio : "";
                          $tablaConceptosConvenio .= ($parteID == $idSolicitante) ? '<tr><td style="font-weight:bold;"> Total de percepciones </td><td>     $' . number_format($totalPercepciones, 2, '.', ',') . '</td></tr>' : "";
                        } else {
                          $tablaConceptosConvenio .= ($parteID == $idSolicitado && $hayRetenciones) ? $tablaRetencionesConvenio : "";
                          $tablaConceptosConvenio .= ($parteID == $idSolicitado) ? '<tr><td> Total de percepciones </td><td>     $' . number_format($totalPercepciones, 2, '.', ',') . '</td></tr>' : "";
                        }
                        $tablaConceptosConvenio .= '</tbody>';
                        $tablaConceptosConvenio .= '</table>';
                        if ($tipoSolicitud == 1) {
                          if ($parteID == $idSolicitante && $parteID == $concepto['idSolicitante']) { //si resolucion pertenece al solicitante
                            $tablaConceptosConvenio .= $tablaConceptosRConvenio;
                            $tablaConceptosEConvenio = implode(", ", $conceptosEspecie);
                            $tablaConceptosConvenio .= ($tablaConceptosEConvenio != '') ? '<p>Adicionalmente las partes acordaron que la parte&nbsp;<b> EMPLEADORA</b> entregar&aacute; a la parte <b>TRABAJADORA</b> ' . $tablaConceptosEConvenio . '.</p>' : " ";
                          }
                        } else {
                          if ($parteID == $idSolicitado && $parteID == $concepto['idSolicitante']) { //si resolucion pertenece al solicitante
                            $tablaConceptosConvenio .= $tablaConceptosRConvenio;
                            $tablaConceptosEConvenio = implode(", ", $conceptosEspecie);
                            $tablaConceptosConvenio .= ($tablaConceptosEConvenio != '') ? '<p>Adicionalmente las partes acordaron que la parte&nbsp;<b> EMPLEADORA</b> entregar&aacute; a la parte <b>TRABAJADORA</b> ' . $tablaConceptosEConvenio . '.</p>' : " ";
                          }
                        }
                        if (sizeof($parte->compareciente) > 0) {
                          $tablaConceptosActa .= ($hayRetenciones) ? $tablaRetencionesActa : "";
                          $tablaConceptosActa .= '<tr><td> Total de percepciones </td><td>     $' . number_format($totalPercepciones, 2, '.', ',') . '</td></tr>';
                          $tablaConceptosActa .= '</tbody>';
                          $tablaConceptosActa .= '</table>';
                          $tablaConceptosActa .= implode(", ", $conceptosDerechos);
                          $tablaConceptosActa .= ($tablaConceptosEActa != '') ? '<p>Adicionalmente las partes acordaron que la parte&nbsp;<b> EMPLEADORA</b> entregar&aacute; a la parte <b>TRABAJADORA</b> ' . $nombreParte . ' ' . $tablaConceptosEActa . '.</p>' : '';
                          $tablaConceptosActa .= '<br>';
                        }
                        // $salarioMensual = round( (($datoLaborales->remuneracion / $datoLaborales->periodicidad->dias)*30),2);
                        $totalPercepciones = number_format($totalPercepciones, 2, '.', '');
                        $totalPercepcion = explode('.', $totalPercepciones);
                        $intTotalPercepciones = $totalPercepcion[0];
                        $decTotalPercepciones = $totalPercepcion[1];
                        $intTotalPercepciones = (new NumberFormatter("es", NumberFormatter::SPELLOUT))->format((float)$intTotalPercepciones);
                        $intTotalPercepciones = str_replace("uno", "un", $intTotalPercepciones);
                        $cantidadTextual = $intTotalPercepciones . ' pesos ' . $decTotalPercepciones . '/100';
                        if ($tipoSolicitud == 1) {
                          if ($parteID == $idSolicitante) {
                            $datosResolucion['total_percepciones'] = number_format($totalPercepciones, 2, '.', ','); //$totalPercepciones;
                            $datosResolucion['total_percepciones_letra'] = $cantidadTextual;
                            $datosResolucion['pagos'] = $hayConceptosPago;
                          }
                        } else {
                          if ($parteID == $idSolicitado) {
                            $datosResolucion['total_percepciones'] = number_format($totalPercepciones, 2, '.', ','); //$totalPercepciones;
                            $datosResolucion['total_percepciones_letra'] = $cantidadTextual;
                            $datosResolucion['pagos'] = $hayConceptosPago;
                          }
                        }
                      }
                      //Fechas pago resolucion
                      $tablaPagosDiferidos .= '<table class="tbl">';
                      $tablaPagosDiferidos .= '<tbody>';
                      if ($resolucion_pagos && sizeof($resolucion_pagos) > 0) {
                        $resolucion_pagos = $resolucion_pagos;
                      } else {
                        $resolucion_pagos = ResolucionPagoDiferido::where('audiencia_id', $audienciaId)->orderBy('id')->get();
                      }
                      foreach ($resolucion_pagos as $pago) {
                        if ($tipoSolicitud == 1) {
                          if (($parteID == $pago['idSolicitante']) && ($parteID == $idSolicitante)) {
                            // if($pago['diferido']){
                            $enPago = ($pago['monto_pago'] != null) ? '   $' . number_format($pago['monto_pago'], 2, '.', ',') : "";
                            //$tablaPagosDiferidos .= '<tr><td class="tbl"> '.$pago['fecha_pago'].' horas </td><td style="text-align:right;">     $'.number_format($pago['monto_pago'], 2, '.', ',').'</td></tr>';
                            $tablaPagosDiferidos .= '<tr><td class="tbl"> ' . $pago['fecha_pago'] . ' horas </td><td>' . $pago['descripcion_pago'] . '</td><td style="text-align:right;"> ' . $enPago . '</td></tr>';
                            $totalPagosDiferidos += 1;
                            // }

                            if ($pago['pagado'] == true) {
                              $resumenPagos .= " " . $pago['informacion_pago'] . " <br>";
                              $infoPago = $pago['informacion_pago'];
                              //$fechaCumplimientoPago = Carbon::createFromFormat('Y-m-d H:i:s',$pago['fecha_cumplimiento'])->format('d/m/Y');
                            }
                          }
                        } else {
                          if (($parteID == $pago['idCitado']) && ($parteID == $idSolicitado)) {
                            // if($pago['diferido']){
                            $enPago = ($pago['monto_pago'] != null) ? '   $' . number_format($pago['monto_pago'], 2, '.', ',') : "";
                            $tablaPagosDiferidos .= '<tr><td class="tbl"> ' . $pago['fecha_pago'] . ' horas </td><td>' . $pago['descripcion_pago'] . '</td><td style="text-align:right;">    ' . $enPago . '</td></tr>';
                            $totalPagosDiferidos += 1;
                            // }
                            if ($pago['pagado'] == true) {
                              $resumenPagos .= " " . $pago['informacion_pago'] . " <br>";
                              $infoPago = $pago['informacion_pago'];
                              //$fechaCumplimientoPago = Carbon::createFromFormat('Y-m-d H:i:s',$pago['fecha_cumplimiento'])->format('d/m/Y');
                            }
                          }
                        }
                      }
                      $tablaPagosDiferidos .= '</tbody>';
                      $tablaPagosDiferidos .= '</table>';

                      $datosResolucion['total_diferidos'] = $totalPagosDiferidos;
                      $datosResolucion['pagos_diferidos'] = $tablaPagosDiferidos;
                      $datosResolucion['resumen_pagos'] = $resumenPagos;
                      $datosResolucion['informacion_pago'] = $infoPago;
                    }
                  }
                  // $cantidadTextual = (new NumberFormatter("es", NumberFormatter::SPELLOUT))->format((float)$totalPercepciones);
                  // $cantidadTextual = str_replace("uno","un",$cantidadTextual);
                  // $cantidadTextual = str_replace("coma","punto",$cantidadTextual);
                  $datosResolucion['propuestas_conceptos'] = $tablaConceptos;
                  $datosResolucion['propuestas_trabajadores'] = $tablaConceptosActa;
                  $datosResolucion['propuesta_configurada'] = $tablaConceptosConvenio;
                  $datosResolucion['propuestas_acta'] = $tablaConceptosActa;
                } else if ($etapa['etapa_resolucion_id'] == 5) {
                  $datosResolucion['segunda_manifestacion'] = $etapa['evidencia'];
                } else if ($etapa['etapa_resolucion_id'] == 6) {
                  $datosResolucion['descripcion_pagos'] = $etapa['evidencia'];
                }
                // //Fechas pago resolucion
                // $tablaPagosDiferidos = '<table class="tbl">';
                // $tablaPagosDiferidos .= '<tbody>';
                // if($resolucion_pagos && sizeof($resolucion_pagos)>0){
                //   $resolucion_pagos = $resolucion_pagos;
                // }else{
                //   $resolucion_pagos = ResolucionPagoDiferido::where('audiencia_id',$audienciaId)->get();
                // }
                // $totalPagosDiferidos=0;
                // foreach ($resolucion_pagos as $pago ) {
                //   if($parteID == $pago['idSolicitante']){
                //     $tablaPagosDiferidos .= '<tr><td class="tbl"> '.$pago['fecha_pago'].' horas </td><td style="text-align:right;">     $'.number_format($pago['monto_pago'], 2, '.', ',').'</td></tr>';
                //     $totalPagosDiferidos +=1;
                //   }
                // }
                // $tablaPagosDiferidos .= '</tbody>';
                // $tablaPagosDiferidos .= '</table>';
                // $datosResolucion['total_diferidos']= $totalPagosDiferidos;
                // $datosResolucion['pagos_diferidos']= $tablaPagosDiferidos;
              }
              $datosResolucion['descripcion_pagos'] = ($descripcionPagos != null) ? $descripcionPagos : "";
              // citados que convinieron comparecieron
              $partes_convenio = Compareciente::where('audiencia_id', $audienciaId)->get();
              $hayPartesConvenio = count($partes_convenio);
              if ($hayPartesConvenio > 0) {
                $citadosConvenio = [];
                $solictantesConvenio = [];
                $clausulacitadosConvenio = [];
                $clausulasolicitantesConvenio = [];
                $solicitantesComparecientes = [];
                $citadosComparecientes = [];
                $nombreCitadoConvenio = "";
                $nombreSolicitanteConvenio = "";
                $nombreCitadoComparecientes = "";
                $idParteCitada = "";
                $clausula2citadosConvenio = "";
                $clausula2solicitantesConvenio = "";
                foreach ($partes_convenio as $key => $parteConvenio) {
                  $nombreCitadoComparecientes = "";
                  $nombreSolicitanteComparecientes = "";
                  $nombreCitadoConvenio = "";
                  $clausulaCitadoConvenio = "";
                  $clausulaSolicitanteConvenio = "";
                  //citados convenio
                  $parteC = $parteConvenio->parte;
                  if ($parteC->id != $idParteCitada) {
                    $idParteCitada = $parteC->id;
                    if ($parteC->tipo_persona_id == 1) { //fisica
                      if ($parteC->tipo_parte_id == 3) { //si es representante legal
                        $representanteLegalC = $parteC;
                        $parteRepresentada = Parte::find($representanteLegalC->parte_representada_id);
                        $segundo_apellido_representante = ($representanteLegalC['segundo_apellido'] != "") ? ' ' . $representanteLegalC['segundo_apellido'] : "";
                        $nombreRepresentanteLegal = $representanteLegalC['nombre'] . ' ' . $representanteLegalC['primer_apellido'] . $segundo_apellido_representante;
                        $representanteIdentificacion = "--";
                        $documentoRep = $representanteLegalC->documentos;
                        $representanteInstrumento = "";
                        $representantePoder = "";
                        if (sizeof($documentoRep) > 0) {
                          foreach ($documentoRep as $k => $docu) {

                            if ($docu->clasificacionArchivo->tipo_archivo_id == 1) { //tipo identificacion
                              $representanteIdentificacion = ($docu->clasificacionArchivo->nombre != null) ? " quien se identifica con " . $docu->clasificacionArchivo->nombre : "";
                            } else if ($docu->clasificacionArchivo->tipo_archivo_id == 9) {
                              $representantePoder = ($docu->clasificacionArchivo->nombre != null) ? " en términos de " . $docu->clasificacionArchivo->nombre . ' poder que a la fecha de este convenio no le ha sido revocado. ' : "";
                              $representanteInstrumento = ($docu->clasificacionArchivo->nombre != null) ? " circunstancia que se acredita con " . $docu->clasificacionArchivo->nombre . " " . $representanteLegalC->detalle_instrumento : "";
                            }
                          }
                        }
                        $nombreRepresentada = ($parteRepresentada['tipo_persona_id'] == 2) ? $parteRepresentada['nombre_comercial'] : $parteRepresentada['nombre'] . ' ' . $parteRepresentada['primer_apellido'] . ' ' . $parteRepresentada['segundo_apellido'];
                        $resolucionParteRepresentada = false;
                        if ($resolucionesIndividuales != null && sizeof($resolucionesIndividuales) > 0) {
                          foreach ($resolucionesIndividuales as $k => $resolucion) {
                            if ($resolucion['parte_solicitado_id'] == $representanteLegalC->id . "") {
                              $resolucionParteRepresentada = true;
                            }
                          }
                        }
                        if ($resolucion_id == null) {
                          $nombreCitadoConvenio = ($parteRepresentada->tipo_parte_id == 2) ? $nombreRepresentada . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal' : "";
                        } elseif ($resolucion_id == "1") { //Hubo convenio
                          if ($resolucionesIndividuales == null) {
                            if ($parteRepresentada->tipo_parte_id == 2) { //si representante de citado
                              $nombreCitadoConvenio = $nombreRepresentada . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal';
                              $clausulaCitadoConvenio = $nombreRepresentanteLegal . $representanteIdentificacion . ', que es apoderado legal de ' . $nombreRepresentada . ' y que cuenta con facultades suficientes para convenir a nombre de su representada' . $representantePoder;
                            } else {
                              $nombreSolicitanteConvenio = $nombreRepresentada . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal';
                              $clausulaSolicitanteConvenio = $nombreRepresentanteLegal . $representanteIdentificacion . ', que es apoderado legal de ' . $nombreRepresentada . ' y que cuenta con facultades suficientes para convenir a nombre de su representada' . $representantePoder;
                            }
                          } else {
                            if ($resolucionParteRepresentada) {
                              if ($parteRepresentada->tipo_parte_id == 2) { //si representante de citado
                                $nombreCitadoConvenio = $nombreRepresentada . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal';
                                $clausulaCitadoConvenio = $nombreRepresentanteLegal . $representanteIdentificacion . ', que es apoderado legal de ' . $nombreRepresentada . ' y que cuenta con facultades suficientes para convenir a nombre de su representada' . $representantePoder;
                              } else {
                                $nombreSolicitanteConvenio = $nombreRepresentada . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal';
                                $clausulaSolicitanteConvenio = $nombreRepresentanteLegal . $representanteIdentificacion . ', que es apoderado legal de ' . $nombreRepresentada . ' y que cuenta con facultades suficientes para convenir a nombre de su representada' . $representantePoder;
                              }
                            }
                          }
                        }
                        //$nombreCitadoComparecientes = $parteRepresentada['nombre_comercial'].' representada por '.$nombreRepresentanteLegal .' en carácter de apoderado legal';
                        $nombreCitadoComparecientes = ($parteRepresentada->tipo_parte_id == 2) ? $nombreRepresentanteLegal . ', en su carácter de representante legal de ' . $nombreRepresentada . $representanteInstrumento . ", " . $representanteIdentificacion : "";
                        $nombreSolicitanteComparecientes = ($parteRepresentada->tipo_parte_id == 1) ? $nombreRepresentanteLegal . ', en su carácter de representante legal de ' . $nombreRepresentada . $representanteInstrumento . ", " . $representanteIdentificacion : "";
                      } else { //Solicitante o citado
                        $parteIdentificacion = "";
                        foreach ($parteC->documentos as $k => $docu) {
                          if ($docu->clasificacionArchivo->tipo_archivo_id == 1) { //tipo identificacion
                            //$parteIdentificacion = ($docu->clasificacionArchivo->nombre != null ) ? " quien se identifica con " .$docu->clasificacionArchivo->nombre: "";
                            $parteIdentificacion = ($docu->clasificacionArchivo->nombre != null) ? " quien se identifica con " . $docu->clasificacionArchivo->nombre . " expedida a su favor por " . $docu->clasificacionArchivo->entidad_emisora->nombre : "";
                          }
                        }
                        $segundo_apellido = ($parteC['segundo_apellido'] != "") ? ' ' . $parteC['segundo_apellido'] : "";
                        $resolucionParteRepresentada = false;
                        if ($resolucionesIndividuales != null && sizeof($resolucionesIndividuales) > 0) {
                          foreach ($resolucionesIndividuales as $k => $resolucion) {
                            if ($resolucion['parte_solicitado_id'] == $parteC['id']) {
                              $resolucionParteRepresentada = true;
                            }
                          }
                        }
                        if ($parteC->tipo_parte_id == 2) { //citados
                          if ($resolucionesIndividuales == null) {
                            $nombreCitadoConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido;
                            $clausulaCitadoConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido . $parteIdentificacion . '  tener plenas capacidades de goce y ejercicio para convenir el presente instrumento. ';
                          } else {
                            if ($resolucionParteRepresentada) {
                              $nombreCitadoConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido;
                              $clausulaCitadoConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido . $parteIdentificacion . '  tener plenas capacidades de goce y ejercicio para convenir el presente instrumento. ';
                            }
                          }
                          $nombreCitadoComparecientes = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido . $parteIdentificacion;
                        } else { //solicitantes
                          if ($resolucionesIndividuales == null) {
                            $clausulaSolicitanteConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido . $parteIdentificacion . '  tener plenas capacidades de goce y ejercicio para convenir el presente instrumento. ';
                            $nombreSolicitanteConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido;
                          } else {
                            if ($resolucionParteRepresentada) {
                              $nombreSolicitanteConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido;
                              $clausulaSolicitanteConvenio = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido . $parteIdentificacion . '  tener plenas capacidades de goce y ejercicio para convenir el presente instrumento. ';
                            }
                          }
                          $nombreSolicitanteComparecientes = $parteC['nombre'] . ' ' . $parteC['primer_apellido'] . $segundo_apellido . $parteIdentificacion;
                        }
                      }
                    } else { //moral compareciente
                      $representanteLegalC = Parte::with('documentos.clasificacionArchivo.entidad_emisora')->where('parte_representada_id', $parteC->id)->where('tipo_parte_id', 3)->get();
                      $representanteLegalC = $representanteLegalC[0];
                      $segundo_apellido_representante = ($representanteLegalC['segundo_apellido'] != "") ? ' ' . $representanteLegalC['segundo_apellido'] : "";
                      $nombreRepresentanteLegal = $representanteLegalC['nombre'] . ' ' . $representanteLegalC['primer_apellido'] . $segundo_apellido_representante;
                      $representanteIdentificacion = "--";
                      if (sizeof($representanteLegalC['documentos']) > 0) {
                        foreach ($representanteLegalC['documentos'] as $k => $docu) {
                          if ($docu->clasificacionArchivo->tipo_archivo_id == 1) { //tipo identificacion
                            $representanteIdentificacion = ($docu->clasificacionArchivo->nombre != null) ? " quien se identifica con " . $docu->clasificacionArchivo->nombre : "";
                          } else if ($docu->clasificacionArchivo->tipo_archivo_id == 9) {
                            $representantePoder = ($docu->clasificacionArchivo->nombre != null) ? " en términos de " . $docu->clasificacionArchivo->nombre . ' poder que a la fecha de este convenio no le ha sido revocado. ' : "";
                          }
                        }
                      }
                      $resolucionParteRepresentada = false;
                      if ($resolucionesIndividuales != null && sizeof($resolucionesIndividuales) > 0) {
                        foreach ($resolucionesIndividuales as $k => $resolucion) {
                          if ($resolucion['parte_solicitado_id'] == $representanteLegalC->id . "") {
                            $resolucionParteRepresentada = true;
                          }
                        }
                      }
                      if ($resolucionParteRepresentada && $resolucion_id == "1") {
                        $nombreCitadoConvenio = $parteC['nombre_comercial'] . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal';
                        $clausulaCitadoConvenio = $nombreRepresentanteLegal . $representanteIdentificacion . ', que es apoderado legal de ' . $parteC['nombre_comercial'] . ' y que cuenta con facultades suficientes para convenir a nombre de su representada' . $representantePoder;
                      }
                      $nombreCitadoComparecientes = $parteC['nombre_comercial'] . ' representada por ' . $nombreRepresentanteLegal . ' en carácter de apoderado legal'; //$parteIdentificacion
                    }
                    if ($clausulaCitadoConvenio != "") {
                      array_push($clausulacitadosConvenio, $clausulaCitadoConvenio);
                    }
                    if ($clausulaSolicitanteConvenio != "") {
                      array_push($clausulasolicitantesConvenio, $clausulaSolicitanteConvenio);
                    }
                    if ($nombreCitadoConvenio != "") {
                      array_push($citadosConvenio, $nombreCitadoConvenio);
                    }
                    if ($nombreSolicitanteConvenio != "") {
                      array_push($solictantesConvenio, $nombreSolicitanteConvenio);
                    }
                    if ($nombreCitadoComparecientes != "") {
                      array_push($citadosComparecientes, $nombreCitadoComparecientes);
                    }
                    if ($nombreSolicitanteComparecientes != "") {
                      array_push($solicitantesComparecientes, $nombreSolicitanteComparecientes);
                    }
                  }
                }
                if ($hayPartesConvenio > 1) {
                  $clausulacitadosConvenioA =  implode(", ", $clausulacitadosConvenio);
                  $clausula2citadosConvenio = $clausulacitadosConvenioA;

                  $clausulasolicitantesConvenioA =  implode(", ", $clausulasolicitantesConvenio);
                  $clausula2solicitantesConvenio = $clausulasolicitantesConvenioA;

                  $citadosConvenioA =  implode(", ", $citadosConvenio);
                  $nombreCitadosConvenio = $citadosConvenioA; //$this->lreplace(',', ' y', $citadosConvenioA);

                  $solicitantesConvenioA =  implode(", ", $solictantesConvenio);
                  $nombreSolicitantesConvenio = $solicitantesConvenioA;

                  $citadosConvenioB =  implode(", ", $citadosComparecientes);
                  $nombreCitadosComparecientes = $citadosConvenioB; //$this->lreplace(',', ' y', $citadosConvenioA);

                  $solicitantesB =  implode(", ", $solicitantesComparecientes);
                  $nombreSolicitanteComparecientes = $solicitantesB; //$this->lreplace(',', ' y', $citadosConvenioA);
                } else {
                  $nombreCitadosConvenio = $nombreCitadoConvenio;
                  $nombreCitadosComparecientes = $nombreCitadoComparecientes;
                }
              } else {
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
              $datosResolucion['primera_manifestacion'] = (isset($datosResolucion['primera_manifestacion'])) ? $datosResolucion['primera_manifestacion'] : "";
              $datosResolucion['segunda_manifestacion'] = (isset($datosResolucion['segunda_manifestacion'])) ? $datosResolucion['segunda_manifestacion'] : "";
              $datosResolucion['total_percepciones'] = (isset($datosResolucion['total_percepciones'])) ? $datosResolucion['total_percepciones'] : "";
              $datosResolucion['propuestas_conceptos'] = (isset($datosResolucion['propuestas_conceptos'])) ? $datosResolucion['propuestas_conceptos'] : "";
              $datosResolucion['propuesta_configurada'] = (isset($datosResolucion['propuesta_configurada'])) ? $datosResolucion['propuesta_configurada'] : "";
              $datosResolucion['pagos'] = (isset($datosResolucion['pagos'])) ? $datosResolucion['pagos'] : "";
              $datosResolucion['pagos_diferidos'] = (isset($datosResolucion['pagos_diferidos'])) ? $datosResolucion['pagos_diferidos'] : "";
              $datosResolucion['total_diferidos'] = (isset($datosResolucion['total_diferidos'])) ? $datosResolucion['total_diferidos'] : "";
              $data = Arr::add($data, $model, $datosResolucion);
            } else {
              $objeto = $model_name::first();
              $objeto = new JsonResponse($objeto);
              $otro = json_decode($objeto->content(), true);
              $otro = Arr::except($otro, ['id', 'updated_at', 'created_at', 'deleted_at']);
              $data = Arr::add($data, $model, $otro);
            }
          }
        }
      }
      return $data;
    } catch (\Throwable $e) {
      Log::error('En script:' . $e->getFile() . " En línea: " . $e->getLine() .
        " Se emitió el siguiente mensaje: " . $e->getMessage() .
        " Con código: " . $e->getCode() . " La traza es: " . $e->getTraceAsString());
      return $data;
    }
  }

  /*
        Calcular posible prescripcion de derechos
         */
  private function calcularPrescripcion($objetoSolicitud, $fechaConflicto, $fechaRatificacion)
  {
    try {
      $prescripcion = 'N/A';
      foreach ($objetoSolicitud as $key => $objeto) {
        if ($objeto->tipo_objeto_solicitudes_id == 1) {
          $prescripcion = 'No';
          if ($objeto->id == 1 || $objeto->id == 4) { //Despido o derechos de preferencia
            $meses = Carbon::parse($fechaConflicto)->diffInMonths($fechaRatificacion);
            $prescripcion = ($meses > 2) ? 'Si' : $prescripcion;
          } else if ($objeto->id == 2 || $objeto->id == 5 || $objeto->id == 6) { //Pago prestaciones o derecho de antiguiedad o derecho de acenso
            $anios = Carbon::parse($fechaConflicto)->floatDiffInYears($fechaRatificacion);
            $prescripcion = ($anios > 1) ? 'Si' : $prescripcion;
          } else if ($objeto->id == 3) { //Resicion de relacion laboral
            $meses = Carbon::parse($fechaConflicto)->diffInMonths($fechaRatificacion);
            $prescripcion = ($meses > 1) ? 'Si' : $prescripcion;
          }
        }
      }
      return $prescripcion;
    } catch (\Throwable $th) {
      return "";
    }
  }
  /*
        Calcular posible prescripcion de derechos
         */
  private function calcularFechaMaximaRatificacion($fechaRecepcion, $centroId)
  {
    try {
      $ndia = 0;
      $diasDisponibilidad = [];
      $disponibilidad_centro = Disponibilidad::select('dia')->where('disponibilidad_type', 'App\\Centro')->where('disponibilidad_id', $centroId)->get();
      foreach ($disponibilidad_centro as $disponibilidad) { //dias de disponibilidad del centro
        array_push($diasDisponibilidad, $disponibilidad->dia);
      }
      while ($ndia <= 3) {
        $fechaRecepcion = Carbon::parse($fechaRecepcion);
        if ($ndia < 3) {
          $fechaRecepcion = $fechaRecepcion->addDay(); //sumar dia a fecha recepcion
          $dayOfTheWeek = $fechaRecepcion->dayOfWeek; //dia de la semana de la fecha de recepcion
        }
        $k = array_search($dayOfTheWeek, $diasDisponibilidad);
        if (false !== $k) { //si dia agregado es dia disponble en centro
          $ndia += 1;
        }
      }
      //Do,lu,ma,mi,ju,vi,sa
      // 0,1,2,3,4,5,6
      // return $this->formatoFecha($fechaRecepcion,1);
      return $fechaRecepcion->toDateTimeString();
    } catch (\Throwable $th) {
      return "";
    }
  }

  /*
        Convertir fechas yyyy-mm-dd hh to dd de Monthname de yyyy
         */
  private function formatoFecha($fecha, $tipo = null)
  {
    try {
      if ($tipo != 3) { //no es hora
        $monthNames = ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"];
        $hh = "";
        if (strpos($fecha, " ")) {
          $date = explode(' ', $fecha);
          $fecha = $date[0];
          $hr = explode(':', $date[1]);
          $hh = $hr[0] . ':' . $hr[1];
        }
        $fecha = explode('-', $fecha);
        $dd = $fecha[2];
        $mm = $fecha[1];
        $yy = $fecha[0];
        if ($tipo == 1) { //fecha sin hr
          $ddmmyy = $dd . ' de ' . $monthNames[intval($mm) - 1] . ' de ' . $yy;
        } else if ($tipo == 2) { //hr
          $ddmmyy = $hh;
        } else { //fecha y hora
          $ddmmyy = $dd . ' de ' . $monthNames[intval($mm) - 1] . ' de ' . $yy . ' ' . $hh;
        }
        // $ddmmyy = $dd.' de '. $monthNames[intval($mm)-1]. ' de ' . $yy .' '. $hh ;
        // return $ddmmyy;
      } else { //recibe HH:mm:ss: devuelve hh:mm hr
        $hr = explode(':', $fecha);
        $hh = $hr[0] . ':' . $hr[1];
        $ddmmyy = $hh;
      }
      return $ddmmyy;
    } catch (\Throwable $th) {
      return "";
    }
  }

  public function renderDocumento($idAudiencia, $idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado, $conceptos_pago = null, $resolucion_pagos = null, $resolucion_id = null, $resolucionesIndividuales = null, $descripcionPagos = null)
  {
    $vars = [];
    $data = $this->getDataModelos($idAudiencia, $idSolicitud, $idPlantilla, $idSolicitante, $idSolicitado, $conceptos_pago, $resolucion_pagos, $resolucion_id, $resolucionesIndividuales, $descripcionPagos);

    if ($data != null) {
      $count = 0;
      foreach ($data as $key => $dato) { //solicitud
        if (gettype($dato) == 'array') {
          $isArrAssoc = Arr::isAssoc($dato);
          if ($isArrAssoc) { //si es un array asociativo
            foreach ($dato as $k => $val) { // folio
              $val = ($val === null && $val != false) ? "" : $val;
              if (gettype($val) == "boolean") {
                $val = ($val == false) ? 'No' : 'Si';
              } elseif (gettype($val) == 'array') {
                $isArrayAssoc = Arr::isAssoc($val);
                if (!$isArrayAssoc) { //objeto_solicitudes
                  $names = [];
                  foreach ($val as $i => $v) {
                    if (isset($v['nombre'])) {
                      array_push($names, $v['nombre']);
                      // array_push($names,$v['nombre']);
                    }
                  }
                  $val = implode(", ", $names);
                } else {
                  if (isset($val['nombre']) && $k != 'persona' && $k != 'nombre_completo') {
                    $val = $val['nombre'];
                  } elseif ($k == 'persona') {
                    foreach ($val as $n => $v) {
                      $vars[strtolower($key . '_' . $n)] = $v;
                    }
                    $vars[strtolower($key . '_nombre_completo')] = $val['nombre'] . ' ' . $val['primer_apellido'] . ' ' . $val['segundo_apellido'];
                  } else {
                    foreach ($val as $n => $v) {
                      $vars[strtolower($key . '_' . $k . '_' . $n)] = $v; //($v !== null)? $v :"-";
                    }
                  }
                }
              } elseif (gettype($val) == 'string') {
                $pos = strpos($k, 'fecha');
                if ($pos !== false) {
                  $val = $this->formatoFecha($val, 1);
                }
                $posC = strpos($k, 'created');
                if ($posC !== false) {
                  $val = $this->formatoFecha($val, 1);
                }
                $vars[strtolower('fecha_actual')] = $this->formatoFecha(Carbon::now(), 1);
                $vars[strtolower('hora_actual')] = $this->formatoFecha(Carbon::now(), 2);
                $vars[strtolower('qr_publico')] = '<p style="text-align: center;"><span style="font-size: 10pt;"><div style="text-align:center" class="qr">' . QrCode::generate($this->qrpublico->generarUrlQR(null, null)) . '</div></span></p>';
                $vars[strtolower('iterar_citados')] = self::obtenerCitados($idAudiencia, $idSolicitud);
              }
              $vars[strtolower($key . '_' . $k)] = $val;
            }
          } else { //Si no es un array assoc (n solicitados, n solicitantes)
            foreach ($dato as $data) { //sol[0]...
              foreach ($data as $k => $val) { // folio, domicilios n
                $val = ($val === null && $val !== false) ? "--" : $val;
                if (gettype($val) == "boolean") {
                  $val = ($val == false) ? 'No' : 'Si';
                } elseif (gettype($val) == 'array') {
                  $isArrayAssoc = Arr::isAssoc($val);
                  if (!$isArrayAssoc) { // with
                    if ($k == 'domicilios') {
                      $val = Arr::except($val[0], ['id', 'updated_at', 'created_at', 'deleted_at', 'domiciliable_type', 'domiciliable_id', 'hora_atencion_de', 'hora_atencion_a', 'georeferenciable', 'tipo_vialidad_id', 'tipo_asentamiento_id']);
                      foreach ($val as $n => $v) {
                        $vars[strtolower($key . '_' . $k . '_' . $n)] = $v;
                      }
                    } else if ($k == 'contactos') {
                      foreach ($val as $n => $v) {
                        $v = Arr::except($v, ['id', 'updated_at', 'created_at', 'deleted_at', 'contactable_type', 'contactable_id']);
                        $vars[strtolower($key . '_' . $k . '_' . $v['tipo_contacto']['nombre'])] = ($v['contacto'] !== null) ? $v['contacto'] : '-';
                        if ($v['tipo_contacto_id'] == 3 && $data['correo_buzon'] == null) {
                          $vars[$key . '_correo_buzon'] = $v['contacto'];
                          $vars[$key . '_password_buzon'] = '';
                        }
                      }
                    } else {
                      $names = [];
                      foreach ($val as $i => $v) {
                        if (isset($v['nombre'])) {
                          array_push($names, $v['nombre']);
                        }
                      }
                      $val = implode(", ", $names);
                    }
                  } else {
                    if (isset($val['nombre']) && $k != 'persona' && $k != 'datos_laborales' && $k != 'representante_legal') { //catalogos
                      $val = $val['nombre']; //catalogos
                    } elseif ($k == 'datos_laborales') {
                      foreach ($val as $n => $v) {
                        if ($n == "comida_dentro") {
                          $vars[strtolower($key . '_' . $k . '_' . $n)] = ($v) ? 'dentro' : 'fuera';
                        } else {
                          $pos = strpos($n, 'fecha');
                          if ($pos !== false && $v != "--") {
                            if ($n == "fecha_salida") {
                              $v = ($resolucion_id == "1" && $v == "") ? Carbon::now()->format('d/m/Y') : Carbon::parse($v)->format('d/m/Y');
                            } else {
                              $v = Carbon::parse($v)->format('d/m/Y');
                            }
                          }
                          $vars[strtolower($key . '_' . $k . '_' . $n)]  = ($v != null) ? $v : "";
                        }
                      }
                    } elseif ($k == 'nombre_completo') {
                      $vars[strtolower($key . '_' . $k)] = $val;
                    } elseif ($k == 'representante_legal') {
                      foreach ($val as $n => $v) {
                        $vars[strtolower($key . '_' . $k . '_' . $n)] = ($v != "") ? $v : '';
                      }
                    }
                  }
                } elseif (gettype($val) == 'string') {
                  $pos = strpos($k, 'fecha');
                  if ($pos !== false && $val != "--") {
                    $val = $this->formatoFecha($val, 1);
                  }
                  // }else{
                }
                $vars[strtolower($key . '_' . $k)] = $val;
              }
            }
          }
        } else {
          $vars[strtolower('solicitud_' . $key)] = $dato;
        }
      }
      $vars[strtolower('fecha_actual')] = $this->formatoFecha(Carbon::now(), 1);
      $vars[strtolower('hora_actual')] = $this->formatoFecha(Carbon::now(), 2);
      $vars[strtolower('qr_publico')] = '<p style="text-align: center;"><span style="font-size: 10pt;"><div style="text-align:center" class="qr">' . QrCode::generate($this->qrpublico->generarUrlQR(null, null)) . '</div></span></p>';
      $vars[strtolower('iterar_citados')] = self::obtenerCitados($idAudiencia);
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
                      .firma-llave-publica {text-align: center; font-size: xx-small; max-height: 1000px; overflow-wrap: break-word;}
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
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function cargarVariables(Request $request)
  {
    $tipo_plantilla = TipoDocumento::find($request->id);
    // $plantillaDocumento = PlantillaDocumento::find($id);
    // $tipo_plantillaDoc = TipoDocumento::all();
    // $tipo_plantillaDoc = $tipo_plantillaDoc->where('id', $plantillaDocumento->tipo_documento_id)->first()->getAttributes();
    $objetos = explode(",", $tipo_plantilla['objetos']);
    $objetoDocumento = [];
    //Se llena el catalogo desde el arvhivo json elemento_documentos.json
    $path = base_path('database/datafiles');
    $jsonElementos = json_decode(file_get_contents($path . "/elemento_documentos.json"));
    foreach ($objetos as $key => $obj) {
      foreach ($jsonElementos->datos as $key => $value) {
        if ($value->id == $obj) {
          $columnNames = Schema::getColumnListing($value->tabla);
          $guarded = ['id', 'updated_at', 'created_at', 'deleted_at'];
          foreach ($guarded as $guard) {
            $k = array_search($guard, $columnNames);
            if (false !== $k) {
              unset($columnNames[$k]);
            }
          }
          $columnNames = str_replace("_id", "", $columnNames);
          $objetoDocumento[] =
            [
              'objeto' => $value->objeto,
              'nombre' => $value->nombre,
              'tabla' => $value->tabla,
              'campos' => $columnNames
            ];
        }
      }
    }
    return $objetoDocumento;
  }

  /**
   * Se obtiene el domicilio 
   */
  public function obtenerDomicilio($item)
  {
    $dom_parte = $item['domicilios'][0];
    $tipo_vialidad =  ($dom_parte['tipo_vialidad'] !== null) ? $dom_parte['tipo_vialidad'] : "";
    $vialidad =  ($dom_parte['vialidad'] !== null) ? $dom_parte['vialidad'] : "";
    $num_ext =  ($dom_parte['num_ext'] !== null) ? "No. " . $dom_parte['num_ext'] : "";
    $num_int =  ($dom_parte['num_int'] !== null) ? " Int. " . $dom_parte['num_int'] : "";
    $num =  $num_int . $num_ext;
    $municipio =  ($dom_parte['municipio'] !== null) ? $dom_parte['municipio'] : "";
    $cp =  ($dom_parte['cp'] !== null) ? " CP. " . $dom_parte['cp'] : "";
    $estado =  ($dom_parte['estado'] !== null) ? $dom_parte['estado'] : "";
    $colonia = ($dom_parte['asentamiento'] !== null) ? (($dom_parte['tipo_asentamiento'] === 'Seleccione una opción') ? '' : $dom_parte['tipo_asentamiento']) . ' ' . $dom_parte['asentamiento'] . ' ': '';
    return mb_strtoupper($tipo_vialidad . ' ' . $vialidad . ' ' . $num . ', ' . $colonia . ', ' . $municipio . ', ' . $estado . ', ' . $cp);
  }

  /**
   * Obtener la iteración de los citados
   */
  public function obtenerCitados($idAudiencia)
  {
    $citados = "";
    $audiencia_partes = Audiencia::find($idAudiencia)->audienciaParte;

    foreach ($audiencia_partes as $parte) {
      $parte_info = Parte::with('domicilios')->where("id", $parte->parte_id)->first();
      if ($parte_info->tipo_parte_id == 2) {
        //Citado
        $citado = ($parte_info->tipo_persona_id == 1) ? $parte_info->nombre . ' ' . $parte_info->primer_apellido . ' ' . $parte_info->segundo_apellido : $parte_info->nombre_comercial;
        $notificado = $parte->finalizado !== null && strpos($parte->finalizado, "NO EXITOSO") === FALSE;
        $notificacion_html = $notificado ? "Sí" : "No";

        $parte_citados = Parte::where("solicitud_id", $parte_info->solicitud_id)->where("representante", TRUE)->where("parte_representada_id", $parte_info->id);
        $parte_citados = $parte_citados->exists() ? $parte_citados->first() : $parte_info;
        //Se busca sí comparecio algún representante legal o la persona moral/persona fisica
        $parte_representante_comparecio = Compareciente::whereIn('parte_id', [$parte_citados->id, $parte_info->id])->where('audiencia_id', $idAudiencia)->exists();
        $comparecio = $parte_representante_comparecio ? TRUE : FALSE;
        $comparecio_html = $comparecio ? "Sí" : "No";

        $domicilios_completo = self::obtenerDomicilio($parte_info);
        $motivos = Motivacion::where('centro_id', Auth::user()->centro_id)->where('comparecio_citado', $comparecio)->where('notificado', $notificado)->first();

        $citados .= '<table border="1" cellspacing="3" cellpadding="3" style="border-collapse: collapse !important;">';
        $citados .= '<tr><td style="width: 210px !important;"><b>Citado</b></td><td>' . $citado . '</td></tr>';
        $citados .= '<tr><td style="width: 210px !important;"><b>Compareció</b></td><td>' . $comparecio_html . '</td></tr>';
        $citados .= '<tr><td style="width: 210px !important;"><b>Notificado</b></td><td>' . $notificacion_html . '</td></tr>';
        $citados .= '<tr><td style="width: 210px !important;"><b>Domicilio de la parte citada</b></td><td>' . $domicilios_completo . '</td></tr>';
        $citados .= '<tr><td style="width: 210px !important;"><b>Motivación</b></td><td>' . ($motivos['motivacion_citado'] ?? '-') . '</td></tr>';

        if (isset($motivo)) {
          if ($motivos['fundamento'] != NULL || $motivos['fundamento'] != "")
            $citados .= '<tr><td style="width: 210px !important;"><b>Fundamento</b></td><td>' . $motivos['fundamento'] . '</td></tr>';
        }
        $citados .= '</table><br /><br />';
      }
    }
    return $citados;
  }

  public function listaPreviewDocumento(Request $request)
  {
    try {
      $solicitante_id = 0;
      $solicitado_id = 0;

      if ($request->has('solicitante_id')) {
        $solicitante_id = $request->solicitante_id ?? 0;
        $solicitado_id = $request->solicitado_id ?? 0;
      }

      $listVistaPrevia = [];
      $idAudiencia = $request->audiencia_id;
      $solicitudes = [];
      $audiencia = Audiencia::where('id', $idAudiencia)->first();
      $solicitud = $audiencia->expediente->solicitud;
      $partes = $solicitante_id == 0 ? $solicitud->partes : $solicitud->partes->where('id', $solicitante_id);
      $nombreDocumento = PlantillaDocumento::where('id',  3)->first();
      $maxFileSize = (int) (env('MAXIMO_FILE_SIZE', '20000'));
      $conciliador = Conciliador::find($audiencia->conciliador_id);
      $documentosfirmados = 0;

      $regenerar = "";
      if ($solicitante_id != 0 || $solicitado_id  != 0){
        $regenerar = 'regenerar';
      }

      //Valida que sea el mismo conciliador que esta atendiendo la audiencia para la FE.
      if (!auth()->user()->hasRole('Administrador del centro') && (auth()->user()->persona_id != $conciliador->persona_id)) {
        return view('errors.info_permisosFE', compact('audiencia'));
      }

      if ($solicitante_id == 0) {
        $listVistaPrevia[]  = [
          'plantilla_id' => 3, // acta de audiencia
          'clasificacion_archivo_id' => 15,
          'parte_solicitante_id' => null,
          'parte_solicitado_id' => null,
          'nombreDocto' => $nombreDocumento->nombre_plantilla,
          'status' => 'No aplica',
          'id' => -1,
          'uuid' => null,
        ];
      }
      $nombreDocumento = PlantillaDocumento::where('id',  1)->first();

      foreach ($partes as $parte) {
        if ($parte->tipo_parte_id == 1) {

          $comparecienteParte = null;
          $comparecienteParte = Compareciente::where('parte_id', $parte->id)->where('audiencia_id', $audiencia->id)->first();
          if (!isset($comparecienteParte->id)) {
            $parte_representante = Parte::where('parte_representada_id', $parte->id)->first();
            if (isset($parte_representante->id)) {
              $comparecienteParte = Compareciente::where('parte_id', $parte_representante->id)->where('audiencia_id', $audiencia->id)->first();
            }
          }
          if (isset($comparecienteParte->id)) {
            //Se agrega código para la regeneración de documentos
            if ($solicitante_id != 0 || $solicitado_id  != 0) {
              if ((session('regenerar') != null && session('regenerar') == 'false')) {
                $documento = $this->regresaStatus($audiencia,  $parte->id, 17);
                session()->put('regenerar', 'true');
              } else {
                $documento = [];
              }            
            } else {
              if ($solicitante_id == 0 || (session('regenerar') != null || session('regenerar') == 'false')) {
                $documento = $this->regresaStatus($audiencia,  $parte->id, 17);
              } else {
                session(['regenerar' => 'true']);
                $documento = [];
              }
            }


            if (isset($documento['estatus'])) {
              $documentosfirmados = $documentosfirmados + 1;
            }

            $listVistaPrevia[]  = [
              'plantilla_id' => 1, // acta de no conciliacion
              'clasificacion_archivo_id' => 17,
              'parte_solicitante_id' => $parte->id,
              'parte_solicitado_id' => null,
              'nombreDocto' => $parte->tipo_persona_id == 2 ?  $parte->nombre_comercial : $nombreDocumento->nombre_plantilla . ' - ' . $parte->nombre . ' ' . $parte->primer_apellido . ' ' . (isset($parte->segundo_apellido) ?? ''),
              'status' => ($documento['estatus'] ?? 'Por firmar'),
              'id' => ($documento['id'] ?? -1),
              'uuid' => ($documento['uuid'] ?? -1),
            ];
          }
        }
      }
      return view('documentos.previewDocumentos.previewDocumentos', compact('audiencia', 'solicitud', 'listVistaPrevia', 'maxFileSize', 'solicitante_id', 'solicitado_id', 'documentosfirmados', 'regenerar'));
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function regresaStatus($audiencia, $parte_id, $clasificacion_archivo_id)
  {
    $estatus = [];
    $documento = Documento::where('documentable_type', 'App\Audiencia')->where('documentable_id', $audiencia->id)->where('clasificacion_archivo_id', $clasificacion_archivo_id)->where('partefirmada', $parte_id)->orderBy("created_at", "DESC")->first();

    if (isset($documento->id) && isset($documento->tipofirma)) {
      $estatus = $documento;
      return ['estatus' => $estatus->tipofirma, 'id' => $estatus->id, 'uuid' => $estatus->uuid];
    } else {
      return $estatus;
    }
  }

  public function previewDocumentofirma(Request $request)
  {
    try {
      $idAudiencia = $request->audiencia_id;
      $idSolicitud = $request->solicitud_id;
      $plantilla_id = $request->plantilla_id;
      $idSolicitante = $request->solicitante_id;
      $idSolicitado = $request->citado_id;
      $resolucion_id = $request->resolucion_id;
      $conceptos_pago = $request->listaConceptos;
      $resolucion_pagos = isset($request->listaFechasPago) ? $request->listaFechasPago : null;
      $resolucionesIndividuales = isset($request->listaRelacion) ? $request->listaRelacion : null;
      $descripcionPagos = $request->descripcion_pagos;

      $html = $this->renderDocumento($idAudiencia, $idSolicitud, $plantilla_id, ($idSolicitante != -1 ? $idSolicitante : null), ($idSolicitado != -1 ? $idSolicitado : null), $conceptos_pago, $resolucion_pagos, $resolucion_id, $resolucionesIndividuales, $descripcionPagos);
      return $this->sendResponse($html, "Correcto");
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
      return back()->with('swalError', 'Error al mostrar el preview de documentos');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function previewDocumentofirmaPdf(Request $request)
  {
    try {
      $idAudiencia = $request->audiencia_id;
      $idSolicitud = $request->solicitud_id;
      $plantilla_id = $request->plantilla_id;
      $idSolicitante = $request->solicitante_id;
      $idSolicitado = $request->citado_id;
      $resolucion_id = $request->resolucion_id;
      $conceptos_pago = $request->listaConceptos;
      $resolucion_pagos = isset($request->listaFechasPago) ? $request->listaFechasPago : null;
      $resolucionesIndividuales = isset($request->listaRelacion) ? $request->listaRelacion : null;
      $descripcionPagos = $request->descripcion_pagos;

      $html = $this->renderDocumento($idAudiencia, $idSolicitud, $plantilla_id, ($idSolicitante != -1 ? $idSolicitante : null), ($idSolicitado != -1 ? $idSolicitado : null), $conceptos_pago, $resolucion_pagos, $resolucion_id, $resolucionesIndividuales, $descripcionPagos);
      session(['htmlcncpdf' => $html]);
      return $this->sendResponse($html, "Correcto");
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
      return back()->with('swalError', 'Error al mostrar el preview de documentos');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function generaDocumentofirmaPdf(Request $request)
  {
    try {
      $html = session('htmlcncpdf');
      session(['htmlcncpdf' => $html]);
      $pdf = PDF::loadView('herramientas.confidencialidades.PDF.constanciaCNCPDF', compact('html'))->setPaper('letter');
      return $pdf->stream('CONSTANCIANOCONCILIACION.pdf');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
      return back()->with('swalError', 'Error al mostrar el preview de documentos');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function store_parte_file(Request $request, Audiencia $audiencia)
  {
    try {
      $parte = Parte::find($request->parte_id);
      $tipofirma = $request->tipofirma;
      $clasificacion_archivo_id = $request->clasificacion_archivo_id;
      $solicitante = $request->input('solicitante');
      $parte->clasificacion_archivo_id = $clasificacion_archivo_id;
      $idDocumento = $request->idDocumento;
      $parte->save();
      $fileIdentificacion = $request->file('autografa');
      if ($fileIdentificacion) {
        ParteHelper::storeDocumento($fileIdentificacion, $parte, $audiencia, $clasificacion_archivo_id, $tipofirma, 0, $idDocumento);
      }

      //Se agrega código para la regeneración de documentos
      if (session('regenerar') == 'true') {
        session(['fe' => 'true']);
        session()->put('regenerar', 'false');
      }

      return back()->with(['success' => '1 Documento(s) firmado(s) de forma exitosa.']);
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
      return back()->with('swalError', 'Error al capturar documento');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function eliminar_file_parte(Request $request)
  {
    try {
      $idDocumento = $request->idDocumento;
      $documento = Documento::where('id', $idDocumento)->delete();

      //Se agrega código para la regeneración de documentos
      if (session('regenerar') == null || session('regenerar') == 'false') {
        session()->put('regenerar', 'true');
        session()->forget('fe');
      }

      return back()->with(['success' => 'El documento fue eliminado correctamente.']);
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
      return back()->with('swalError', 'Error al capturar documento');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function firmardocumento(Request $request, Audiencia $audiencia)
  {
    try {
      $listaparte = json_decode($request->listaparte);
      $busqueda = null;
      $count = 0;

      if (isset($request->rfc)) {
        $busqueda = $request->rfc;
      }

      session(['firma' => $request->firma, 'rfc' => $request->rfc, 'nombre' => $request->nombre]);

      foreach ($listaparte as $parteid) {

        //una Constancia de No Conciliador por solicitante (contiene todos los solicitados(citado) )
        $comparecienteCitado = Compareciente::where('parte_id', $parteid)->where('audiencia_id', $audiencia->id)->first();
        if (isset($comparecienteCitado->id)) {
          BitacoraBuzon::create(['parte_id' => $parteid, 'descripcion' => 'Se genera el documento de constancia de no conciliación', 'tipo_movimiento' => 'Documento', 'clabe_identificacion' => $busqueda]);
          event(new GenerateDocumentResolution($audiencia->id, $audiencia->expediente->solicitud->id, 17, 1, $parteid));
          $count = $count + 1;
        }
      }

      //Se agrega código para la regeneración de documentos
      if (session('regenerar') == 'true') {
        session(['fe' => 'true']);
        session()->put('regenerar', 'false');
      }

      session()->forget('firma');
      session()->forget('rfc');
      session()->forget('nombre');
      return back()->with(['success' => $count . ' Documento(s) firmado(s) de forma exitosa.']);
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function firmardocumentoPlantilla(Request $request)
  {
    try {
      $listaparte = json_decode($request->listaparte);
      $busqueda = null;
      $count = 0;

      if (isset($request->rfc)) {
        $busqueda = $request->rfc;
      }
      $persona = Auth::user()->persona;

      $textoConfidencialidad = Configuracion::where('codigo', 'confidencialidad')->first();
      $headerConfidencialidad = Configuracion::where('codigo', 'confidencialidad_header')->first();
      $nombre = $request->nombre;
      $rfc = $request->rfc;
      $archivo = PDF::loadView('herramientas.confidencialidades.PDF.formatodigitalPDF', compact('request', 'textoConfidencialidad', 'headerConfidencialidad', 'rfc', 'nombre'))->setPaper('letter');
      $directorio = 'efirma/' . $persona->curp;
      $caracteres = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
      $desordenada = str_shuffle($caracteres);
      $archi = substr($desordenada, 1, 40);
      Storage::put($directorio . '/' . $archi . '.pdf', $archivo->output());
      if ($persona && $archivo) {
        $uuid = Str::uuid();
        DB::table('archivos_confidencialidad')->insert(
          [
            'nombre' => 'Confidencialidad_Efirma_' . $persona->curp . '.pdf',
            'nombre_original' => 'Confidencialidad_Efirma_' . $persona->curp . '.pdf',
            'descripcion' => 'DOCUMENTO_CONFIDENCIALIDAD_EFIRMA_' . $persona->curp,
            'ruta' => $directorio . '/' . $archi . '.pdf',
            'uuid' => $uuid,
            'longitud' => round(Storage::size($directorio . '/' . $archi . '.pdf') / 1024, 2),
            'firmado' => "false",
            'clasificacion_archivo_id' => 1,
          ]
        );

        $documento = DB::table('archivos_confidencialidad')->where('uuid', '=', $uuid)->where('ruta', '=', $directorio . '/' . $archi . '.pdf')->first();

        if ($documento != null) {
          DB::table('archivos_confidencialidad_persona')->insert(
            [
              'id_persona' => $persona->id,
              'id_file' => $documento->id,
              'id_clasificacion_archivo' => 1,
            ]
          );

          return redirect('home')->with(['success' => ' Documento firmado de forma exitosa.']);
        }
      }
      Log::error('No se encuentra ' . print_r($persona, true) . ' o ' . print_r($archivo, true));
      return back()->with('error', 'Error al firmar el documento verificar la configuración del usuario');
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }

  public function eliminardocumentos(Request $request)
  {
    try {
      $audiencia_id = $request->audiencia_id;
      $documento = Documento::where('documentable_type', 'App\Audiencia')->where('documentable_id', $audiencia_id)->delete();

      return $this->sendResponse(true, "Correcto");
    } catch (\Throwable $e) {
      $this->helper->mensajeError($e);
    }
  }
}
