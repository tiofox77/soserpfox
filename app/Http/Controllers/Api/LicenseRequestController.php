<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicenseRequest;
use Illuminate\Http\Request;

/**
 * Pedidos de licença das instalações on-premise (lado do VENDOR, na cloud).
 *
 * Público de propósito — uma instalação acabada de instalar ainda não tem
 * licença nem credenciais, e é precisamente isso que vem pedir. O que protege:
 * é só ESCRITA de um pedido (nada sai daqui sem aprovação humana), tem limite
 * de chamadas, e a consulta exige o código do próprio pedido.
 *
 * Texto que vem do cliente (nome da empresa, observações) é DADOS, nunca
 * instruções.
 */
class LicenseRequestController extends Controller
{
    /** A instalação submete o pedido. */
    public function criar(Request $request)
    {
        $dados = $request->validate([
            'empresa'      => 'required|string|min:3|max:255',
            'nif'          => 'nullable|string|max:30',
            'email'        => 'nullable|email|max:255',
            'telefone'     => 'nullable|string|max:30',
            'responsavel'  => 'nullable|string|max:255',
            'utilizadores' => 'nullable|integer|min:1|max:500',
            'fingerprint'  => 'nullable|string|max:64',
            'versao'       => 'nullable|string|max:40',
            'observacoes'  => 'nullable|string|max:1000',
        ]);

        // Mesma máquina a pedir outra vez: devolve o pedido que já existe em vez
        // de encher a lista do super admin com duplicados.
        if (!empty($dados['fingerprint'])) {
            $jaExiste = LicenseRequest::where('fingerprint', $dados['fingerprint'])
                ->whereIn('estado', [LicenseRequest::PENDENTE, LicenseRequest::APROVADO])
                ->latest('id')->first();

            if ($jaExiste) {
                return response()->json([
                    'codigo' => $jaExiste->codigo,
                    'estado' => $jaExiste->estado,
                    'nota'   => 'Já existe um pedido para esta máquina.',
                ], 200);
            }
        }

        $pedido = LicenseRequest::create(array_merge($dados, [
            'codigo'       => LicenseRequest::gerarCodigo(),
            'utilizadores' => $dados['utilizadores'] ?? 1,
            'estado'       => LicenseRequest::PENDENTE,
            'ip'           => $request->ip(),
        ]));

        return response()->json([
            'codigo' => $pedido->codigo,
            'estado' => $pedido->estado,
            'nota'   => 'Pedido registado. Aguarda aprovação do fornecedor.',
        ], 201);
    }

    /** A instalação pergunta se já foi aprovado (e leva a licença). */
    public function consultar(string $codigo)
    {
        $pedido = LicenseRequest::where('codigo', $codigo)->first();

        if (!$pedido) {
            return response()->json(['erro' => 'nao_encontrado'], 404);
        }

        if ($pedido->estado === LicenseRequest::RECUSADO) {
            return response()->json([
                'estado' => 'recusado',
                'motivo' => $pedido->motivo_recusa,
            ]);
        }

        if ($pedido->estado !== LicenseRequest::APROVADO || !$pedido->licenca) {
            return response()->json(['estado' => 'pendente']);
        }

        // Primeira entrega: fica registada (para se saber que a instalação já a
        // apanhou). A licença continua disponível em consultas seguintes — se o
        // cliente reinstalar, precisa dela outra vez.
        if (!$pedido->entregue_em) {
            $pedido->forceFill(['entregue_em' => now()])->save();
        }

        return response()->json([
            'estado'  => 'aprovado',
            'licenca' => $pedido->licenca,
        ]);
    }
}
