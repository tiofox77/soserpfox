<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\Tax;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Treasury\PaymentMethod;
use App\Services\Invoicing\DefinicoesDaFacturacao;
use App\Services\Invoicing\GestorDeSeries;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

/*
 * AS REGRAS VIVEM NOS SERVIÇOS: `DefinicoesDaFacturacao` (ler, validar,
 * guardar, o armazém que passa a ser mesmo o padrão) e `GestorDeSeries`
 * (prefixo do catálogo, criar, renomear, escolher a padrão com confirmação).
 * Este ecrã e o ecrã em React chamam os mesmos.
 */
#[Layout('layouts.app')]
#[Title('Configurações de Faturação')]
class Settings extends Component
{
    public $settings;

    // Padrões
    public $default_warehouse_id;
    public $default_client_id;
    public $default_supplier_id;
    public $default_tax_id;
    public $default_currency = 'AOA';
    public $default_exchange_rate = 1.0000;
    public $default_payment_method = 'dinheiro';

    // Formato de Números
    public $number_format = 'angola';
    public $decimal_places = 2;
    public $price_mask_enabled = true;

    /** Em que papel sai a venda do balcão. O talão por omissão — é o que o
     *  balcão imprime; a factura A4 é a excepção, para a venda a empresas. */
    public $pos_formato_impressao = 'talao';
    public $rounding_mode = 'normal';

    // Séries (deprecated - agora usa invoicing_series table)
    public $proforma_series = 'PRF';
    public $invoice_series = 'FT';
    public $receipt_series = 'RC';

    // Numeração (deprecated - agora usa invoicing_series table)
    public $proforma_next_number = 1;
    public $invoice_next_number = 1;
    public $receipt_next_number = 1;

    // Impostos
    public $default_tax_rate = 14.00;
    public $default_irt_rate = 6.50;
    public $apply_irt_services = true;

    // Descontos
    public $allow_line_discounts = true;
    public $allow_commercial_discount = true;
    public $allow_financial_discount = true;
    public $max_discount_percent = 100.00;

    // Validade
    public $proforma_validity_days = 30;
    public $invoice_due_days = 30;

    // Impressão
    public $auto_print_after_save = false;
    public $show_company_logo = true;

    /**
     * Qual dos nomes da empresa sai impresso: 'social' ou 'comercial'.
     *
     * A omissao e a designacao social, que e o que se espera num documento
     * fiscal. Nao mexe no SAFT-AO nem na comunicacao a AGT.
     */
    public $nome_nos_documentos = InvoicingSettings::NOME_SOCIAL;
    public $invoice_footer_text;

    // Observações
    public $default_notes;
    public $default_terms;

    // POS - Ponto de Venda
    public $pos_auto_print = true;
    public $pos_play_sounds = true;
    public $pos_validate_stock = true;
    public $pos_allow_negative_stock = false;
    public $pos_hide_out_of_stock = true;
    public $pos_show_product_images = true;
    public $pos_products_per_page = 12;
    public $pos_auto_complete_sale = false;
    public $pos_require_customer = false;
    public $pos_default_payment_method_id = null;

    /**
     * A condição de pagamento com que os clientes novos nascem.
     *
     * Não é uma coluna das definições: é o `is_default` da própria condição.
     * Guardar aqui uma cópia daria duas definições para a mesma coisa, e
     * duas definições acabam sempre a discordar.
     */
    public $default_payment_term_id = null;

    /**
     * As entradas do PWA que a empresa quer no aparelho.
     *
     * Sem tipo declarado de propósito: a coluna vem `null` em quem nunca a
     * configurou — com `array` declarado, esse `null` rebentava com um erro
     * de tipo no ecrã inteiro. A normalização faz-se no serviço, ao ler.
     */
    public $pwa_menu = [];

    // Perfil do Negócio
    // Podem estar todos ligados ao mesmo tempo (um supermercado com balcão de
    // farmácia e prateleira de cosmética é as três coisas); nenhum ligado é o
    // caso normal.
    public $profile_pharmacy = false;
    public $profile_clothing = false;
    public $profile_cosmetics = false;
    public $profile_grocery = false;

    // Gestão de Séries
    public $showSeriesModal = false;
    public $editingSeriesId = null;
    public $seriesDocumentType = null;
    public $seriesCode = 'A';
    public $seriesName = '';
    public $seriesPrefix = '';
    public $seriesDescription = '';

    // Confirmação consciente antes de abrir uma numeração em paralelo
    // (ver setDefaultSeries).
    public ?int $seriePadraoPendente = null;
    public array $avisoNumeracaoParalela = [];

    public function mount()
    {
        $this->settings = InvoicingSettings::forTenant(activeTenantId());

        $definicoes = app(DefinicoesDaFacturacao::class);

        // A guarda ao tenant não é defensiva por hábito: este ecrã ABRE sem
        // empresa resolvida — mostra a página "sem empresa" — e sem ela o
        // provisionamento rebentava com um erro de tipo, deitando abaixo o
        // único ecrã que explicava o que se passava.
        if ($empresaId = activeTenantId()) {
            $definicoes->essenciais($this->settings, $empresaId);
        }

        foreach ($definicoes->ler($this->settings, auth()->user()?->activeTenant()) as $chave => $valor) {
            if (property_exists($this, $chave)) {
                $this->$chave = $valor;
            }
        }
    }

