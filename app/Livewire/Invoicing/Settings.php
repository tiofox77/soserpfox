<?php

namespace App\Livewire\Invoicing;

use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\InvoicingSeries;
use App\Models\Invoicing\Warehouse;
use App\Models\Invoicing\Tax;
use App\Models\Client;
use App\Models\Supplier;
use App\Models\Treasury\PaymentMethod;
use App\Services\Invoicing\SeriesCatalog;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;

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
     * As entradas do PWA que a empresa quer no aparelho.
     *
     * Sem tipo declarado de propósito: a coluna vem `null` em quem nunca a
     * configurou, e o mount() carrega as colunas por reflexão — com `array`
     * declarado, esse `null` rebentava com um erro de tipo no ecrã inteiro.
     * A normalização faz-se logo a seguir, no mount.
     */
    public $pwa_menu = [];

    // Perfil do Negócio
    // Podem estar todos ligados ao mesmo tempo (um supermercado com balcão de
    // farmácia e prateleira de cosmética é as três coisas); nenhum ligado é o
    // caso normal. O carregamento é o mesmo do resto: o mount() percorre as
    // colunas e enche as propriedades com o mesmo nome.
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

        // Pré-preencher os defaults essenciais (armazém, cliente, fornecedor,
        // imposto) com o que já está marcado noutros sítios, para aparecerem
        // aqui já selecionados.
        $this->ensureEssentialDefaults();

        // Carregar valores
        foreach ($this->settings->toArray() as $key => $value) {
            if (property_exists($this, $key) && $key !== 'settings') {
                $this->$key = $value;
            }
        }

        // Nunca configurado significa TUDO ligado, e não nada ligado. Uma
        // definição nova não pode apagar o menu de quem já usava o PWA — a
        // mesma regra que o MenuDoPwa aplica do lado de lá.
        $this->pwa_menu = \App\Support\MenuDoPwa::escolhidasPelaEmpresa(
            auth()->user()?->activeTenant()
        );
    }

    /**
     * Resolve e persiste os defaults essenciais quando ainda não estão definidos,
     * usando os registos já marcados como padrão (ou o primeiro disponível).
     */
    protected function ensureEssentialDefaults(): void
    {
        $tid = activeTenantId();
        $changes = [];

        // Armazém padrão (is_default marcado na gestão de armazéns)
        if (empty($this->settings->default_warehouse_id)) {
            $w = \App\Models\Invoicing\Warehouse::where('tenant_id', $tid)->where('is_default', true)->first()
                ?? \App\Models\Invoicing\Warehouse::where('tenant_id', $tid)->orderBy('id')->first();
            if ($w) { $changes['default_warehouse_id'] = $w->id; }
        }

        // Imposto padrão (Tax is_default)
        if (empty($this->settings->default_tax_id)) {
            $t = \App\Models\Invoicing\Tax::getDefaultTax($tid)
                ?? \App\Models\Invoicing\Tax::where('tenant_id', $tid)->where('is_active', true)->orderBy('id')->first();
            if ($t) { $changes['default_tax_id'] = $t->id; }
        }

        // Cliente padrão (Consumidor Final, senão o primeiro)
        if (empty($this->settings->default_client_id)) {
            $c = \App\Models\Client::where('tenant_id', $tid)->where('nif', '999999999')->first()
                ?? \App\Models\Client::where('tenant_id', $tid)->orderBy('id')->first();
            if ($c) { $changes['default_client_id'] = $c->id; }
        }

        // Fornecedor padrão (o primeiro disponível)
        if (empty($this->settings->default_supplier_id)) {
            $s = \App\Models\Supplier::where('tenant_id', $tid)->orderBy('id')->first();
            if ($s) { $changes['default_supplier_id'] = $s->id; }
        }

        if (!empty($changes)) {
            $this->settings->update($changes);
        }
    }
    
    public function save()
    {
        $this->validate([
            // Só os dois valores conhecidos: o que vem do navegador não manda
            // numa coluna que decide o cabeçalho de todos os documentos.
            'nome_nos_documentos' => 'required|in:social,comercial',
            'default_currency' => 'required|in:AOA,USD,EUR',
            'default_exchange_rate' => 'required|numeric|min:0',
            'proforma_series' => 'required|max:10',
            'invoice_series' => 'required|max:10',
            'receipt_series' => 'required|max:10',
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
            'rounding_mode' => 'nullable|string|max:20',
            // Só chaves que existem: o que vem do navegador não escolhe o que
            // se guarda numa coluna que decide portas fechadas.
            'pwa_menu' => 'array',
            'pwa_menu.*' => 'string|in:' . implode(',', array_keys(\App\Support\MenuDoPwa::ENTRADAS)),
        ]);

        $this->settings->update([
            // As fixas entram sempre: uma lista sem o Início deixava o PWA sem
            // saída, e ninguém escolhe isso de propósito.
            'pwa_menu' => array_values(array_unique(array_merge(
                \App\Support\MenuDoPwa::fixas(),
                array_intersect((array) $this->pwa_menu, array_keys(\App\Support\MenuDoPwa::ENTRADAS))
            ))),
            'default_warehouse_id' => $this->default_warehouse_id,
            'default_client_id' => $this->default_client_id,
            'default_supplier_id' => $this->default_supplier_id,
            'default_tax_id' => $this->default_tax_id,
            'default_currency' => $this->default_currency,
            'default_exchange_rate' => $this->default_exchange_rate,
            'default_payment_method' => $this->default_payment_method,
            // Estes três estavam no formulário e no $fillable mas faltavam
            // aqui: o utilizador alterava-os, via "Configurações salvas com
            // sucesso!" e ao recarregar a página estava tudo como antes.
            'number_format' => $this->number_format,
            'decimal_places' => $this->decimal_places,
            'price_mask_enabled' => (bool) $this->price_mask_enabled,
            'rounding_mode' => $this->rounding_mode,
            'proforma_series' => $this->proforma_series,
            'invoice_series' => $this->invoice_series,
            'receipt_series' => $this->receipt_series,
            'proforma_next_number' => $this->proforma_next_number,
            'invoice_next_number' => $this->invoice_next_number,
            'receipt_next_number' => $this->receipt_next_number,
            'default_tax_rate' => $this->default_tax_rate,
            'default_irt_rate' => $this->default_irt_rate,
            'apply_irt_services' => $this->apply_irt_services,
            'allow_line_discounts' => $this->allow_line_discounts,
            'allow_commercial_discount' => $this->allow_commercial_discount,
            'allow_financial_discount' => $this->allow_financial_discount,
            'max_discount_percent' => $this->max_discount_percent,
            'proforma_validity_days' => $this->proforma_validity_days,
            'invoice_due_days' => $this->invoice_due_days,
            'auto_print_after_save' => $this->auto_print_after_save,
            'show_company_logo' => $this->show_company_logo,
            'nome_nos_documentos' => $this->nome_nos_documentos,
            'invoice_footer_text' => $this->invoice_footer_text,
            'default_notes' => $this->default_notes,
            'default_terms' => $this->default_terms,
            'pos_auto_print' => $this->pos_auto_print,
            'pos_play_sounds' => $this->pos_play_sounds,
            'pos_validate_stock' => $this->pos_validate_stock,
            'pos_allow_negative_stock' => $this->pos_allow_negative_stock,
            'pos_hide_out_of_stock' => $this->pos_hide_out_of_stock,
            'pos_show_product_images' => $this->pos_show_product_images,
            'pos_products_per_page' => $this->pos_products_per_page,
            'pos_auto_complete_sale' => $this->pos_auto_complete_sale,
            'pos_require_customer' => $this->pos_require_customer,
            'pos_default_payment_method_id' => $this->pos_default_payment_method_id,
            // (bool) explícito: as colunas são NOT NULL e estas propriedades
            // chegam do browser, onde um valor em falta viria como null.
            'profile_pharmacy' => (bool) $this->profile_pharmacy,
            'profile_clothing' => (bool) $this->profile_clothing,
            'profile_cosmetics' => (bool) $this->profile_cosmetics,
            'profile_grocery' => (bool) $this->profile_grocery,
        ]);

        $this->fixarArmazemPrincipal();

        $this->dispatch('notify', [
            'type' => 'success',
            'message' => __('Configurações salvas com sucesso!')
        ]);
    }

    /**
     * O armazém escolhido aqui passa a ser MESMO o padrão.
     *
     * Havia duas verdades a competir: `invoicing_settings.default_warehouse_id`,
     * escrito por este ecrã, e `invoicing_warehouses.is_default`, que é o que
     * TODOS os formulários lêem (facturas, orçamentos, compras, POS, SAFT).
     * Resultado: escolher o armazém principal nas definições não fazia nada, e
     * só marcá-lo em Armazéns é que pegava — exactamente o que o utilizador
     * reportou.
     *
     * Em vez de mais um sítio a ler duas colunas, este ecrã passa a escrever
     * nas duas: a coluna das definições continua a existir (o módulo de
     * restaurante lê-a) e o `is_default` é acertado por arrasto.
     */
    private function fixarArmazemPrincipal(): void
    {
        if (!$this->default_warehouse_id) {
            return;
        }

        $armazem = \App\Models\Invoicing\Warehouse::where('tenant_id', activeTenantId())
            ->find($this->default_warehouse_id);

        if (!$armazem) {
            // Armazém de outra empresa (ou apagado entretanto): limpa-se a
            // definição em vez de a deixar a apontar para o nada.
            $this->settings->update(['default_warehouse_id' => null]);
            $this->default_warehouse_id = null;

            $this->dispatch('notify', [
                'type' => 'error',
                'message' => __('O armazém escolhido já não existe nesta empresa.'),
            ]);

            return;
        }

        if (!$armazem->is_default) {
            $armazem->setAsDefault();   // desmarca os outros da mesma empresa
        }
    }
    
    /**
     * O prefixo que este tipo de documento TEM de ter — ou null se o tipo não
     * pertencer a catálogo nenhum.
     *
     * Documento fiscal → catálogo AGT, que é a fonte única. Documento interno,
     * como a proforma de compra → catálogo canónico da casa, o mesmo sítio de
     * onde já sai o código da série: nunca vai à AGT, logo não tem nem pode ter
     * prefixo fiscal. Devolver null é o que impede que se componha um prefixo a
     * partir de um tipo que ninguém reconhece — inventá-lo é como nasceram as
     * séries 'PRF' e 'PP'.
     */
    protected function prefixoDoCatalogo(string $documentType): ?string
    {
        $agt = InvoicingSeries::prefixoDe($documentType);

        if ($agt !== null) {
            return $agt;
        }

        if (InvoicingSeries::tipoInterno($documentType)) {
            return SeriesCatalog::paraTipo($documentType)['prefix'] ?? null;
        }

        return null;
    }

    // Gestão de Séries
    public function openNewSeriesModal($documentType)
    {
        $this->reset(['editingSeriesId', 'seriesCode', 'seriesName', 'seriesDescription']);
        $this->seriesDocumentType = (string) $documentType;

        // O prefixo vem do catálogo, não do que o browser mandar. Era o ecrã
        // que o escolhia e o mandava de volta: bastava alterar o pedido para
        // criar uma série com o primeiro bloco do número à escolha — e é esse
        // bloco que a AGT lê para classificar o documento.
        //
        // O segundo argumento — o prefixo — deixou de existir de propósito.
        // Enquanto ficou como valor de recurso continuava a ser aceite: era
        // exactamente esse valor que o saveSeries() gravava sempre que o tipo
        // não constasse do catálogo.
        $this->seriesPrefix = $this->prefixoDoCatalogo((string) $documentType) ?? '';

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
        
        if ($this->editingSeriesId) {
            // Editar série existente.
            //
            // O scope ao tenant é OBRIGATÓRIO: $editingSeriesId é propriedade
            // pública, logo definível a partir do browser. Sem ele, um
            // utilizador da empresa A podia renomear a SÉRIE FISCAL da empresa
            // B — a numeração dos documentos dela. O editSeries() valida o
            // tenant, mas essa validação não protege este método, que é
            // invocável directamente. E um id inexistente dava erro 500.
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

            $series->update([
                'series_code' => $this->seriesCode,
                // O prefixo do nome sai da própria série e não de $seriesPrefix:
                // esse chega do browser e ficava escrito no nome de uma série
                // cujo prefixo real é outro.
                'name' => $this->seriesName ?: "Série {$series->prefix} {$this->seriesCode}",
                'description' => $this->seriesDescription,
            ]);

            $message = 'Série atualizada com sucesso!';
        } else {
            // Criar nova série.
            //
            // O prefixo é sempre o do catálogo. $seriesPrefix é propriedade
            // pública — chega do browser — e é ela que ia parar à coluna que
            // forma o primeiro bloco do número. Assim nascem as séries com 'PRF'
            // ou 'PP' onde a AGT espera 'PR': documentos recusados com E32.
            //
            // O TIPO também chega do browser e também não era validado, o que
            // reabria a mesma porta pelo outro lado: com um tipo fora do
            // catálogo, o prefixo caía no valor do formulário e voltava a ser
            // texto livre. Um tipo que o catálogo não conhece não dá série
            // nenhuma — o prefixo de um documento fiscal não se adivinha.
            $tipo = (string) $this->seriesDocumentType;
            $prefixoCanonico = $this->prefixoDoCatalogo($tipo);

            if ($prefixoCanonico === null) {
                $this->showSeriesModal = false;
                $this->dispatch('notify', [
                    'type' => 'error',
                    'message' => __('Tipo de documento desconhecido (:tipo). A série não foi criada.', [
                        'tipo' => $tipo !== '' ? $tipo : __('vazio'),
                    ]),
                ]);

                return;
            }

            // O ecrã passa a mostrar o que ficou gravado, e não o que veio do
            // pedido.
            $this->seriesPrefix = $prefixoCanonico;

            InvoicingSeries::create([
                'tenant_id' => activeTenantId(),
                'document_type' => $tipo,
                'series_code' => $this->seriesCode,
                'name' => $this->seriesName ?: "Série {$prefixoCanonico} {$this->seriesCode}",
                'prefix' => $prefixoCanonico,
                'include_year' => true,
                'next_number' => 1,
                'number_padding' => 6,
                'is_default' => false,
                'is_active' => true,
                'current_year' => now()->year,
                'reset_yearly' => true,
                'description' => $this->seriesDescription,
            ]);
            
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

        // Passar o padrão para uma série POR ESTREAR enquanto outra do mesmo
        // tipo já vai adiantada abre uma segunda numeração a andar em paralelo
        // com a primeira — duas sequências do mesmo tipo de documento no mesmo
        // exercício, que é o que não passa num SAFT.
        //
        // Pode ser deliberado (mudar de série no início do ano é exactamente
        // isto), por isso não se bloqueia. Mas tem de ser uma decisão tomada, e
        // não o efeito lateral de um clique — daí a confirmação com os números
        // concretos das duas séries à frente dos olhos.
        if (!$confirmado) {
            $adiantada = InvoicingSeries::where('tenant_id', activeTenantId())
                ->where('document_type', $series->document_type)
                ->where('id', '!=', $series->id)
                ->where('next_number', '>', 1)
                ->orderByDesc('next_number')
                ->first();

            if ((int) $series->next_number <= 1 && $adiantada) {
                $this->seriePadraoPendente = $series->id;
                $this->avisoNumeracaoParalela = [
                    'nova'           => $series->series_code,
                    'nova_proximo'   => (int) $series->next_number,
                    'em_uso'         => $adiantada->series_code,
                    'em_uso_proximo' => (int) $adiantada->next_number,
                ];

                return;
            }
        }

        // Aqui é o utilizador a ESCOLHER a padrão: desmarcar as outras e marcar
        // esta é o pedido, não um efeito lateral. O tornarPadrao() faz as duas
        // coisas numa transacção — em passos soltos, como estava, uma falha entre
        // os dois UPDATEs deixava o tipo sem padrão nenhuma.
        $series->tornarPadrao();

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
        
        return view('livewire.invoicing.settings', [
            'warehouses' => $warehouses,
            'clients' => $clients,
            'suppliers' => $suppliers,
            'taxes' => $taxes,
            'paymentMethods' => $paymentMethods,
        ]);
    }
}
