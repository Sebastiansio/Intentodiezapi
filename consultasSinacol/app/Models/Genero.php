<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Genero extends Model
{
    //
    use SoftDeletes;
    protected $softDelete = true;
    public $incrementing = false;
}
