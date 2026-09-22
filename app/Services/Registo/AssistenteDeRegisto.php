<?php

namespace App\Services\Registo;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Rules\EmailQueExiste;
use App\Rules\NifDeEmpresa;
use App\Rules\NomeQueParecePessoa;
use App\Services\Subscriptions\DireitoACortesia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * O ESTADO DO ASSISTENTE DE REGISTO — em que passo está, o que já foi escrito,
 * e as regras de cada passo.
 *
 * Era o componente Livewire inteiro menos o desenho. As regras que ele juntou
 * ao longo do tempo continuam todas aqui, e cada uma com a razão:
 *
 *   · O PROGRESSO VIVE NA SESSÃO, menos a palavra-passe (é uma credencial). Um
 *     F5 a meio não deita fora a empresa nem o plano: volta-se ao passo 1 só
 *     para reescrever a palavra-passe, e daí segue-se para onde se estava.
 *   · O PLANO DO LINK não é perguntado outra vez — mas pode sempre trocar-se.
 *   · A CORTESIA É UMA SÓ: o gratuito fecha a quem já o gastou, e os dias de
 *     teste só se anunciam a quem os vai receber.
 *   · UM PLANO A 0 KZ não pede IBAN, referência nem comprovativo.
 */
class AssistenteDeRegisto
{
    /** Os campos que o ecrã escreve e a sessão guarda. */
    public const CAMPOS = [
        'name', 'email',
        'company_name', 'company_nif', 'company_regime', 'company_address', 'company_phone', 'company_email',
        'selected_plan_id', 'payment_method', 'payment_reference',
        // O código do revendedor (programa de revendedores, RV-06) — opcional.
        'reseller_code',
    ];

    public int $passo = 1;
    public bool $autenticado = false;

    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public string $company_name = '';
    public string $company_nif = '';
    public string $company_regime = Tenant::REGIME_GERAL;
    public string $company_address = '';
    public string $company_phone = '';
    public string $company_email = '';

    public ?int $selected_plan_id = null;
    public string $payment_method = 'transfer';
    public string $payment_reference = '';
    public ?UploadedFile $payment_proof = null;
    public bool $aceito_termos = false;

    /** O código do revendedor, escrito pelo cliente (nunca preenchido sozinho). */
    public string $reseller_code = '';

    /** O código veio do link (o cookie) — a ligação fica «por link», não «por código». */
    public bool $revendedorVeioDoLink = false;

    public bool $planoVeioDoLink = false;
    public ?int $passoAntesDaSenha = null;

    /** @var array{tipo:string,texto:string}|null */
    public ?array $aviso = null;

    private ?Collection $planos = null;

    private function __construct(public readonly ?User $utilizador)
    {
        $this->autenticado = $utilizador !== null;
    }

