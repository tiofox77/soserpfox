<?php

namespace App\Livewire\SuperAdmin;

use App\Models\Plan;
use App\Models\SmsSetting;
use App\Models\SmsTemplate;
use App\Models\Tenant;
use App\Services\SmsService;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Enviar um SMS às empresas — a todas, ou só às escolhidas.
 *
 * Usa a configuração D7 da plataforma, a mesma do ecrã de definições de SMS
 * (SmsSetting com tenant_id nulo), e o mesmo SmsService que já regista cada
 * envio. Não é uma segunda via de envio: é o mesmo cano com um ecrã à frente.
 *
 * O SMS custa dinheiro por mensagem, e um engano aqui multiplica-se por todas
 * as empresas. Daí três coisas neste desenho:
 *
 *   · o número de destinatários é contado e mostrado ANTES de enviar, separando
 *     quem tem telefone de quem não tem;
 *   · o comprimento é contado em partes de SMS e não em caracteres, porque é a
 *     parte que se paga — e um acento faz a mensagem passar a UCS-2, onde cada
 *     parte encolhe de 160 para 70;
 *   · há uma confirmação explícita, com o número de mensagens à vista.
 */
// Sem isto o Livewire procura o layout `components.layouts.app`, que este
// projecto não tem, e a página rebenta com 500 — como aconteceu. Os outros
// ecrãs do super admin declaram-no todos; este ficou por declarar.
#[Layout('layouts.superadmin')]
class SmsParaEmpresas extends Component
{
    /** Limites do GSM-7 e do UCS-2, e o que sobra em cada parte quando a mensagem se divide. */
    private const GSM_UMA = 160;
    private const GSM_VARIAS = 153;
    private const UCS2_UMA = 70;
    private const UCS2_VARIAS = 67;

    public string $mensagem = '';
    public $template_id = '';

    /** todas | empresas | planos */
    public string $publico = 'todas';

    public array $empresa_ids = [];
    public array $plano_ids = [];

    public bool $porConfirmar = false;

    /** O que aconteceu no último envio, para ficar no ecrã. */
    public array $resultado = [];

    public function mount(): void
    {
        $this->apenasDonoDaPlataforma();
    }

    protected function rules(): array
    {
        return [
            // 640 = quatro partes GSM-7. Acima disto o custo por destinatário
            // começa a ser difícil de justificar sem alguém reparar.
            'mensagem' => 'required|string|min:3|max:640',
            'publico'  => 'required|in:todas,empresas,planos',
        ];
    }

    protected function messages(): array
    {
        return [
            'mensagem.required' => 'Escreva a mensagem.',
            'mensagem.max'      => 'A mensagem é demasiado longa (máximo 640 caracteres).',
        ];
    }

    public function updated($campo): void
    {
        // Mudar o que quer que seja depois de pedir a confirmação desfá-la: a
        // confirmação é sobre um número de destinatários concreto, e mudar o
        // público debaixo dela era confirmar uma coisa e enviar outra.
        if ($campo !== 'porConfirmar') {
            $this->porConfirmar = false;
        }
    }

    public function updatedTemplateId($id): void
    {
        $template = SmsTemplate::whereNull('tenant_id')->where('is_active', true)->find($id);
        if ($template) $this->mensagem = $template->content;
    }

    /** Passo 1: mostrar a quem vai, e quanto custa em partes. */
    public function rever(): void
    {
        $this->apenasDonoDaPlataforma();
        $this->validate();

        if ($this->publico === 'empresas' && empty($this->empresa_ids)) {
            $this->addError('empresa_ids', 'Escolha pelo menos uma empresa.');

            return;
        }

        if ($this->publico === 'planos' && empty($this->plano_ids)) {
            $this->addError('plano_ids', 'Escolha pelo menos um plano.');

            return;
        }

        if ($this->comTelefone()->isEmpty()) {
            $this->addError('mensagem', 'Nenhuma das empresas escolhidas tem telefone registado.');

            return;
        }

        $this->porConfirmar = true;
    }

    public function cancelar(): void
    {
        $this->porConfirmar = false;
    }

