<?php
namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Audiencia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
class AudienciasController extends Controller
{
    public function getAudiencias(Request $request)
    {
        // $fechaAudiencia = $request->input('fecha_audiencia');y
        $fechaAudiencia = Carbon::now();
        $fechaAudiencia = $fechaAudiencia->format('Y-m-d');

        $audiencias = Audiencia::withDetails($fechaAudiencia)->get();
        return response()->json($audiencias);
    }

    public function getHolaMundo(Request $request){
        return response()->json(['hola' => 'mundo']);
    }
}
