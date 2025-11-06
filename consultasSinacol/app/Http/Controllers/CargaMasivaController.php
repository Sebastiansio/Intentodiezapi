<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CitadoImport;


class CargaMasivaController extends Controller
{
    public function showUploadForm()
    {
        // Esta vista contendría un simple formulario con un <input type="file">
        return view('carga.upload_form'); 
    }

    public function handleUpload(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,csv'
        ]);

        try {
            // Aquí es donde la magia sucede. El Importador se encarga de todo.
            Excel::import(new CitadoImport, $request->file('excel_file'));

            return redirect()->back()->with('success', 'Archivo recibido. Las solicitudes se están procesando en segundo plano.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ocurrió un error al procesar el archivo: ' . $e->getMessage());
        }
    }
}