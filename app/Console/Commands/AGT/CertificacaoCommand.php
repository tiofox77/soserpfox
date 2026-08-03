<?php

namespace App\Console\Commands\AGT;

use App\Livewire\Invoicing\Sales\InvoiceCreate;
use App\Models\Client;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\LineTax;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AGT\DocumentMapper;
use App\Services\AGT\RegisterService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Cenário de certificação AGT — Conformidade da Emissão do Documento.
 *
 * Emite documentos REAIS pelo fluxo do módulo (componente Livewire, não SQL) e
 * gera o payload a partir da base de dados pelo DocumentMapper. É a diferença
 * entre provar que o JSON está bem formado e provar que o produto o emite.
 *
 * Cobre o que a AGT verifica:
 *   · vários documentos num só pedido
 *   · tipo de documento e regras fiscais afectas (FR, FT, NC, ND)
 *   · IVA nas taxas 14%, 7%, 5% e 0%; IEC; Imposto de Selo
 *   · adquirentes: Angola continental, Cabinda (AO-CAB), estrangeiro, com e sem NIF
 *   · isenções com códigos distintos (M04 exportação, M01 art. 12.º, M99 não sujeição)
 *   · retenção na fonte em tipos e taxas distintas (IRT 6,5%, IPC 10%)
 *   · documentTotals coerentes (netTotal + taxPayable = grossTotal)
 *   · NIF do emissor e as três assinaturas JWS
 *
 * Por omissão NÃO grava (rollback). Use --gravar para persistir.
 *
 *   php artisan agt:certificacao --tenant=11 --tipo=FR
 *   php artisan agt:certificacao --tenant=11 --tipo=FR --gravar
 */
class CertificacaoCommand extends Command
{
    protected $signature = 'agt:certificacao
        {--tenant=11 : Empresa emissora}
        {--tipo=FR : Tipo de documento (FR, FT, NC ou ND)}
        {--customer-nif= : NIF real para os adquirentes angolanos}
        {--gravar : Persiste na base de dados (por omissão faz rollback)}
        {--output= : Caminho do JSON gerado}';

    protected $description = 'Emite os cenários de certificação AGT pelo fluxo real e gera o payload';

    /**
     * Adquirentes. Os angolanos usam TODOS o NIF real da própria empresa: a AGT
     * recusa NIFs que não conheça ("Número fiscal Angolano ... é desconhecido")
     * e o índice UNIQUE (tenant_id, nif) só permite um cliente por NIF — logo é
     * um único cliente angolano. Cabinda distingue-se pela região do documento,
     * não por um cliente separado.
     */
    private const ADQUIRENTES = [
        'ao'      => ['CERT Cliente Angola (NIF proprio)', null,        'Luanda', 'AO'],
        'final'   => ['CERT Consumidor Final',             '999999999', 'Luanda', 'AO'],
        'estrang' => ['CERT Foreign Client Ltd',           '999999999', '',       'PT'],
    ];

    /**
     * Produtos de teste, um por regime fiscal.
     * [chave => [nome, código da taxa em invoicing_taxes, preço]]
     */
    private const PRODUTOS = [
        'normal'    => ['CERT Servico IVA Normal',      'IVA14',   100000],
        'red7'      => ['CERT Bem IVA Reduzido 7',      'IVA7',     80000],
        'red5'      => ['CERT Bem IVA Reduzido 5',      'IVA5',     60000],
        'exportacao'=> ['CERT Servico Exportacao',      'IVA0',    200000],
        'isento'    => ['CERT Bem Isento Art12',        'IVAISEN',  90000],
        'naosujeito'=> ['CERT Operacao Nao Sujeita',    'IVANS',    70000],
        'iec'       => ['CERT Cerveja (IEC 2203)',      'IVA14',    50000],
        'selo'      => ['CERT Comissao Quitacao',       'IVA14',   300000],
        'iecselo'   => ['CERT Bebida com Selo',         'IVA14',   120000],
    ];

