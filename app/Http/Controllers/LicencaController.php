<?php

namespace App\Http\Controllers;

use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\MachineFingerprint;
use Illuminate\Http\Request;

/**
 * Ecrã de activação / estado da licença (build offline). É a única porta que
 * responde com o sistema bloqueado — por isso o middleware da licença deixa
 * `licenca*` sempre passar.
 */
class LicencaController extends Controller
{
    public function __construct(private LicenseManager $licencas)
    {
    }

    public function index()
    {
        return view('licenca.index', [
            'estado'      => $this->licencas->estado(true),
            'fingerprint' => MachineFingerprint::atual(),
        ]);
    }

    /** Instala um token colado no formulário, depois de o verificar. */
    public function guardar(Request $request)
    {
        $dados = $request->validate([
            'token' => 'required|string|min:20',
        ]);

        $token = trim($dados['token']);
        $estado = $this->licencas->verificarToken($token, [
            'fingerprint' => MachineFingerprint::atual(),
        ]);

        // Recusa uma licença que nem sequer é válida (assinatura/máquina/
        // formato). Estados brandos (aviso/expirada) deixam-se instalar — o
        // cliente pode estar a substituir por uma renovada.
        if ($estado->estado === \App\Services\Licensing\LicenseState::INVALIDA) {
            return back()->withInput()->with('erro', 'Licença recusada: ' . $estado->motivo);
        }

        $this->licencas->store()->guardarToken($token);

        return redirect()->route('licenca.index')
            ->with('ok', 'Licença instalada. Estado: ' . $estado->estado . '.');
    }
}
