<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Mostrar el formulario de login
     */
    public function showLoginForm()
    {
        // Si ya está autenticado, redirigir a consulta de expedientes
        if (session('authenticated')) {
            return redirect('/consulta-expedientes');
        }

        return view('auth.login');
    }

    /**
     * Procesar el login
     */
    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        // Contraseña configurada (puedes cambiarla aquí o en el .env)
        $correctPassword = env('ADMIN_PASSWORD', 'Sinacol2024');

        if ($request->password === $correctPassword) {
            // Guardar en sesión que está autenticado
            session(['authenticated' => true]);
            
            return redirect('/consulta-expedientes');
        }

        return back()->with('error', 'Código de acceso incorrecto. Inténtalo de nuevo.');
    }

    /**
     * Cerrar sesión
     */
    public function logout()
    {
        session()->forget('authenticated');
        return redirect('/login')->with('error', 'Sesión cerrada exitosamente.');
    }
}
