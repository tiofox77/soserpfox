<?php

namespace App\Services\Salon;

use App\Models\Salon\Appointment;
use App\Models\Salon\AppointmentService;
use App\Models\Salon\Service;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * AS MARCAÇÕES DO SALÃO — gravar, e mudar de estado.
 *
 * O QUE ISTO CORRIGE, e é o principal: **não havia tabela de transições**. Os
 * métodos do modelo (`confirm`, `start`, `complete`, `cancel`) escreviam o
 * estado sem perguntar de onde vinham, e o ecrã mostrava os botões todos a
 * toda a hora. Dava para:
 *
 *   · CONCLUIR uma marcação cancelada — e ela passava a contar na receita;
 *   · CONFIRMAR quem já não compareceu;
 *   · COMEÇAR um atendimento que já tinha acabado, apagando a hora de início
 *     e com ela o tempo real — o número de que vive o relatório de tempos.
 *
 * Aqui há um mapa e uma só porta. O que o ecrã oferece sai deste mapa, e o que
 * o servidor aceita também: um botão que não devia existir deixa de funcionar
 * mesmo que alguém o invente no browser.
 *
 * A SOBREPOSIÇÃO TAMBÉM NÃO ERA VERIFICADA. Duas marcações à mesma hora com o
 * mesmo profissional entravam as duas, e só se descobria com as duas clientes
 * sentadas à espera da mesma pessoa.
 */
class Marcacoes
{
    /** @var array<string, list<string>> */
    public const TRANSICOES = [
        'scheduled' => ['confirmed', 'arrived', 'cancelled', 'no_show'],
        'confirmed' => ['arrived', 'in_progress', 'cancelled', 'no_show'],
        'arrived' => ['in_progress', 'cancelled', 'no_show'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    /** Os estados que ainda ocupam a agenda do profissional. */
    public const OCUPAM = ['scheduled', 'confirmed', 'arrived', 'in_progress'];

    /** @return list<string> */
    public function seguintes(Appointment $m): array
    {
        return self::TRANSICOES[$m->status] ?? [];
    }

    /**
     * Muda o estado — e só para onde se pode ir.
     *
     * Cada passo deixa a sua HORA gravada, e é dessas horas que saem o tempo de
     * espera e o tempo real do atendimento. Escrever o estado sem a hora era o
     * que fazia o relatório de tempos ter linhas vazias.
     */
    public function estado(Appointment $m, string $estado, ?string $motivo = null): Appointment
    {
        if (! in_array($estado, $this->seguintes($m), true)) {
            throw new InvalidArgumentException(__(
                'Uma marcação :de não pode passar a :para.',
                ['de' => __(Appointment::STATUSES[$m->status] ?? $m->status),
                 'para' => __(Appointment::STATUSES[$estado] ?? $estado)],
            ));
        }

        match ($estado) {
            'confirmed' => $m->confirm(),
            'arrived' => $m->markArrived(),
            'in_progress' => $m->start(),
            'completed' => $m->complete($m->total, $m->payment_method ?: 'cash'),
            'cancelled' => $m->cancel($motivo ?: __('Cancelado no ecrã das marcações')),
            'no_show' => $m->markNoShow(),
            default => null,
        };

        return $m->fresh(['client', 'professional', 'services.service']);
    }

    /**
     * Grava uma marcação — nova ou existente.
     *
     * A DURAÇÃO E O PREÇO SAEM DOS SERVIÇOS e não do formulário: quem marca
     * escolhe serviços, e é o catálogo que diz quanto tempo levam e quanto
     * custam. Um preço vindo do browser era um desconto que ninguém autorizou.
     */
    public function guardar(array $dados, int $tenantId, ?int $userId, ?Appointment $marcacao = null): Appointment
    {
        return DB::transaction(function () use ($dados, $tenantId, $userId, $marcacao) {
            $servicos = Service::where('tenant_id', $tenantId)
                ->whereIn('id', $dados['service_ids'])
                ->get();

            if ($servicos->count() !== count(array_unique($dados['service_ids']))) {
                throw new InvalidArgumentException(__('Há serviços que não são desta empresa.'));
            }

            $duracao = (int) $servicos->sum('duration');
            $inicio = Carbon::parse($dados['start_time']);
            $fim = $inicio->copy()->addMinutes($duracao);
            $subtotal = (float) $servicos->sum('price');

            $this->confirmarQueCabe(
                $tenantId, (int) $dados['professional_id'], $dados['date'],
                $inicio->format('H:i'), $fim->format('H:i'), $marcacao?->id,
            );

            $valores = [
                'client_id' => $dados['client_id'] ?? null,
                'professional_id' => $dados['professional_id'],
                'date' => $dados['date'],
                'start_time' => $inicio->format('H:i'),
                'end_time' => $fim->format('H:i'),
                'total_duration' => $duracao,
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => $dados['notes'] ?? null,
                'source' => $dados['source'] ?? 'system',
            ];

            if ($marcacao) {
                $marcacao->update($valores);
                $marcacao->services()->delete();
            } else {
                $marcacao = Appointment::create($valores + [
                    'tenant_id' => $tenantId,
                    'created_by' => $userId,
                ]);
            }

            foreach ($servicos as $servico) {
                AppointmentService::create([
                    'appointment_id' => $marcacao->id,
                    'service_id' => $servico->id,
                    'professional_id' => $dados['professional_id'],
                    'duration' => $servico->duration,
                    'price' => $servico->price,
                    'total' => $servico->price,
                ]);
            }

            return $marcacao->fresh(['client', 'professional', 'services.service']);
        });
    }

    /**
     * O PROFISSIONAL NÃO SE DESDOBRA.
     *
     * Duas marcações sobrepostas com a mesma pessoa entravam as duas — e só se
     * descobria com as duas clientes sentadas à espera dela. A comparação é a
     * de sempre para intervalos: começa antes de o outro acabar E acaba depois
     * de o outro começar. Encostadas (uma acaba às 10:00, a outra começa às
     * 10:00) não se sobrepõem.
     */
    private function confirmarQueCabe(
        int $tenantId, int $profissional, string $dia, string $inicio, string $fim, ?int $excepto,
    ): void {
        $choque = Appointment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('professional_id', $profissional)
            ->whereDate('date', $dia)
            ->whereIn('status', self::OCUPAM)
            ->when($excepto, fn ($q) => $q->whereKeyNot($excepto))
            ->where('start_time', '<', $fim)
            ->where('end_time', '>', $inicio)
            ->exists();

        if ($choque) {
            throw new InvalidArgumentException(__(
                'Este profissional já tem uma marcação entre as :de e as :ate.',
                ['de' => $inicio, 'ate' => $fim],
            ));
        }
    }
}