    /**
     * A PÁGINA ABRE — o `mount()` de sempre, pela mesma ordem: a origem da
     * publicidade, quem está autenticado, o progresso guardado, o plano do
     * link, a verificação do F5 e, por fim, o Starter por omissão.
     */
    public static function abrir(Request $request): self
    {
        self::guardarOrigem($request);

        $a = new self(self::utilizadorDe($request));

        if ($a->autenticado) {
            $a->name = (string) $a->utilizador->name;
            $a->email = (string) $a->utilizador->email;
            $a->passo = 2;
        }

        $a->carregarProgresso();

        // Links de campanha pré-escolhem o plano pelo slug (/subscrever/fox-friendly
        // → /register?plan=fox-friendly). O parâmetro prevalece sobre o progresso.
        $pedido = $request->query('plan');
        if (is_string($pedido) && $pedido !== '') {
            $plano = $a->planos()->firstWhere('slug', $pedido);
            if ($plano) {
                $a->selected_plan_id = $plano->id;
                // A não ser que o plano do link já não esteja disponível para
                // ele: aí é preciso escolher outro, e o passo tem de voltar.
                $a->planoVeioDoLink = $a->direito()->podeEscolher($plano);
            }
        }

        $a->verificarDepoisDeRecarregar();

        // O CÓDIGO DO REVENDEDOR NASCE SEMPRE VAZIO (22/09/2026, decisão do
        // dono da plataforma). Vinha preenchido pelo cookie do link, que dura
        // 60 dias: quem tinha aberto o link de um revendedor — o próprio dono,
        // um computador partilhado — via-o já marcado em todos os registos
        // seguintes. Quem escreve o código é o cliente, se tiver revendedor.
        // O cookie serve só para dizer «por link» quando o código escrito é o
        // mesmo (ver `doPedido`).

        // Sem plano do link nem do progresso, usa-se o MÓDULO por onde a pessoa
        // entrou (guardado na sessão na aterragem — ver CapturarCampanha). É a
        // intenção mais recente; só um plano VÁLIDO para ela é que se aceita.
        if (! $a->selected_plan_id) {
            $doModulo = $request->session()->get('registration_plan');
            if (is_string($doModulo) && $doModulo !== '') {
                $plano = $a->planos()->firstWhere('slug', $doModulo);
                if ($plano && $a->direito()->podeEscolher($plano)) {
                    $a->selected_plan_id = $plano->id;
                    $a->planoVeioDoLink = true;
                }
            }
        }

        // O Starter fica só para quem não veio de nenhum módulo nem link.
        if (! $a->selected_plan_id) {
            $a->selected_plan_id = $a->planos()->firstWhere('slug', 'starter')?->id;
        }

        // O que só a abertura sabe — o plano veio do link, ou o F5 mandou
        // reescrever a palavra-passe — tem de ficar na sessão: é dela que o
        // «Próximo» parte. No Livewire vivia na memória do componente; sem isto,
        // o plano do link voltava a ser perguntado e o passo de onde se vinha
        // perdia-se.
        if ($a->planoVeioDoLink || $a->passoAntesDaSenha !== null) {
            $a->guardar();
        }

        return $a;
    }

    /**
     * UM PEDIDO DO ECRÃ: o progresso guardado, por cima dele o que o ecrã tem
     * escrito agora. O plano do link e o passo a que se volta depois da
     * palavra-passe são do servidor — o ecrã não os decide.
     */
    public static function doPedido(Request $request): self
    {
        $a = new self(self::utilizadorDe($request));

        if ($a->autenticado) {
            $a->name = (string) $a->utilizador->name;
            $a->email = (string) $a->utilizador->email;
        }

        $a->carregarProgresso();

        foreach (self::CAMPOS as $campo) {
            if (! $request->has($campo) || ($a->autenticado && in_array($campo, ['name', 'email'], true))) {
                continue;
            }
            $valor = $request->input($campo);
            $a->{$campo} = $campo === 'selected_plan_id'
                ? (filled($valor) && ctype_digit((string) $valor) ? (int) $valor : null)
                : trim((string) $valor);
        }

        $a->reseller_code = strtoupper($a->reseller_code);
        // Veio do link se é o mesmo código que o link deixou.
        $a->revendedorVeioDoLink = $a->reseller_code !== ''
            && $a->reseller_code === \App\Services\Revenda\LigacaoAoRevendedor::doCookie($request);

        $a->password = (string) $request->input('password', '');
        $a->password_confirmation = (string) $request->input('password_confirmation', '');
        $a->payment_proof = $request->file('payment_proof');
        $a->aceito_termos = $request->boolean('aceito_termos');

        if ($request->has('passo')) {
            $a->passo = max($a->passoMinimo(), min(4, (int) $request->input('passo')));
        }

        return $a;
    }

    private static function utilizadorDe(Request $request): ?User
    {
        $u = $request->user();

        return $u instanceof User ? $u : null;
    }

