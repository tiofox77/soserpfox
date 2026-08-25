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

        // ── UM SÓ NÚMERO, NOS DOIS SENTIDOS ──────────────────────────────
        //
        // O prazo offline tem de ser exactamente o da cloud. Como as duas
        // pontas só guardam valores ASSINADOS pelo fornecedor, a regra pode
        // ser simples e segura: ganha a decisão MAIS RECENTE.
        //
        //   painel mexeu depois  → a instalação recebe o número do painel
        //   licença instalada à  → a cloud adopta o número da licença (é uma
        //   mão, sem internet      decisão do fornecedor na mesma: veio
        //                          assinada, o cliente não a podia forjar)
        //
        // Sem isto, renovar um cliente sem rede por telefone era desfeito no
        // primeiro dia em que ele voltasse a ter internet.
        $decisao = $this->decidirPrazo($linha, $payload, (int) ($cfg['renew_days'] ?? 30));

        $expira   = $decisao['expira'];
        $modulos  = $decisao['modulos'] ?: ['*'];
        $maxUsers = $decisao['max_users'];
        $plano    = $decisao['plano'] ?: $tenant->activeSubscription?->plan?->name;

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

        // Regista o contacto e FIXA o número decidido. Escrever sempre o
        // `expira_em` é o que garante que o painel mostra exactamente o que a
        // instalação passa a contar — quer tenha ganho um lado, quer o outro.
        try {
            LicencaEmitida::updateOrCreate(
                ['tenant_id' => $tenant->id, 'fingerprint' => $fpToken],
                [
                    'plano'            => $plano,
                    'max_users'        => $maxUsers,
                    'modulos'          => $modulos,
                    'token'            => $novo,
                    'expira_em'        => $expira,
                    'emitida_em'       => now(),
                    'ultimo_checkin'   => now(),
                    'versao_instalada' => ($dados['versao'] ?? null) ?: null,
                    'ultimo_ip'        => $request->ip(),
                ]
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
     * Qual dos dois lados tem a decisão mais recente do fornecedor.
     *
     * Ambos os valores são vendor-signed: o da linha foi escrito pelo painel,
     * o do token foi assinado com a chave privada. Por isso dá para comparar
     * `iat` (quando a licença foi emitida) com `emitida_em` (quando o painel
     * mexeu) e deixar ganhar o mais novo — sem abrir a porta a ninguém: o
     * cliente não consegue forjar nem um `iat` mais recente nem um `exp` maior.
     *
     * Empate ou dúvida → manda a linha (o lado que o fornecedor controla).
     *
     * @return array{expira:CarbonImmutable, modulos:array, max_users:?int, plano:?string}
     */
    private function decidirPrazo(?LicencaEmitida $linha, $payload, int $renewDays): array
    {
        $expToken = $payload->expiraEm();
        $iatToken = $payload->emitidaEm();

        $temLinha = $linha && $linha->expira_em;

        // Só se dá a vitória ao cliente quando dá para PROVAR que é mais novo.
        // Sem `emitida_em` na linha não há prova — e nesse caso manda a cloud.
        $clienteMaisRecente = $expToken
            && $temLinha
            && $iatToken
            && $linha->emitida_em
            && $iatToken->gt(CarbonImmutable::instance($linha->emitida_em->toDateTime())->addSeconds(5));

        if ($temLinha && !$clienteMaisRecente) {
            return [
                'expira'    => CarbonImmutable::instance($linha->expira_em->toDateTime()),
                'modulos'   => $linha->modulos ? (array) $linha->modulos : ($payload->modulos() ?: []),
                'max_users' => $linha->max_users ? (int) $linha->max_users : ($payload->maxUtilizadores() ?: null),
                'plano'     => $linha->plano,
            ];
        }

        if ($expToken) {
            // O cliente traz a decisão mais recente (ou é o primeiro contacto
            // de uma licença emitida fora do painel): a cloud adopta-a.
            return [
                'expira'    => $expToken,
                'modulos'   => $payload->modulos(),
                'max_users' => $payload->maxUtilizadores() ?: null,
                'plano'     => $payload->plano(),
            ];
        }

        // Licença sem prazo e sem linha: só resta a validade por omissão.
        return [
            'expira'    => CarbonImmutable::now()->addDays($renewDays),
            'modulos'   => $payload->modulos(),
            'max_users' => $payload->maxUtilizadores() ?: null,
            'plano'     => $payload->plano(),
        ];
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
