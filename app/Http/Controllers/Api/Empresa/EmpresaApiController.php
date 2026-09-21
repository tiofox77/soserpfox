<?php

namespace App\Http\Controllers\Api\Empresa;

use App\Http\Controllers\Controller;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\Tax;
use App\Models\Product;
use App\Models\Tenant;
use App\Services\Tenant\TaxRegimeSyncer;
use App\Support\Geografia;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * OS DADOS DA EMPRESA.
 *
 * VER E MUDAR SÃO DIREITOS DIFERENTES. Um contabilista precisa do NIF e da
 * morada; não precisa de mexer no REGIME FISCAL — o campo mais perigoso do
 * sistema, porque mudá-lo reescreve o imposto por omissão e o regime de TODOS
 * os produtos de uma vez. Por isso `settings.view` abre a página e
 * `settings.edit` é que grava.
 *
 * O PAÍS É UM CÓDIGO ISO DE DUAS LETRAS, e não um nome. É o único formato que a
 * AGT aceita; escrevia-se aqui à mão e viu-se uma empresa angolana gravada com
 * «Portugal».
 */
class EmpresaApiController extends Controller
{
    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    private function recusa(string $mensagem): never
    {
        throw ValidationException::withMessages(['geral' => [$mensagem]]);
    }

    private function empresa(): Tenant
    {
        $t = Tenant::find(activeTenantId());

        abort_if(! $t, 404, __('Empresa não encontrada.'));

        return $t;
    }

    public function mostrar(Request $request): JsonResponse
    {
        $this->exigir($request, 'settings.view');

        $t = $this->empresa();
        $regime = Tenant::canonicalRegime($t->regime);

        $imposto = Tax::where('tenant_id', $t->id)->where('is_default', true)->first();
        $definicoes = InvoicingSettings::where('tenant_id', $t->id)->first();

        $produtos = fn () => Product::withoutGlobalScopes()->where('tenant_id', $t->id);

        return response()->json([
            'data' => [
                'name' => $t->name,
                'company_name' => $t->company_name,
                'nif' => $t->nif,
                'email' => $t->email,
                'phone' => $t->phone,
                'address' => $t->address,
                'postal_code' => $t->postal_code,
                'city' => $t->city,
                // Um país gravado à mão («Angola», «Portugal») converte-se ao
                // ler, para o selector o encontrar. Não se reconhecendo, fica o
                // padrão e a pessoa escolhe — melhor do que adivinhar num campo
                // que sai nos documentos.
                'country' => Geografia::normalizarPais($t->country) ?? Geografia::PAIS_PADRAO,
                'province' => $t->province ?: '',
                'municipality' => $t->municipality ?: '',
                'neighbourhood' => $t->neighbourhood ?: '',
                'regime' => $regime,
                'logo' => $t->logo ? Storage::url($t->logo) : null,
                'slug' => $t->slug,
                'actualizado' => $t->updated_at?->format('Y-m-d H:i'),
            ],
            /*
             * O RESUMO FISCAL — o efeito prático do regime, em números.
             *
             * Um regime não se escolhe no abstracto: escolhe-se olhando para o
             * imposto que vai passar a sair nos documentos e para quantos
             * produtos vão mudar de mão.
             */
            'resumo' => [
                'regime' => [
                    'chave' => $regime,
                    'curto' => Tenant::REGIMES[$regime]['short'],
                    'isento' => (bool) Tenant::REGIMES[$regime]['exempt'],
                    'taxa' => (float) Tenant::REGIMES[$regime]['default_rate'],
                ],
                'imposto' => $imposto ? [
                    'nome' => $imposto->name,
                    'taxa' => (float) $imposto->rate,
                    'saft' => $imposto->saft_type,
                    'isencao' => $imposto->exemption_code,
                ] : null,
                'produtos' => [
                    'total' => $produtos()->count(),
                    'com_iva' => $produtos()->where('tax_type', 'iva')->count(),
                    'isentos' => $produtos()->where('tax_type', 'isento')->count(),
                    /*
                     * ISENTOS SEM MOTIVO é o número que interessa: a AGT recusa
                     * um documento isento sem código de isenção, e cada linha
                     * aqui é uma factura que vai voltar rejeitada.
                     */
                    'isentos_sem_motivo' => $produtos()->where('tax_type', 'isento')
                        ->where(fn ($q) => $q->whereNull('exemption_reason')->orWhere('exemption_reason', ''))
                        ->count(),
                ],
                // Sem imposto por omissão configurado, nenhum documento sai
                // com a taxa certa — e o ecrã aponta para onde isso se arranja.
                'falta_configurar_imposto' => $imposto === null,
                'tem_definicoes_de_facturacao' => $definicoes !== null,
            ],
            'regimes' => collect(Tenant::REGIMES)->map(fn ($m, $k) => [
                'valor' => $k,
                'rotulo' => $m['label'],
                'curto' => $m['short'],
                'descricao' => $m['description'],
                'facturacao' => $m['turnover'],
                'isento' => (bool) $m['exempt'],
                'taxa' => (float) $m['default_rate'],
                'codigo_de_isencao' => $m['exemption_code'] ?? null,
            ])->values(),
            'geografia' => [
                'provincias' => Geografia::provincias(),
                'provincias_novas' => Geografia::provinciasNovas(),
                'municipios' => $this->municipiosPorProvincia(),
                'bairros' => $this->bairrosPorMunicipio(),
                'paises' => Geografia::paises(),
                'pais_padrao' => Geografia::PAIS_PADRAO,
            ],
            'permissoes' => [
                'editar' => (bool) $request->user()?->can('settings.edit'),
                'definicoes_de_facturacao' => (bool) $request->user()?->can('invoicing.settings.view'),
            ],
        ]);
    }

