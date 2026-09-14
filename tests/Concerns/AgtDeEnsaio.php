<?php

namespace Tests\Concerns;

use App\Models\AGT\AGTSubmission;
use App\Models\Invoicing\InvoicingSettings;
use App\Models\Invoicing\SalesInvoice;
use App\Models\Invoicing\SalesInvoiceItem;
use App\Models\SoftwareSetting;
use App\Services\AGT\AGTKeyStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * A AGT montada para um ensaio: chaves, produtor, submissões e uma factura.
 *
 * Tudo no disco FALSO (quem usa isto chama `Storage::fake('local')` antes) e
 * nada que saia para a rede — cada ensaio que fale com a AGT põe o seu
 * `Http::fake`. Existe para os ensaios das guardas da AGT não repetirem, cada
 * um à sua maneira, a mesma meia centena de linhas de preparação.
 */
trait AgtDeEnsaio
{
    /**
     * Par RSA de 2048 bits gerado só para ensaios — nunca assinou nada real.
     * Por extenso porque o `openssl_pkey_new()` precisa do `openssl.cnf` e não
     * o encontra em todas as bancadas; ler um PEM não precisa.
     */
    protected static string $pemDeEnsaio = <<<'CHAVE'
        -----BEGIN PRIVATE KEY-----
        MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCqK6aLY2MJIFko
        Xcka/d1RQzW9Q8A6RDtU5c7kG9DwNb5XbYNOJ+4jF6gXSKnrQ1twEjQmAonL1IBe
        qAhhi+1a2gxfhTT5GEnhbuiqaZOX+DG+N+p6zE/s7Mu54ImA+aKX8WXUe8re5ogi
        K6WvkvWXFFLuYMM69Uvpa6p8WyRhhtaWZTKpJw16K7EYPS+xpS6Iv42vX1pei6Zc
        JlYRF0cNizO6dVqskbFMgCESHiatT0oZn6cZntNZh6YM6xDjT9HrDwKVzwiEf5e9
        qsKVj/5BsRE4plBK9wsSa/dpukbea+rPyNSgYJnkUoYJNDBqOYNov1LEVaGtLOLY
        v3rmC7mpAgMBAAECggEADT959yjRA1tAea0ofIiWR+7rsn0BbH/WnybHdziHqQXE
        GGYbFnTzHAoJ0Os8KEfNiPFv3CQvMl5MMqJSCbcVLtEiLLcP/1MB4HIsHKU8w3Rj
        +f88Ktx4XfV27FUD23XDz+CwGO1cx0LrBv7/JoayRjVjufFwTYkehHD4fDczw0BV
        tQMRNsaJpJkvAgPomfM0wDK7rnXT1Qaz7eN8bxb0hcdY7D95v8mRiZiuxmTXlhVo
        3RWhIU7BpptDiUckW7R+nb3hDsu6uAT1PeWAXrCDhgTA8OGke3jeqffqCaEjfdgc
        PEVPKJuzIcjzAo4Ji1yzUkmvE6o0cda1mmWEtlq9AQKBgQDXvXim6G4tiHlrvZDK
        fUIcGEfUKXvWiU2k8nyvg6BSWPdAX2ouHly2XCTKCWhJ4LjOWdNjUbYK3MaQq7oE
        8zai9JJoNZ8tcfnRUu4oeFHWGES0Qay4Udjz61pFK1SR8FJfhyUS1svxpoybc6G+
        V9DH548W6imZmmxUrmzkARoZAQKBgQDJ7S/x5gb+zr0/Z5w6D6MXfJrHMTrv0tUA
        8BFCOTnjFqOoagt7RGO9Xzhn25DQvfZDoOpaU0OVXzliRWEbjM8gqeHML0Onx+Wd
        iA7lDcafZsenEq4WO7HjGp8rz0m/MRdQRWuCBNg8xFBSgmcbq0Y4tgqaWirSY9Ci
        FF1oxVk4qQKBgQCr6/+z8tGqU3F/XGeAFeWTAf5roktfobdQVTTroVcniGIw2FiD
        PArh//gJUQncpcpgFtEP+tO5QEq0i0UIINFPdtsdVG3vBz7vgsjrU0bT+C73/sYn
        dIIRj2I2cNtKGVtraQUwSB/qCLFQSAuC5fQo+ezbc+uGzrq5mO6JnB8yAQKBgCoB
        NiUK5c+hsAp9gikt0Y50NDpVil4TLI4aYmy1PM55iifhj2vgCSN+qFwqd5CEw7LD
        yZxqj7eF7Ij9x7qUaw3vaPIxrtA7LA++GuMZH4VPOx8NKrujRVjp08yoPT4RdzkS
        h8+vNFBHwjG3wL0nvt7TN5duRFQpwV/F/rxpuSqpAoGAGosNk4yXCUHfIi5ffrV3
        rpzwvsh+Uj3+YWEIsmROpmTfug6aoVXaoZh32nFQVCh5BF0WWEulv6KahYLIj/BC
        26YDNMSOolV9f1ITmzAQbqSDpy0wBqCtwPHa03vWCAiUCNljz7oqiNkG1K8vma15
        B45eum73Hf11QXtRbyAn/NI=
        -----END PRIVATE KEY-----
        CHAVE;

