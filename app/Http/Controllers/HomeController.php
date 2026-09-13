<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index()
    {
        $user = auth()->user();

        // ====== SUPER ADMIN: home dedicado com analytics ======
        if ($user->isSuperAdmin() && !session()->has('impersonate_tenant_id')) {
            return $this->superAdminHome($user);
        }

        // O ecrã em React (`inicio`) pede o que mostra a /api/v1/casca/inicio —
        // ver App\Services\Casca\PaginaInicial. Daqui só vai o recado da sessão.
        return \App\Support\EcraReact::pagina('inicio', 'Início', ['estado' => session('status')])();
    }

    /**
     * As boas-vindas do super admin: o ecrã `plataforma/inicio`, com os dados
     * de App\Services\Plataforma\InicioDaPlataforma.
     */
    protected function superAdminHome($user)
    {
        return \App\Support\EcraReact::plataforma('plataforma/inicio', 'Início — Super Admin')();
    }
}
