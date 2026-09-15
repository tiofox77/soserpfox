<?php

namespace App\Http\Controllers\Api\Copias;

use App\Http\Controllers\Controller;
use App\Models\Copias\AgendaDeCopia;
use App\Models\Copias\CopiaDeSeguranca;
use App\Models\Copias\DestinoDeCopia;
use App\Models\Copias\RestauroDeCopia;
use App\Services\Copias\Cifra;
use App\Services\Copias\Destinos\Fornecedores;
use App\Services\Copias\Destinos\OAuth;
use App\Services\Copias\Destinos\Rede;
use App\Services\Copias\FazerCopia;
use App\Services\Copias\Pasta;
use App\Services\Copias\RestaurarCopia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * O ECRÃ DAS CÓPIAS DE SEGURANÇA — o mesmo para a plataforma e para cada empresa.
 *
 * O que muda entre os dois é só o ÂMBITO (`ambito()`): a plataforma trabalha com
 * `tenant_id` nulo e copia a base inteira; a empresa trabalha com o seu id e
 * copia só os seus dados. Tudo o resto — destinos, agenda, histórico, restauro —
 * é igual, e cada consulta vai presa ao âmbito: uma empresa nunca vê, descarrega
 * nem repõe uma cópia ou um destino que não seja dela.
 *
 * FAZER E REPOR NÃO PRENDEM O PEDIDO. Um mysqldump de 35 MB e o envio para um
 * Drive levam minutos: responde-se já com o registo «a correr» e o trabalho
 * continua depois de a resposta sair (`app()->terminating`). O ecrã vai
 * perguntando até acabar.
 */
abstract class CopiasApiController extends Controller
{
    abstract protected function ambito(Request $request): ?int;

    /* ════════ O painel ════════ */

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->ambito($request);
        $agenda = AgendaDeCopia::para($tenantId);

        $copias = CopiaDeSeguranca::doAmbito($tenantId)->with('envios.destino')->orderByDesc('id')
            ->paginate(10, ['*'], 'pagina', (int) $request->query('pagina', 1));

