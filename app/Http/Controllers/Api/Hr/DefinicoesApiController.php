<?php

namespace App\Http\Controllers\Api\Hr;

use App\Http\Controllers\Controller;
use App\Models\HR\HRSetting;
use App\Services\HR\DefinicoesRH;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * AS DEFINIÇÕES DE RH — os números que decidem quanto cada pessoa recebe.
 *
 * A taxa de INSS, os limites de isenção, o multiplicador da hora extra, os
 * dias de licença de maternidade. Um erro aqui não dá erro nenhum: dá salários
 * errados, mês após mês, até alguém reparar.
 *
 * DUAS PERMISSÕES, E NÃO UMA. `hr.settings.view` abre o ecrã;
 * `hr.settings.edit` é o que deixa gravar. Ver as regras da casa não é poder
 * mudá-las — e o componente Livewire que aqui estava não verificava nenhuma,
 * com um comentário a explicar porquê: as permissões de RH não existiam.
 * Agora existem.
 *
 * O CATÁLOGO GARANTE-SE À ENTRADA. Uma empresa sem definições abria o ecrã
 * antigo e via uma página em branco — sem lista, sem aviso, sem explicação —
 * porque o catálogo estava preso à empresa 1. `DefinicoesRH::garantirPara()`
 * só ACRESCENTA o que falta: quem baixou o INSS não vê isso revertido por ter
 * aberto o ecrã.
 */
class DefinicoesApiController extends Controller
{
    /** As categorias, pela ordem em que se lêem, com cor e ícone. */
    private const CATEGORIAS = [
        'general' => ['rotulo' => 'Geral', 'icone' => 'fa-circle-info', 'cor' => 'primaria'],
        'worktime' => ['rotulo' => 'Horário de Trabalho', 'icone' => 'fa-business-time', 'cor' => 'roxo'],
        'overtime' => ['rotulo' => 'Horas Extras', 'icone' => 'fa-clock', 'cor' => 'aviso'],
        'vacation' => ['rotulo' => 'Férias', 'icone' => 'fa-umbrella-beach', 'cor' => 'bom'],
        'leave' => ['rotulo' => 'Licenças', 'icone' => 'fa-calendar-xmark', 'cor' => 'ciano'],
        'payroll' => ['rotulo' => 'Folha de Pagamento', 'icone' => 'fa-money-bill-wave', 'cor' => 'bom'],
        'benefits' => ['rotulo' => 'Subsídios', 'icone' => 'fa-gift', 'cor' => 'rosa'],
    ];

    private const TIPOS = [
        'integer' => 'Número inteiro', 'decimal' => 'Número decimal',
        'percentage' => 'Percentagem', 'boolean' => 'Sim ou não',
        'text' => 'Texto', 'json' => 'JSON',
    ];

    private function exigir(Request $request, string $permissao): void
    {
        abort_unless($request->user()?->can($permissao), 403, __('Sem permissão para esta operação.'));
    }

    public function index(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.settings.view');

        $tenantId = activeTenantId();

        // Criar o que falta ANTES de ler — e dizer quantas nasceram, para quem
        // abre o ecrã pela primeira vez saber de onde vieram os valores.
        $criadas = DefinicoesRH::garantirPara($tenantId);

        $definicoes = HRSetting::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('category')->orderBy('display_order')
            ->get();

        $porCategoria = $definicoes->groupBy('category');

        // A ordem das categorias é a da constante, não a alfabética da base:
        // «Geral» antes de «Subsídios» é a ordem por que se lê.
        $seccoes = collect(self::CATEGORIAS)
            ->map(fn ($c, $chave) => [
                'chave' => $chave,
                'rotulo' => __($c['rotulo']),
                'icone' => $c['icone'],
                'cor' => $c['cor'],
                'campos' => ($porCategoria[$chave] ?? collect())->map(fn (HRSetting $s) => $this->campo($s))->values(),
            ])
            ->filter(fn ($s) => count($s['campos']) > 0)
            ->values();

        // O que a base tem e a constante não conhece não desaparece: junta-se
        // no fim, em «Outras». Uma definição escondida é uma definição que
        // ninguém consegue corrigir.
        $orfas = $porCategoria->keys()->diff(array_keys(self::CATEGORIAS));

        if ($orfas->isNotEmpty()) {
            $seccoes->push([
                'chave' => 'outras',
                'rotulo' => __('Outras'),
                'icone' => 'fa-sliders',
                'cor' => 'neutra',
                'campos' => $orfas->flatMap(fn ($c) => $porCategoria[$c])
                    ->map(fn (HRSetting $s) => $this->campo($s))->values(),
            ]);
        }

        return response()->json([
            'seccoes' => $seccoes,
            'criadas_agora' => $criadas,
            'permissoes' => ['pode_editar' => (bool) $request->user()?->can('hr.settings.edit')],
        ]);
    }

