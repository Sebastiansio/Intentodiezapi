<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use Illuminate\Database\Eloquent\SoftDeletes;

class Configuracion extends Model
{
    use SoftDeletes;
    protected $table = 'configuraciones';
    protected $guarded = ['id', 'created_at', 'updated_at', ];
}
