<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;

class UsuariosPartesIntermedio extends Authenticatable
{
    use Notifiable;

    protected $guarded = 'partes_intermedios';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'password', 'email'];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function retrieveByCredentials(array $credentials)
    {
        $user = static::where('email', $credentials['email'])->first();

        if (! $user) {
            return null;
        }

        if (Hash::check($credentials['password'], $user->password)) {
            return $user;
        }
    }

}
