<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BitacoraBuzon extends Model
{
    use SoftDeletes;
    protected $table = 'bitacora_buzones';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

    /**
     * Get the parte that owns the BitacoraBuzon
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parte(): BelongsTo
    {
        return $this->belongsTo(Parte::class);
    }
}