    public function handle(): int
    {
        $tenantId = (int) $this->option('tenant');
        $tipo     = strtoupper($this->option('tipo'));
        $gravar   = (bool) $this->option('gravar');

        $tenant = Tenant::find($tenantId);
        if (!$tenant) {
            $this->error("Empresa {$tenantId} não existe.");
            return self::FAILURE;
        }

        $this->info("=== Certificação AGT · {$tenant->name} · NIF {$tenant->nif} ===");
        $this->line('  Tipo de documento: ' . $tipo);
        $this->line('  Modo: ' . ($gravar ? '<fg=yellow>GRAVAR</>' : 'simulação (rollback)'));
        $this->newLine();

        $user = User::whereHas('tenants', fn ($q) => $q->where('tenants.id', $tenantId))->first()
            ?? User::where('tenant_id', $tenantId)->first();
        if (!$user) {
            $this->error('Empresa sem utilizador — impossível emitir pelo fluxo real.');
            return self::FAILURE;
        }

        auth()->login($user);
        session(['active_tenant_id' => $tenantId]);
        setPermissionsTeamId($tenantId);

        DB::beginTransaction();
        $ok = true;

        try {
            // A certificação corre em homologação: é onde as séries estão registadas.
            DB::table('invoicing_settings')->where('tenant_id', $tenantId)
                ->update(['agt_environment' => 'sandbox']);

            $clientes = $this->prepararClientes($tenantId, $tenant);
            $produtos = $this->prepararProdutos($tenantId);
            $armazem  = DB::table('invoicing_warehouses')->where('tenant_id', $tenantId)
                ->where('is_active', 1)->value('id');

            if (in_array($tipo, ['NC', 'ND'], true)) {
                // NC e ND rectificam facturas: emitem-se primeiro as FT (mesma
                // matriz fiscal) e rectifica-se cada uma, herdando taxa, região,
                // isenção e impostos extra da linha original.
                $facturas = $this->emitirCenarios('FT', $clientes, $produtos, $armazem, $tenantId);
                $emitidas = $tipo === 'NC'
                    ? $this->emitirNotasCredito($facturas, $tenantId)
                    : $this->emitirNotasDebito($facturas, $tenantId);
            } else {
                $emitidas = $this->emitirCenarios($tipo, $clientes, $produtos, $armazem, $tenantId);
            }
            $ok = $this->validar($emitidas);
            $ficheiro = $this->gerarPayload($emitidas, $tenantId, $tipo);

            $this->newLine();
            $this->info("  Payload: {$ficheiro}");
        } catch (\Throwable $e) {
            $ok = false;
            $this->newLine();
            $this->error('EXCEÇÃO: ' . $e->getMessage());
            $this->line('   ' . basename($e->getFile()) . ':' . $e->getLine());
        } finally {
            if ($gravar && $ok) {
                DB::commit();
                $this->newLine();
                $this->warn('>>> DOCUMENTOS GRAVADOS NA BASE DE DADOS <<<');
            } else {
                DB::rollBack();
                $this->newLine();
                $this->line('  (rollback — nada foi gravado' . ($gravar ? '; houve falhas' : '') . ')');
            }
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** Cria/reutiliza os adquirentes de cada contexto. */
    private function prepararClientes(int $tenantId, Tenant $tenant): array
    {
        // NIF angolano: o da própria empresa por omissão, porque é o único que a
        // AGT reconhece com certeza. --customer-nif permite outro NIF real.
        $nifAngolano = $this->option('customer-nif') ?: $tenant->nif;
        $out = [];

        foreach (self::ADQUIRENTES as $chave => [$nome, $nif, $prov, $pais]) {
            $nifFinal = $nif ?: $nifAngolano;

            $out[$chave] = Client::firstOrCreate(
                ['tenant_id' => $tenantId, 'nif' => $nifFinal],
                ['name' => $nome, 'province' => $prov, 'country' => $pais, 'is_active' => 1]
            );

            if ($out[$chave]->province !== $prov || $out[$chave]->country !== $pais) {
                $out[$chave]->update(['province' => $prov, 'country' => $pais]);
            }
        }

        $this->line("  Adquirente angolano: {$out['ao']->name} · NIF {$out['ao']->nif}");

        return $out;
    }

    /** Cria/reutiliza um produto por regime fiscal, com a taxa certa atribuída. */
    private function prepararProdutos(int $tenantId): array
    {
        $out = [];

        foreach (self::PRODUTOS as $chave => [$nome, $codigoTaxa, $preco]) {
            $taxa = DB::table('invoicing_taxes')->where('tenant_id', $tenantId)
                ->where('code', $codigoTaxa)->first();

            if (!$taxa) {
                throw new \RuntimeException("Taxa {$codigoTaxa} não existe na empresa {$tenantId}.");
            }

            $out[$chave] = Product::firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => 'CERT-' . strtoupper($chave)],
                [
                    'name'             => $nome,
                    'type'             => 'servico',   // evita exigência de stock
                    'price'            => $preco,
                    'tax_type'         => 'iva',
                    'tax_rate_id'      => $taxa->id,
                    'exemption_reason' => $taxa->exemption_code,
                    'is_active'        => 1,
                ]
            );

            // Reutilização: garantir que a taxa e o preço são os do cenário
            $out[$chave]->update([
                'tax_rate_id'      => $taxa->id,
                'exemption_reason' => $taxa->exemption_code,
                'price'            => $preco,
                'is_active'        => 1,
            ]);
        }

        return $out;
    }