    protected function publicaDeEnsaio(): string
    {
        return openssl_pkey_get_details(openssl_pkey_get_private(self::$pemDeEnsaio))['key'];
    }

    protected function definicoesAgt(): InvoicingSettings
    {
        return InvoicingSettings::forTenant($this->tenant->id);
    }

    protected function emitirEm(string $ambiente): void
    {
        $this->definicoesAgt()->update(['agt_environment' => $ambiente]);
        InvoicingSettings::esquecerMemoria();
    }

    /** O par do contribuinte deste ambiente, no disco falso. */
    protected function instalarChavesDoContribuinte(string $ambiente): void
    {
        $dir = AGTKeyStore::directory($this->tenant->id, $ambiente);
        Storage::disk('local')->put("{$dir}/private_key.pem", self::$pemDeEnsaio);
        Storage::disk('local')->put("{$dir}/public_key.pem", $this->publicaDeEnsaio());
    }

    /**
     * O produtor PRÓPRIO do ambiente: credenciais, chave e certificação. É o
     * que a prontidão de produção exige e o que os pré-requisitos do ecrã
     * pedem antes de sair qualquer pedido.
     */
    protected function instalarProdutor(string $ambiente): void
    {
        config([
            "services.agt.{$ambiente}.username" => "produtor-{$ambiente}",
            "services.agt.{$ambiente}.password" => 'segredo-de-ensaio',
        ]);
        Storage::disk('local')->put("saft/{$ambiente}/private_key.pem", self::$pemDeEnsaio);
        Storage::disk('local')->put("saft/{$ambiente}/public_key.pem", $this->publicaDeEnsaio());
        SoftwareSetting::set('invoicing', "saft_software_cert_{$ambiente}", $ambiente === 'production' ? 'FE/324/AGT/2026' : 'FE/351/AGT/2026', 'string');
        Cache::flush();
    }

    /** Nada do produtor de produção: nem credenciais próprias, nem certificação própria. */
    protected function semProdutorDeProducao(): void
    {
        config([
            'services.agt.production.username' => '',
            'services.agt.production.password' => '',
            'services.agt.username' => '',
            'services.agt.password' => '',
        ]);
        SoftwareSetting::set('invoicing', 'saft_software_cert_production', '', 'string');
        Cache::flush();
    }

    protected function submissaoAgt(array $campos = []): AGTSubmission
    {
        return AGTSubmission::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'agt_environment' => $this->definicoesAgt()->agt_environment ?: 'sandbox',
            'document_type' => SalesInvoice::class,
            'document_id' => 999999999,
            'document_number' => 'FT S/' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'document_type_code' => 'FT',
            'status' => AGTSubmission::STATUS_PENDING,
            'retry_count' => 0,
        ], $campos));
    }

    /** Uma factura com uma linha, pronta a ser mapeada para a AGT. */
    protected function facturaDeEnsaio(float $base = 1000.0): SalesInvoice
    {
        $f = SalesInvoice::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->cliente->id,
            'created_by' => $this->user->id,
            'invoice_number' => 'FT S/' . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT),
            'invoice_type' => 'FT',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'sent',
            'subtotal' => $base,
            'net_total' => $base,
            'tax_payable' => 0,
            'total' => $base,
            'gross_total' => $base,
        ]);

        SalesInvoiceItem::create([
            'sales_invoice_id' => $f->id,
            'product_name' => 'Serviço de ensaio',
            'description' => 'Serviço de ensaio',
            'quantity' => 1,
            'unit' => 'UN',
            'unit_price' => $base,
            'unit_price_base' => $base,
            'subtotal' => $base,
            'tax_rate' => 14,
            'credit_amount' => $base,
            'order' => 1,
            'tax_country_region' => 'AO',
            'tax_code' => 'NOR',
        ]);

        return $f->fresh();
    }
}
