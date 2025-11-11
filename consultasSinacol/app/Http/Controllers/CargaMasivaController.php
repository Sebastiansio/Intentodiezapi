<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CitadoImport;
use App\TipoSolicitud;
use App\GiroComercial;
use App\ObjetoSolicitud;
use App\Estado;
use App\TipoVialidad;


class CargaMasivaController extends Controller
{
    public function showUploadForm()
    {
        // Mostrar la vista completa que incluye los datos del solicitante
        // Cargamos datos mínimos para los select (si fallan, pasamos arrays vacíos)
        $tipo_solicitudes = TipoSolicitud::pluck('nombre','id')->toArray();
        $giros_comerciales = GiroComercial::pluck('nombre','id')->toArray();
        $objeto_solicitudes = ObjetoSolicitud::pluck('nombre','id')->toArray();
        $estados = Estado::all();
        $tipo_vialidades = TipoVialidad::all();

        return view('solicitante.carga_masiva', compact('tipo_solicitudes','giros_comerciales','objeto_solicitudes','estados','tipo_vialidades'));
    }

    public function handleUpload(Request $request)
    {
        // Validamos el archivo y los campos mínimos del formulario
        $request->validate([
            'archivo_citados' => 'required|mimes:xlsx,csv,txt',
            'fecha_conflicto' => 'required|string',
            'tipo_solicitud_id' => 'required|integer',
            'giro_comercial_id' => 'required', // aceptamos id o nombre
            'objeto_solicitudes' => 'required|array',
        ]);

        try {
            // Recolectamos los datos del solicitante y campos comunes
            $solicitante = $request->input('solicitante', []);
            $common = $request->only(['fecha_conflicto','tipo_solicitud_id','giro_comercial_id','objeto_solicitudes','virtual']);

            // Normalizar fecha_conflicto (aceptamos dd/mm/YYYY)
            try {
                $fechaConf = \Carbon\Carbon::createFromFormat('d/m/Y', $common['fecha_conflicto']);
                $common['fecha_conflicto'] = $fechaConf->toDateString();
            } catch (\Exception $e) {
                try {
                    $common['fecha_conflicto'] = (new \Carbon\Carbon($common['fecha_conflicto']))->toDateString();
                } catch (\Exception $e2) {
                    return redirect()->back()->withErrors(['fecha_conflicto' => 'Formato de fecha inválido. Usa dd/mm/YYYY o YYYY-mm-dd.']);
                }
            }

            // Si el archivo es CSV, normalizar encoding a UTF-8 para evitar mojibake
            $uploaded = $request->file('archivo_citados');
            if ($uploaded && strtolower($uploaded->getClientOriginalExtension()) === 'csv') {
                $path = $uploaded->getRealPath();
                $content = file_get_contents($path);
                $encoding = mb_detect_encoding($content, ['UTF-8','ISO-8859-1','WINDOWS-1252'], true);
                if ($encoding && strtoupper($encoding) !== 'UTF-8') {
                    $utf8 = mb_convert_encoding($content, 'UTF-8', $encoding);
                    file_put_contents($path, $utf8);
                }
            }

            // Pasamos el solicitante y la info común al importador
            Excel::import(new CitadoImport($solicitante, $common), $uploaded ?? $request->file('archivo_citados'));

            return redirect()->back()->with('success', 'Archivo recibido. Las solicitudes se están procesando en segundo plano.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ocurrió un error al procesar el archivo: ' . $e->getMessage());
        }
    }
}