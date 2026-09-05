<?php

namespace App\Console\Commands;

use App\Models\Hotel\LigacaoKiandaStay;
use App\Models\Hotel\Reservation;
use App\Services\Hotel\KiandaStay;
use App\Services\Hotel\ReceberDaKiandaStay;
use Illuminate\Console\Command;

/**
 * A ligação do hotel ao KiandaStay, vista da linha de comandos.
 *
 * Serve para o apoio: ver como está, provar que o site responde, voltar a
 * registar o webhook e trazer à mão uma reserva que se tenha perdido — que é o
 * remédio possível enquanto o site entregar cada evento uma vez só.
 */
class LigacaoKiandaStayCommand extends Command
{
    protected $signature = 'hotel:kiandastay
                            {--empresa= : id da empresa (sem isto, todas as que têm ligação)}
                            {--testar : pergunta ao site se está de pé}
                            {--ligar : volta a registar o webhook}
                            {--reserva= : traz uma reserva pelo código (OKB-...)}';

    protected $description = 'Estado e manutenção da ligação do hotel ao KiandaStay';

    public function handle(): int
    {
        $ligacoes = LigacaoKiandaStay::withoutGlobalScopes()
            ->when($this->option('empresa'), fn ($q) => $q->where('tenant_id', (int) $this->option('empresa')))
            ->with('tenant')
            ->get();

        if ($ligacoes->isEmpty()) {
            $this->warn('Nenhuma empresa tem ligação ao KiandaStay.');

            return self::SUCCESS;
        }

        foreach ($ligacoes as $l) {
            $this->newLine();
            $this->line('<fg=cyan>Empresa ' . $l->tenant_id . ' — ' . ($l->tenant->name ?? '?') . '</>');

            $this->table(['', ''], [
                ['Endereço',           $l->base_url ?: '—'],
                ['Chave da API',       $l->api_key ? 'guardada' : '—'],
                ['Segredo do webhook', $l->webhook_secret ? 'guardado' : '—'],
                ['Hotel no site',      $l->property_id ? ($l->property_name . ' (#' . $l->property_id . ')') : '—'],
                ['A receber',          $l->aReceber() ? 'SIM' : 'não'],
                ['Recebe em',          $l->urlDoWebhook()],
                ['Eventos recebidos',  $l->eventos_recebidos],
                ['Último evento',      $l->ultimo_evento_em?->diffForHumans() ?? '—'],
                ['Último erro',        $l->ultimo_erro ?: '—'],
                ['Reservas do canal',  Reservation::withoutGlobalScopes()
                    ->where('tenant_id', $l->tenant_id)
                    ->where('external_source', 'kiandastay')
                    ->count()],
            ]);

            if (! $l->configurada()) {
                $this->warn('  Falta o endereço ou a chave: nada mais a fazer nesta empresa.');

                continue;
            }

            $api = KiandaStay::para($l);

            if ($this->option('testar')) {
                $r = $api->estado();
                $r['ok']
                    ? $this->info('  O site respondeu: ' . json_encode($r['dados']))
                    : $this->error('  O site não respondeu: ' . $r['erro']);
            }

            if ($this->option('ligar')) {
                $r = $api->registarWebhook();
                $r['ok']
                    ? $this->info('  Webhook registado. A partir de agora as reservas entram sozinhas.')
                    : $this->error('  Não foi possível registar: ' . $r['erro']);
            }

            if ($codigo = $this->option('reserva')) {
                $dados = $api->reserva($codigo);

                if (! $dados) {
                    $this->error('  O site não conhece a reserva ' . $codigo . '.');

                    continue;
                }

                // Entra pelo mesmo caminho do webhook: uma reserva trazida à
                // mão tem de ficar exactamente igual a uma que tivesse chegado
                // sozinha, senão haveria duas maneiras de a mesma coisa existir.
                $reserva = app(ReceberDaKiandaStay::class)->processar($l, [
                    'event' => 'reservation.created',
                    'data'  => $dados,
                ]);

                $reserva
                    ? $this->info('  ' . $codigo . ' → ' . $reserva->reservation_number . ' (' . $reserva->status . ')')
                    : $this->warn('  ' . $codigo . ' não deu reserva nenhuma (é de outro hotel?).');
            }
        }

        return self::SUCCESS;
    }
}