    /** Emite um documento por cenário, pelo componente real. */
    private function emitirCenarios(string $tipo, array $cli, array $prod, $armazem, int $tenantId): array
    {
        $cenarios = [
            ['Angola continental · IVA 14%',        $cli['ao'],       $prod['normal']],
            ['Cabinda · região AO-CAB',             $cli['ao'],       $prod['normal'],   ['regiao' => 'AO-CAB']],
            ['Taxa reduzida 7%',                    $cli['ao'],       $prod['red7']],
            ['Taxa reduzida 5%',                    $cli['ao'],       $prod['red5']],
            ['Consumidor final s/NIF · IEC 2203',   $cli['final'],    $prod['iec'],      ['iec' => '2203']],
            ['Exportação · isenção M04',            $cli['estrang'],  $prod['exportacao']],
            ['Isento art. 12.º · M01',              $cli['ao'],       $prod['isento']],
            ['Não sujeição · M99',                  $cli['ao'],       $prod['naosujeito']],
            ['Cativação IRT 6,5%',                  $cli['ao'],       $prod['normal'],   ['ret' => ['IRT', 6.5]]],
            ['Cativação IAC 10%',                   $cli['ao'],       $prod['normal'],   ['ret' => ['IAC', 10]]],
            // Verbas da Tabela anexa (Lei 3/14): 23.3 "Recibos de quitação" a 1%
            // é a que uma factura/recibo liquida; 16.2.4 "Outras comissões" a 0,7%.
            ['Imposto de Selo · verba 23.3',        $cli['ao'],       $prod['selo'],     ['is' => '23.3']],
            ['IEC + Selo na mesma linha',           $cli['ao'],       $prod['iecselo'],  ['iec' => '2204', 'is' => '16.2.4']],
        ];

        $this->line('<fg=white;options=bold>A emitir pelo fluxo do módulo</>');
        $emitidas = [];

        foreach ($cenarios as $i => $cenario) {
            // Nem todos os cenários trazem o 4.º elemento (extras)
            [$nome, $cliente, $produto] = $cenario;
            $extra = $cenario[3] ?? [];

            $c = Livewire::test(InvoiceCreate::class)
                ->set('invoice_type', $tipo)
                ->set('client_id', $cliente->id)
                // Cabinda testa-se pela região do documento, não por outro cliente
                ->set('tax_country_region', $extra['regiao'] ?? '')
                ->set('warehouse_id', $armazem)
                ->set('invoice_date', now()->toDateString())
                ->set('due_date', now()->addDays(30)->toDateString())
                ->set('payment_method', 'cash')
                ->call('addProduct', $produto->id);

            if (!empty($extra['iec'])) { $c->call('setLineIec', $produto->id, $extra['iec']); }
            if (!empty($extra['is']))  { $c->call('setLineIs',  $produto->id, $extra['is']); }
            if (!empty($extra['ret'])) {
                $c->set('withholding_type', $extra['ret'][0])
                  ->set('withholding_percentage', $extra['ret'][1]);
            }

            $c->call('save', 'sent');

            $inv = SalesInvoice::where('tenant_id', $tenantId)->latest('id')->first();
            $emitidas[] = $inv;

            $item    = $inv->items->first();
            $extras  = LineTax::where('line_type', get_class($item))
                ->whereIn('line_id', $inv->items->pluck('id'))->get();
            $retencao = DB::table('invoicing_withholding_taxes')
                ->where('document_type', get_class($inv))->where('document_id', $inv->id)->first();

            $this->line(sprintf('  %2d. %-34s %s', $i + 1, $nome, $inv->invoice_number));
            $this->line(sprintf('      IVA %s%% (%s) · região %s%s%s · líquido %s · imposto %s · bruto %s',
                rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ''), '0'), '.'),
                $item->tax_code . ($item->tax_exemption_code ? ' ' . $item->tax_exemption_code : ''),
                $item->tax_country_region,
                $extras->isEmpty() ? '' : ' · ' . $extras->map(fn ($e) => $e->tax_type . ' ' . number_format((float) $e->tax_amount, 0))->implode(' + '),
                $retencao ? ' · ret ' . $retencao->withholding_tax_type . ' ' . number_format((float) $retencao->withholding_tax_amount, 0) : '',
                number_format((float) $inv->net_total, 2),
                number_format((float) $inv->tax_payable, 2),
                number_format((float) $inv->gross_total, 2),
            ));
        }

        return $emitidas;
    }

    /**
     * Credita cada factura com uma Nota de Crédito, pelo componente real.
     * A NC herda taxa, código SAFT, região, isenção e impostos extra da linha
     * original — creditar tem de reverter o documento por inteiro.
     */
    private function emitirNotasCredito(array $facturas, int $tenantId): array
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>A creditar cada factura (Notas de Crédito)</>');

        $notas = [];

        foreach ($facturas as $i => $factura) {
            $c = Livewire::test(\App\Livewire\Invoicing\CreditNotes\CreditNoteCreate::class)
                ->set('client_id', $factura->client_id)
                ->set('invoice_id', $factura->id)
                ->set('issue_date', now()->toDateString())
                ->set('reason', 'return')
                ->set('type', 'total')
                ->call('loadInvoiceItems', $factura->id)
                ->call('save');

            $nc = \App\Models\Invoicing\CreditNote::where('tenant_id', $tenantId)
                ->latest('id')->first();
            $notas[] = $nc;

            $item = $nc->items->first();
            $extras = LineTax::where('line_type', get_class($item))
                ->whereIn('line_id', $nc->items->pluck('id'))->get();

            $this->line(sprintf('  %2d. %-26s credita %-26s', $i + 1,
                $nc->credit_note_number, $factura->invoice_number));
            $this->line(sprintf('      IVA %s%% (%s) · região %s%s · ref linha %s',
                rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ''), '0'), '.'),
                $item->tax_code . ($item->tax_exemption_code ? ' ' . $item->tax_exemption_code : ''),
                $item->tax_country_region,
                $extras->isEmpty() ? '' : ' · ' . $extras->map(fn ($e) => $e->tax_type . ' ' . number_format((float) $e->tax_amount, 0))->implode(' + '),
                $item->reference_invoice_no ?: '(sem referência)'
            ));
        }

        return $notas;
    }

    /**
     * Emite uma Nota de Débito por cada factura, pelo componente real. A ND
     * acresce ao documento original (creditAmount, ao contrário da NC) e herda
     * dele taxa, região, isenção, IEC/IS e retenção.
     */
    private function emitirNotasDebito(array $facturas, int $tenantId): array
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>A rectificar cada factura (Notas de Débito)</>');

        $notas = [];

        foreach ($facturas as $i => $factura) {
            Livewire::test(\App\Livewire\Invoicing\DebitNotes\DebitNoteCreate::class)
                ->set('client_id', $factura->client_id)
                ->set('invoice_id', $factura->id)
                ->set('issue_date', now()->toDateString())
                ->set('reason', 'correction')
                ->call('loadInvoiceItems', $factura->id)
                ->call('save');

            $nd = \App\Models\Invoicing\DebitNote::where('tenant_id', $tenantId)
                ->latest('id')->first();
            $notas[] = $nd;

            $item = $nd->items->first();
            $extras = LineTax::where('line_type', get_class($item))
                ->whereIn('line_id', $nd->items->pluck('id'))->get();

            $this->line(sprintf('  %2d. %-26s rectifica %-26s', $i + 1,
                $nd->debit_note_number, $factura->invoice_number));
            $this->line(sprintf('      IVA %s%% (%s) · região %s%s · ref linha %s',
                rtrim(rtrim(number_format((float) $item->tax_rate, 2, '.', ''), '0'), '.'),
                $item->tax_code . ($item->tax_exemption_code ? ' ' . $item->tax_exemption_code : ''),
                $item->tax_country_region,
                $extras->isEmpty() ? '' : ' · ' . $extras->map(fn ($e) => $e->tax_type . ' ' . number_format((float) $e->tax_amount, 0))->implode(' + '),
                $item->reference_invoice_no ?: '(sem referência)'
            ));
        }

        return $notas;
    }

    /** Verifica cada exigência da AGT sobre os documentos emitidos. */
    private function validar(array $emitidas): bool
    {
        $this->newLine();
        $this->line('<fg=white;options=bold>Verificação</fg=white;options=bold>');

        $falhas = 0;
        $ok = function (string $desc, bool $cond, string $extra = '') use (&$falhas) {
            if ($cond) {
                $this->line("  <fg=green>OK</>      {$desc}" . ($extra ? " ({$extra})" : ''));
            } else {
                $this->line("  <fg=red>FALHOU</>  {$desc}" . ($extra ? " ({$extra})" : ''));
                $falhas++;
            }
        };

        $ok('todos os cenários emitidos', count($emitidas) === 12, count($emitidas) . '/12');

        $incoerentes = 0; $semHash = 0; $semSerie = 0; $semAtcud = 0;
        foreach ($emitidas as $inv) {
            if (round((float) $inv->net_total + (float) $inv->tax_payable, 2) !== round((float) $inv->gross_total, 2)) {
                $incoerentes++;
            }
            if (blank($inv->hash))      { $semHash++; }
            if (blank($inv->series_id)) { $semSerie++; }
            if (blank($inv->atcud))     { $semAtcud++; }
        }

        $ok('documentTotals coerentes em todos', $incoerentes === 0, "{$incoerentes} incoerentes");
        $ok('todos com hash SAFT encadeado', $semHash === 0, "{$semHash} sem hash");
        $ok('todos ligados a uma série fiscal', $semSerie === 0, "{$semSerie} sem série");
        $ok('todos com ATCUD', $semAtcud === 0, "{$semAtcud} sem ATCUD");

        // Taxas de IVA distintas
        $taxas = collect($emitidas)->map(fn ($i) => (float) $i->items->first()->tax_rate)->unique()->sort()->values();
        $ok('várias taxas de IVA testadas', $taxas->count() >= 4, $taxas->implode('%, ') . '%');

        // Códigos de isenção distintos
        $isencoes = collect($emitidas)->map(fn ($i) => $i->items->first()->tax_exemption_code)
            ->filter()->unique()->values();
        $ok('vários códigos de isenção testados', $isencoes->count() >= 3, $isencoes->implode(', '));

        // Regiões
        $regioes = collect($emitidas)->map(fn ($i) => $i->items->first()->tax_country_region)->unique()->values();
        $ok('Angola continental e Cabinda', $regioes->contains('AO') && $regioes->contains('AO-CAB'),
            $regioes->implode(', '));

        // Retenções distintas
        $rets = DB::table('invoicing_withholding_taxes')
            ->whereIn('document_id', collect($emitidas)->pluck('id'))
            ->get(['withholding_tax_type', 'withholding_tax_percentage']);
        $ok('retenção em tipos/taxas distintas', $rets->pluck('withholding_tax_type')->unique()->count() >= 2,
            $rets->map(fn ($r) => $r->withholding_tax_type . ' ' . rtrim(rtrim($r->withholding_tax_percentage, '0'), '.') . '%')->implode(', '));

        // IEC e IS
        $extras = LineTax::whereIn('line_id', collect($emitidas)->flatMap->items->pluck('id'))->get();
        $ok('IEC emitido', $extras->where('tax_type', 'IEC')->isNotEmpty(),
            $extras->where('tax_type', 'IEC')->count() . ' linha(s)');
        $ok('Imposto de Selo emitido', $extras->where('tax_type', 'IS')->isNotEmpty(),
            $extras->where('tax_type', 'IS')->count() . ' linha(s)');
        $ok('IEC e IS na mesma linha', $extras->groupBy('line_id')->filter(fn ($g) => $g->count() >= 2)->isNotEmpty());

        return $falhas === 0;
    }

    /** Constrói e grava o payload assinado a partir dos documentos da base. */
    private function gerarPayload(array $emitidas, int $tenantId, string $tipo): string
    {
        $mapper = new DocumentMapper();
        $docs = [];
        foreach ($emitidas as $inv) {
            $docs[] = $mapper->map($inv->fresh(['items', 'client']));
        }

        $payload = (new RegisterService(InvoicingSettings::forTenant($tenantId)))->buildPayload($docs);

        $ficheiro = $this->option('output')
            ?: base_path("scripts/agt_certificacao_{$tipo}.json");

        @mkdir(dirname($ficheiro), 0775, true);
        file_put_contents($ficheiro, json_encode($payload,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $ficheiro;
    }
}
