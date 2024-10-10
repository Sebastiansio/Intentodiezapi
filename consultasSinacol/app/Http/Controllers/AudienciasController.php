<?php
namespace App\Http\Controllers;

use App\Models\Audiencia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
class AudienciasController extends Controller
{
    public function getAudiencias(Request $request)
    {
        // $fechaAudiencia = $request->input('fecha_audiencia');
        $fechaAudiencia = "2024-10-09";
        $audiencias = Audiencia::withDetails($fechaAudiencia)->get();
        return response()->json($audiencias);
    }

    public function getHolaMundo(Request $request){
        return response()->json(['hola' => 'mundo']);
    }
}
