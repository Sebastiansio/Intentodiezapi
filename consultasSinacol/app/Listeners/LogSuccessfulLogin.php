<?php

namespace App\Listeners;

use App\Traits\Menu;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OwenIt\Auditing\Models\Audit;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Events\Login;
class LogSuccessfulLogin
{
    use Menu;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {
        if (Auth::guard('partes_intermedios')->check()) {
            $rol = Role::where('name', 'Usuario Buzón')->first();
            auth()->guard('partes_intermedios')->user()->roles = collect();
            auth()->guard('partes_intermedios')->user()->roles->push($rol);
            $menu = $this->construirMenu($rol->id);
            session(['menu' => $menu]);
            session(['roles' => $rol]);
            session(['rolActual' => $rol]);
            session(['persona' => []]);
            session(['centro' => []]);
        } else {
            $rol = auth()->user()->roles->first->get();
            if ($rol != null) {
                $menu = $this->construirMenu($rol->id);
                session(['menu' => $menu]);
                session(['roles' => auth()->user()->roles]);
                session(['rolActual' => $rol]);
            } else {
                session(['menu' => []]);
                session(['roles' => []]);
                session(['rolActual' => []]);
            }
            if (auth()->user() != null) {
                $data = [
                    'auditable_id' => auth()->user()->id,
                    'auditable_type' => 'Logged In',
                    'event' => 'Logged In',
                    'url' => request()->fullUrl(),
                    'ip_address' => request()->getClientIp(),
                    'user_agent' => request()->userAgent(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                    'user_id' => auth()->user()->id,
                ];
                session(['persona' => auth()->user()->persona]);
                session(['centro' => auth()->user()->centro]);

                //create audit trail data
                $details = Audit::create($data);
            }
        }
    }
}
