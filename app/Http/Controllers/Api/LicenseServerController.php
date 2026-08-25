<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LicencaEmitida;
use App\Models\Tenant;
use App\Services\Licensing\LicenseIssuer;
use App\Services\Licensing\LicenseVerifier;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * SERVIDOR de licenças (lado do vendor, corre na cloud): recebe o check-in de
 * uma instalação offline e decide.
 *
 * Confia no token pela ASSINATURA (só o vendor a produz) para saber QUEM é o
 * tenant; decide o resto pela base — não pelo que o cliente diz.
 *
 * QUEM MANDA NO PRAZO É O PAINEL. A linha em `licencas_emitidas` é a fonte de
 * verdade: prazo, módulos, tecto de utilizadores. Antes isto emitia cegamente
 * `renew_days` (30) e REESCREVIA o `expira_em` da linha — ou seja, o super
 * admin punha 1 dia e o check-in seguinte devolvia 30, apagando a decisão.
 *
 * Respostas:
 *   { "licenca": "<token>", "proximo_checkin_minutos": N }  → renova
 *   { "acao": "bloquear", "motivo": "..." }                 → suspensa/desconhecida
 *   { "erro": "renovacao_indisponivel" }                    → sem chave privada
 *
 * Segurança do cliente: ele só reinicia o contador com a licença renovada
 * ASSINADA. Um "acao: continuar" à solta não o safa.
 */
class LicenseServerController extends Controller
{
    public function checkin(Request $request)
    {
        $dados = $request->validate([
            'token'       => 'required|string',
            'fingerprint' => 'nullable|string|max:64',
            'versao'      => 'nullable|string|max:40',
        ]);

        $cfg = config('licensing', []);
        $pub = $cfg['public_key'] ?? '';
        if (!$pub) {
            return response()->json(['erro' => 'servidor_sem_chave_publica'], 500);
        }

        // Quem é? (só a assinatura importa aqui — vigência/máquina decide o cliente)
        $payload = (new LicenseVerifier($pub, $cfg))->payloadAssinado($dados['token']);
        if (!$payload || !$payload->tenantId()) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'assinatura_invalida'], 200);
        }

        $tenant = Tenant::find($payload->tenantId());
        if (!$tenant) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'empresa_desconhecida'], 200);
        }

        // Licença presa a uma máquina a pedir renovação de OUTRA: é uma cópia.
        // O cliente honesto já se recusaria; recusar também aqui evita que uma
        // cópia se mantenha viva a colher licenças novas.
        $fpToken = $payload->fingerprint();
        $fpReal  = $dados['fingerprint'] ?? null;
        if ($fpToken && $fpReal && !hash_equals((string) $fpToken, (string) $fpReal)) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'maquina_diferente'], 200);
        }

        // Estado na base manda. is_active é o que o painel/openclaw já mexe ao
        // suspender — logo, "suspender na cloud" bloqueia no próximo check-in.
        if (!$tenant->is_active) {
            return response()->json(['acao' => 'bloquear', 'motivo' => 'subscricao_suspensa'], 200);
        }

        $secret = $cfg['signing_key'] ?? null;
        if (!$secret) {
            // Servidor não configurado para renovar: não bloqueia, mas também
            // não renova — o cliente não reinicia o contador.
            return response()->json(['erro' => 'renovacao_indisponivel'], 200);
        }

        $linha = $this->linhaDaInstalacao($tenant->id, $fpToken);

        // O painel decidiu um prazo? Então é esse. Só quando não há linha
        // nenhuma (primeiro contacto de sempre) é que se usa `renew_days`.
        $painelMandou = $linha && $linha->expira_em;
        $expira = $painelMandou
            ? CarbonImmutable::instance($linha->expira_em->toDateTime())
            : CarbonImmutable::now()->addDays((int) ($cfg['renew_days'] ?? 30));

        $modulos  = $linha && $linha->modulos ? (array) $linha->modulos : ($payload->modulos() ?: ['*']);
        $maxUsers = $linha && $linha->max_users ? (int) $linha->max_users : ($payload->maxUtilizadores() ?: null);
        $plano    = $linha?->plano ?: $tenant->activeSubscription?->plan?->name;

        try {
            $novo = (new LicenseIssuer())->emitir(array_filter([
                'tenant_id' => $tenant->id,
                'empresa'   => $tenant->name,
                'nif'       => $tenant->nif,
                'plano'     => $plano,
                'modulos'   => $modulos,
                'max_users' => $maxUsers,
                'fp'        => $fpToken,   // mantém o binding original
                'graca'     => $payload->gracaDias() ?? ($cfg['offline_grace_days'] ?? 15),
                'exp'       => $expira->getTimestamp(),
            ], fn ($v) => $v !== null && $v !== []), $secret);
        } catch (\Throwable $e) {
            return response()->json(['erro' => 'falha_ao_renovar'], 500);
        }

        // Regista o contacto: é o que alimenta a lista de clientes offline no
        // painel. NUNCA mexe no `expira_em` quando foi o painel a decidi-lo.
        try {
            $mudancas = [
                'plano'            => $plano,
                'max_users'        => $maxUsers,
                'modulos'          => $modulos,
                'token'            => $novo,
                'emitida_em'       => now(),
                'ultimo_checkin'   => now(),
                'versao_instalada' => ($dados['versao'] ?? null) ?: null,
                'ultimo_ip'        => $request->ip(),
            ];

            if (!$painelMandou) {
                $mudancas['expira_em'] = $expira;
            }

            LicencaEmitida::updateOrCreate(
                ['tenant_id' => $tenant->id, 'fingerprint' => $fpToken],
                $mudancas
            );
        } catch (\Throwable $e) {
            // O registo é secundário — a licença renovada é que interessa e já
            // vai. Mas engolir isto em silêncio deixava o painel a dizer "nunca
            // ligou" de uma instalação que liga todos os dias, sem rasto.
            \Log::warning('checkin: falhou o registo da instalação', [
                'tenant_id' => $tenant->id,
                'erro'      => $e->getMessage(),
            ]);
        }

        return response()->json([
            'licenca'                 => $novo,
            'proximo_checkin_minutos' => $this->cadencia($expira),
            'notificacoes'            => [],
        ]);
    }

    /**
     * A linha desta instalação. Uma licença flutuante (sem fingerprint) e uma
     * presa à máquina são linhas diferentes; sem correspondência exacta,
     * aceita-se a única que a empresa tenha — senão o painel e o check-in
     * ficavam a falar de linhas diferentes.
     */
    private function linhaDaInstalacao(int $tenantId, ?string $fp): ?LicencaEmitida
    {
        $exacta = LicencaEmitida::where('tenant_id', $tenantId)
            ->where('fingerprint', $fp)
            ->first();

        if ($exacta) {
            return $exacta;
        }

        $todas = LicencaEmitida::where('tenant_id', $tenantId)->get();

        return $todas->count() === 1 ? $todas->first() : null;
    }

    /**
     * De quanto em quanto tempo esta instalação deve voltar a ligar.
     *
     * Quem tem meses de licença não precisa de incomodar o servidor; quem tem
     * horas precisa de apanhar a renovação depressa — senão o painel diz uma
     * coisa e o ecrã do cliente diz outra durante meio dia.
     */
    private function cadencia(CarbonImmutable $expira): int
    {
        $horas = CarbonImmutable::now()->diffInHours($expira, false);

        if ($horas <= 48) {
            return 10;
        }

        if ($horas <= 24 * 7) {
            return 60;
        }

        return 12 * 60;
    }
}