    public function enviar(): void
    {
        $this->apenasDonoDaPlataforma();
        $this->validate();

        if (!$this->porConfirmar) {
            return;
        }

        $destinatarios = $this->comTelefone();

        if ($destinatarios->isEmpty()) {
            $this->addError('mensagem', 'Nenhuma das empresas escolhidas tem telefone registado.');
            $this->porConfirmar = false;

            return;
        }

        $enviados = 0;
        $falhados = [];
        $servico  = new SmsService();
        $template = $this->template_id
            ? SmsTemplate::whereNull('tenant_id')->where('is_active', true)->find($this->template_id)
            : null;

        foreach ($destinatarios as $empresa) {
            try {
                // Uma falha não pode parar as restantes: com trinta empresas,
                // a décima em baixo deixaria vinte por avisar sem ninguém saber
                // quais. Guarda-se o que falhou e mostra-se no fim.
                // O retorno conta: o send() apanha as falhas do fornecedor por
                // dentro e devolve um array em vez de as deixar subir. Ignorar
                // esse array fazia o ecrã dizer "enviado a 30 empresas" quando
                // metade tinha falhado do outro lado.
                $mensagem = $template ? $template->render([
                    'app_name' => config('app.name', 'SOS ERP'),
                    'tenant_name' => $empresa->name,
                    'app_url' => config('app.url'),
                ]) : $this->mensagem;
                $resultado = $servico->send($empresa->phone, $mensagem, 'aviso_plataforma', null, $empresa->id);

                if (is_array($resultado) && ($resultado['success'] ?? true) === false) {
                    throw new \RuntimeException($resultado['error'] ?? 'o fornecedor recusou');
                }

                $enviados++;
            } catch (\Throwable $e) {
                $falhados[] = $empresa->name;

                Log::error('SMS às empresas: falhou num destinatário.', [
                    'tenant_id' => $empresa->id,
                    'erro'      => $e->getMessage(),
                ]);
            }
        }

        $this->resultado = [
            'enviados' => $enviados,
            'falhados' => $falhados,
            'partes'   => $enviados * $this->partes(),
        ];

        Log::info('SMS às empresas concluído.', [
            'enviados' => $enviados,
            'falhados' => count($falhados),
            'publico'  => $this->publico,
        ]);

        $this->porConfirmar = false;
        $this->mensagem = '';
        $this->dispatch('success', message: "SMS enviado a {$enviados} empresa(s).");
    }

    /** As empresas do público escolhido, tenham telefone ou não. */
    public function alvo()
    {
        $query = Tenant::where('is_active', true)->orderBy('name');

        if ($this->publico === 'empresas') {
            $query->whereIn('id', $this->empresa_ids ?: [0]);
        }

        if ($this->publico === 'planos') {
            // Pelo plano da subscrição activa, que é o que define em que plano
            // a empresa está hoje — e não pelo histórico dela.
            $query->whereHas('subscriptions', function ($s) {
                $s->whereIn('plan_id', $this->plano_ids ?: [0])
                    ->whereIn('status', ['active', 'trial']);
            });
        }

        return $query->get();
    }

    /** Só as que podem mesmo receber. */
    public function comTelefone()
    {
        return $this->alvo()->filter(fn ($e) => !empty($e->phone))->values();
    }

    /**
     * Em quantas partes a mensagem vai — que é o que se paga.
     *
     * Um único acento leva a mensagem para UCS-2, e aí cada parte encolhe de
     * 160 para 70 caracteres. Contar caracteres em vez de partes escondia isso:
     * uma mensagem de 160 letras é uma parte, e a mesma com "ã" são três.
     */
    public function partes(): int
    {
        $texto = trim($this->mensagem);
        $tamanho = mb_strlen($texto);

        if ($tamanho === 0) {
            return 0;
        }

        $unicode = mb_strlen($texto) !== strlen($texto);
        $umaSo   = $unicode ? self::UCS2_UMA : self::GSM_UMA;
        $varias  = $unicode ? self::UCS2_VARIAS : self::GSM_VARIAS;

        return $tamanho <= $umaSo ? 1 : (int) ceil($tamanho / $varias);
    }

    public function render()
    {
        $alvo = $this->alvo();

        return view('livewire.super-admin.sms-para-empresas', [
            'empresas'     => Tenant::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone']),
            'planos'       => Plan::orderBy('name')->get(['id', 'name']),
            'quantasAlvo'  => $alvo->count(),
            'quantasPodem' => $alvo->filter(fn ($e) => !empty($e->phone))->count(),
            'configurado'  => (bool) SmsSetting::whereNull('tenant_id')->where('is_active', true)->first(),
            'gateway' => SmsSetting::whereNull('tenant_id')->where('is_active', true)->value('provider'),
            'templates' => SmsTemplate::whereNull('tenant_id')->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    private function apenasDonoDaPlataforma(): void
    {
        abort_unless(auth()->check() && auth()->user()->is_super_admin, 403);
    }
}
