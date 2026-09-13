<?php

namespace App\Http\Controllers;

use App\Support\EcraReact;

class ChangelogController extends Controller
{
    /**
     * As actualizações do sistema — o ecrã `conta/actualizacoes`, com as versões
     * do `config/changelog.php` nas props (são poucas dezenas de KB e não mudam
     * entre deploys, por isso não merecem uma ida à API).
     */
    public function index()
    {
        return EcraReact::pagina('conta/actualizacoes', 'Atualizações do Sistema', [
            'atual' => (string) config('changelog.current', '1.0.0'),
            'versoes' => array_values((array) config('changelog.releases', [])),
        ])();
    }
}
