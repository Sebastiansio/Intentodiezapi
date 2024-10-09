<?php
namespace App\Http\Controllers;

use App\Models\Audiencia;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
class AudienciasController extends Controller
{
    public function getAudiencias(Request $request)
    {
        $fechaAudiencia = $request->input('fecha_audiencia');
        $audiencias = Audiencia::getAudienciasWithDetails($fechaAudiencia);
        return response()->json($audiencias);
    }
}
