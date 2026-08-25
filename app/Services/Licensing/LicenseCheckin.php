<?php

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Http;

/**
 * O lado CLIENTE do phone-home: liga ao servidor de licenças, dá sinal de vida
 * e aplica o que vier.
 *
 * A REGRA QUE PROTEGE TUDO: o contador offline só reinicia (e um bloqueio
 * remoto só se levanta) quando chega uma **licença renovada com assinatura
 * válida**. Uma resposta sem assinatura — de um servidor falso, de um
 * man-in-the-middle, de um proxy que devolve 200 a tudo — NÃO conta como
 * check-in. Assim não há forma de manter uma cópia viva a fingir um servidor.
 */
class LicenseCheckin
{
    public function __construct(
        private LicenseManager $manager,
        private array $cfg,
    ) {
    }

    public static function apartirDaConfig(): self
    {
        return new self(LicenseManager::apartirDaConfig(), config('licensing', []));
    }

    /**
     * @return array{ok:bool, acao?:string, motivo?:string, notificacoes?:array, proximo_minutos?:int|null}
     */
    public function executar(): array
    {
        $url = trim((string) ($this->cfg['checkin_url'] ?? ''));
        if ($url === '') {
            return ['ok' => false, 'motivo' => 'sem_url'];
        }

        $token = $this->manager->store()->token();
        if (!$token) {
            return ['ok' => false, 'motivo' => 'sem_licenca'];
        }

        try {
            $resp = Http::timeout(10)->acceptJson()->post($url, [
                'token'       => $token,
                'fingerprint' => MachineFingerprint::atual(),
                // config('app.version') NAO existe no Laravel — ia sempre nulo,
                // e no painel a versao instalada aparecia a traco.
                'versao'      => (string) ($this->cfg['update']['current'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            // Sem rede: não é erro, é o caso normal offline. O contador anda.
            return ['ok' => false, 'motivo' => 'sem_rede'];
        }

        if (!$resp->successful()) {
            return ['ok' => false, 'motivo' => 'http_' . $resp->status()];
        }

        $dados = (array) $resp->json();

        // 1) Renovação assinada — o ÚNICO caminho que reinicia o contador.
        if (!empty($dados['licenca'])) {
            $estado = $this->manager->verificarToken($dados['licenca'], [
                'fingerprint' => MachineFingerprint::atual(),
            ]);

            if ($estado->estado === LicenseState::INVALIDA) {
                // Assinatura má / outra máquina → NÃO conta como check-in.
                return ['ok' => false, 'motivo' => 'renovacao_invalida'];
            }

            $this->manager->store()->guardarToken($dados['licenca']);
            $this->manager->store()->registarCheckin();     // reinicia o contador
            $this->manager->store()->limparBloqueioRemoto(); // e levanta bloqueios

            return [
                'ok'           => true,
                'acao'         => 'renovada',
                'notificacoes' => $dados['notificacoes'] ?? [],
                // É o servidor que sabe quanto tempo falta a esta licença, por
                // isso é ele que manda quando voltar a ligar: quem tem horas
                // volta em minutos, quem tem meses só volta daqui a meio dia.
                'proximo_minutos' => isset($dados['proximo_checkin_minutos'])
                    ? max(5, min(1440, (int) $dados['proximo_checkin_minutos']))
                    : null,
            ];
        }

        // 2) Bloqueio explícito do fornecedor (via painel). Marca já — mesmo
        // que MITM não possa LEVANTAR isto sem uma renovação assinada.
        if (($dados['acao'] ?? null) === 'bloquear') {
            $this->manager->store()->marcarBloqueioRemoto($dados['motivo'] ?? 'suspensa');

            return ['ok' => true, 'acao' => 'bloquear', 'motivo' => $dados['motivo'] ?? 'suspensa'];
        }

        // 3) Qualquer outra coisa: sem assinatura válida → não mexe no contador.
        return ['ok' => false, 'motivo' => $dados['erro'] ?? 'sem_alteracao'];
    }
}