    /** Guarda a origem publicitária durante todo o assistente. */
    private static function guardarOrigem(Request $request): void
    {
        $chaves = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid'];
        $chegou = array_filter($request->only($chaves), fn ($v) => is_string($v) && $v !== '');

        if ($chegou !== []) {
            session(['registration_acquisition' => array_merge(session('registration_acquisition', []), $chegou)]);
        }

        if (! session()->has('registration_visitor_id')) {
            session(['registration_visitor_id' => (string) Str::uuid()]);
        }
    }

    // ------------------------------------------------------------ progresso

    private function carregarProgresso(): void
    {
        $p = session('wizard_progress', []);
        if (empty($p)) {
            return;
        }

        $this->passo = (int) ($p['currentStep'] ?? $this->passo);

        if (! $this->autenticado) {
            $this->name = (string) ($p['name'] ?? $this->name);
            $this->email = (string) ($p['email'] ?? $this->email);
        }

        foreach (['company_name', 'company_nif', 'company_regime', 'company_address', 'company_phone', 'company_email', 'payment_method', 'payment_reference'] as $campo) {
            $this->{$campo} = (string) ($p[$campo] ?? $this->{$campo});
        }

        // Só o código ESCRITO pela pessoa volta. Um progresso guardado antes de
        // 22/09/2026 pode trazer o que o cookie do link lá pôs sozinho — e é
        // exactamente isso que já não se mostra.
        if (! empty($p['reseller_code_escrito'])) {
            $this->reseller_code = (string) ($p['reseller_code'] ?? '');
        }

        $this->selected_plan_id = isset($p['selected_plan_id']) && $p['selected_plan_id'] !== null ? (int) $p['selected_plan_id'] : $this->selected_plan_id;
        // ...e se ele veio do link, para não voltar a perguntar depois de um F5.
        $this->planoVeioDoLink = (bool) ($p['planoVeioDoLink'] ?? $this->planoVeioDoLink);
        $this->passoAntesDaSenha = $p['passoAntesDaSenha'] ?? $this->passoAntesDaSenha;
    }

    public function guardar(): void
    {
        session(['wizard_progress' => [
            'currentStep' => $this->passo,
            'name' => $this->name,
            'email' => $this->email,
            'company_name' => $this->company_name,
            'company_nif' => $this->company_nif,
            'company_regime' => $this->company_regime,
            'company_address' => $this->company_address,
            'company_phone' => $this->company_phone,
            'company_email' => $this->company_email,
            'selected_plan_id' => $this->selected_plan_id,
            'planoVeioDoLink' => $this->planoVeioDoLink,
            'passoAntesDaSenha' => $this->passoAntesDaSenha,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'reseller_code' => $this->reseller_code,
            'reseller_code_escrito' => true,
            'saved_at' => now()->toDateTimeString(),
        ]]);
    }

    public static function esquecer(): void
    {
        session()->forget('wizard_progress');
    }

    /**
     * A palavra-passe é a única coisa que não fica guardada — e a seguir a um F5
     * vem sempre vazia. Daqui saía um recomeço que apagava a empresa, o NIF, a
     * morada e o plano por causa do único campo que de propósito não se guarda.
     * Agora volta-se ao passo 1 só para a reescrever.
     */
    private function verificarDepoisDeRecarregar(): void
    {
        if (! $this->autenticado && $this->passo > 1) {
            if ($this->name === '' || $this->email === '') {
                Log::info('Dados do utilizador em falta. Reiniciando o registo.');
                $this->recomecar(__('Dados do utilizador incompletos. Por favor, preencha novamente.'), 'warning');

                return;
            }

            $this->passoAntesDaSenha = $this->passo;
            $this->passo = 1;
            $this->aviso = ['tipo' => 'info', 'texto' => __('Os seus dados foram guardados. Confirme a palavra-passe para continuar de onde parou.')];

            return;
        }

        // Estes guardam-se na sessão; se faltam, faltam mesmo.
        if ($this->passo > 2 && ($this->company_name === '' || $this->company_nif === '')) {
            $this->recomecar(__('Dados da empresa incompletos. Por favor, preencha novamente.'), 'warning');

            return;
        }

        if ($this->passo > 3 && ! $this->selected_plan_id) {
            $this->recomecar(__('Nenhum plano foi selecionado. Por favor, selecione um plano.'), 'warning');
        }
    }

