<?php

namespace App\Services\Salon;

use App\Models\Salon\Appointment;
use App\Models\Salon\Client;
use App\Models\Salon\Professional;
use App\Models\Salon\SalonSettings;
use App\Models\Salon\Service;
use App\Models\Salon\ServiceCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * A PÁGINA PÚBLICA DO SALÃO — a montra e a marcação.
 *
 * Sem sessão de empresa: QUEM MANDA É O SLUG. Todas as leituras levam o
 * `tenant_id` do salão e tiram o escopo global de empresa, que aqui não serve
 * (e que, a quem tem a sua empresa aberta noutro separador, filtraria pela
 * empresa ERRADA).
 *
 * O QUE ESTA MIGRAÇÃO FECHA, que no Livewire estava aberto ao browser:
 *
 *  • A HORA NÃO ERA VERIFICADA AO MARCAR. As propriedades públicas vinham do
 *    browser, e dava para marcar às 03:00, num domingo fechado ou por cima de
 *    outra cliente. Agora a hora tem de estar nos horários que o servidor
 *    calcula nesse instante.
 *  • OS SERVIÇOS E O PROFISSIONAL eram aceites por id solto: um serviço fora
 *    da marcação online, ou um profissional que não aceita marcações.
 *  • ENTRAR SÓ COM O TELEFONE mostrava o nome e as últimas marcações de quem
 *    quer que tivesse aquele número. Entrar passa a exigir a senha da conta.
 */
class AgendamentoOnline
{
    public function __construct(private readonly SalonSettings $d)
    {
    }

    public static function numero(?string $telefone): string
    {
        return preg_replace('/[^0-9]/', '', (string) $telefone);
    }

