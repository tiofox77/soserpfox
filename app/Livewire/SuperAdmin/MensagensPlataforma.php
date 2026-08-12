<?php

namespace App\Livewire\SuperAdmin;

use App\Models\PlatformMessage;
use App\Models\Plan;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Onde o dono da plataforma escreve às empresas.
 *
 * Antes disto não havia forma nenhuma: uma paragem para manutenção, uma
 * mudança de preços, uma obrigação nova da AGT, um aviso a um cliente em
 * particular — tudo saía por WhatsApp, à mão, empresa a empresa, e ninguém
 * sabia quem tinha lido.
 */
#[Layout('layouts.superadmin')]
#[Title('Mensagens às empresas')]
class MensagensPlataforma extends Component
{
    use WithPagination;

    public bool $showModal = false;
    public ?int $editandoId = null;

    public string $title = '';
    public string $body = '';
    public string $level = 'info';
    public string $display = 'popup';
    public string $audience = 'todas';
    public array $tenant_ids = [];
    public array $plan_ids = [];
    public ?string $starts_at = null;
    public ?string $ends_at = null;
    public bool $dismissible = true;
    public string $link_url = '';
    public string $link_label = '';
    public bool $is_active = true;

    /** A mensagem cujas leituras estão abertas. */
    public ?int $aVerLeiturasDe = null;

    private function apenasDonoDaPlataforma(): void
    {
        abort_unless(auth()->check() && auth()->user()->isPlatformSuperAdmin(), 403);
    }

    protected function rules(): array
    {
        return [
            'title'       => 'required|string|min:3|max:255',
            'body'        => 'required|string|min:5|max:5000',
            'level'       => 'required|in:' . implode(',', array_keys(PlatformMessage::NIVEIS)),
            'display'     => 'required|in:' . implode(',', array_keys(PlatformMessage::FORMAS)),
            'audience'    => 'required|in:' . implode(',', array_keys(PlatformMessage::PUBLICOS)),
            'tenant_ids'  => 'array',
            'plan_ids'    => 'array',
            'starts_at'   => 'nullable|date',
            'ends_at'     => 'nullable|date|after_or_equal:starts_at',
            'link_url'    => 'nullable|url|max:255',
            'link_label'  => 'nullable|string|max:80',
        ];
    }

    protected function messages(): array
    {
        return [
            'title.required'      => 'A mensagem precisa de um título.',
            'body.required'       => 'Escreva a mensagem — é o que as pessoas vão ler.',
            'ends_at.after_or_equal' => 'O fim não pode ser antes do início.',
            'link_url.url'        => 'A ligação tem de ser um endereço completo (https://…).',
        ];
    }

    public function nova(): void
    {
        $this->apenasDonoDaPlataforma();

        $this->reset([
            'editandoId', 'title', 'body', 'tenant_ids', 'plan_ids',
            'starts_at', 'ends_at', 'link_url', 'link_label',
        ]);
        $this->level       = 'info';
        $this->display     = 'popup';
        $this->audience    = 'todas';
        $this->dismissible = true;
        $this->is_active   = true;
        $this->resetValidation();
        $this->showModal   = true;
    }

    public function editar(int $id): void
    {
        $this->apenasDonoDaPlataforma();

        $m = PlatformMessage::findOrFail($id);

        $this->editandoId  = $m->id;
        $this->title       = $m->title;
        $this->body        = $m->body;
        $this->level       = $m->level;
        $this->display     = $m->display;
        $this->audience    = $m->audience;
        $this->tenant_ids  = array_map('intval', $m->tenant_ids ?? []);
        $this->plan_ids    = array_map('intval', $m->plan_ids ?? []);
        $this->starts_at   = $m->starts_at?->format('Y-m-d\TH:i');
        $this->ends_at     = $m->ends_at?->format('Y-m-d\TH:i');
        $this->dismissible = (bool) $m->dismissible;
        $this->link_url    = (string) $m->link_url;
        $this->link_label  = (string) $m->link_label;
        $this->is_active   = (bool) $m->is_active;

        $this->resetValidation();
        $this->showModal = true;
    }

