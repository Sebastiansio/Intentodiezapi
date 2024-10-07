<?php

namespace App;

use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Lab404\Impersonate\Models\Impersonate;
use Laravel\Passport\HasApiTokens;
use OwenIt\Auditing\Auditable;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Auth;


class User extends Authenticatable implements AuditableContract
{
    use Notifiable,
        SoftDeletes,
        HasApiTokens,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies,
        HasRoles,
        Impersonate,
        Auditable,
        \App\Traits\CambiarEventoAudit;
    public function transformAudit($data): array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /**
     * Las relaciones que son cargables.
     *
     * @var array
     */
    protected $loadable = ['solicitudes', 'persona'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'password', 'email', 'centro_id'];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Funcion para asociar con modelo Solicitud con hasMany
     *
     * Utilizando hasMany para relacion uno a muchos
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function solicitudes()
    {
        return $this->hasMany('App\Solicitud');
    }

    /**
     * Una cuenta pertenece a una persona
     *
     * Relaciona usuario con persona de uno a uno
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class)
            ->withDefault(['nombre' => '', 'primer_apellido' => '', 'segundo_apellido' => '']);
    }
    /**
     * Una cuenta pertenece a una persona
     *
     * Relaciona usuario con persona de uno a uno
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function centro()
    {
        return $this->belongsTo(Centro::class)
            ->withDefault(['nombre' => 'No asignado', 'abreviatura' => "N/A"]);
    }
    /**
     * Una cuenta pertenece a una persona
     *
     * Relaciona usuario con persona de uno a uno
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function centros()
    {
        return $this->hasMany(UsuarioCentro::class);
    }

    public function setCentroIdAttribute($centro_id)
    {
        $this->attributes["centro_id"] = $centro_id;

        if (Auth::check() && !auth()->user()->hasRole("Super Usuario")) {
            $this->attributes["centro_id"] = auth()->user()->centro_id;
        }
    }

    public function setPasswordAttribute($v)
    {
        if (trim($v)) {
            $this->attributes['password'] = app('hash')->make(trim($v));
        }
    }
}