    public function save()
    {
        $definicoes = app(DefinicoesDaFacturacao::class);
        $tenantId = activeTenantId();

        $this->validate($definicoes->regras($tenantId));

        $aviso = $definicoes->guardar($this->settings, $this->dadosDoEcra(), $tenantId);

        if ($aviso) {
            // Armazém de outra empresa (ou apagado entretanto): a definição
            // foi limpa em vez de ficar a apontar para o nada.
            $this->default_warehouse_id = null;

            $this->dispatch('notify', ['type' => 'error', 'message' => $aviso]);

            return;
        }

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Configurações salvas com sucesso!')
        ]);
    }

    /** O que este ecrã edita, com os nomes das colunas. */
    private function dadosDoEcra(): array
    {
        $dados = [];

        foreach (array_merge(DefinicoesDaFacturacao::CAMPOS, ['pwa_menu', 'default_payment_term_id']) as $campo) {
            $dados[$campo] = $this->$campo;
        }

        return $dados;
    }

    // Gestão de Séries
    public function openNewSeriesModal($documentType)
    {
        $this->reset(['editingSeriesId', 'seriesCode', 'seriesName', 'seriesDescription']);
        $this->seriesDocumentType = (string) $documentType;

        // O prefixo vem do catálogo, não do que o browser mandar: é o bloco
        // do número que a AGT lê para classificar o documento.
        $this->seriesPrefix = app(GestorDeSeries::class)->prefixoDoCatalogo((string) $documentType) ?? '';

        $this->showSeriesModal = true;
    }

    public function editSeries($seriesId)
    {
        // Scope na própria consulta, em vez de find() + comparação: é o mesmo
        // resultado mas não depende de ninguém se lembrar da verificação.
        $series = InvoicingSeries::where('tenant_id', activeTenantId())->find($seriesId);

        if ($series) {
            $this->editingSeriesId = $series->id;
            $this->seriesDocumentType = $series->document_type;
            $this->seriesCode = $series->series_code;
            $this->seriesName = $series->name;
            $this->seriesPrefix = $series->prefix;
            $this->seriesDescription = $series->description;
            $this->showSeriesModal = true;
        }
    }

    public function saveSeries()
    {
        $this->validate([
            'seriesCode' => 'required|max:10',
            'seriesName' => 'nullable|max:100',
            'seriesDescription' => 'nullable|max:500',
        ]);

        $gestor = app(GestorDeSeries::class);

        if ($this->editingSeriesId) {
            // O scope ao tenant é OBRIGATÓRIO: $editingSeriesId é propriedade
            // pública, logo definível a partir do browser. Sem ele, um
            // utilizador da empresa A podia renomear a SÉRIE FISCAL da empresa
            // B — a numeração dos documentos dela.
            $series = InvoicingSeries::where('tenant_id', activeTenantId())
                ->find($this->editingSeriesId);

            if (!$series) {
                $this->showSeriesModal = false;
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Série não encontrada nesta empresa.'),
                ]);
                return;
            }

            $gestor->actualizar($series, $this->seriesCode, $this->seriesName, $this->seriesDescription);

            $message = 'Série atualizada com sucesso!';
        } else {
            try {
                $serie = $gestor->criar(
                    activeTenantId(),
                    (string) $this->seriesDocumentType,
                    $this->seriesCode,
                    $this->seriesName,
                    $this->seriesDescription
                );
            } catch (\DomainException $e) {
                $this->showSeriesModal = false;
                $this->dispatch('notify', ['type' => 'error', 'message' => $e->getMessage()]);

                return;
            }

            // O ecrã passa a mostrar o que ficou gravado, e não o que veio do
            // pedido.
            $this->seriesPrefix = $serie->prefix;

            $message = 'Nova série criada com sucesso!';
        }

        $this->showSeriesModal = false;
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => $message
        ]);
    }

    public function setDefaultSeries($seriesId, bool $confirmado = false)
    {
        $series = InvoicingSeries::where('tenant_id', activeTenantId())->find($seriesId);

        if (!$series) {
            return;
        }

        // Sem confirmação, e havendo outra série do mesmo tipo já adiantada,
        // o gestor devolve o aviso com os números das duas — e não faz nada.
        $aviso = app(GestorDeSeries::class)->tornarPadrao($series, $confirmado);

        if ($aviso) {
            $this->seriePadraoPendente = $series->id;
            $this->avisoNumeracaoParalela = $aviso;

            return;
        }

        $this->reset(['seriePadraoPendente', 'avisoNumeracaoParalela']);

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Série padrão definida com sucesso!')
        ]);
    }

    /** Confirmação explícita do aviso de numeração em paralelo. */
    public function confirmarSeriePadrao()
    {
        if ($this->seriePadraoPendente) {
            $this->setDefaultSeries($this->seriePadraoPendente, true);
        }
    }

    public function cancelarSeriePadrao()
    {
        $this->reset(['seriePadraoPendente', 'avisoNumeracaoParalela']);
    }

    public function render()
    {
        // Sem empresa activa não há definições para editar, e desenhar o
        // formulário na mesma era pior do que o erro que isto substitui: a
        // ficha aparecia toda preenchida com os valores por omissão de uma
        // linha que não existe na base, e gravá-la não gravava nada em lado
        // nenhum. Quem entra sem empresa — o dono da plataforma, tipicamente —
        // fica a saber porquê, em vez de escrever para o vazio.
        if (!activeTenantId()) {
            return view('livewire.invoicing.settings-sem-empresa');
        }

        $warehouses = Warehouse::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->get();

        $clients = Client::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $taxes = Tax::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        $paymentMethods = PaymentMethod::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $paymentTerms = \App\Models\Invoicing\PaymentTerm::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('livewire.invoicing.settings', [
            'warehouses' => $warehouses,
            'paymentTerms' => $paymentTerms,
            'clients' => $clients,
            'suppliers' => $suppliers,
            'taxes' => $taxes,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
