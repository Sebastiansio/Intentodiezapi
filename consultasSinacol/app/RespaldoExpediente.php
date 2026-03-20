<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RespaldoExpediente extends Model
{
    use HasFactory;

    protected $fillable = [
        'numero_expediente',
        'fecha_apertura',
        'fecha_cierre',
        'tipo_tramite',
        'tipo_solicitud',
        'nombre_trabajador',
        'nombre_empresa',
        'resultado_audiencia',
        'asesor_atendio',
        'conciliador_atendio',
    ];
}
