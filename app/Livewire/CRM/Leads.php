<?php

namespace App\Livewire\CRM;

use App\Models\CRM\Activity;
use App\Models\CRM\Lead;
use App\Models\CRM\MetaContact;
use App\Models\CRM\MetaIntegration;
use App\Services\CRM\ConversaoDeLead;
use App\Services\CRM\EnviarPeloMeta;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Os leads: quem ainda não é cliente mas pode vir a ser.
 *
 * O ecrã é uma fila de trabalho, não um arquivo: criar é uma linha (nome +
 * telefone + origem, Enter), e cada cartão tem os três destinos possíveis à
 * vista — avançar no estado, converter em cliente, ou perder com motivo.
 */
#[Layout('layouts.app')]
#[Title('Leads - CRM')]
class Leads extends Component
{
    use WithPagination;

    public string $procurar = '';

    public string $filtroEstado = 'abertos';

    // O lead novo — o mínimo para começar uma conversa.
    public string $novoNome = '';

    public string $novoTelefone = '';

    public string $novaEmpresa = '';

    public string $novaOrigem = 'telefone';

    // Converter: o modal pergunta se já abre a oportunidade.
    public ?int $paraConverter = null;

    public string $convTitulo = '';

    public ?string $convValor = null;

    // Perder: o motivo é obrigatório — «perdido sem razão» não ensina nada.
    public ?int $paraPerder = null;

    public string $motivoPerda = '';

    // A actividade rápida no cartão.
    public ?int $paraActividade = null;

    public string $actTipo = 'chamada';

    public string $actAssunto = '';

    // A conversa: ver o histórico do lead e responder por WhatsApp.
    public ?int $conversaLeadId = null;

    public string $respostaTexto = '';

    public ?string $erroResposta = null;

