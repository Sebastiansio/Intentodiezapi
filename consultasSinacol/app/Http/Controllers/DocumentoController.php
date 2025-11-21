<?php

namespace App\Http\Controllers;

use App\Audiencia;
use App\ClasificacionArchivo;
use App\Conciliador;
use App\Docsigner\Docsigner;
use App\Documento;
//use EdgarOrozco\Docsigner\Facades\Docsigner;
use App\Events\GenerateDocumentResolution;
use App\Exceptions\CredencialesParaFirmaNoValidosException;
use App\Exceptions\TextoFirmableInexistenteException;
use App\Expediente;
use App\FirmaDocumento;
use App\Parte;
use App\PlantillaDocumento;
use App\Solicitud;
use App\TipoParte;
use App\Traits\GenerateDocument;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Session;

class DocumentoController extends Controller
{
    use GenerateDocument;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Documento::all();
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'required|max:500',
            'ruta' => 'required|max:100',
            'documentable_id' => 'required|Integer',
            'documentable_type' => 'required|max:30',
        ]);
        if ($validator->fails()) {
            return response()->json($validator, 201);
        }

        return Documento::create($request->all());
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(int $id)
    {
        return Documento::find($id);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(int $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Documento $documento)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'required|max:500',
            'ruta' => 'required|max:100',
            'documentable_id' => 'required|Integer',
            'documentable_type' => 'required|max:30',
        ]);
        if ($validator->fails()) {
            return response()->json($validator, 201);
        }
        $documento->fill($request->all())->save();

        return $documento;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id)
    {
        $documento = Documento::findOrFail($id)->delete();

        return 204;
    }

    public function uploadSubmit() {}

    public function postAudiencia(Request $request)
    {
        try {

            $audiencia = Audiencia::find($request->audiencia_id[0]);
            if ($audiencia != null) {
                $directorio = 'expedientes/'.$audiencia->expediente_id.'/audiencias/'.$request->audiencia_id[0];
                Storage::makeDirectory($directorio);
                $archivos = $request->file('files');
                $tipoArchivo = ClasificacionArchivo::find($request->tipo_documento_id[0]);
                foreach ($archivos as $archivo) {
                    $path = $archivo->store($directorio);
                    $uuid = Str::uuid();
                    $audiencia->documentos()->create([
                        'nombre' => str_replace($directorio.'/', '', $path),
                        'nombre_original' => str_replace($directorio, '', $archivo->getClientOriginalName()),
                        'descripcion' => 'Documento de audiencia '.$tipoArchivo->nombre,
                        'ruta' => $path,
                        'uuid' => $uuid,
                        'tipo_almacen' => 'local',
                        'uri' => $path,
                        'longitud' => round(Storage::size($path) / 1024, 2),
                        'firmado' => 'false',
                        'clasificacion_archivo_id' => $tipoArchivo->id,
                    ]);
                }
            }

            return 'Product saved successfully';
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return 'Product saved successfully';
        }
    }

    public function obtenerIdTipoDocumento($tipo_documento_id)
    {
        $_tipo_documento_id = null;
        foreach ($tipo_documento_id as $key) {
            if (isset($key)) {
                $_tipo_documento_id = $key;
                //dd($key);
            }
        }

        return $_tipo_documento_id;
    }

    public function obtenerIdParte($parte)
    {
        $_parte = null;
        foreach ($parte as $key) {
            if (isset($key)) {
                $_parte = $key;
                //dd($key);
            }
        }

        return $_parte;
    }

    public function solicitud(Request $request)
    {
        $tipo_documento_id = $this->obtenerIdTipoDocumento($request->tipo_documento_id);
        $parteid = $this->obtenerIdParte($request->parte);

        //dd($request, $tipo_documento_id, $parteid);

        if (! isset($parteid) || $parteid == null || ! isset($tipo_documento_id) || $tipo_documento_id == null) {
            return '{ "files": [ { "error": "No se capturó tipo de documento o parte solicitada", "name": "thumb2.jpg" } ] }';
        }

        $parte = Parte::find($parteid);
        $solicitud = Solicitud::find($request->solicitud_id[0]);

        try {
            $existeDocumento = $parte->documentos;
            if ($solicitud != null && count($existeDocumento) == 0) {
                $archivo = $request->files;
                $solicitud_id = $solicitud->id;
                $clasificacion_archivo = $tipo_documento_id;
                $directorio = 'solicitud/'.$solicitud_id.'/parte/'.$parte->id;
                Storage::makeDirectory($directorio);
                $archivos = $request->file('files');
                $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo);
                foreach ($archivos as $archivo) {
                    $path = $archivo->store($directorio);
                    $uuid = Str::uuid();
                    $documento = $parte->documentos()->create([
                        'nombre' => str_replace($directorio.'/', '', $path),
                        'nombre_original' => str_replace($directorio, '', $archivo->getClientOriginalName()),
                        // "numero_documento" => str_replace($directorio, '',$archivo->getClientOriginalName()),
                        'descripcion' => 'Documento de audiencia '.$tipoArchivo->nombre,
                        'ruta' => $path,
                        'uuid' => $uuid,
                        'tipo_almacen' => 'local',
                        'uri' => $path,
                        'longitud' => round(Storage::size($path) / 1024, 2),
                        'firmado' => 'false',
                        'clasificacion_archivo_id' => $tipoArchivo->id,
                    ]);
                }

                return '{ "files": [ { "success": "Documento almacenado correctamente","thumbnailUrl":"/api/documentos/getFile/'.$documento->uuid.'" ,"error":0, "name": "'.$tipoArchivo->nombre.'.pdf" } ] }';
            } else {
                return '{ "files": [ { "error": "Ya existe un documento para este solicitante", "name": "" } ] }';
            }
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return '{ "files": [ { "error": "No se pudo guardar el archivo", "name": "thumb2.jpg" } ] }';
        }

        return '{ "files": [ { "error": "No se capturó solicitud", "name": "thumb2.jpg" } ] }';
    }

    public function postComparece(Request $request)
    {
        $tipo_documento_id = $this->obtenerIdTipoDocumento($request->tipo_documento_id);
        $parteid = $this->obtenerIdParte($request->parte);

        //dd($request, $tipo_documento_id, $parteid);

        if (! isset($parteid) || $parteid == null || ! isset($tipo_documento_id) || $tipo_documento_id == null) {
            return '{ "files": [ { "error": "No se capturo tipo de documento o parte solicitada", "name": "thumb2.jpg" } ] }';
        }

        $parte = Parte::find($parteid);
        $audiencia = Audiencia::find($request->audiencia_idC[0]);

        try {
            $existeDocumento = $parte->documentos;
            if ($audiencia != null && count($existeDocumento) == 0) {
                $archivo = $request->files;
                $audiencia_id = $audiencia->id;
                $clasificacion_archivo = $tipo_documento_id;
                $directorio = 'expedientes/'.$audiencia->expediente_id.'/audiencias/'.$audiencia_id;
                Storage::makeDirectory($directorio);
                $archivos = $request->file('files');
                $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo);
                foreach ($archivos as $archivo) {
                    $path = $archivo->store($directorio);
                    $uuid = Str::uuid();
                    $documento = $parte->documentos()->create([
                        'nombre' => str_replace($directorio.'/', '', $path),
                        'nombre_original' => str_replace($directorio, '', $archivo->getClientOriginalName()),
                        // "numero_documento" => str_replace($directorio, '',$archivo->getClientOriginalName()),
                        'descripcion' => 'Documento de audiencia '.$tipoArchivo->nombre,
                        'ruta' => $path,
                        'uuid' => $uuid,
                        'tipo_almacen' => 'local',
                        'uri' => $path,
                        'longitud' => round(Storage::size($path) / 1024, 2),
                        'firmado' => 'false',
                        'clasificacion_archivo_id' => $tipoArchivo->id,
                    ]);
                }

                return '{ "files": [ { "success": "Documento almacenado correctamente","thumbnailUrl":"/api/documentos/getFile/'.$documento->uuid.'" ,"error":0, "name": "'.$tipoArchivo->nombre.'.pdf" } ] }';
            } else {
                return '{ "files": [ { "error": "Ya existe un documento para este solicitante", "name": "" } ] }';
            }
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return '{ "files": [ { "error": "No se pudo guardar el archivo", "name": "thumb2.jpg" } ] }';
        }

        return '{ "files": [ { "error": "No se capturó solicitud", "name": "thumb2.jpg" } ] }';
    }

    public function getFile($uuid)
    {
        try {
            $solicitud_id = null;
            $documento = Documento::where('uuid', $uuid)->first();
            
            //Sirve para validar que el funcionario y el documento esten ligados al mismo centro
            //Cuando el documento a consultar es de una audiencia para obtener la solictud hay que buscar por el expediente primero y obtener el campo solicitud_id
            //Cuando el documento a consultar es de una solicitud el campo solicitud_id ya viene en la información de documentable

            if ($documento && $documento->documentable && isset($documento->documentable->solicitud_id)) {
                // Se asigna el id de la solicitud
                $solicitud_id = $documento->documentable->solicitud_id;
            } else {
                if (isset($documento->documentable->expediente_id)) {
                    //Buscamos el expediente el cual contiene el id solicitud
                    //Se asina el id de la solicitud
                    $solicitud_id = Expediente::find($documento->documentable->expediente_id)->solicitud_id;
                } elseif (isset($documento->documentable->parte_id)) {
                    //Esta validación aplica cuando viene de la tabla audiencia parte y documento proviene de la captura de información de SIGNO
                    $solicitud_id = Parte::find($documento->documentable->parte_id)->solicitud_id;
                }
            }

            if (isset($solicitud_id)) {
                if (isset(Auth::user()->id)) {
                    $solicitud_centro = Solicitud::find($solicitud_id)->centro_id;
                    //Valida que el usuario sea diferente al rol Usuario Buzón
                    if (Auth::user()->roles->first()->name != 'Usuario Buzón') {
                        //Se valida que el usuario pertenezca al mismo centro del documento
                        if (Auth::user()->centro->id != $solicitud_centro && Auth::user()->roles->first()->name != 'Super Usuario') {
                            return view('errors.error_funcionario');
                        }
                    } else {
                        //Validar que el documento este ligado al buzón del usuario si el usuario tiene el rol de Usuario Buzón
                        $partes_documento = Parte::where('solicitud_id', $solicitud_id)->where('correo_buzon', Auth::user()->email)->first();

                        //Los documentos que son de tipo solicitud vienen del modelo parte
                        if ($documento->documentable_type == \App\Parte::class) {
                            return view('errors.error_usuariobuzon');
                        }

                        if (! isset($partes_documento)) {
                            return view('errors.error_usuariobuzon');
                        }

                        $ArchivosNoMostrar = env('ARCHIVOS_OCULTOS_BUZON', null);
                        if ($ArchivosNoMostrar != null) {
                            $ArchivosNoMostrar = explode(',', $ArchivosNoMostrar);
                            if (in_array($documento->clasificacion_archivo_id, $ArchivosNoMostrar)) {
                                return view('errors.error_usuariobuzon');
                            }
                        }
                    }
                } else {
                    session(['urlSession' => '/api/documentos/getFile/'.$uuid]);

                    return redirect('login');
                }
            }

            if ($documento && !empty($documento->ruta) && Storage::exists($documento->ruta)) {
                $file = Storage::get($documento->ruta);
                $fileMime = Storage::mimeType($documento->ruta);

                return response($file, 200)->header('Content-Type', $fileMime);
            } else {
                return view('errors.documento_no_encontrado');
            }
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
            return view('errors.documento_no_encontrado');
        }
    }

    public function aviso_privacidad()
    {
        try {
            $path = public_path('/assets/img/asesoria/aviso-privacidad.pdf');

            return response()->file($path);
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
            abort(404);
        }
    }

    public function preview(Request $request)
    {
        try {
            $idSolicitud = $request->get('solicitud_id');
            $idAudiencia = $request->get('audiencia_id');
            $plantilla_id = $request->get('plantilla_id', 1);
            $idSolicitado = $request->get('solicitado_id');
            $idSolicitante = $request->get('solicitante_id');

            $pdf = $request->exists('pdf');

            $solicitud = Solicitud::find($idSolicitud);
            if ($solicitud) {
                if (! $idAudiencia && isset($solicitud->expediente->audiencia->first()->id)) {
                    $idAudiencia = $solicitud->expediente->audiencia->first()->id;
                }
            }

            $html = $this->renderDocumento(
                $idAudiencia,
                $idSolicitud,
                $plantilla_id,
                $idSolicitante, //solicitante
                $idSolicitado, //solicitado
                '' //documento
            );

            if ($pdf) {
                return $this->renderPDF($html, $plantilla_id);
            } else {
                echo $html;
                exit;
            }
        } catch (\Throwable $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
            //throw $th;
        }
    }

    public function firmado(Request $request)
    {
        try {

            $idParte = $request->get('persona_id');
            $tipoPersona = $request->get('tipo_persona');
            $idSolicitud = $request->get('solicitud_id');
            $idAudiencia = $request->get('audiencia_id');
            $idPlantilla = $request->get('plantilla_id');
            $idDocumento = $request->get('documento_id');
            $idSolicitado = $request->get('solicitado_id');
            $idSolicitante = $request->get('solicitante_id');
            $firma_documento_id = $request->get('firma_documento_id');

            $firmaBase64 = $request->get('img_firma');
            $tipo_firma = $request->get('tipo_firma');
            $firma = null;
            $texto_firmado = null;
            $nombre_owner = null;

            //Si firma con llave publica, FIEL o FIREL o certificado X.509
            if ($tipo_firma == 'llave-publica' || $tipo_firma == null) {
                [$firma, $texto_firmado, $nombre_owner] = $this->firmaConLlavePublica(
                    $request,
                    $idAudiencia,
                    $idSolicitud,
                    $idPlantilla
                );
            }
            //Si firma de forma autógrafa
            elseif ($tipo_firma == 'autografa') {
                $firma = $firmaBase64;
            }

            // if ($tipoPersona != 'conciliador') {
            //     $model = 'App\Parte';
            // } else {
            //     $model = 'App\Conciliador';
            // }

            //guardar o actualizar firma
            // $match = [
            //     'firmable_id'=>$idParte,
            //     'plantilla_id'=>$idPlantilla,
            //     'audiencia_id'=>$idAudiencia,
            // ];
            $firmaDocumento = FirmaDocumento::find($firma_documento_id);
            $firmaDocumento->update([
                'audiencia_id' => $idAudiencia,
                'solicitud_id' => $idSolicitud,
                'plantilla_id' => $idPlantilla,
                'tipo_firma' => $tipo_firma,
                'texto_firmado' => $texto_firmado,
                'firma' => $nombre_owner.': '.$firma,
            ]);
            // $updateOrCreate = ;
            // $firmaDocumento = FirmaDocumento::UpdateOrCreate($match, $updateOrCreate);

            //eliminar documento con codigo QR
            $documento = Documento::find($idDocumento);
            $documento->update(['firmado' => true]);
            $clasificacionArchivo = $documento->clasificacion_archivo_id;
            $totalFirmantes = $documento->total_firmantes;
            $firmasDocumento = FirmaDocumento::where('plantilla_id', $idPlantilla)->where(
                'audiencia_id',
                $idAudiencia
            )->where('documento_id', $idDocumento)->whereRaw('firma is not null')->get();
            if ($totalFirmantes <= count($firmasDocumento)) {
                // if ($documento != null) {
                //     $documento->delete();
                // }
                //generar documento con firmas
                event(
                    new GenerateDocumentResolution(
                        $idAudiencia,
                        $idSolicitud,
                        $clasificacionArchivo,
                        $idPlantilla,
                        $idSolicitante,
                        $idSolicitado,
                        $documento->id
                    )
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'OK',
            ], 200);
        } catch (\Throwable $e) {

            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'ERROR:'.$e->getMessage(),
            ], 200);
        }
    }

    public function storeDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'descripcion' => 'required',
            'nombre_documento' => 'required',
            'solicitud_id' => 'integer|required',
            'fileDocumento' => 'max:10000|mimes:pdf',
        ], [
            'fileDocumento.max' => 'El documento no puede ser de tamaño mayor a :max Kb.',
            'descripcion.required' => 'El campo :required es requerido.',
            'nombre_documento.required' => 'El campo :required es requerido.',
            'solicitud_id.required' => 'El campo :required es requerido.',
        ]);
        if ($validator->fails()) {
            $error = '';
            foreach ($validator->errors()->all() as $key => $value) {
                $error .= ' - '.$value;
            }

            return response()->json(['success' => false, 'message' => 'Por favor verifica tus datos: '.$error, 'data' => null], 200);
        }
        try {
            $solicitud = Solicitud::find($request->solicitud_id);
            $archivo = $request->fileDocumento;
            $clasificacion_archivo = 37;
            $directorio = '/solicitud/'.$solicitud->id;
            Storage::makeDirectory($directorio);
            $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo);

            $path = $archivo->store($directorio);
            if ($solicitud && $archivo) {
                $uuid = Str::uuid();
                $documento = $solicitud->documentos()->create([
                    'nombre' => $request->nombre_documento,
                    'nombre_original' => str_replace($directorio, '', $archivo->getClientOriginalName()),
                    // "numero_documento" => str_replace($directorio, '',$archivo->getClientOriginalName()),
                    'descripcion' => $request->descripcion,
                    'ruta' => $path,
                    'uuid' => $uuid,
                    'tipo_almacen' => 'local',
                    'uri' => $path,
                    'longitud' => round(Storage::size($path) / 1024, 2),
                    'firmado' => 'false',
                    'clasificacion_archivo_id' => $tipoArchivo->id,
                ]);
                if ($documento != null) {
                    return response()->json(['success' => true, 'message' => 'Se guardo correctamente', 'data' => $documento], 200);
                }
            }
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'No se pudo guardar el documento', 'data' => null], 200);
        }
    }

    public function generar_documento()
    {

        try {
            $plantillas = PlantillaDocumento::orderBy('nombre_plantilla')->whereNotIn('id', [27, 28])->get()->pluck('nombre_plantilla', 'id');

            //$clasificacion_archivos = array_pluck(ClasificacionArchivo::whereIn('id',[13,14,15,16,17,18,40,45,41,19,43,52,54,49,56,61,62,64,66,65])->orderBy('nombre')->get(),'nombre','id');
            return view('herramientas.regenerar_documentos', compact('plantillas'));
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return view('herramientas.regenerar_documentos');
        }
    }

    public function storeRegenerarDocumento(Request $request)
    {

        try {

            //$arrayPlantilla = [40=>6,18=>7,17=>1,16=>2,15=>3,14=>4,13=>10,19=>11,41=>8,43=>9,52=>14,54=>15,45=>12,49=>13,61=>24,56=>18,62=>19,64=>29,66=>30,65=>31];
            $arrayPlantilla = [1 => 17, 6 => 40, 7 => 18, 17 => 1, 2 => 16, 16 => 16, 3 => 15, 4 => 14, 10 => 13, 11 => 19, 8 => 41, 9 => 43, 14 => 52, 15 => 54, 12 => 45, 13 => 49, 24 => 61, 18 => 56, 19 => 62, 20 => 62, 29 => 64, 30 => 66, 31 => 65, 21 => 59, 22 => 60, 23 => 60, 25 => 63, 26 => 63];
            $arrayPlantillaParte = [65, 66, 62];
            $idSolicitud = $request->get('solicitud_id', 1);
            $idAudiencia = $request->get('audiencia_id');
            $plantilla_id = $request->get('plantilla_id');
            $idSolicitante = $request->get('solicitante_id');
            $idSolicitado = $request->get('solicitado_id');
            $clasificacion_archivo_id = $arrayPlantilla[$plantilla_id];
            $id_parte_asociada = null;
            if (in_array($clasificacion_archivo_id, $arrayPlantillaParte)) {
                $id_parte_asociada = $idSolicitado;
                if (! empty($idSolicitante)) {
                    $id_parte_asociada = $idSolicitante;
                }
            }

            //Se agrega código para el proceso de firma electrónica
            //Si ya existe el documento firmado no se crea de nuevo
            $documento = null;
            if ($plantilla_id == 1) {
                $documento = Documento::where('documentable_type', 'App\Audiencia')->where('documentable_id', $idAudiencia)->where('partefirmada', $idSolicitante)->first();
                
                if (!isset($documento->id)) {
                    event(new GenerateDocumentResolution($idAudiencia, $idSolicitud, $clasificacion_archivo_id, $plantilla_id, $idSolicitante, $idSolicitado, null, $id_parte_asociada));
                } else {
                    if (!Session::has('fe')){
                        event(new GenerateDocumentResolution($idAudiencia, $idSolicitud, $clasificacion_archivo_id, $plantilla_id, $idSolicitante, $idSolicitado, null, $id_parte_asociada));
                    }
                }
            } else {
                event(new GenerateDocumentResolution($idAudiencia, $idSolicitud, $clasificacion_archivo_id, $plantilla_id, $idSolicitante, $idSolicitado, null, $id_parte_asociada));
            }
            
            if (session('regenerar') == 'true') {
                session()->put('regenerar', 'false');
            }

            session()->forget('regenerar');
            session()->forget('fe');

            return response()->json(['success' => true, 'message' => 'Se genero el documento correctamente', 'data' => null], 200);
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'No se genero el documento correctamente', 'data' => null], 200);
        }
    }

    public function regenerarAcuseCambioMod(Request $request)
    {
        try {
            $idAudiencia = '';
            $idSolicitado = '';

            $idSolicitud = $request->get('solicitud_id', 1);
            $clasificacion_archivo_id = 40; //Este id es el que corresponde a acuse de solicitud
            $plantilla_id = 6;
            //Generar Archivo
            $arrSolicitantes = Solicitud::find($idSolicitud)->Partes->where('tipo_parte_id', 1);

            foreach ($arrSolicitantes as $sol) {
                event(new GenerateDocumentResolution($idAudiencia, $idSolicitud, $clasificacion_archivo_id, $plantilla_id, $sol->id, $idSolicitado));
            }

            //Fin Generar Archivo
            return response()->json(['success' => true, 'message' => 'Se genero el documento correctamente', 'data' => null], 200);
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'No se genero el documento correctamente', 'data' => null], 200);
        }
    }

    public function regenerarCitatorioCambioMod(Request $request)
    {
        try {
            $idAudiencia = '';
            $idSolicitud = $request->get('solicitud_id', 1);
            $clasificacion_archivo_id = 14; //Este id es el que corresponde a citatorio
            $plantilla_id = 4;

            $arrExpediente = Expediente::whereSolicitudId($idSolicitud)->get();
            $expediente_id = $arrExpediente[0]->id;
            $arrAudiencia = Audiencia::whereExpedienteId($expediente_id)->orderBy('id', 'desc')->first();
            $idAudiencia = $arrAudiencia->id;
            //Generar Archivo
            $arrSolicitantes = Solicitud::find($idSolicitud)->Partes->where('tipo_parte_id', 1);
            foreach ($arrSolicitantes as $sol) {
                $arrCitado = Solicitud::find($idSolicitud)->Partes->where('tipo_parte_id', 2);
                foreach ($arrCitado as $cit) {
                    event(new GenerateDocumentResolution($idAudiencia, $idSolicitud, $clasificacion_archivo_id, $plantilla_id, $sol->id, $cit->id));
                }
            }

            return response()->json(['success' => true, 'message' => 'Se genero el documento correctamente', 'data' => null], 200);
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'No se genero el documento correctamente', 'data' => null], 200);
        }
    }

    /**
     * Recibe los archivos de llave, certificados y parámetros para firmado del documento y regresa un arreglo
     * con la firma en el primer elemento y en el segundo el texto que se firma.
     *
     *
     * @throws CredencialesParaFirmaNoValidosException
     */
    protected function firmaConLlavePublica(Request $request, $idAudiencia, $idSolicitud, $idPlantilla)
    {
        $encoding_firmas = $request->get('encoding_firmas');
        $base_firmas_path = 'firmas';
        if (! Storage::exists($base_firmas_path)) {
            Storage::makeDirectory($base_firmas_path);
        }
        if ($encoding_firmas == 'post') {
            $key_path = storage_path('app/'.$request->file('key')->store('firmas'));
            $cert_path = storage_path('app/'.$request->file('cert')->store('firmas'));
        } else {
            $binkey = file_get_contents($request->get('key'));
            $bincert = file_get_contents($request->get('cert'));
            $nombrekey = md5($request->get('key'));
            $nombrecert = md5($request->get('cert'));
            $key_path = storage_path('app/firmas/'.$nombrekey.'.key');
            $cert_path = storage_path('app/firmas/'.$nombrecert.'.cer');
            file_put_contents($key_path, $binkey);
            file_put_contents($cert_path, $bincert);
        }
        $password = $request->get('password');

        try {
            $texto_a_firmar = $this->textoQueSeFirma($idAudiencia, $idSolicitud, $idPlantilla);
            $docsigner = new Docsigner;
            $ds = $docsigner->setCredenciales($cert_path, $key_path, $password);
            $firma = $ds->firma($texto_a_firmar);
            $nombre_owner = $ds->nombre();

            return [$firma, $texto_a_firmar, $nombre_owner];
        } catch (\Exception $e) {
            $message = 'No ha sido posible realizar la firma del documento. Favor de revisar la validéz de su clave, archivo .key y/o archivo.cer';
            throw new CredencialesParaFirmaNoValidosException($message);
        } finally {
            if (Storage::exists($key_path)) {
                Storage::delete($key_path);
            }
            if (Storage::exists($cert_path)) {
                Storage::delete($cert_path);
            }
        }
    }

    protected function firmaConAutografaDigital(
        $tipoPersona,
        $idParte,
        $idPlantilla,
        $idAudiencia,
        $firmaBase64,
        $idSolicitud,
        $idDocumento,
        $idSolicitante,
        $idSolicitado
    ) {}

    /**
     * Devuelve el texto que se debe firmar, sin elementos html ni de control de plantilla, esto se extrae
     * del cuerpo del documento.
     *
     * @important Debe existir en la plantilla un tag con class = body si no existe emitirá una excepción
     *
     * @throws TextoFirmableInexistenteException
     */
    protected function textoQueSeFirma($idAudiencia, $idSolicitud, $idPlantilla)
    {
        $html = $this->renderDocumento(
            $idAudiencia,
            $idSolicitud,
            $idPlantilla,
            '', //solicitante
            '', //solicitado
            '' //documento
        );

        //Ojo que aquí el supuesto es que en el documento hay un div o un tag que encierra todo el texto
        //útil a firmar. Sin encabezados ni elementos de control, sólo el texto firmable
        //Si no hay ese elemento este código va a emitir una excepción.

        $crawler = new Crawler($html);
        $elements = $crawler->filter('.body')->each(
            function ($node) {
                return $node->text();
            }
        );
        if (! $elements || ! isset($elements[0])) {
            throw new TextoFirmableInexistenteException('ELEMENTO CON CLASE .body NO SE ENCONTRÓ EN PLANTILLA', '20201');
        }
        $text_a_firmar = strip_tags($elements[0]);

        return $text_a_firmar;
    }

    public function ObtenerFirmado(Request $request)
    {
        //        Obtenemos la firma
        $respuesta = [];
        $firma = FirmaDocumento::find($request->firma_documento_id);
        $parte = Parte::find($firma->firmable_id);
        $respuesta['persona_id'] = $parte->id;
        $tipo_persona_solicitante = TipoParte::where('nombre', 'SOLICITANTE')->first();
        $tipo_persona_citado = TipoParte::where('nombre', 'CITADO')->first();
        if ($parte->tipo_parte_id == $tipo_persona_solicitante->id) {
            $respuesta['tipo_persona'] = 'solicitante';
            $respuesta['solicitante_id'] = $parte->id;
            $respuesta['solicitado_id'] = null;
        } elseif ($parte->tipo_parte_id == $tipo_persona_citado->id) {
            $respuesta['tipo_persona'] = 'citado';
            $respuesta['solicitante_id'] = null;
            $respuesta['solicitado_id'] = $parte->id;
        } else {
            $respuesta['tipo_persona'] = 'representante legal';
            $respuesta['solicitante_id'] = null;
            $respuesta['solicitado_id'] = null;
        }
        $respuesta['audiencia_id'] = $firma->audiencia_id;
        $respuesta['solicitud_id'] = $firma->solicitud_id;
        $respuesta['plantilla_id'] = $firma->plantilla_id;
        $respuesta['documento_id'] = $firma->documento_id;
        $respuesta['tipo_firma'] = $firma->tipo_firma;
        $respuesta['encoding_firmas'] = 'post';
        $respuesta['firma_documento_id'] = $request->firma_documento_id;

        return $respuesta;
    }

    public function eliminar_documentos(Request $request)
    {
        return view('herramientas.eliminar_documentos');
    }

    public function delete_documento(Request $request)
    {
        try {
            $documento_id = $request->documento_id;
            $documento = Documento::find($documento_id);
            $documento->delete();

            return response()->json(['success' => true, 'message' => 'Documento eliminado correctamente', 'data' => null], 200);
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensale: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'No se encontraron datos relacionados', 'data' => null], 200);
        }
    }

    public function delete_documento_solicitud(Request $request, Solicitud $solicitud)
    {
        try {
            $documento_id = $request->documento_id;
            $documento = Documento::find($documento_id);
            $solicitud = Solicitud::find($request->solicitud_id);
            $documento->delete();

            return response()->json(['success' => true, 'message' => 'Documento eliminado correctamente', 'data' => null], 200);
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensale: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return response()->json(['success' => false, 'message' => 'Documento eliminado correctamente', 'data' => null], 200);
        }
    }

    public function getFileqr($uuid, $token)
    {
        try {
            $documento = Documento::where('uuid', $uuid)->where('qrtoken', $token)->first();

            if ($documento && !empty($documento->ruta) && Storage::exists($documento->ruta)) {
                $file = Storage::get($documento->ruta);
                $fileMime = Storage::mimeType($documento->ruta);

                return response($file, 200)->header('Content-Type', $fileMime);
            } else {
                return view('errors.error_usuariobuzon');
                //abort(404);
            }
        } catch (Exception $e) {
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                ' Se emitió el siguiente mensaje: '.$e->getMessage().
                ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
            abort(404);
        }
    }
}