    public function recomecar(?string $mensagem = null, string $tipo = 'info'): void
    {
        self::esquecer();

        if (! $this->autenticado) {
            $this->name = $this->email = '';
        }
        $this->password = $this->password_confirmation = '';
        $this->company_name = $this->company_nif = $this->company_address = $this->company_phone = $this->company_email = '';
        $this->company_regime = Tenant::REGIME_GERAL;
        $this->selected_plan_id = null;
        $this->payment_method = 'transfer';
        $this->payment_reference = '';
        $this->planoVeioDoLink = false;
        $this->passoAntesDaSenha = null;
        $this->passo = $this->passoMinimo();

        if ($mensagem) {
            $this->aviso = ['tipo' => $tipo, 'texto' => $mensagem];
        }
    }

    // ------------------------------------------------------------ navegação

    public function passoMinimo(): int
    {
        return $this->autenticado ? 2 : 1;
    }

    public function seguinte(): void
    {
        if ($this->passo === 1) {
            $this->validarPasso1();
            // Voltou só para reescrever a palavra-passe: segue para onde estava.
            $this->passo = $this->passoAntesDaSenha ?: 2;
            $this->passoAntesDaSenha = null;
        } elseif ($this->passo === 2) {
            $this->validarPasso2();

            // Só agora se sabe o NIF, e é por ele que se descobre se a empresa
            // já gastou o gratuito. Se o plano do link deixou de estar
            // disponível, a pergunta volta — mais vale aqui do que no fim.
            $recusa = $this->direito()->motivoParaRecusar($this->planoEscolhido());
            if ($recusa) {
                $this->planoVeioDoLink = false;
                $this->selected_plan_id = null;
                $this->passo = 3;
                $this->aviso = ['tipo' => 'warning', 'texto' => $recusa.' '.__('Escolha outro plano para continuar.')];
                $this->guardar();

                return;
            }

            $this->passo = $this->temPassoDePlano() ? 3 : 4;
        } elseif ($this->passo === 3) {
            $this->validarPasso3();
            $this->passo = 4;
        }

        $this->guardar();
    }

    public function anterior(): void
    {
        if ($this->passo <= $this->passoMinimo()) {
            return;
        }

        $anterior = $this->passo - 1;

        // Saltar para trás o passo que não se aplica, senão o «Voltar» levava a
        // um ecrã que o «Próximo» volta a saltar.
        if ($anterior === 3 && ! $this->temPassoDePlano()) {
            $anterior = 2;
        }

        $this->passo = max($this->passoMinimo(), $anterior);
        $this->guardar();
    }

    /** Não perguntar duas vezes não pode virar não deixar mudar de ideias. */
    public function escolherOutroPlano(): void
    {
        $this->planoVeioDoLink = false;
        $this->passo = 3;
        $this->guardar();
    }

    // ------------------------------------------------------------ validação

