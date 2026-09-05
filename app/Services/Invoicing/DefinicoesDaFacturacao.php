<?php

namespace App\Services\Invoicing;

use App\Models\Client;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\PaymentTerm;
use App\Models\Invoicing\Tax;
use App\Models\Invoicing\Warehouse;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Support\MenuDoPwa;
use Illuminate\Validation\Rule;

/**
 * AS DEFINIÇÕES DA FACTURAÇÃO — ler, validar e guardar, num sítio só.
 *
 * Vivia dentro do `Livewire\Invoicing\Settings`. Ao migrar o ecrã para React,
 * saiu para aqui; o Livewire e a API chamam o mesmo.
 *
 * O QUE ESTE CAMINHO GARANTE:
 *
 *  · A CONDIÇÃO DE PAGAMENTO DOS CLIENTES NOVOS vive no `is_default` da
 *    própria condição, não numa cópia aqui — duas definições para a mesma
 *    coisa acabam sempre a discordar.
 *
 *  · O MENU DO PWA leva sempre as entradas fixas: uma lista sem o Início
 *    deixava o PWA sem saída, e ninguém escolhe isso de propósito.
 *
 *  · O ARMAZÉM ESCOLHIDO PASSA A SER MESMO O PADRÃO: escreve-se na coluna
 *    das definições E no `is_default` do armazém, que é o que todos os
 *    formulários lêem. Um armazém de outra empresa limpa a definição em vez
 *    de a deixar a apontar para o nada.
 */
class DefinicoesDaFacturacao
{
    /** As colunas que o ecrã edita. O que não estiver aqui não se grava por aqui. */
    public const CAMPOS = [
        'default_warehouse_id', 'default_client_id', 'default_supplier_id', 'default_tax_id',
        'default_currency', 'default_exchange_rate', 'default_payment_method',
        'number_format', 'decimal_places', 'price_mask_enabled', 'pos_formato_impressao', 'rounding_mode',
        'proforma_series', 'invoice_series', 'receipt_series',
        'proforma_next_number', 'invoice_next_number', 'receipt_next_number',
        'default_tax_rate', 'default_irt_rate', 'apply_irt_services',
        'allow_line_discounts', 'allow_commercial_discount', 'allow_financial_discount', 'max_discount_percent',
        'proforma_validity_days', 'invoice_due_days',
        'auto_print_after_save', 'show_company_logo', 'nome_nos_documentos', 'invoice_footer_text',
        'default_notes', 'default_terms',
        'pos_auto_print', 'pos_play_sounds', 'pos_validate_stock', 'pos_allow_negative_stock',
        'pos_hide_out_of_stock', 'pos_show_product_images', 'pos_products_per_page',
        'pos_auto_complete_sale', 'pos_require_customer', 'pos_default_payment_method_id',
        'profile_pharmacy', 'profile_clothing', 'profile_cosmetics', 'profile_grocery',
    ];

    /** As que são NOT NULL e chegam do browser, onde um valor em falta viria como null. */
    private const BOOLEANAS = [
        'price_mask_enabled', 'apply_irt_services',
        'allow_line_discounts', 'allow_commercial_discount', 'allow_financial_discount',
        'auto_print_after_save', 'show_company_logo',
        'pos_auto_print', 'pos_play_sounds', 'pos_validate_stock', 'pos_allow_negative_stock',
        'pos_hide_out_of_stock', 'pos_show_product_images', 'pos_auto_complete_sale', 'pos_require_customer',
        'profile_pharmacy', 'profile_clothing', 'profile_cosmetics', 'profile_grocery',
    ];

    /**
     * O que o ecrã mostra: as colunas editáveis, o menu do PWA já
     * normalizado e a condição de pagamento padrão.
     */
    public function ler(InvoicingSettings $definicoes, ?Tenant $empresa): array
    {
        // Uma linha acabada de criar não traz os valores por omissão da base
        // até se reler: sem isto, uma empresa nova via o ecrã sem o nome nos
        // documentos nem o papel do POS — e não conseguia gravar.
        $definicoes->refresh();

        $dados = array_intersect_key($definicoes->toArray(), array_flip(self::CAMPOS));

        foreach (self::BOOLEANAS as $c) {
            $dados[$c] = (bool) ($dados[$c] ?? false);
        }

        // Nunca configurado significa TUDO ligado, e não nada ligado. Uma
        // definição nova não pode apagar o menu de quem já usava o PWA — a
        // mesma regra que o MenuDoPwa aplica do lado de lá.
        $dados['pwa_menu'] = MenuDoPwa::escolhidasPelaEmpresa($empresa);

        // A condição dos clientes novos vive no `is_default` da própria
        // condição; o catálogo é provisionado à primeira vista.
        $dados['default_payment_term_id'] = null;

        if ($empresa) {
            PaymentTerm::provisionarPadroes($empresa->id);
            $dados['default_payment_term_id'] = PaymentTerm::padraoDe($empresa->id)?->id;
        }

        return $dados;
    }