    public function criar(): void
    {
        $this->validate([
            'novoNome' => ['required', 'string', 'min:2', 'max:150'],
            'novoTelefone' => ['nullable', 'string', 'max:30'],
            'novaEmpresa' => ['nullable', 'string', 'max:150'],
            'novaOrigem' => ['required', 'in:'.implode(',', array_keys(Lead::ORIGENS))],
        ], [], ['novoNome' => 'nome', 'novoTelefone' => 'telefone']);

        Lead::create([
            'tenant_id' => activeTenantId(),
            'name' => trim($this->novoNome),
            'phone' => trim($this->novoTelefone) ?: null,
            'company' => trim($this->novaEmpresa) ?: null,
            'source' => $this->novaOrigem,
            'status' => 'novo',
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        $this->reset(['novoNome', 'novoTelefone', 'novaEmpresa']);
        $this->dispatch('notify', type: 'success', message: 'Na fila. O próximo passo é ligar-lhe.');
    }

    /** Avança um passo no caminho: novo → contactado → qualificado. */
    public function avancar(int $id): void
    {
        $lead = Lead::where('tenant_id', activeTenantId())->findOrFail($id);

        $seguinte = ['novo' => 'contactado', 'contactado' => 'qualificado'][$lead->status] ?? null;

        if (! $seguinte) {
            return;
        }

        $lead->update(['status' => $seguinte]);
    }

    public function prepararConversao(int $id): void
    {
        $lead = Lead::where('tenant_id', activeTenantId())->findOrFail($id);

        $this->paraConverter = $lead->id;
        $this->convTitulo = 'Proposta para '.($lead->company ?: $lead->name);
        $this->convValor = null;
    }

    public function converter(ConversaoDeLead $servico): void
    {
        $lead = Lead::where('tenant_id', activeTenantId())->findOrFail($this->paraConverter);

        try {
            $cliente = $servico->converter($lead, activeTenantId(), auth()->id(), array_filter([
                'title' => trim($this->convTitulo),
                'amount' => $this->convValor !== null ? (float) str_replace(['.', ','], ['', '.'], $this->convValor) : 0,
            ]));

            $this->paraConverter = null;
            $this->dispatch('notify', type: 'success',
                message: 'Cliente criado: '.$cliente->name.'. A oportunidade está no funil.');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('notify', type: 'error', message: $e->getMessage());
        }
    }

    public function perder(): void
    {
        $this->validate(['motivoPerda' => ['required', 'string', 'min:3', 'max:255']], [], ['motivoPerda' => 'motivo']);

        Lead::where('tenant_id', activeTenantId())->findOrFail($this->paraPerder)
            ->update(['status' => 'perdido', 'lost_reason' => trim($this->motivoPerda)]);

        $this->reset(['paraPerder', 'motivoPerda']);
        $this->dispatch('notify', type: 'info', message: 'Registado. Os motivos somados dizem onde se perde.');
    }

    public function reabrir(int $id): void
    {
        Lead::where('tenant_id', activeTenantId())->where('status', 'perdido')->findOrFail($id)
            ->update(['status' => 'contactado', 'lost_reason' => null]);
    }

    public function registarActividade(): void
    {
        $this->validate([
            'actAssunto' => ['required', 'string', 'min:2', 'max:200'],
            'actTipo' => ['required', 'in:'.implode(',', array_keys(Activity::TIPOS))],
        ], [], ['actAssunto' => 'assunto']);

        $lead = Lead::where('tenant_id', activeTenantId())->findOrFail($this->paraActividade);

        Activity::create([
            'tenant_id' => activeTenantId(),
            'type' => $this->actTipo,
            'subject' => trim($this->actAssunto),
            'lead_id' => $lead->id,
            'done' => true,
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        // Falar com um lead novo é tê-lo contactado — o estado acompanha
        // sozinho, para o quadro não mentir por esquecimento.
        if ($lead->status === 'novo') {
            $lead->update(['status' => 'contactado']);
        }

        $this->reset(['paraActividade', 'actAssunto']);
        $this->dispatch('notify', type: 'success', message: 'Registado no histórico.');
    }

    /* ── A conversa ────────────────────────────────────────────────── */

    public function verConversa(int $id): void
    {
        $this->erroResposta = null;
        $this->respostaTexto = '';
        $this->conversaLeadId = Lead::where('tenant_id', activeTenantId())->whereKey($id)->value('id');
    }

    public function fecharConversa(): void
    {
        $this->reset(['conversaLeadId', 'respostaTexto', 'erroResposta']);
    }

    /** O lead, as suas actividades por ordem, e se dá para responder por WhatsApp. */
    public function getConversaProperty(): ?array
    {
        if (! $this->conversaLeadId) {
            return null;
        }

        $tenantId = activeTenantId();
        $lead = Lead::where('tenant_id', $tenantId)->find($this->conversaLeadId);
        if (! $lead) {
            return null;
        }

        $numero = MetaContact::where('tenant_id', $tenantId)->where('channel', 'whatsapp')
            ->where('lead_id', $lead->id)->value('external_id') ?: $lead->phone;

        $mi = MetaIntegration::where('tenant_id', $tenantId)->first();

        return [
            'lead' => $lead,
            'actividades' => Activity::where('tenant_id', $tenantId)->where('lead_id', $lead->id)
                ->orderBy('created_at')->get(),
            'numero' => $numero,
            'podeWhatsapp' => (bool) ($mi && $mi->whatsappActivo() && $numero),
        ];
    }

    public function responder(EnviarPeloMeta $svc): void
    {
        $this->erroResposta = null;
        $texto = trim($this->respostaTexto);
        if ($texto === '') {
            return;
        }

        $tenantId = activeTenantId();
        $lead = Lead::where('tenant_id', $tenantId)->findOrFail($this->conversaLeadId);
        $mi = MetaIntegration::where('tenant_id', $tenantId)->first();
        $numero = MetaContact::where('tenant_id', $tenantId)->where('channel', 'whatsapp')
            ->where('lead_id', $lead->id)->value('external_id') ?: $lead->phone;

        if (! $mi || ! $numero) {
            $this->erroResposta = 'Este lead não tem WhatsApp para onde responder.';

            return;
        }

        $res = $svc->whatsappTexto($mi, $numero, $texto);
        if (! ($res['ok'] ?? false)) {
            $this->erroResposta = $res['erro'] ?? 'Não foi possível enviar.';

            return;
        }

        Activity::create([
            'tenant_id' => $tenantId,
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
            'direction' => 'out',   // enviada
            'subject' => 'Resposta WhatsApp',
            'notes' => $texto,
            'done' => true,
            'assigned_to' => auth()->id(),
            'created_by' => auth()->id(),
        ]);

        if ($lead->status === 'novo') {
            $lead->update(['status' => 'contactado']);
        }

        $this->respostaTexto = '';
        $this->dispatch('notify', type: 'success', message: 'Enviado por WhatsApp.');
    }

    public function updatedProcurar(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $tenantId = activeTenantId();

        $leads = Lead::where('tenant_id', $tenantId)
            ->with(['assignee:id,name', 'client:id,name'])
            ->withCount('activities')
            ->when($this->filtroEstado === 'abertos', fn ($q) => $q->whereIn('status', Lead::ABERTOS))
            ->when(array_key_exists($this->filtroEstado, Lead::ESTADOS), fn ($q) => $q->where('status', $this->filtroEstado))
            ->when(trim($this->procurar) !== '', function ($q) {
                $t = '%'.trim($this->procurar).'%';
                $q->where(fn ($w) => $w->where('name', 'like', $t)
                    ->orWhere('company', 'like', $t)
                    ->orWhere('phone', 'like', $t)
                    ->orWhere('email', 'like', $t));
            })
            ->latest()
            ->paginate(24);

        return view('livewire.crm.leads', compact('leads'));
    }
}
