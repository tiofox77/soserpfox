<?php

namespace Tests\Feature;

use App\Models\HR\HRSetting;
use App\Services\HR\DefinicoesRH;
use App\Services\HR\HRSettingsService;
use Tests\TenantTestCase;

/**
 * O ecrã de configurações de RH tem de mostrar alguma coisa.
 *
 * Não mostrava. O catálogo vivia dentro do HRSettingsSeeder com
 * `$tenantId = 1;` escrito à mão e um comentário "Ajustar conforme
 * necessário" — que nunca foi ajustado. Só a empresa 1 tinha configurações;
 * as restantes com o módulo activo abriam /hr/settings e viam o cabeçalho, o
 * filtro, e mais nada. Sem lista, sem aviso, sem explicação.
 *
 * E não havia como corrigir de dentro: o ecrã só sabe editar linhas que já
 * existem, portanto criá-las era trabalho de consola, uma empresa de cada vez.
 */
class DefinicoesRHTest extends TenantTestCase
{
    /**
     * A porta do ecrã em React. O componente Livewire deixou de existir, e com
     * ele a nota que dizia que este ecrã não verificava permissão nenhuma —
     * porque nenhuma existia. Existem agora, e são duas.
     */
    private const RAIZ = '/api/v1/invoicing/react/rh/definicoes';

    private function valorDe(string $chave): ?string
    {
        return HRSetting::where('tenant_id', $this->tenant->id)->where('key', $chave)->value('value');
    }