    private function servicos(): Collection
    {
        return Service::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->d->tenant_id)
            ->where('is_active', true)
            ->onlineBooking()
            ->orderBy('name')
            ->get();
    }

    private function profissionais(): Collection
    {
        return Professional::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->d->tenant_id)
            ->where('is_active', true)
            ->where('accepts_online_booking', true)
            ->orderBy('name')
            ->get();
    }

    /** Tudo o que a montra precisa: vai no HTML, e a página abre sem mais pedidos. */
    public function paraAPagina(): array
    {
        $d = $this->d;
        $categorias = ServiceCategory::withoutGlobalScope('tenant')
            ->where('tenant_id', $d->tenant_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
        $porId = $categorias->keyBy('id');

        return [
            'casa' => [
                'nome' => $d->salon_name,
                'descricao' => $d->salon_description,
                'boas_vindas' => $d->welcome_message ?? null,
                'morada' => $d->salon_address,
                'telefone' => $d->salon_phone,
                'whatsapp' => self::numero($d->salon_whatsapp) ?: null,
                'email' => $d->salon_email,
                'instagram' => $d->salon_instagram,
                'facebook' => $d->salon_facebook,
                'tiktok' => $d->salon_tiktok,
                'mapa' => $d->salon_google_maps_url ? str_replace('/maps/', '/maps/embed?pb=', $d->salon_google_maps_url) : null,
                'logo' => $d->logo_url,
                'capa' => $d->cover_url,
                'cor' => $d->primary_color ?: '#ec4899',
                'cor2' => $d->secondary_color ?: '#8b5cf6',
                'horario' => $d->schedule_formatted,
                'dias' => $d->working_days_formatted,
                'exige_confirmacao' => (bool) ($d->require_confirmation ?? true),
                'galeria' => collect($d->gallery_images ?? [])->map(fn ($i) => filter_var($i, FILTER_VALIDATE_URL) ? $i : Storage::url($i))->values()->all(),
            ],
            'categorias' => $categorias->map(fn ($c) => ['id' => $c->id, 'nome' => $c->name, 'cor' => $c->color, 'icone' => $c->icon])->values()->all(),
            'servicos' => $this->servicos()->map(fn (Service $s) => [
                'id' => $s->id,
                'nome' => $s->name,
                'descricao' => $s->text_description ?: null,
                'preco' => (float) $s->price,
                'duracao' => (int) $s->duration,
                'duracao_texto' => $s->duration_formatted,
                'categoria_id' => $s->category_id ? (int) $s->category_id : null,
                'categoria' => $s->category_id && $porId->has($s->category_id) ? ['nome' => $porId[$s->category_id]->name, 'cor' => $porId[$s->category_id]->color] : null,
            ])->values()->all(),
            'profissionais' => $this->profissionais()->map(fn (Professional $p) => [
                'id' => $p->id,
                'nome' => $p->nickname ?: $p->name,
                'especialidade' => $p->specialization,
                'foto' => $p->photo ? (filter_var($p->photo, FILTER_VALIDATE_URL) ? $p->photo : Storage::url($p->photo)) : null,
            ])->values()->all(),
            'datas' => $this->datas(),
        ];
    }

    /** Os dias em que o salão abre, de amanhã até ao limite de antecedência. */
    public function datas(): array
    {
        $datas = [];
        $inicio = now()->addDay()->startOfDay();
        $dias = $this->d->working_days ?? [1, 2, 3, 4, 5, 6];

        for ($i = 0; $i < ($this->d->max_advance_booking_days ?? 30); $i++) {
            $dia = $inicio->copy()->addDays($i);
            if (in_array($dia->dayOfWeekIso, $dias)) {
                $datas[] = $dia->toDateString();
            }
        }

        return $datas;
    }

    /** @param list<int> $ids */
    private function servicosEscolhidos(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $servicos = $this->servicos()->whereIn('id', $ids);

        if (! $ids || $servicos->count() !== count($ids)) {
            throw ValidationException::withMessages(['servicos' => __('Há serviços que não se marcam por aqui.')]);
        }

        return $servicos->values();
    }

    private function profissional(int $id): Professional
    {
        $p = $this->profissionais()->firstWhere('id', $id);

        if (! $p) {
            throw ValidationException::withMessages(['profissional' => __('Esse profissional não aceita marcações por aqui.')]);
        }

        return $p;
    }

    /**
     * OS HORÁRIOS LIVRES de um profissional num dia, para a duração dos serviços.
     *
     * A régua de sempre: o dia tem de ser de trabalho dele (ou do salão), dentro
     * do horário, fora do almoço, com a antecedência mínima, e sem tocar numa
     * marcação que ainda ocupe a agenda.
     *
     * @param  list<int>  $servicoIds
     * @return list<string>
     */
    public function horarios(int $profissionalId, string $data, array $servicoIds): array
    {
        $p = $this->profissional($profissionalId);
        $duracao = (int) $this->servicosEscolhidos($servicoIds)->sum('duration');

        if (! in_array($data, $this->datas(), true)) {
            return [];
        }

        $dia = Carbon::parse($data);
        $diasDeTrabalho = $p->working_days ?: ($this->d->working_days ?? []);
        if (! in_array($dia->dayOfWeekIso, $diasDeTrabalho)) {
            return [];
        }

        $naHora = fn ($h, string $omissao) => Carbon::parse($dia->toDateString().' '.Carbon::parse($h ?: $omissao)->format('H:i'));

        $abre = $naHora($p->work_start ?? $this->d->opening_time, '09:00');
        $fecha = $naHora($p->work_end ?? $this->d->closing_time, '19:00');
        $almoco = ($p->lunch_start && $p->lunch_end) ? [$naHora($p->lunch_start, '12:00'), $naHora($p->lunch_end, '13:00')] : null;
        $intervalo = max(5, (int) ($this->d->slot_interval ?? 30));
        $minimo = now()->addHours((int) ($this->d->min_advance_booking_hours ?? 2));

        $ocupadas = Appointment::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->d->tenant_id)
            ->where('professional_id', $p->id)
            ->whereDate('date', $data)
            ->whereIn('status', Marcacoes::OCUPAM)
            ->get(['start_time', 'end_time'])
            ->map(fn ($m) => [$naHora($m->start_time, '00:00'), $naHora($m->end_time, '00:00')]);

        $livres = [];
        for ($inicio = $abre->copy(); $inicio->copy()->addMinutes($duracao)->lte($fecha); $inicio->addMinutes($intervalo)) {
            $fim = $inicio->copy()->addMinutes($duracao);

            if ($inicio->lt($minimo)) {
                continue;
            }
            if ($almoco && $inicio->lt($almoco[1]) && $fim->gt($almoco[0])) {
                continue;
            }
            if ($ocupadas->contains(fn ($o) => $inicio->lt($o[1]) && $fim->gt($o[0]))) {
                continue;
            }

            $livres[] = $inicio->format('H:i');
        }

        return $livres;
    }

    // ------------------------------------------------------------ a cliente

    private function porTelefone(string $telefone): ?Client
    {
        $numero = self::numero($telefone);

        if (strlen($numero) < 6) {
            return null;
        }

        return Client::where('tenant_id', $this->d->tenant_id)
            ->where(fn ($q) => $q->where('phone', 'like', "%{$numero}%")->orWhere('mobile', 'like', "%{$numero}%"))
            ->first();
    }

    /** ENTRAR PEDE A SENHA. Só com o telefone, qualquer pessoa via a ficha de outra. */
    public function entrar(string $telefone, string $senha): Client
    {
        $c = $this->porTelefone($telefone);

        if (! $c) {
            throw ValidationException::withMessages(['telefone' => __('Cliente não encontrado. Crie uma conta ou faça reserva rápida.')]);
        }

        $guardada = $c->salon_data['password'] ?? null;

        if (! $guardada) {
            throw ValidationException::withMessages(['telefone' => __('Esta ficha ainda não tem senha. Faça uma reserva rápida — o salão já a conhece pelo telefone.')]);
        }

        if (! Hash::check($senha, $guardada)) {
            throw ValidationException::withMessages(['password' => __('Password incorreta')]);
        }

        return $c;
    }

    public function registar(string $nome, string $telefone, ?string $email, string $senha): Client
    {
        if ($this->porTelefone($telefone)) {
            throw ValidationException::withMessages(['telefone' => __('Já existe uma conta com este telefone. Faça login.')]);
        }

        $c = Client::create([
            'tenant_id' => $this->d->tenant_id,
            'name' => $nome,
            'phone' => $telefone,
            'mobile' => $telefone,
            'email' => $email ?: null,
            // A coluna é um enum: sem isto a ficha não chegava a ser criada.
            'type' => 'pessoa_fisica',
            'is_active' => true,
        ]);

        $c->updateSalonData([
            'password' => Hash::make($senha),
            'registered_at' => now()->toISOString(),
        ]);

        return $c;
    }

    /** O que se mostra a quem entrou: o nome, o telefone, os pontos, e as últimas marcações. */
    public function paraACliente(Client $c): array
    {
        $marcacoes = Appointment::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->d->tenant_id)
            ->where('client_id', $c->id)
            ->orderByDesc('date')->orderByDesc('start_time')
            ->limit(3)
            ->get(['id', 'date', 'start_time', 'status']);

        return [
            'nome' => $c->name,
            'telefone' => $c->phone ?? $c->mobile,
            'email' => $c->email,
            'vip' => (bool) $c->is_vip,
            'pontos' => (int) $c->loyalty_points,
            'marcacoes' => $marcacoes->map(fn ($m) => [
                'data' => $m->date?->toDateString(),
                'hora' => substr((string) $m->start_time, 0, 5),
                'estado' => $m->status,
            ])->values()->all(),
        ];
    }

    /**
     * MARCAR — com a hora recalculada aqui, no instante de gravar.
     *
     * @param  array{servicos:list<int>,profissional:int,data:string,hora:string,notas?:?string}  $pedido
     */
    public function marcar(array $pedido, ?Client $cliente, ?array $convidada): Appointment
    {
        $servicos = $this->servicosEscolhidos($pedido['servicos']);
        $profissional = $this->profissional((int) $pedido['profissional']);

        if (! in_array($pedido['hora'], $this->horarios($profissional->id, $pedido['data'], $pedido['servicos']), true)) {
            throw ValidationException::withMessages(['hora' => __('Esse horário já não está livre. Escolha outro.')]);
        }

        return DB::transaction(function () use ($pedido, $cliente, $convidada, $servicos, $profissional) {
            if (! $cliente) {
                // A reserva rápida: a ficha encontra-se pelo telefone, ou nasce.
                $cliente = $this->porTelefone($convidada['telefone']) ?? Client::create([
                    'tenant_id' => $this->d->tenant_id,
                    'name' => $convidada['nome'],
                    'phone' => $convidada['telefone'],
                    'mobile' => $convidada['telefone'],
                    'email' => $convidada['email'] ?: null,
                    'type' => 'pessoa_fisica',
                    'is_active' => true,
                ]);
            }

            if (! empty($convidada['email']) && ! $cliente->email) {
                $cliente->update(['email' => $convidada['email']]);
            }

            try {
                $m = app(Marcacoes::class)->guardar([
                    'service_ids' => $servicos->pluck('id')->all(),
                    'professional_id' => $profissional->id,
                    'client_id' => $cliente->id,
                    'date' => $pedido['data'],
                    'start_time' => $pedido['hora'],
                    'notes' => $pedido['notas'] ?? null,
                    'source' => 'website',
                ], $this->d->tenant_id, null);
            } catch (InvalidArgumentException $e) {
                throw ValidationException::withMessages(['hora' => $e->getMessage()]);
            }

            $m->update($this->d->require_confirmation ?? true
                ? ['status' => 'scheduled']
                : ['status' => 'confirmed', 'confirmed_at' => now()]);

            return $m->fresh();
        });
    }
}