    public function fechar(): void
    {
        $this->showModal = false;
        $this->reset(['editandoId', 'title', 'body', 'tenant_ids', 'plan_ids', 'starts_at', 'ends_at', 'link_url', 'link_label']);
        $this->resetValidation();
    }

    public function guardar(): void
    {
        $this->apenasDonoDaPlataforma();

        $this->validate();

        // Escolher "empresas escolhidas" e não escolher nenhuma manda a
        // mensagem para ninguém — e o ecrã dizia "guardada com sucesso".
        if ($this->audience === 'empresas' && empty($this->tenant_ids)) {
            $this->addError('tenant_ids', 'Escolha pelo menos uma empresa, ou mude o público para "todas".');
            return;
        }

        if ($this->audience === 'planos' && empty($this->plan_ids)) {
            $this->addError('plan_ids', 'Escolha pelo menos um plano, ou mude o público para "todas".');
            return;
        }

        $dados = [
            'title'       => $this->title,
            'body'        => $this->body,
            'level'       => $this->level,
            'display'     => $this->display,
            'audience'    => $this->audience,
            'tenant_ids'  => $this->audience === 'empresas' ? array_values(array_map('intval', $this->tenant_ids)) : null,
            'plan_ids'    => $this->audience === 'planos'   ? array_values(array_map('intval', $this->plan_ids))   : null,
            'starts_at'   => $this->starts_at ?: null,
            'ends_at'     => $this->ends_at ?: null,
            'dismissible' => $this->dismissible,
            'link_url'    => $this->link_url ?: null,
            'link_label'  => $this->link_label ?: null,
            'is_active'   => $this->is_active,
        ];

        if ($this->editandoId) {
            PlatformMessage::findOrFail($this->editandoId)->update($dados);
            $this->dispatch('success', message: 'Mensagem actualizada.');
        } else {
            $dados['created_by'] = auth()->id();
            PlatformMessage::create($dados);
            $this->dispatch('success', message: 'Mensagem no ar.');
        }

        // O componente que a mostra guarda as mensagens no ar por um minuto;
        // sem isto, uma mensagem urgente só aparecia daí a um minuto.
        \Cache::forget('mensagens-plataforma-no-ar');

        $this->fechar();
    }

    public function alternarActiva(int $id): void
    {
        $this->apenasDonoDaPlataforma();

        $m = PlatformMessage::findOrFail($id);
        $m->update(['is_active' => !$m->is_active]);

        \Cache::forget('mensagens-plataforma-no-ar');

        $this->dispatch('success', message: $m->is_active ? 'Mensagem no ar.' : 'Mensagem retirada.');
    }

    public function apagar(int $id): void
    {
        $this->apenasDonoDaPlataforma();

        PlatformMessage::findOrFail($id)->delete();
        \Cache::forget('mensagens-plataforma-no-ar');

        $this->dispatch('success', message: 'Mensagem apagada.');
    }

    public function verLeituras(int $id): void
    {
        $this->aVerLeiturasDe = $this->aVerLeiturasDe === $id ? null : $id;
    }

    public function render()
    {
        $this->apenasDonoDaPlataforma();

        $mensagens = PlatformMessage::with('autor')
            ->withCount([
                'leituras as vistas',
                'leituras as dispensadas' => fn ($q) => $q->whereNotNull('dismissed_at'),
            ])
            ->orderByDesc('id')
            ->paginate(15);

        $leituras = collect();

        if ($this->aVerLeiturasDe) {
            $leituras = \App\Models\PlatformMessageRead::with(['user:id,name,email'])
                ->where('platform_message_id', $this->aVerLeiturasDe)
                ->orderByDesc('seen_at')
                ->limit(100)
                ->get();
        }

        return view('livewire.super-admin.mensagens-plataforma', [
            'mensagens' => $mensagens,
            'leituras'  => $leituras,
            'empresas'  => Tenant::orderBy('name')->get(['id', 'name']),
            'planos'    => Plan::where('is_active', true)->orderBy('order')->get(['id', 'name']),
        ]);
    }
}