    private function dados(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
            'company_name' => $this->company_name,
            'company_nif' => $this->company_nif,
            'company_regime' => $this->company_regime,
            'company_address' => $this->company_address,
            'company_phone' => $this->company_phone,
            'company_email' => $this->company_email,
            'selected_plan_id' => $this->selected_plan_id,
            'payment_method' => $this->payment_method,
            'payment_reference' => $this->payment_reference,
            'payment_proof' => $this->payment_proof,
            'aceito_termos' => $this->aceito_termos,
            'reseller_code' => $this->reseller_code,
        ];
    }

    private function validar(array $regras, array $mensagens = []): void
    {
        Validator::make($this->dados(), $regras, $mensagens, [
            'name' => __('Nome Completo'),
            'email' => __('Email'),
            'password' => __('Senha'),
            'company_name' => __('Nome da Empresa'),
            'company_nif' => __('NIF da empresa'),
            'company_email' => __('Email da Empresa'),
            'selected_plan_id' => __('Plano'),
            'payment_reference' => __('Referência da Transferência'),
            'payment_proof' => __('Comprovativo de Pagamento'),
        ])->validate();
    }

    public function validarPasso1(): void
    {
        $this->validar([
            'name' => ['required', 'min:3', new NomeQueParecePessoa()],
            'email' => ['required', 'email', 'unique:users,email', new EmailQueExiste()],
            'password' => ['required', 'confirmed', \App\Support\Seguranca\RegraDaSenha::regra()],
        ]);
    }

    public function validarPasso2(): void
    {
        $this->validar([
            'company_name' => ['required', 'min:3', new NomeQueParecePessoa()],
            'company_nif' => ['required', new NifDeEmpresa(), 'unique:tenants,nif'],
            'company_regime' => 'required|in:'.implode(',', array_keys(Tenant::REGIMES)),
            'company_address' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:50',
            'company_email' => 'nullable|email',
            // Um código escrito tem de ser de um revendedor aprovado: um engano
            // aqui deixava a empresa sem o revendedor que a trouxe.
            'reseller_code' => ['nullable', 'string', 'max:20', function ($atributo, $valor, $falhar) {
                if (filled($valor) && ! \App\Services\Revenda\LigacaoAoRevendedor::porCodigo($valor)) {
                    $falhar(__('Não encontramos nenhum revendedor com este código.'));
                }
            }],
        ], [
            'company_regime.required' => __('Escolha o regime fiscal da empresa.'),
            'company_regime.in' => __('Regime fiscal inválido.'),
        ]);
    }

    /** O revendedor do código escrito (ou do link), se for válido. */
    public function revendedor(): ?\App\Models\Reseller
    {
        return \App\Services\Revenda\LigacaoAoRevendedor::porCodigo($this->reseller_code);
    }

    /**
     * UMA EMPRESA CRIADA PELO REVENDEDOR (RV-09) — o mesmo caminho do registo,
     * sem sessão nem passos: os dados chegam já validados do portal dele.
     *
     * @param  array{name:string, email:string, password:string, company_name:string, company_nif:string, company_regime:string, company_address:?string, company_phone:?string, company_email:?string, selected_plan_id:int, payment_reference:?string}  $dados
     */
    public static function paraRevendedor(array $dados, ?UploadedFile $comprovativo = null): self
    {
        $a = new self(null);
        $a->name = $dados['name'];
        $a->email = $dados['email'];
        $a->password = $dados['password'];
        $a->password_confirmation = $dados['password'];
        $a->company_name = $dados['company_name'];
        $a->company_nif = $dados['company_nif'];
        $a->company_regime = $dados['company_regime'];
        $a->company_address = (string) ($dados['company_address'] ?? '');
        $a->company_phone = (string) ($dados['company_phone'] ?? '');
        $a->company_email = (string) ($dados['company_email'] ?? '');
        $a->selected_plan_id = (int) $dados['selected_plan_id'];
        $a->payment_method = 'transfer';
        $a->payment_reference = (string) ($dados['payment_reference'] ?? '');
        $a->payment_proof = $comprovativo;
        $a->aceito_termos = true;

        return $a;
    }

    public function validarPasso3(): void
    {
        $this->validar(['selected_plan_id' => 'required|exists:plans,id']);

        // O gratuito é de uma vez só. O ecrã já o mostra fechado, mas a escolha
        // viaja no pedido e o pedido é do lado de fora.
        $recusa = $this->direito()->motivoParaRecusar($this->planoEscolhido());
        if ($recusa) {
            throw ValidationException::withMessages(['selected_plan_id' => $recusa]);
        }
    }

    public function validarPasso4(): void
    {
        $this->validar(['aceito_termos' => 'accepted'], [
            'aceito_termos.accepted' => __('Aceite os Termos de Serviço e a Política de Privacidade para continuar.'),
        ]);

        // Sem nada a pagar não há nada a validar.
        if ($this->naoHaNadaAPagar()) {
            return;
        }

        // Em período de teste ainda não há transferência feita; a quem já o
        // gastou, o plano começa a pagar e a prova volta a ser obrigatória.
        $regra = $this->temDireitoATeste() ? 'nullable' : 'required';

        $this->validar([
            'payment_method' => 'required|in:transfer',
            'payment_reference' => $regra.'|string|max:255',
            'payment_proof' => $regra.'|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [
            'payment_reference.required' => __('Indique a referência da transferência.'),
            'payment_proof.required' => __('Anexe o comprovativo da transferência.'),
        ]);
    }

    public function validarTudo(): void
    {
        if (! $this->autenticado) {
            $this->validarPasso1();
        }
        $this->validarPasso2();
        $this->validarPasso3();
        $this->validarPasso4();
    }

    // ------------------------------------------------------------ o que se sabe

    public function planos(): Collection
    {
        return $this->planos ??= Plan::publico()->orderBy('order')->get();
    }

    public function planoEscolhido(): ?Plan
    {
        return $this->selected_plan_id ? Plan::find($this->selected_plan_id) : null;
    }

    /** No registo a identidade é o NIF da empresa — e o utilizador, se já autenticado. */
    public function direito(): DireitoACortesia
    {
        return DireitoACortesia::de($this->utilizador, $this->company_nif);
    }

    /**
     * Só o preço conta: todos os planos têm dias de teste, e se contassem
     * ninguém pagava no registo. Mensal, o ciclo com que a subscrição nasce.
     */
    public function naoHaNadaAPagar(): bool
    {
        $plano = $this->planoEscolhido();

        return $plano !== null && (float) ($plano->getPrice('monthly') ?? 0) <= 0;
    }

    public function temDireitoATeste(): bool
    {
        return $this->direito()->temDireitoATeste($this->planoEscolhido());
    }

    public function temPassoDePlano(): bool
    {
        return ! $this->planoVeioDoLink;
    }

    /** O que o ecrã precisa para se desenhar. */
    public function paraEcra(): array
    {
        $direito = $this->direito();

        return [
            'passo' => $this->passo,
            'autenticado' => $this->autenticado,
            'campos' => [
                'name' => $this->name,
                'email' => $this->email,
                'company_name' => $this->company_name,
                'company_nif' => $this->company_nif,
                'company_regime' => $this->company_regime,
                'company_address' => $this->company_address,
                'company_phone' => $this->company_phone,
                'company_email' => $this->company_email,
                'selected_plan_id' => $this->selected_plan_id,
                'payment_method' => $this->payment_method,
                'payment_reference' => $this->payment_reference,
                'reseller_code' => $this->reseller_code,
            ],
            // O revendedor do código, para o ecrã confirmar o nome antes de criar a conta.
            'revendedor' => ($rev = $this->revendedor()) ? ['codigo' => $rev->code, 'nome' => $rev->nomeVisivel()] : null,
            'plano_veio_do_link' => $this->planoVeioDoLink,
            'passo_antes_da_senha' => $this->passoAntesDaSenha,
            'sem_teste' => ($direito->jaTeveTeste() || $direito->jaTeveGratuito()) ? __($direito->motivoSemTeste()) : null,
            'planos' => $this->planos()->map(fn (Plan $p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'nome' => $p->name,
                'descricao' => $p->description,
                'preco' => (float) $p->price_monthly,
                'gratuito' => (float) ($p->getPrice('monthly') ?? 0) <= 0,
                'destaque' => (bool) $p->is_featured,
                'utilizadores' => (int) $p->max_users,
                'empresas' => (int) $p->max_companies,
                'dias_de_teste' => (int) $p->trial_days,
                'recusa' => ($r = $direito->motivoParaRecusar($p)) ? __($r) : null,
                'com_teste' => $direito->temDireitoATeste($p),
            ])->values()->all(),
            'guardado_em' => session('wizard_progress.saved_at'),
            'aviso' => $this->aviso,
        ];
    }
}