    /**
     * GRAVAR — uma definição ou várias, pela mesma porta.
     *
     * O ecrã grava campo a campo (é assim que se corrige um número sem medo),
     * mas a porta aceita um lote: o botão «Restaurar padrões» é um lote de
     * cinquenta e nove, e cinquenta e nove pedidos seriam cinquenta e nove
     * oportunidades de metade passar e metade não.
     */
    public function guardar(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.settings.edit');

        $dados = $request->validate([
            'valores' => ['required', 'array', 'min:1'],
        ]);

        $tenantId = activeTenantId();

        $definicoes = HRSetting::where('tenant_id', $tenantId)
            ->whereIn('key', array_keys($dados['valores']))
            ->get()
            ->keyBy('key');

        $erros = [];
        $paraGravar = [];

        foreach ($dados['valores'] as $chave => $valor) {
            $d = $definicoes[$chave] ?? null;

            if (! $d) {
                // Uma chave que não existe nesta empresa não se cria por aqui:
                // o catálogo é que manda o que existe.
                $erros["valores.{$chave}"] = [__('Esta definição não existe nesta empresa.')];

                continue;
            }

            // O BOOLEANO NORMALIZA-SE ANTES DE VALIDAR: o `false` do JSON
            // chegava como '' e falhava um `required` que devia passar.
            if ($d->value_type === 'boolean') {
                $valor = filter_var($valor, FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            }

            if ($d->validation_rules) {
                $v = Validator::make(['v' => $valor], ['v' => $d->validation_rules]);

                if ($v->fails()) {
                    // A ETIQUETA DA DEFINIÇÃO NA MENSAGEM: «O valor deve ser
                    // entre 20 e 26» não diz de qual dos cinquenta campos se
                    // fala quando a mensagem aparece no topo.
                    $erros["valores.{$chave}"] = array_map(
                        fn ($m) => $d->label . ': ' . str_replace('v ', '', $m),
                        $v->errors()->get('v')
                    );

                    continue;
                }
            }

            $paraGravar[$chave] = ['modelo' => $d, 'valor' => (string) $valor];
        }

        if ($erros !== []) {
            return response()->json(['message' => __('Há valores por corrigir.'), 'errors' => $erros], 422);
        }

        DB::transaction(function () use ($paraGravar) {
            foreach ($paraGravar as $item) {
                $item['modelo']->update(['value' => $item['valor']]);
            }
        });

        HRSetting::clearCache();

        return response()->json([
            'message' => trans_choice('{1}:n definição gravada.|[2,*]:n definições gravadas.', count($paraGravar), ['n' => count($paraGravar)]),
            'valores' => collect($paraGravar)->map(fn ($i) => $i['modelo']->fresh()->casted_value),
        ]);
    }

    /**
     * RESTAURAR OS PADRÕES — de uma secção, ou de tudo.
     *
     * Repor tudo apaga meses de afinação de uma empresa, e o botão que o fazia
     * não perguntava nada. Aqui a secção é obrigatória de propósito: quem quer
     * repor tudo escolhe «tudo» e o ecrã pergunta-lhe outra vez.
     */
    public function repor(Request $request): JsonResponse
    {
        $this->exigir($request, 'hr.settings.edit');

        $dados = $request->validate([
            'seccao' => ['required', 'string', 'max:40'],
        ]);

        $q = HRSetting::where('tenant_id', activeTenantId())->where('is_active', true);

        if ($dados['seccao'] !== 'tudo') {
            $q->where('category', $dados['seccao']);
        }

        $definicoes = $q->get();

        DB::transaction(function () use ($definicoes) {
            foreach ($definicoes as $d) {
                $d->update(['value' => $d->default_value]);
            }
        });

        HRSetting::clearCache();

        return response()->json([
            'message' => trans_choice(
                '{1}:n definição reposta no valor padrão.|[2,*]:n definições repostas nos valores padrão.',
                $definicoes->count(),
                ['n' => $definicoes->count()]
            ),
        ]);
    }

    /* ─── Por dentro ──────────────────────────────────────────────────── */

    private function campo(HRSetting $s): array
    {
        return [
            'chave' => $s->key,
            'etiqueta' => $s->label,
            'ajuda' => $s->description,
            'tipo' => $s->value_type,
            'tipo_rotulo' => __(self::TIPOS[$s->value_type] ?? 'Texto'),
            'valor' => $s->casted_value,
            'padrao' => $s->default_value,
            /*
             * INFORMATIVA: fica no ecrã mas nenhum cálculo a lê ainda.
             * Mostrá-la como um campo vulgar é mentir — edita-se, grava, e não
             * acontece nada, que é exactamente a impressão de «isto não
             * funciona».
             */
            'informativa' => in_array($s->key, DefinicoesRH::INFORMATIVAS, true),
        ];
    }
}