    public function guardar(Request $request): JsonResponse
    {
        /*
         * A GUARDA DA ROTA NÃO CHEGA.
         *
         * A página abre-se com `settings.view`; gravar é outro direito. Sem
         * esta linha, quem só podia ver passava a mudar o regime fiscal da
         * empresa inteira com um pedido escrito à mão.
         */
        $this->exigir($request, 'settings.edit');

        $t = $this->empresa();

        $dados = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            // O NIF DE EMPRESA, e não o que o campo antigo aceitava: um
            // `[A-Za-z0-9]{5,20}` deixava passar o número do bilhete de
            // identidade — no próprio ecrã onde se vem corrigir isso.
            'nif' => ['required', new \App\Rules\NifDeEmpresa()],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'neighbourhood' => ['nullable', 'string', 'max:100'],
            // Duas letras, e da lista. Ver App\Rules\PaisIso.
            'country' => ['required', 'string', 'size:2', new \App\Rules\PaisIso()],
            'regime' => ['required', 'in:'.implode(',', array_keys(Tenant::REGIMES))],
            /*
             * MUDAR DE REGIME PEDE UM SIM ESCRITO.
             *
             * Não é um campo como os outros: ao gravar, o imposto por omissão e
             * o regime de todos os produtos mudam de uma vez. O ecrã mostra as
             * consequências e só depois manda este `confirmar_regime`.
             */
            'confirmar_regime' => ['nullable', 'boolean'],
        ], [
            'name.required' => __('O nome da empresa é obrigatório.'),
            'email.email' => __('Escreva um e-mail válido.'),
            'country.required' => __('O país é obrigatório.'),
            'regime.in' => __('Escolha um dos regimes fiscais da AGT.'),
        ], ['name' => __('nome'), 'nif' => __('NIF'), 'regime' => __('regime fiscal')]);

        $anterior = $t->regime;
        $novo = Tenant::canonicalRegime($dados['regime']);
        $mudouDeRegime = $novo !== Tenant::canonicalRegime($anterior);

        if ($mudouDeRegime && ! ($dados['confirmar_regime'] ?? false)) {
            throw ValidationException::withMessages([
                'regime' => [__('Confirme a mudança de regime antes de guardar.')],
            ]);
        }

        /*
         * O NIF DE UMA EMPRESA QUE JÁ FALA COM A AGT EM PRODUÇÃO NÃO SE MUDA AQUI.
         *
         * Vai em cada documento assinado e em cada série registada: trocá-lo
         * deixava as séries a apontar para outro contribuinte e os documentos
         * seguintes recusados. É a mesma regra da ficha AGT (GestaoAgt::
         * exigirNifMutavel), aqui só para PRODUÇÃO — em homologação nada tem
         * valor fiscal, e é aí que se corrige um NIF mal escrito no arranque.
         */
        $novoNif = ! empty($dados['nif']) ? strtoupper(trim($dados['nif'])) : null;

        if ($novoNif !== strtoupper(trim((string) $t->nif))) {
            $falaComAgt = \App\Models\Invoicing\InvoicingSeries::where('tenant_id', $t->id)->whereNotNull('agt_series_id')->where('agt_environment', 'production')->exists()
                || \App\Models\AGT\AGTSubmission::where('tenant_id', $t->id)->where('agt_environment', 'production')->exists();

            if ($falaComAgt) {
                throw ValidationException::withMessages([
                    'nif' => [__('O NIF desta empresa já está em séries registadas ou documentos comunicados à AGT e não se muda aqui. Fale com o suporte.')],
                ]);
            }
        }

        $valores = [
            'name' => trim($dados['name']),
            'company_name' => ! empty($dados['company_name']) ? trim($dados['company_name']) : null,
            'nif' => ! empty($dados['nif']) ? strtoupper(trim($dados['nif'])) : null,
            'email' => ! empty($dados['email']) ? trim($dados['email']) : null,
            'phone' => ! empty($dados['phone']) ? trim($dados['phone']) : null,
        ] + $this->moradaParaGravar($dados);

        /*
         * SÓ SE ESCREVE `regime` QUANDO ELE MUDA DE FACTO.
         *
         * Assim, guardar os contactos de uma empresa com um valor antigo
         * («regime_isencao») não reescreve o campo em silêncio — a
         * canonicalização acontece na leitura.
         */
        if ($mudouDeRegime) {
            $valores['regime'] = $novo;
        }

        $t->update($valores);

        $recado = __('Dados da empresa actualizados.');
        $aviso = null;

        if ($mudouDeRegime) {
            try {
                $resultado = (new TaxRegimeSyncer())->sync($t->fresh(), $anterior);

                if (! empty($resultado['changed'])) {
                    $recado .= ' '.__('Regime aplicado: :regime.', [
                        'regime' => Tenant::REGIMES[$novo]['label'],
                    ]);

                    if (! empty($resultado['products_count'])) {
                        $recado .= ' '.__(':n produto(s) actualizado(s).', ['n' => $resultado['products_count']]);
                    }
                }
            } catch (\Throwable $e) {
                // Os dados ficaram gravados; o que falhou foi a propagação. Um
                // 500 aqui escondia as duas coisas atrás de uma só.
                \Log::error('Empresa: falha ao sincronizar o regime fiscal', [
                    'tenant_id' => $t->id, 'de' => $anterior, 'para' => $novo, 'erro' => $e->getMessage(),
                ]);

                $aviso = __('Dados guardados, mas a sincronização fiscal falhou: :erro', ['erro' => $e->getMessage()]);
            }
        }

        return response()->json(['message' => $recado, 'aviso' => $aviso]);
    }

    /** A morada normalizada, com a mesma regra de todo o sistema. */
    private function moradaParaGravar(array $dados): array
    {
        $pais = Geografia::normalizarPais($dados['country']) ?? Geografia::PAIS_PADRAO;
        $ehAngola = $pais === Geografia::PAIS_PADRAO;

        $provincia = trim((string) ($dados['province'] ?? ''));
        $municipio = trim((string) ($dados['municipality'] ?? ''));

        return [
            'address' => trim((string) ($dados['address'] ?? '')) ?: null,
            'country' => $pais,
            'province' => $provincia === '' ? null
                : ($ehAngola ? Geografia::normalizarProvincia($provincia) : $provincia),
            'municipality' => $municipio === '' ? null : $municipio,
            'neighbourhood' => trim((string) ($dados['neighbourhood'] ?? '')) ?: null,
            // EM ANGOLA A CIDADE É O MUNICÍPIO. A coluna `city` é lida por
            // relatórios, filtros e pelo SAFT; se o município passasse a viver
            // só noutra coluna, tudo isso via os endereços antigos para sempre.
            'city' => $ehAngola
                ? ($municipio ?: null)
                : (trim((string) ($dados['city'] ?? '')) ?: null),
            'postal_code' => trim((string) ($dados['postal_code'] ?? '')) ?: null,
        ];
    }

    /* ─── O logótipo ──────────────────────────────────────────────────── */

    public function logotipo(Request $request): JsonResponse
    {
        $this->exigir($request, 'settings.edit');

        $request->validate([
            'logo' => ['required', 'image', 'max:2048'],
        ], [
            'logo.image' => __('O logótipo tem de ser uma imagem.'),
            'logo.max' => __('O logótipo não pode passar de 2 MB.'),
        ]);

        $t = $this->empresa();
        $anterior = $t->logo;

        $caminho = $request->file('logo')->storeAs(
            'tenants/'.$t->id,
            'logo.'.$request->file('logo')->extension(),
            'public',
        );

        // O anterior só se apaga se mudou de nome — se a extensão for a mesma,
        // o ficheiro novo já escreveu por cima dele.
        if ($anterior && $anterior !== $caminho && Storage::disk('public')->exists($anterior)) {
            Storage::disk('public')->delete($anterior);
        }

        $t->update(['logo' => $caminho]);

        return response()->json([
            'message' => __('Logótipo actualizado.'),
            'logo' => Storage::url($caminho),
        ]);
    }

    public function apagarLogotipo(Request $request): JsonResponse
    {
        $this->exigir($request, 'settings.edit');

        $t = $this->empresa();

        if (! $t->logo) {
            $this->recusa(__('Esta empresa não tem logótipo.'));
        }

        if (Storage::disk('public')->exists($t->logo)) {
            Storage::disk('public')->delete($t->logo);
        }

        $t->update(['logo' => null]);

        return response()->json(['message' => __('Logótipo removido.')]);
    }

    /**
     * O REVENDEDOR DA EMPRESA, e se o deixa entrar — com as vezes que entrou.
     *
     * Ver `SuporteDoRevendedor`. Uma empresa sem revendedor responde que não
     * tem, e o ecrã não mostra a secção.
     */
    public function suporteDoRevendedor(Request $request): JsonResponse
    {
        $this->exigir($request, 'settings.view');
        $t = $this->empresa();

        return response()->json([
            'revendedor' => \App\Services\Revenda\SuporteDoRevendedor::revendedor($t),
            'permitido' => \App\Services\Revenda\SuporteDoRevendedor::permitido($t),
            'entradas' => $t->reseller_id ? \App\Services\Revenda\SuporteDoRevendedor::entradas($t) : [],
            'pode_mudar' => (bool) $request->user()?->can('settings.edit'),
        ]);
    }

    /**
     * Ligar ou desligar. É de quem gere a empresa (`settings.edit`) e nunca do
     * próprio revendedor: durante a personificação dele, esta porta está
     * fechada no PersonificacaoComPrazo.
     */
    public function definirSuporteDoRevendedor(Request $request): JsonResponse
    {
        $this->exigir($request, 'settings.edit');
        $t = $this->empresa();

        $dados = $request->validate(['permitido' => ['required', 'boolean']]);

        abort_unless($t->reseller_id, 422, __('Esta empresa não tem revendedor.'));

        \App\Services\Revenda\SuporteDoRevendedor::definir($t, (bool) $dados['permitido']);

        // Fica na trilha quem abriu ou fechou a porta, e quando.
        app(\App\Services\Audit\AuditRecorder::class)->acto(
            $dados['permitido'] ? 'revendedor.suporte.autorizado' : 'revendedor.suporte.retirado',
            $t->id,
            ['revendedor' => \App\Services\Revenda\SuporteDoRevendedor::revendedor($t)],
            $t,
        );

        return response()->json([
            'permitido' => \App\Services\Revenda\SuporteDoRevendedor::permitido($t),
            'message' => $dados['permitido']
                ? __('O seu revendedor pode entrar na empresa para dar suporte.')
                : __('O acesso do revendedor foi retirado. Se estava dentro, sai no próximo clique.'),
        ]);
    }

    /* ─── A geografia ─────────────────────────────────────────────────── */

    /** @return array<string,list<string>> província => municípios */
    private function municipiosPorProvincia(): array
    {
        $mapa = [];

        foreach (Geografia::provincias() as $provincia) {
            $mapa[$provincia] = Geografia::municipios($provincia);
        }

        return $mapa;
    }

    /**
     * @return array<string,list<string>> município => bairros SUGERIDOS
     *
     * Só os municípios que têm sugestões. Angola não tem registo nacional de
     * bairros, por isso o campo sugere e aceita o que se escrever.
     */
    private function bairrosPorMunicipio(): array
    {
        $mapa = [];

        foreach (Geografia::todosOsMunicipios() as $municipio => $provincia) {
            $bairros = Geografia::bairros($municipio);

            if ($bairros !== []) {
                $mapa[$municipio] = $bairros;
            }
        }

        return $mapa;
    }
}