    public function test_uma_empresa_sem_definicoes_recebe_as_do_catalogo(): void
    {
        $this->assertSame(0, HRSetting::where('tenant_id', $this->tenant->id)->count());

        $criadas = DefinicoesRH::garantirPara($this->tenant->id);

        $this->assertSame(count(DefinicoesRH::catalogo()), $criadas);
        $this->assertSame(
            count(DefinicoesRH::catalogo()),
            HRSetting::where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_correr_duas_vezes_nao_duplica(): void
    {
        DefinicoesRH::garantirPara($this->tenant->id);
        $segunda = DefinicoesRH::garantirPara($this->tenant->id);

        $this->assertSame(0, $segunda, 'a segunda passagem não tem nada a criar');
        $this->assertSame(
            count(DefinicoesRH::catalogo()),
            HRSetting::where('tenant_id', $this->tenant->id)->count()
        );
    }

    public function test_nao_reverte_um_valor_que_a_empresa_alterou(): void
    {
        // O ponto mais importante deste serviço. Ele corre no mount() do ecrã,
        // portanto corre a cada visita: se repusesse os valores do catálogo,
        // uma empresa que baixou o INSS via isso desfeito por alguém ter aberto
        // a página.
        DefinicoesRH::garantirPara($this->tenant->id);

        $definicao = HRSetting::where('tenant_id', $this->tenant->id)
            ->where('key', 'inss_employee_rate')->first();

        $this->assertNotNull($definicao, 'a taxa de INSS do trabalhador está no catálogo');

        $definicao->update(['value' => '2.5']);

        DefinicoesRH::garantirPara($this->tenant->id);

        $this->assertSame('2.5', $definicao->fresh()->value, 'o valor da empresa fica');
    }

    public function test_uma_definicao_nova_do_catalogo_chega_a_uma_empresa_antiga(): void
    {
        DefinicoesRH::garantirPara($this->tenant->id);

        // Simula o catálogo a crescer: apaga-se uma e volta a garantir-se.
        HRSetting::where('tenant_id', $this->tenant->id)
            ->where('key', 'working_hours_per_day')->delete();

        $criadas = DefinicoesRH::garantirPara($this->tenant->id);

        $this->assertSame(1, $criadas);
        $this->assertNotNull(
            HRSetting::where('tenant_id', $this->tenant->id)->where('key', 'working_hours_per_day')->first()
        );
    }

    public function test_o_catalogo_nao_tem_chaves_repetidas(): void
    {
        $chaves = array_column(DefinicoesRH::catalogo(), 'key');

        $this->assertSame(count($chaves), count(array_unique($chaves)));
    }

    public function test_toda_a_definicao_tem_o_que_o_ecra_precisa(): void
    {
        foreach (DefinicoesRH::catalogo() as $d) {
            foreach (['category', 'key', 'label', 'value', 'default_value', 'value_type'] as $campo) {
                $this->assertArrayHasKey($campo, $d, "falta {$campo} em " . ($d['key'] ?? '?'));
                $this->assertNotSame('', (string) $d[$campo], "{$campo} vazio em " . ($d['key'] ?? '?'));
            }
        }
    }

    public function test_o_ecra_cria_as_definicoes_na_primeira_visita(): void
    {
        // É isto que faz o ecrã deixar de estar vazio, e sem ninguém ter de
        // correr nada na consola.
        $this->comModulo('rh')->comPermissoes('hr.settings.view');

        $r = $this->getJson(self::RAIZ)->assertOk();

        $this->assertGreaterThan(0, $r->json('criadas_agora'));
        $this->assertSame(
            count(DefinicoesRH::catalogo()),
            HRSetting::where('tenant_id', $this->tenant->id)->count()
        );

        // E vêm agrupadas pelas secções por que se lêem.
        $this->assertContains('Horário de Trabalho', collect($r->json('seccoes'))->pluck('rotulo')->all());
    }

    public function test_a_segunda_visita_nao_anuncia_nada(): void
    {
        $this->comModulo('rh')->comPermissoes('hr.settings.view');

        $this->getJson(self::RAIZ)->assertOk();

        $this->getJson(self::RAIZ)->assertOk()->assertJsonPath('criadas_agora', 0);
    }

    public function test_gravar_um_lote_devolve_os_valores_gravados(): void
    {
        // O ecrã antigo esvaziava o formulário ao gravar tudo, o que se lia
        // como «apagou as minhas configurações». Aqui a porta devolve o que
        // ficou gravado, e o ecrã escreve-o de volta no campo.
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();

        $this->putJson(self::RAIZ, ['valores' => ['working_hours_per_day' => '9', 'working_days_per_month' => '24']])
            ->assertOk()
            ->assertJsonPath('valores.working_hours_per_day', 9)
            ->assertJsonPath('valores.working_days_per_month', 24);

        $this->assertSame('9', $this->valorDe('working_hours_per_day'));
        $this->assertSame('24', $this->valorDe('working_days_per_month'));
    }

    public function test_gravar_uma_definicao_sozinha(): void
    {
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();

        $this->putJson(self::RAIZ, ['valores' => ['working_days_per_month' => '24']])->assertOk();

        $this->assertSame('24', $this->valorDe('working_days_per_month'));
    }

    public function test_um_valor_fora_das_regras_e_recusado(): void
    {
        // `working_days_per_month` aceita 20 a 26. Um mês de 99 dias úteis
        // passaria direito para o cálculo dos salários.
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();

        $this->putJson(self::RAIZ, ['valores' => ['working_days_per_month' => '99']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valores.working_days_per_month');

        $this->assertSame('22', $this->valorDe('working_days_per_month'), 'o valor do catálogo mantém-se');
    }

    /**
     * UM LOTE É TUDO OU NADA.
     *
     * Metade das definições gravadas e a outra metade não deixava a folha a
     * ser calculada com uma mistura de dois estados.
     */
    public function test_um_lote_com_um_valor_mau_nao_grava_os_bons(): void
    {
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();

        $this->putJson(self::RAIZ, ['valores' => [
            'working_hours_per_day' => '9',
            'working_days_per_month' => '99',
        ]])->assertStatus(422);

        $this->assertSame('8', $this->valorDe('working_hours_per_day'), 'o bom também não passa');
        $this->assertSame('22', $this->valorDe('working_days_per_month'));
    }

    public function test_restaurar_padroes_repoe_a_seccao_escolhida(): void
    {
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();
        $this->putJson(self::RAIZ, ['valores' => ['working_days_per_month' => '25']])->assertOk();

        $this->postJson(self::RAIZ . '/repor', ['seccao' => 'worktime'])->assertOk();

        $this->assertSame('22', $this->valorDe('working_days_per_month'));
    }

    /** VER AS REGRAS DA CASA NÃO É PODER MUDÁ-LAS. */
    public function test_quem_so_ve_nao_grava(): void
    {
        $this->comModulo('rh')->comPermissoes('hr.settings.view');

        $this->getJson(self::RAIZ)->assertOk()->assertJsonPath('permissoes.pode_editar', false);

        $this->putJson(self::RAIZ, ['valores' => ['working_days_per_month' => '24']])->assertForbidden();
        $this->postJson(self::RAIZ . '/repor', ['seccao' => 'tudo'])->assertForbidden();

        $this->assertSame('22', $this->valorDe('working_days_per_month'));
    }

    /** E sem permissão nenhuma, o ecrã nem abre. */
    public function test_sem_permissao_o_ecra_nao_abre(): void
    {
        $this->comModulo('rh');

        $this->getJson(self::RAIZ)->assertForbidden();
    }

    /** Uma chave que não existe nesta empresa não se cria pela porta de gravar. */
    public function test_uma_chave_desconhecida_e_recusada(): void
    {
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();

        $this->putJson(self::RAIZ, ['valores' => ['inventada_agora' => '1']])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valores.inventada_agora');
    }

    public function test_o_nome_da_categoria_cobre_o_catalogo_todo(): void
    {
        // `worktime` e `benefits` faltavam no acessor, e são 17 das 59
        // definições — quase um terço aparecia como "Outro".
        DefinicoesRH::garantirPara($this->tenant->id);

        foreach (HRSetting::where('tenant_id', $this->tenant->id)->get() as $definicao) {
            $this->assertNotSame(
                'Outro',
                $definicao->category_name,
                "categoria sem nome: {$definicao->category}"
            );
        }
    }

    /**
     * Chaves antigas, lidas apenas como recurso quando a nova não existe.
     *
     * Ex.: `HRSetting::get('monthly_food_allowance', HRSetting::get('meal_allowance', 0))`
     * — quem manda é a nova, que está no catálogo. Pôr a antiga também no
     * catálogo dava dois campos no ecrã para a mesma coisa.
     */
    private const LEGADAS = [
        'meal_allowance',
        'transport_allowance',
    ];

    public function test_uma_chave_renomeada_deixa_de_aparecer_no_ecra(): void
    {
        // Sem isto, uma renomeação deixa um campo fantasma: o antigo continua
        // gravado, continua a aparecer, e edita-se sem efeito nenhum porque já
        // ninguém o lê — com a mesma etiqueta do novo, ao lado dele.
        DefinicoesRH::garantirPara($this->tenant->id);

        // Simula o que a sincronização criou antes da renomeação.
        HRSetting::create([
            'tenant_id'     => $this->tenant->id,
            'key'           => 'salary_advance_max_percentage',
            'category'      => 'benefits',
            'label'         => 'Percentual Máximo de Adiantamento',
            'value'         => '50',
            'default_value' => '50',
            'value_type'    => 'percentage',
            'is_active'     => true,
        ]);

        DefinicoesRH::garantirPara($this->tenant->id);

        $antiga = HRSetting::where('tenant_id', $this->tenant->id)
            ->where('key', 'salary_advance_max_percentage')->first();

        $this->assertFalse((bool) $antiga->is_active, 'a antiga sai do ecrã');
        $this->assertSame('50', $antiga->value, 'mas o valor fica guardado — não se apaga nada');

        $this->assertTrue(
            HRSetting::where('tenant_id', $this->tenant->id)
                ->where('key', 'max_salary_advance_percentage')
                ->where('is_active', true)->exists(),
            'e a que o código lê fica activa'
        );
    }

    public function test_o_limite_do_adiantamento_editado_no_ecra_e_o_que_vale(): void
    {
        // O caso concreto: mudava-se o percentual nas configurações e o ecrã de
        // adiantamentos continuava nos 50% escritos no PHP.
        $this->comModulo('rh')->comPermissoes('hr.settings.view', 'hr.settings.edit');

        $this->getJson(self::RAIZ)->assertOk();
        $this->putJson(self::RAIZ, ['valores' => ['max_salary_advance_percentage' => '70']])->assertOk();

        HRSetting::clearCache();

        $this->assertSame(
            70.0,
            (float) HRSetting::get('max_salary_advance_percentage', 50),
            'é isto que o ecrã de adiantamentos lê'
        );
    }

    public function test_subsidio_de_natal_tem_uma_unica_chave_activa_e_o_servico_le_a_mesma_do_payroll(): void
    {
        DefinicoesRH::garantirPara($this->tenant->id);

        $this->assertFalse((bool) HRSetting::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('key', 'christmas_bonus_percentage')
            ->value('is_active'));

        HRSetting::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('key', 'christmas_subsidy_percentage')
            ->update(['value' => '65']);
        HRSetting::clearCache();

        $this->assertSame(0.65, app(HRSettingsService::class)->getChristmasBonusPercentage());
    }

    /**
     * As chaves de definições que o código lê, incluindo as dinâmicas.
     *
     * Procurar só por `HRSetting::get('literal')` NÃO CHEGA, e enganou-me: dá
     * uma lista que ignora os três sítios que lêem por variável —
     *
     *   PayrollCalculatorHelper::loadHRSettings()  $keys = [ ...28 chaves... ]
     *   HRSettingsService::getOvertimeMultiplier() match($type) { ... }
     *   PayrollService::calcularSubsidio()         $dailyRateKey = ...
     *
     * — e com ela concluí que trinta e cinco definições do catálogo não faziam
     * nada. Faziam todas.
     *
     * Por isso recolhe-se, de cada ficheiro que mencione HRSetting, TODA a
     * cadeia entre plicas com forma de chave. Apanha a mais ('proportional',
     * 'weekday'), o que é inofensivo: o que se pergunta a seguir é sempre se
     * uma chave concreta aparece, nunca o contrário.
     *
     * @return array<string, string> chave => ficheiro onde aparece
     */
    private function chavesMencionadasNoCodigo(): array
    {
        $mencoes = [];
        $raiz    = app_path();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz)) as $f) {
            if ($f->isDir() || $f->getExtension() !== 'php') {
                continue;
            }

            $conteudo = file_get_contents($f->getPathname());

            if (!str_contains($conteudo, 'HRSetting')) {
                continue;
            }

            // O próprio catálogo menciona todas as chaves — não conta como leitor.
            if (str_contains($f->getPathname(), 'DefinicoesRH.php')) {
                continue;
            }

            if (preg_match_all("/'([a-z][a-z0-9_]{3,})'/", $conteudo, $m)) {
                foreach ($m[1] as $cadeia) {
                    $mencoes[$cadeia] ??= str_replace($raiz . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }
        }

        return $mencoes;
    }

    public function test_o_catalogo_tem_todas_as_chaves_que_o_codigo_le(): void
    {
        // Dois defeitos que isto fixa:
        //
        //  - o catálogo tinha `salary_advance_max_percentage` e o código lê
        //    `max_salary_advance_percentage`: o campo aparecia, editava-se, e o
        //    limite dos adiantamentos continuava nos 50% escritos no PHP;
        //
        //  - os quatro `overtime_*_multiplier` não estavam no catálogo, e o
        //    HRSettingsService devolvia 1.5 para tudo — dia útil, fim-de-semana,
        //    feriado e noite pagos ao mesmo, sem ninguém poder ver ou mudar.
        $catalogo = array_column(DefinicoesRH::catalogo(), 'key');
        $mencoes  = $this->chavesMencionadasNoCodigo();

        $this->assertNotEmpty($mencoes, 'a varredura tem de encontrar alguma coisa');

        // Só as que são de facto lidas como definição: passar por
        // HRSetting::get, literal ou por variável nas listas conhecidas.
        $lidas = [];
        $raiz  = app_path();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($raiz)) as $f) {
            if ($f->isDir() || $f->getExtension() !== 'php' || str_contains($f->getPathname(), 'DefinicoesRH.php')) {
                continue;
            }

            $conteudo = file_get_contents($f->getPathname());

            // (a) leituras literais
            if (preg_match_all("/HRSetting::(?:get|getValue)\(\s*'([a-z0-9_]+)'/i", $conteudo, $m)) {
                foreach ($m[1] as $k) {
                    $lidas[$k] = str_replace($raiz . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }

            // (b) listas de chaves passadas a leituras dinâmicas.
            //
            // Só em ficheiros que falem de HRSetting: a regra foi feita para o
            // loadHRSettings() do processamento salarial, mas `$keys = [...]`
            // é um nome que qualquer código usa — o RegisterWizard passou a
            // guardar UTMs numa variável assim e o detector acusava
            // `utm_source` como definição de RH em falta.
            if (str_contains($conteudo, 'HRSetting')
                && preg_match("/\\\$keys\s*=\s*\[(.*?)\];/s", $conteudo, $bloco)
                && preg_match_all("/'([a-z][a-z0-9_]{3,})'/", $bloco[1], $m)) {
                foreach ($m[1] as $k) {
                    $lidas[$k] = str_replace($raiz . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }

            // (c) arms de match que devolvem nomes de definição
            if (preg_match_all("/=>\s*'([a-z][a-z0-9_]*multiplier|[a-z_]*allowance)'/", $conteudo, $m)) {
                foreach ($m[1] as $k) {
                    $lidas[$k] = str_replace($raiz . DIRECTORY_SEPARATOR, '', $f->getPathname());
                }
            }
        }

        $emFalta = array_diff(array_keys($lidas), $catalogo, self::LEGADAS);

        $this->assertEmpty(
            $emFalta,
            "O código lê definições que o catálogo não tem — a empresa nunca as vê e o cálculo usa o valor escrito no PHP:\n"
            . implode("\n", array_map(fn ($k) => "  {$k}  ({$lidas[$k]})", $emFalta))
        );
    }

    public function test_nenhuma_definicao_do_catalogo_e_decorativa(): void
    {
        // O reverso: uma definição que aparece no ecrã, se edita, e não é
        // mencionada em código nenhum não muda nada — e faz o ecrã parecer que
        // não funciona, que foi exactamente a queixa que trouxe aqui.
        $mencoes = $this->chavesMencionadasNoCodigo();

        // As INFORMATIVAS são a excepção assumida: guardam regras reais da
        // empresa que o cálculo ainda não lê, e o ecrã marca-as como tal. O que
        // não pode existir é uma definição que se apresenta como vulgar e não
        // faz nada.
        $decorativas = array_values(array_filter(
            array_column(DefinicoesRH::catalogo(), 'key'),
            fn ($k) => !isset($mencoes[$k]) && !in_array($k, DefinicoesRH::INFORMATIVAS, true)
        ));

        $this->assertEmpty(
            $decorativas,
            "Definições no catálogo que nenhum código menciona e não estão marcadas como informativas:\n  "
            . implode("\n  ", $decorativas)
        );
    }

    public function test_uma_informativa_que_passe_a_ser_lida_sai_da_lista(): void
    {
        // O contrário do teste acima: a lista das informativas não pode ficar
        // desactualizada quando o cálculo passar a ler uma delas — senão o ecrã
        // continua a dizer "não usado" sobre um valor que já mexe em dinheiro,
        // que é pior do que o problema original.
        $mencoes = $this->chavesMencionadasNoCodigo();

        $jaLidas = array_values(array_filter(
            DefinicoesRH::INFORMATIVAS,
            fn ($k) => isset($mencoes[$k])
        ));

        $this->assertEmpty(
            $jaLidas,
            "Estas já são lidas pelo código e continuam marcadas como informativas:\n  "
            . implode("\n  ", $jaLidas)
        );
    }

    public function test_as_definicoes_de_uma_empresa_nao_se_veem_de_outra(): void
    {
        DefinicoesRH::garantirPara($this->tenant->id);

        $outra = \App\Models\Tenant::create([
            'name'      => 'Outra Empresa',
            'slug'      => 'outra-' . uniqid(),
            'nif'       => (string) random_int(800000000, 899999999),
            'email'     => 'outra' . uniqid() . '@exemplo.ao',
            'is_active' => true,
        ]);

        DefinicoesRH::garantirPara($outra->id);

        $this->assertSame(
            count(DefinicoesRH::catalogo()),
            HRSetting::where('tenant_id', $this->tenant->id)->count(),
            'cada empresa tem a sua cópia'
        );

        $this->assertSame(
            count(DefinicoesRH::catalogo()),
            HRSetting::withoutGlobalScopes()->where('tenant_id', $outra->id)->count()
        );
    }
}
