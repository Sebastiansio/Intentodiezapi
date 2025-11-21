<?php

namespace App\Helper;

use App\ClasificacionArchivo;
use App\Documento;
use App\Parte;
use App\Persona;
use App\Solicitud;
use App\User;
use Exception;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ParteHelper
{
    public static function storeFileParte($request)
    {
        try {
            $parte_id = $request->parte_id;
            $archivo = $request->file_parte;
            $clasificacion_archivo_id = $request->clasificacion_archivo_id;
            $parte = Parte::find($parte_id);
            $solicitud_id = $parte->solicitud->id;
            $directorio = 'solicitud/'.$solicitud_id.'/parte/'.$parte->id;
            $path = $archivo->store($directorio);
            Storage::makeDirectory($directorio);
            $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo_id);

            $uuid = Str::uuid();

            $documento = $parte->documentos()->create([
                'nombre' => str_replace($directorio.'/', '', $path),
                'nombre_original' => str_replace($directorio, '', $archivo->getClientOriginalName()),
                'descripcion' => $tipoArchivo->nombre,
                'ruta' => $path,
                'uuid' => $uuid,
                'tipo_almacen' => 'local',
                'uri' => $path,
                'longitud' => round(Storage::size($path) / 1024, 2),
                'firmado' => 'false',
                'clasificacion_archivo_id' => $tipoArchivo->id,
            ]);

            return ['valido' => true, 'mensaje' => 'Archivo guardado correctamente'];
        } catch (Exception $e) {
            return ['valido' => false, 'mensaje' => $e->getMessage()];
        }
    }

    public static function storeParte($file, $parte, $solicitud, $clasificacion_archivo, $file_id)
    {
        try {
            $existe = true;
            $deleted = true;
            if (! $existe || $deleted) {

                if ($solicitud) {

                    $archivoIde = $file;
                    $solicitud_id = $solicitud->id;
                    $directorio = 'solicitud/'.$solicitud_id.'/parte/'.$parte->id;
                    Storage::makeDirectory($directorio);
                    $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo);
                    $path = $archivoIde->store($directorio);
                    $nombreOriginal = $archivoIde->getClientOriginalName();
                    $nombreSinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombreOriginal);
                    $fileName = str_replace(' ', '_', $nombreSinAcentos);
                    $fileName = str_replace($directorio.'/', '', $path);
                    $uuid = Str::uuid();
                    $documento = Documento::find($file_id);
                    if ($documento) {
                        // Buscar el documento existente y actualizarlo
                        $documento->update([
                            'nombre' => $fileName,
                            'nombre_original' => str_replace($directorio, '', $archivoIde->getClientOriginalName()),
                            'descripcion' => $tipoArchivo->nombre,
                            'ruta' => $path,
                            'uuid' => $uuid,
                            'tipo_almacen' => 'local',
                            'uri' => $path,
                            'longitud' => round(Storage::size($path) / 1024, 2),
                            'firmado' => 'false',
                            'clasificacion_archivo_id' => $tipoArchivo->id,
                        ]);
                    } else {
                        // Crear un nuevo documento
                        $documento = $parte->documentos()->create([
                            'nombre' => $fileName,
                            'nombre_original' => str_replace($directorio, '', $archivoIde->getClientOriginalName()),
                            'descripcion' => $tipoArchivo->nombre,
                            'ruta' => $path,
                            'uuid' => $uuid,
                            'tipo_almacen' => 'local',
                            'uri' => $path,
                            'longitud' => round(Storage::size($path) / 1024, 2),
                            'firmado' => 'false',
                            'documentable_id' => $parte->id,
                            'clasificacion_archivo_id' => $tipoArchivo->id,
                        ]);
                    }
                    $exito = true;
                } else {
                    $exito = false;
                }
            }
        } catch (Exception $e) {
            $exito = false;
        }
    }

    public static function updateTypeFilePate($clasificacion_archivo, $file_id)
    {
        $documento = Documento::find($file_id);
        if ($documento) {
            $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo);
            $documento->update([
                'descripcion' => $tipoArchivo->nombre,
                'clasificacion_archivo_id' => $tipoArchivo->id,
            ]);
        }
    }

    public static function getDocumentos($parte)
    {
        $keys = ['id', 'documentable_id', 'uuid', 'tipo_almacen', 'ruta', 'nombre_original'];
        $fileIdentificacion = null;
        $fileInstrumento = null;
        $documentos = $parte->documentos;
        foreach ($documentos as $item) {
            $extension = pathinfo($item->uri, PATHINFO_EXTENSION);

            if (! $fileIdentificacion && $item->clasificacion_archivo_id == $parte->clasificacion_archivo_id && $item->documentable_id == $parte->id) {
                $fileIdentificacion = $item->only($keys);
                $fileIdentificacion['extension'] = $extension;
            } elseif (! $fileInstrumento && $item->clasificacion_archivo_id == 39) {
                $fileInstrumento = $item->only($keys);
                $fileInstrumento['extension'] = $extension;
            }
        }
        $parte->fileIdentificacion = $fileIdentificacion;
        $parte->fileInstrumento = $fileInstrumento;

        return $parte;
    }

    public static function replaceDocumentParte($file_id, $clasificacion_archivo_id, $parte)
    {
        // Crear una copia del documento original
        $file = Documento::find($file_id);
        if ($file) {
            $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo_id);
            $documentoData = $file->replicate();
            $documentoData->descripcion = $tipoArchivo->nombre;
            $documentoData->documentable_id = $parte->id;
            $documentoData->clasificacion_archivo_id = $tipoArchivo->id;
            $documento = new Documento($documentoData->toArray());
            // Asociar la copia del documento con la Parte
            $parte->documentos()->save($documento);
        }
    }

    public static function formatFromArray($representante, $representada_id, $new_parte = false)
    {
        $data = [];
        $contactos = [];
        if ($representante) {
            if (isset($representante->contactos) && ! empty($representante->contactos->toArray())) {
                $contactos = $representante->contactos
                    ->map(function ($item) {
                        return collect($item->toArray())
                            ->only(['contactable_id', 'contacto', 'tipo_contacto_id'])
                            ->all();
                    })
                    ->toArray();
            }

            $documentos = $representante->documentos;
            $fileCedula = null;
            $fileInstrumento = null;
            $fileIdentificacion = null;
            foreach ($documentos as $item) {
                if (! $fileIdentificacion && $item->clasificacion_archivo_id == $representante->clasificacion_archivo_id && $item->documentable_id == $representante->id) {
                    $fileIdentificacion = $item->only(['id', 'documentable_id', 'nombre_original', 'clasificacion_archivo_id']);
                } elseif (! $fileCedula && $item->clasificacion_archivo_id == 3) {
                    $fileCedula = $item->only(['id', 'documentable_id', 'nombre_original', 'clasificacion_archivo_id']);
                } elseif (! $fileInstrumento && $item->clasificacion_archivo_id == 39) {
                    $fileInstrumento = $item->only(['id', 'documentable_id', 'nombre_original', 'clasificacion_archivo_id']);
                }
            }

            $data = [
                'id' => $new_parte ? '' : $representante->id,
                'parte_representada_id' => $representada_id ? (int) $representada_id : $representante->parte_representada_id,
                'representante' => [
                    'id' => $new_parte ? '' : $representante->id,
                    'parte_representada_id' => $representada_id ? (int) $representada_id : $representante->parte_representada_id,
                    'tipo_persona_id' => $representante->tipo_persona_id,
                    'clasificacion_archivo_id' => $representante->clasificacion_archivo_id,
                    'detalle_instrumento' => $representante->detalle_instrumento,
                    'curp' => $representante->curp,
                    'nombre' => $representante->nombre,
                    'primer_apellido' => $representante->primer_apellido,
                    'segundo_apellido' => $representante->segundo_apellido,
                    'fecha_nacimiento' => $representante->fecha_nacimiento ? date_format(date_create($representante->fecha_nacimiento), 'd/m/Y') : null,
                    'feha_instrumento' => $representante->feha_instrumento ? date_format(date_create($representante->feha_instrumento), 'd/m/Y') : null,
                    'rfc' => $representante->rfc,
                    'genero_id' => $representante->genero_id,
                ],
                'contactos' => $contactos,
                'file_identificacion' => $fileIdentificacion,
                'file_cedula' => $fileCedula,
                'file_instrumento' => $fileInstrumento,
            ];
        }

        return $data;
    }

    private static function getNombreCompleto($solicitud)
    {
        if (isset($solicitud->modified_user_id)) {
            $user_persona_id = User::where('id', $solicitud->modified_user_id)->first('persona_id')->persona_id;
            $get_user = Persona::where('id', $user_persona_id)->first();

            return $get_user->nombre.' '.$get_user->primer_apellido.' '.$get_user->segundo_apellido;
        }

        return '';
    }

    private static function handleSolicitud($solicitud, $status, $modified_user_id)
    {
        Solicitud::find($solicitud->id)->update(['code_estatus' => $status, 'modified_user_id' => $modified_user_id]);
    }

    public static function storeDocumento($file, $parte, $audiencia, $clasificacion_archivo, $tipofirma = null, $firmantes = 0, $idDocumento = null)
    {
        try {
            $existe = true;
            $deleted = true;
            if (! $existe || $deleted) {

                if ($audiencia) {
                    $archivoIde = $file;
                    $solicitud = $audiencia->expediente->solicitud;
                    $solicitud->id;
                    $directorio = 'expedientes/'.$solicitud->id.'/audiencias/'.$audiencia->id;
                    Storage::makeDirectory($directorio);
                    $tipoArchivo = ClasificacionArchivo::find($clasificacion_archivo);
                    $path = $archivoIde->store($directorio);
                    $nombreOriginal = $archivoIde->getClientOriginalName();
                    $nombreSinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombreOriginal);
                    $fileName = str_replace(' ', '_', $nombreSinAcentos);
                    $fileName = str_replace($directorio.'/', '', $path);
                    $uuid = Str::uuid();
                    $token = Str::random(64);
                    $documento = Documento::where('id', $idDocumento)->first();

                    if ($documento) {
                        // Buscar el documento existente y actualizarlo
                        $documento->update([
                            'nombre' => $fileName,
                            'nombre_original' => str_replace($directorio, '', $archivoIde->getClientOriginalName()),
                            'descripcion' => 'Documento de '.$tipoArchivo->nombre,
                            'ruta' => $path,
                            'uuid' => $uuid,
                            'tipo_almacen' => 'local',
                            'uri' => $path,
                            'longitud' => round(Storage::size($path) / 1024, 2),
                            'firmado' => 'false',
                            'clasificacion_archivo_id' => $tipoArchivo->id,
                            'tipofirma' => $tipofirma,
                            'qrtoken' => $token,
                            'total_firmantes' => $firmantes,
                            'partefirmada' => $parte->id,
                            'firma' => session('firma') ?? null,
                        ]);
                    } else {
                        // Crear un nuevo documento
                        $documento = Documento::create([
                            'nombre' => $fileName,
                            'nombre_original' => str_replace($directorio, '', $archivoIde->getClientOriginalName()),
                            'descripcion' => 'Documento de '.$tipoArchivo->nombre,
                            'documentable_type' => \App\Audiencia::class,
                            'ruta' => $path,
                            'uuid' => $uuid,
                            'tipo_almacen' => 'local',
                            'uri' => $path,
                            'longitud' => round(Storage::size($path) / 1024, 2),
                            'firmado' => 'false',
                            'documentable_id' => $audiencia->id,
                            'clasificacion_archivo_id' => $tipoArchivo->id,
                            'tipofirma' => $tipofirma,
                            'qrtoken' => $token,
                            'total_firmantes' => $firmantes,
                            'partefirmada' => $parte->id,
                            'firma' => session('firma') ?? null,
                        ]);
                    }
                    $exito = true;
                } else {
                    $exito = false;
                }
            }
        } catch (Exception $e) {
            $exito = false;
        }
    }
}