    /**
     * Pré-preenche os padrões essenciais (armazém, imposto, cliente,
     * fornecedor) com o que já está marcado noutros sítios, para aparecerem
     * já seleccionados.
     */
    public function essenciais(InvoicingSettings $definicoes, int $tenantId): void
    {
        $mudancas = [];

        if (empty($definicoes->default_warehouse_id)) {
            $w = Warehouse::where('tenant_id', $tenantId)->where('is_default', true)->first()
                ?? Warehouse::where('tenant_id', $tenantId)->orderBy('id')->first();
            if ($w) {
                $mudancas['default_warehouse_id'] = $w->id;
            }
        }

        if (empty($definicoes->default_tax_id)) {
            $t = Tax::getDefaultTax($tenantId)
                ?? Tax::where('tenant_id', $tenantId)->where('is_active', true)->orderBy('id')->first();
            if ($t) {
                $mudancas['default_tax_id'] = $t->id;
            }
        }

        if (empty($definicoes->default_client_id)) {
            $c = Client::where('tenant_id', $tenantId)->where('nif', '999999999')->first()
                ?? Client::where('tenant_id', $tenantId)->orderBy('id')->first();
            if ($c) {
                $mudancas['default_client_id'] = $c->id;
            }
        }

        if (empty($definicoes->default_supplier_id)) {
            $s = Supplier::where('tenant_id', $tenantId)->orderBy('id')->first();
            if ($s) {
                $mudancas['default_supplier_id'] = $s->id;
            }
        }

        if ($mudancas) {
            $definicoes->update($mudancas);
        }
    }

    /** As regras de validação — as mesmas para o Livewire e para a API. */
    public function regras(int $tenantId): array
    {
        return [
            // Só os dois valores conhecidos: o que vem do navegador não manda
            // numa coluna que decide o cabeçalho de todos os documentos.
            'nome_nos_documentos' => 'required|in:social,comercial',
            'default_currency' => 'required|in:AOA,USD,EUR',
            'default_exchange_rate' => 'required|numeric|min:0',
            'proforma_series' => 'sometimes|required|max:10',
            'invoice_series' => 'sometimes|required|max:10',
            'receipt_series' => 'sometimes|required|max:10',
            'default_tax_rate' => 'required|numeric|min:0|max:100',
            'default_irt_rate' => 'required|numeric|min:0|max:100',
            'max_discount_percent' => 'required|numeric|min:0|max:100',
            'proforma_validity_days' => 'required|integer|min:1',
            'invoice_due_days' => 'required|integer|min:1',
            // As colunas são string(20)/tinyInteger: sem validação, um valor
            // fora da lista rebentava com "Data too long" em vez de avisar.
            'number_format' => 'nullable|string|max:20',
            'decimal_places' => 'nullable|integer|min:0|max:4',
            'price_mask_enabled' => 'boolean',
            // Dois valores e mais nenhum: isto decide o que sai na
            // impressora de quem está ao balcão.
            'pos_formato_impressao' => 'nullable|in:a4,talao',
            'rounding_mode' => 'nullable|string|max:20',
            'pos_products_per_page' => 'nullable|integer|min:1|max:200',
            // Só chaves que existem: o que vem do navegador não escolhe o que
            // se guarda numa coluna que decide portas fechadas.
            'pwa_menu' => 'array',
            'pwa_menu.*' => 'string|in:' . implode(',', array_keys(MenuDoPwa::ENTRADAS)),
            // Tem de ser uma condição DESTA empresa: sem o `exists` com o
            // tenant, o navegador podia apontar os clientes novos para uma
            // condição de outra.
            'default_payment_term_id' => [
                'nullable', 'integer',
                Rule::exists('invoicing_payment_terms', 'id')->where('tenant_id', $tenantId),
            ],
        ];
    }

    /**
     * Guarda o que veio validado.
     *
     * @return string|null  Um aviso para mostrar (o armazém escolhido já não
     *                      existe nesta empresa), ou null quando correu tudo.
     */
    public function guardar(InvoicingSettings $definicoes, array $dados, int $tenantId): ?string
    {
        PaymentTerm::definirPadrao(
            $tenantId,
            ! empty($dados['default_payment_term_id']) ? (int) $dados['default_payment_term_id'] : null
        );

        $valores = array_intersect_key($dados, array_flip(self::CAMPOS));

        foreach (self::BOOLEANAS as $c) {
            if (array_key_exists($c, $valores)) {
                $valores[$c] = (bool) $valores[$c];
            }
        }

        if (array_key_exists('pos_formato_impressao', $valores)) {
            $valores['pos_formato_impressao'] = $valores['pos_formato_impressao'] ?: 'talao';
        }

        if (array_key_exists('pwa_menu', $dados)) {
            // As fixas entram sempre.
            $valores['pwa_menu'] = array_values(array_unique(array_merge(
                MenuDoPwa::fixas(),
                array_intersect((array) $dados['pwa_menu'], array_keys(MenuDoPwa::ENTRADAS))
            )));
        }

        $definicoes->update($valores);

        return $this->fixarArmazemPrincipal($definicoes, $tenantId);
    }

    /**
     * O armazém escolhido aqui passa a ser MESMO o padrão.
     *
     * Havia duas verdades a competir: `invoicing_settings.default_warehouse_id`
     * e `invoicing_warehouses.is_default`, que é o que TODOS os formulários
     * lêem. Em vez de mais um sítio a ler duas colunas, escreve-se nas duas.
     */
    private function fixarArmazemPrincipal(InvoicingSettings $definicoes, int $tenantId): ?string
    {
        if (! $definicoes->default_warehouse_id) {
            return null;
        }

        $armazem = Warehouse::where('tenant_id', $tenantId)->find($definicoes->default_warehouse_id);

        if (! $armazem) {
            $definicoes->update(['default_warehouse_id' => null]);

            return __('O armazém escolhido já não existe nesta empresa.');
        }

        if (! $armazem->is_default) {
            $armazem->setAsDefault();   // desmarca os outros da mesma empresa
        }

        return null;
    }
}
