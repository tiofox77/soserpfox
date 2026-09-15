<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * OS PRAZOS DE CONSERVAÇÃO DOS DADOS PESSOAIS — a limitação da conservação.
 *
 * RGPD art. 5.º-1-e, LGPD art. 16.º, Lei 22/11: os dados guardam-se pelo tempo
 * necessário à finalidade, e não mais. A Política de Privacidade promete prazos
 * (config/privacidade.php → retencao); isto cumpre-os.
 *
 * SIMULAÇÃO POR OMISSÃO: sem --aplicar só conta o que sairia. Apagar ou
 * truncar dados em produção é sempre por ordem de quem responde por eles.
 *
 * O que NÃO toca, de propósito: documentos fiscais, a trilha de auditoria (é
 * encadeada — apagar uma linha partia a prova de todas as seguintes), as
 * entradas falhadas (segurança) e os dados que as empresas guardam sobre os
 * clientes delas (delas é a decisão).
 */
class ReterDadosPessoais extends Command
{
    protected $signature = 'privacidade:reter {--aplicar : grava as alterações (sem isto, só simula)}';

    protected $description = 'Aplica os prazos de conservação dos dados pessoais da Política de Privacidade (simulação por omissão)';

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $r = config('privacidade.retencao');

        $this->info($aplicar ? 'A APLICAR os prazos de conservação' : 'SIMULAÇÃO (nada é gravado; use --aplicar)');

        $passos = [
            [
                'o quê' => "analytics: eventos com mais de {$r['analytics_eventos']} dias",
                'tabela' => 'analytics_events',
                'consulta' => fn () => DB::table('analytics_events')->where('created_at', '<', now()->subDays($r['analytics_eventos'])),
                'accao' => fn ($q) => $q->delete(),
            ],
            [
                'o quê' => "analytics: IP, browser e cidade das visitas com mais de {$r['analytics_ip']} dias",
                'tabela' => 'analytics_events',
                'consulta' => fn () => DB::table('analytics_events')->where('created_at', '<', now()->subDays($r['analytics_ip']))
                    ->where(fn ($w) => $w->whereNotNull('ip')->orWhereNotNull('user_agent')),
                'accao' => fn ($q) => $q->update(['ip' => null, 'user_agent' => null]),
            ],
            [
                // Os IPs de ANTES de a recolha passar a truncar (2026-09-15): o
                // IP inteiro fica x.x.x.0 já, sem esperar pelo prazo.
                'o quê' => 'analytics: IPv4 inteiros gravados antes da truncagem',
                'tabela' => 'analytics_events',
                'consulta' => fn () => DB::table('analytics_events')->whereNotNull('ip')->where('ip', 'like', '%.%')->where('ip', 'not like', '%.0'),
                'accao' => fn ($q) => $q->update(['ip' => DB::raw("CONCAT(SUBSTRING_INDEX(ip, '.', 3), '.0')")]),
            ],
            [
                'o quê' => "mensagens de contacto: IP com mais de {$r['mensagens_de_contacto_ip']} dias",
                'tabela' => 'contact_messages',
                'consulta' => fn () => DB::table('contact_messages')->whereNotNull('ip_address')->where('created_at', '<', now()->subDays($r['mensagens_de_contacto_ip'])),
                'accao' => fn ($q) => $q->update(['ip_address' => null]),
            ],
            [
                'o quê' => "convites por aceitar expirados há mais de {$r['convites_expirados']} dias",
                'tabela' => 'user_invitations',
                'consulta' => fn () => DB::table('user_invitations')->where('status', '!=', 'accepted')->where('expires_at', '<', now()->subDays($r['convites_expirados'])),
                'accao' => fn ($q) => $q->delete(),
            ],
            [
                'o quê' => 'pedidos de recuperação de senha expirados',
                'tabela' => 'password_reset_tokens',
                'consulta' => fn () => DB::table('password_reset_tokens')->where('created_at', '<', now()->subMinutes((int) config('auth.passwords.users.expire', 60))),
                'accao' => fn ($q) => $q->delete(),
            ],
            [
                'o quê' => "registo de emails: conteúdo com mais de {$r['registos_de_email_conteudo']} dias",
                'tabela' => 'email_logs',
                'coluna' => 'body_preview',
                'consulta' => fn () => DB::table('email_logs')->where('created_at', '<', now()->subDays($r['registos_de_email_conteudo']))
                    ->where(fn ($w) => $w->whereNotNull('body_preview')->orWhereNotNull('template_data')),
                'accao' => fn ($q) => $q->update(['body_preview' => null, 'template_data' => null]),
            ],
            [
                'o quê' => "consentimentos com mais de {$r['consentimentos']} dias",
                'tabela' => 'consentimentos',
                'consulta' => fn () => DB::table('consentimentos')->where('created_at', '<', now()->subDays($r['consentimentos'])),
                'accao' => fn ($q) => $q->delete(),
            ],
        ];

        $linhas = [];
        foreach ($passos as $passo) {
            if (! Schema::hasTable($passo['tabela']) || (isset($passo['coluna']) && ! Schema::hasColumn($passo['tabela'], $passo['coluna']))) {
                $linhas[] = [$passo['o quê'], '—', 'tabela ou coluna não existe'];

                continue;
            }

            try {
                $n = $passo['consulta']()->count();
                $feito = $aplicar && $n > 0 ? $passo['accao']($passo['consulta']()) : 0;
                $linhas[] = [$passo['o quê'], $n, $aplicar ? "tratados: {$feito}" : 'sairiam'];
            } catch (\Throwable $e) {
                $linhas[] = [$passo['o quê'], '—', 'erro: ' . mb_strimwidth($e->getMessage(), 0, 80, '…')];
            }
        }

        $this->table(['Prazo', 'Linhas', $aplicar ? 'Resultado' : 'Simulação'], $linhas);

        return self::SUCCESS;
    }
}