        return response()->json([
            'ambito' => $tenantId === null ? 'plataforma' : 'empresa',
            'agenda' => [
                'activa' => $agenda->activa,
                'intervalo_horas' => (int) $agenda->intervalo_horas,
                'manter_locais' => (int) $agenda->manter_locais,
                'cifrar' => $agenda->cifrar,
                'tem_frase' => (bool) $agenda->frase,
                'ultima_em' => $agenda->ultima_em?->toIso8601String(),
                'proxima_em' => $agenda->proxima_em?->toIso8601String(),
            ],
            'intervalos' => collect(config('copias.intervalos'))
                ->filter(fn ($h) => $tenantId === null || $h >= (int) config('copias.empresa.intervalo_minimo_horas'))
                ->values(),
            'a_correr' => $this->aCorrer($tenantId),
            'espaco_bytes' => Pasta::espacoUsado($tenantId),
            'destinos' => DestinoDeCopia::doAmbito($tenantId)->orderBy('nome')->get()->map(fn ($d) => Fornecedores::paraEcra($d))->values(),
            'fornecedores' => Fornecedores::catalogo(),
            'retorno_oauth' => OAuth::retorno(),
            'copias' => collect($copias->items())->map(fn (CopiaDeSeguranca $c) => $this->copia($c))->values(),
            'paginacao' => ['pagina' => $copias->currentPage(), 'ultima' => $copias->lastPage(), 'total' => $copias->total()],
            'restauros' => RestauroDeCopia::doAmbito($tenantId)->orderByDesc('id')->limit(10)->get()->map(fn ($r) => $this->restauro($r))->values(),
            'avisos' => $this->avisos($tenantId),
        ]);
    }

    public function agenda(Request $request): JsonResponse
    {
        $tenantId = $this->ambito($request);
        $minimo = $tenantId === null ? 1 : (int) config('copias.empresa.intervalo_minimo_horas');

        $d = $request->validate([
            'activa' => ['required', 'boolean'],
            'intervalo_horas' => ['required', 'integer', Rule::in(config('copias.intervalos')), "min:{$minimo}"],
            'manter_locais' => ['required', 'integer', 'min:1', 'max:' . ($tenantId === null ? 60 : 30)],
            'cifrar' => ['required', 'boolean'],
            'frase' => ['nullable', 'string', 'min:12', 'max:200'],
        ], [
            'frase.min' => __('A frase-passe tem de ter pelo menos 12 caracteres.'),
            'intervalo_horas.min' => __('Uma empresa não pode fazer cópias mais do que de :h em :h horas.', ['h' => $minimo]),
        ]);

        $agenda = AgendaDeCopia::para($tenantId);

        if ($d['cifrar'] && ! $agenda->frase && empty($d['frase'])) {
            throw ValidationException::withMessages(['frase' => [__('Para cifrar as cópias escolha uma frase-passe — e guarde-a num sítio seguro: sem ela, as cópias cifradas não se abrem.')]]);
        }

        $mudouIntervalo = (int) $agenda->intervalo_horas !== (int) $d['intervalo_horas'];
        $agenda->fill(collect($d)->except('frase')->all());
        if (! empty($d['frase'])) {
            $agenda->guardarFrase($d['frase']);
        }
        if ($mudouIntervalo || ! $agenda->activa) {
            $agenda->proxima_em = ($agenda->ultima_em ?? now())->copy()->addHours((int) $d['intervalo_horas']);
        }
        $agenda->save();

        return response()->json(['message' => __('Agenda das cópias guardada.')]);
    }

    public function fazer(Request $request, FazerCopia $servico): JsonResponse
    {
        $tenantId = $this->ambito($request);

        if ($this->aCorrer($tenantId)) {
            return response()->json(['message' => __('Já está uma cópia a ser feita. Aguarde que termine.')], 409);
        }

        $copia = CopiaDeSeguranca::create([
            'tenant_id' => $tenantId,
            'ficheiro' => 'soserp-a-preparar-' . uniqid() . '.tmp',
            'origem' => 'manual',
            'estado' => 'a_correr',
            'pedida_por' => $request->user()->id,
            'iniciada_em' => now(),
        ]);

        app()->terminating(function () use ($servico, $tenantId, $copia) {
            try {
                $servico->fazer($tenantId, 'manual', $copia->pedida_por, $copia);
            } catch (\Throwable $e) {
                $copia->refresh();
                if ($copia->estado === 'a_correr') {
                    $copia->forceFill(['estado' => 'falhou', 'erro' => mb_strimwidth($e->getMessage(), 0, 1000, '…'), 'concluida_em' => now()])->save();
                }
            }
        });

        return response()->json(['message' => __('Cópia a ser feita. Pode continuar a trabalhar — o ecrã avisa quando terminar.'), 'copia_id' => $copia->id], 202);
    }

    /* ════════ Destinos ════════ */

    public function guardarDestino(Request $request, ?int $id = null): JsonResponse
    {
        $tenantId = $this->ambito($request);
        $destino = $id ? DestinoDeCopia::doAmbito($tenantId)->findOrFail($id) : null;

        $tipo = $destino?->tipo ?? $request->input('tipo');
        $d = $request->validate(array_merge([
            'tipo' => [$destino ? 'nullable' : 'required', Rule::in(Fornecedores::TIPOS)],
            'nome' => ['required', 'string', 'max:120'],
            'pasta' => ['nullable', 'string', 'max:255', 'not_regex:/\.\./'],
            'manter' => ['required', 'integer', 'min:1', 'max:100'],
            'activo' => ['required', 'boolean'],
            'configuracao' => ['array'],
        ], Fornecedores::regras((string) $tipo, $destino === null)));

        $catalogo = Fornecedores::catalogo()[$tipo];
        if (! $catalogo['disponivel']) {
            throw ValidationException::withMessages(['tipo' => [__('Este servidor não suporta :f.', ['f' => $catalogo['nome']])]]);
        }

        // Segredos em branco ao editar: fica o que estava.
        $novo = [];
        foreach ($catalogo['campos'] as $c) {
            if ($c['chave'] === 'pasta') {
                continue;
            }
            $valor = $d['configuracao'][$c['chave']] ?? null;
            if (! empty($c['segredo']) && ($valor === null || $valor === '' || $valor === '••••••••') && $destino) {
                $valor = $destino->cfg($c['chave']);
            }
            $novo[$c['chave']] = $valor;
        }

        // Numa empresa, o anfitrião tem de ser público (ver Rede).
        if ($tenantId !== null) {
            $anfitriao = $novo['anfitriao'] ?? (isset($novo['url']) ? Rede::anfitriaoDe((string) $novo['url']) : (isset($novo['endpoint']) ? Rede::anfitriaoDe((string) $novo['endpoint']) : null));
            if ($anfitriao) {
                try {
                    Rede::exigirPublico($anfitriao);
                } catch (\RuntimeException $e) {
                    throw ValidationException::withMessages(['configuracao' => [$e->getMessage()]]);
                }
            }
        }

        // Tokens OAuth já obtidos não se perdem ao editar o nome ou a pasta.
        if ($destino && $catalogo['oauth']) {
            $novo = array_merge($destino->configuracao ?? [], $novo);
            if (($destino->pasta ?? '') !== ($d['pasta'] ?? '')) {
                $novo['pasta_id'] = null;
            }
        }

        $destino ??= new DestinoDeCopia(['tenant_id' => $tenantId, 'tipo' => $tipo, 'criado_por' => $request->user()->id]);
        $destino->fill([
            'nome' => $d['nome'],
            'pasta' => $d['pasta'] ?: null,
            'manter' => $d['manter'],
            'activo' => $d['activo'],
            'configuracao' => $novo,
        ]);
        if (! $catalogo['oauth']) {
            $destino->ligado = false;
        }
        $destino->save();

        return response()->json([
            'message' => $id ? __('Destino guardado.') : __('Destino criado.'),
            'destino' => Fornecedores::paraEcra($destino),
        ], $id ? 200 : 201);
    }

    public function apagarDestino(Request $request, int $id): JsonResponse
    {
        DestinoDeCopia::doAmbito($this->ambito($request))->findOrFail($id)->delete();

        return response()->json(['message' => __('Destino removido. As cópias que lá estão não foram apagadas.')]);
    }

    public function testarDestino(Request $request, int $id): JsonResponse
    {
        $destino = DestinoDeCopia::doAmbito($this->ambito($request))->findOrFail($id);

        try {
            Fornecedores::criar($destino)->testar();
            $destino->forceFill(['ligado' => true, 'testado_em' => now(), 'ultimo_erro' => null])->save();
        } catch (\Throwable $e) {
            $destino->forceFill(['testado_em' => now(), 'ultimo_erro' => mb_strimwidth($e->getMessage(), 0, 1000, '…')])->save();

            return response()->json(['message' => __('O teste falhou: :e', ['e' => $e->getMessage()])], 422);
        }

        return response()->json(['message' => __('Ligação testada: foi possível escrever e apagar um ficheiro de teste.')]);
    }

    public function ligarDestino(Request $request, int $id): JsonResponse
    {
        $destino = DestinoDeCopia::doAmbito($this->ambito($request))->findOrFail($id);

        try {
            return response()->json(['url' => OAuth::urlDeAutorizacao($destino, $request->user()->id)]);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** O que está na pasta do destino — para repor quando o servidor já não tem a cópia. */
    public function ficheirosDoDestino(Request $request, int $id): JsonResponse
    {
        $tenantId = $this->ambito($request);
        $destino = DestinoDeCopia::doAmbito($tenantId)->findOrFail($id);

        try {
            $ficheiros = Fornecedores::criar($destino)->listar();
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Numa empresa, só os ficheiros com o nome dela.
        if ($tenantId !== null) {
            $ficheiros = array_values(array_filter($ficheiros, fn ($f) => str_starts_with($f['nome'], "soserp-empresa-{$tenantId}-")));
        } else {
            $ficheiros = array_values(array_filter($ficheiros, fn ($f) => str_starts_with($f['nome'], 'soserp-bd-')));
        }

        return response()->json(['ficheiros' => $ficheiros]);
    }

    /* ════════ Cópias ════════ */

    public function descarregar(Request $request, int $id): BinaryFileResponse
    {
        $copia = CopiaDeSeguranca::doAmbito($this->ambito($request))->where('estado', 'concluida')->findOrFail($id);
        $caminho = Pasta::caminho($copia);
        abort_unless($copia->ficheiro_local && is_file($caminho), 404, __('Esta cópia já não está no servidor.'));

        Log::info('Cópia de segurança descarregada', ['copia' => $copia->id, 'tenant_id' => $copia->tenant_id, 'por' => $request->user()->id]);

        return response()->download($caminho, $copia->ficheiro, ['Cache-Control' => 'no-store']);
    }

    public function reenviar(Request $request, int $id, FazerCopia $servico): JsonResponse
    {
        $tenantId = $this->ambito($request);
        $copia = CopiaDeSeguranca::doAmbito($tenantId)->where('estado', 'concluida')->where('ficheiro_local', true)->findOrFail($id);
        $destino = $request->filled('destino_id') ? DestinoDeCopia::doAmbito($tenantId)->findOrFail((int) $request->input('destino_id')) : null;

        app()->terminating(fn () => $servico->enviar($copia, $destino));

        return response()->json(['message' => __('A enviar outra vez. O estado do envio actualiza-se sozinho.')], 202);
    }

    public function apagarCopia(Request $request, int $id): JsonResponse
    {
        $copia = CopiaDeSeguranca::doAmbito($this->ambito($request))->findOrFail($id);
        abort_if($copia->estado === 'a_correr', 409, __('A cópia ainda está a ser feita.'));

        foreach ($copia->envios()->where('estado', 'enviado')->with('destino')->get() as $envio) {
            try {
                if ($envio->destino) {
                    Fornecedores::criar($envio->destino)->apagar((string) $envio->remoto);
                }
            } catch (\Throwable $e) {
                Log::warning('Não foi possível apagar a cópia no destino', ['envio' => $envio->id, 'erro' => $e->getMessage()]);
            }
        }

        if (str_starts_with($copia->ficheiro, 'soserp-') && ! str_contains($copia->ficheiro, 'a-preparar')) {
            @unlink(Pasta::caminho($copia));
            @unlink(Pasta::caminho($copia) . '.json');
        }
        $copia->envios()->delete();
        $copia->delete();

        return response()->json(['message' => __('Cópia apagada do servidor e dos destinos.')]);
    }

    /* ════════ Restauro ════════ */

    public function restaurar(Request $request, RestaurarCopia $servico): JsonResponse
    {
        $tenantId = $this->ambito($request);

        $d = $request->validate([
            'copia_id' => ['nullable', 'integer', 'required_without:destino_id'],
            'destino_id' => ['nullable', 'integer'],
            'ficheiro' => ['nullable', 'string', 'max:500', 'required_without:copia_id'],
            'frase' => ['nullable', 'string', 'max:200'],
            'confirmacao' => ['required', 'in:RESTAURAR'],
            'senha' => ['required', 'string'],
        ], [
            'confirmacao.in' => __('Escreva RESTAURAR, em maiúsculas, para confirmar.'),
        ]);

        $this->exigirSenha($request, $d['senha']);

        $copia = ! empty($d['copia_id']) ? CopiaDeSeguranca::doAmbito($tenantId)->where('estado', 'concluida')->findOrFail($d['copia_id']) : null;
        $destino = ! empty($d['destino_id']) ? DestinoDeCopia::doAmbito($tenantId)->findOrFail($d['destino_id']) : null;

        if (! $copia && $d['ficheiro']) {
            $nome = basename($d['ficheiro']);
            $esperado = $tenantId === null ? 'soserp-bd-' : "soserp-empresa-{$tenantId}-";
            abort_unless(str_starts_with($nome, $esperado), 422, __('Esse ficheiro não é uma cópia deste âmbito.'));
        }

        return $this->lancarRestauro($request, $servico, $tenantId, [
            'copia_id' => $copia?->id,
            'destino_id' => $destino?->id,
            'ficheiro' => $copia ? null : $d['ficheiro'],
        ], $d['frase'] ?? null);
    }

    /** Repor a partir de um ficheiro carregado do computador. */
    public function restaurarCarregado(Request $request, RestaurarCopia $servico): JsonResponse
    {
        $tenantId = $this->ambito($request);

        $d = $request->validate([
            'copia' => ['required', 'file', 'max:' . (1024 * 1024)],
            'frase' => ['nullable', 'string', 'max:200'],
            'confirmacao' => ['required', 'in:RESTAURAR'],
            'senha' => ['required', 'string'],
        ], [
            'confirmacao.in' => __('Escreva RESTAURAR, em maiúsculas, para confirmar.'),
        ]);

        $this->exigirSenha($request, $d['senha']);

        $nome = $request->file('copia')->getClientOriginalName();
        $valido = $tenantId === null
            ? preg_match('/\.sql\.gz(\.soscopia)?$/', $nome)
            : preg_match('/\.jsonl\.gz(\.soscopia)?$/', $nome);
        abort_unless($valido, 422, $tenantId === null
            ? __('Carregue uma cópia da base (.sql.gz ou .sql.gz.soscopia).')
            : __('Carregue uma cópia desta empresa (.jsonl.gz ou .jsonl.gz.soscopia).'));

        $destino = Pasta::doAmbito($tenantId) . '/.carregada-' . uniqid() . (str_ends_with($nome, '.soscopia') ? '.soscopia' : '.gz');
        $request->file('copia')->move(dirname($destino), basename($destino));

        return $this->lancarRestauro($request, $servico, $tenantId, ['ficheiro' => $nome], $d['frase'] ?? null, $destino);
    }

    public function estadoDoRestauro(Request $request, int $id): JsonResponse
    {
        return response()->json(['restauro' => $this->restauro(RestauroDeCopia::doAmbito($this->ambito($request))->findOrFail($id))]);
    }

    /* ════════ Peças ════════ */

    private function lancarRestauro(Request $request, RestaurarCopia $servico, ?int $tenantId, array $origem, ?string $frase, ?string $carregado = null): JsonResponse
    {
        if (RestauroDeCopia::doAmbito($tenantId)->where('estado', 'a_correr')->where('created_at', '>', now()->subHour())->exists()) {
            return response()->json(['message' => __('Já está um restauro a correr.')], 409);
        }

        $restauro = RestauroDeCopia::create($origem + [
            'tenant_id' => $tenantId,
            'estado' => 'a_correr',
            'pedido_por' => $request->user()->id,
            'iniciado_em' => now(),
        ]);

        Log::warning('Restauro de cópia pedido', ['tenant_id' => $tenantId, 'restauro' => $restauro->id, 'por' => $request->user()->id]);

        app()->terminating(fn () => $servico->restaurar($restauro, $frase, $carregado));

        return response()->json([
            'message' => $tenantId === null
                ? __('Restauro a correr. Primeiro faz-se uma cópia do estado actual; a sessão pode terminar quando a base for reposta.')
                : __('Restauro a correr. Primeiro faz-se uma cópia do estado actual da empresa.'),
            'restauro_id' => $restauro->id,
        ], 202);
    }

    private function exigirSenha(Request $request, string $senha): void
    {
        $chave = 'copias-senha:' . $request->user()->id;

        if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($chave, 5)) {
            throw ValidationException::withMessages(['senha' => [__('Demasiadas tentativas. Tente de novo dentro de alguns minutos.')]])->status(429);
        }

        if (! Hash::check($senha, (string) $request->user()->password)) {
            \Illuminate\Support\Facades\RateLimiter::hit($chave, 600);

            throw ValidationException::withMessages(['senha' => [__('A senha não está certa.')]]);
        }

        \Illuminate\Support\Facades\RateLimiter::clear($chave);
    }

    /**
     * Está uma cópia a correr neste âmbito? Tenta-se a tranca e, se veio, larga-se
     * logo — só a NOSSA. Nunca `forceRelease`: soltava a de uma cópia a meio.
     */
    private function aCorrer(?int $tenantId): bool
    {
        $tranca = Cache::lock(FazerCopia::tranca($tenantId), 5);

        if (! $tranca->get()) {
            return true;
        }

        $tranca->release();

        return false;
    }

    private function copia(CopiaDeSeguranca $c): array
    {
        return [
            'id' => $c->id,
            'ficheiro' => str_contains($c->ficheiro, 'a-preparar') ? null : $c->ficheiro,
            'tamanho' => (int) $c->tamanho,
            'cifrada' => $c->cifrada,
            'origem' => $c->origem,
            'estado' => $c->estado,
            'erro' => $c->erro,
            'no_servidor' => $c->ficheiro_local && $c->estado === 'concluida',
            'linhas' => $c->resumo['linhas'] ?? null,
            'tabelas' => $c->resumo['tabelas'] ?? null,
            'iniciada_em' => $c->iniciada_em?->toIso8601String(),
            'concluida_em' => $c->concluida_em?->toIso8601String(),
            'duracao_s' => $c->iniciada_em && $c->concluida_em ? $c->iniciada_em->diffInSeconds($c->concluida_em) : null,
            'envios' => $c->envios->map(fn ($e) => [
                'destino_id' => $e->destino_id,
                'destino' => $e->destino?->nome,
                'tipo' => $e->destino?->tipo,
                'estado' => $e->estado,
                'erro' => $e->erro,
                'enviado_em' => $e->enviado_em?->toIso8601String(),
            ])->values(),
        ];
    }

    private function restauro(RestauroDeCopia $r): array
    {
        return [
            'id' => $r->id,
            'copia_id' => $r->copia_id,
            'ficheiro' => $r->ficheiro,
            'estado' => $r->estado,
            'erro' => $r->erro,
            'resumo' => $r->resumo,
            'copia_previa_id' => $r->copia_previa_id,
            'iniciado_em' => $r->iniciado_em?->toIso8601String(),
            'concluido_em' => $r->concluido_em?->toIso8601String(),
        ];
    }

    /** O que impede ou ameaça as cópias, dito antes de alguém precisar delas. */
    private function avisos(?int $tenantId): array
    {
        $avisos = [];

        if ($tenantId === null && ! function_exists('exec')) {
            $avisos[] = ['cor' => 'perigo', 'texto' => __('exec() está desligado neste servidor: a cópia da base inteira não funciona.')];
        }

        $destinos = DestinoDeCopia::doAmbito($tenantId)->where('activo', true)->count();
        if ($destinos === 0) {
            $avisos[] = ['cor' => 'aviso', 'texto' => __('As cópias só estão neste servidor. Se o servidor se perder, perdem-se com ele — ligue pelo menos um destino fora (Google Drive, OneDrive, FTP…).')];
        }

        $agenda = AgendaDeCopia::para($tenantId);
        if (! $agenda->cifrar && $destinos > 0) {
            $avisos[] = ['cor' => 'aviso', 'texto' => __('As cópias saem do servidor sem cifra. Ligue a cifra com uma frase-passe: uma cópia tem todos os dados, incluindo dados pessoais.')];
        }

        $ultima = CopiaDeSeguranca::doAmbito($tenantId)->where('estado', 'concluida')->max('concluida_em');
        if ($agenda->activa && $ultima && \Carbon\Carbon::parse($ultima)->lt(now()->subHours((int) $agenda->intervalo_horas * 3))) {
            $avisos[] = ['cor' => 'perigo', 'texto' => __('A última cópia concluída tem mais de :h horas. As cópias automáticas podem não estar a correr.', ['h' => $agenda->intervalo_horas * 3])];
        }

        return $avisos;
    }
}
