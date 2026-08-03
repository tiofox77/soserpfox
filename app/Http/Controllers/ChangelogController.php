<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ChangelogController extends Controller
{
    /**
     * Página pública do changelog do SOS ERP.
     * Lê a configuração `config/changelog.php`.
     */
    public function index()
    {
        $current  = (string) config('changelog.current', '1.0.0');
        $releases = (array) config('changelog.releases', []);

        return view('changelog.index', [
            'currentVersion' => $current,
            'releases'       => $releases,
        ]);
    }
}
