<?php
namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Motivacion extends Model
{
    use SoftDeletes;
    protected $table = 'motivaciones';
    protected $guarded = ['id', 'created_at', 'updated_at',];
}
