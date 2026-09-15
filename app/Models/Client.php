<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Client extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'invoicing_clients';

    // A lista das províncias saiu daqui. Estava escrita neste modelo, no
    // Supplier e usada em quatro vistas — 18 províncias, a divisão anterior à
    // reforma de 2024. Agora vem de App\Support\Geografia, que tem as 21 e os
    // municípios, e onde se corrige uma vez só.

    // Países disponíveis (África + Portugal)
    // A lista dos países saiu daqui: eram sete e um «Outro», que não é país
    // nenhum. Ver App\Support\Geografia — 256 códigos ISO com o nome em
    // português, gerados do ICU e não escritos à mão.

    protected $fillable = [
        'tenant_id', 'type', 'name', 'nif', 'logo', 'email', 'phone', 'mobile',
        'address', 'city', 'province', 'municipality', 'neighbourhood', 'postal_code', 'country',
        'tax_regime', 'is_iva_subject', 'credit_limit', 'payment_term_days', 'payment_term_id',
        'website', 'notes', 'is_active', 'password', 'portal_access', 'portal_modulos',
        'last_login_at', 'password_changed_at',
        // Hotel guest fields
        'hotel_vip', 'hotel_blacklisted', 'document_type', 'document_number',
        'nationality', 'birth_date', 'gender',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_iva_subject' => 'boolean',
        'is_active' => 'boolean',
        'portal_access' => 'boolean',
        // As áreas do portal que a empresa deu a este cliente (App\Support\PortalDoCliente).
        'portal_modulos' => 'array',
        'credit_limit' => 'decimal:2',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        // Hotel guest casts
        'hotel_vip' => 'boolean',
        'hotel_blacklisted' => 'boolean',
        'birth_date' => 'date',
    ];

    // Relacionamentos
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function paymentTerm()
    {
        return $this->belongsTo(\App\Models\Invoicing\PaymentTerm::class, 'payment_term_id');
    }

    /**
     * Um cliente novo nasce com a condição de pagamento da empresa.
     *
     * ESTÁ AQUI, E NÃO NOS ECRÃS, DE PROPÓSITO. O formulário de clientes já a
     * pré-seleccionava, mas era o único: o PWA, a API, as importações e o
     * "Consumidor Final" do POS criavam clientes sem condição nenhuma — e sem
     * condição a factura sai sem vencimento. Nesta base estavam 67 clientes em
     * 67 sem condição atribuída, com o catálogo de condições montado desde
     * sempre. Um sítio que se pode esquecer acaba sempre esquecido; num
     * modelo, não há como criar um cliente por fora.
     *
     * Só preenche o que vier VAZIO: quem escolher uma condição no formulário
     * fica com a que escolheu.
     */
    protected static function booted(): void
    {
        static::creating(function (self $cliente) {
            if ($cliente->payment_term_id) {
                return;
            }

            try {
                $padrao = \App\Models\Invoicing\PaymentTerm::padraoDe($cliente->tenant_id);
            } catch (\Throwable $e) {
                // Nunca impedir a criação de um cliente por causa disto: sem
                // condição perde-se o vencimento por omissão, com excepção
                // perde-se a venda.
                return;
            }

            if (!$padrao) {
                return;
            }

            $cliente->payment_term_id = $padrao->id;

            // `payment_term_days` é o valor legado que o cálculo do vencimento
            // ainda lê. Fica em sincronia à nascença, como o formulário já
            // fazia ao gravar.
            if (!$cliente->payment_term_days) {
                $cliente->payment_term_days = $padrao->days;
            }
        });
    }

    /**
     * ATENÇÃO: esta relação está PARTIDA e não é a que se quer.
     *
     * `App\Models\Invoice` é a tabela `invoices`, que são as facturas da
     * PLATAFORMA ao dono da empresa (tem `subscription_id`, não tem
     * `client_id`). Qualquer consulta por aqui rebenta com «Unknown column
     * 'invoices.client_id'». Nada no sistema a usa — descobriu-se ao escrever
     * a API dos clientes, e foi por isso que nasceu a `facturas()` abaixo.
     *
     * Fica por apagar num passo próprio, para não misturar limpeza com o que
     * está a ser feito.
     *
     * @deprecated usar facturas()
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'client_id');
    }

    /** As facturas de venda deste cliente — as que a empresa lhe emitiu. */
    public function facturas()
    {
        return $this->hasMany(\App\Models\Invoicing\SalesInvoice::class, 'client_id');
    }

    // Loyalty / Fidelidade
    const LOYALTY_POINTS_PER_1000 = 10;
    const VIP_MIN_VISITS = 10;
    const VIP_MIN_SPENT = 100000;

    public function getLoyaltyDataAttribute()
    {
        if ($this->notes && str_starts_with($this->notes, '{')) {
            return json_decode($this->notes, true) ?? [];
        }
        return [];
    }

    public function updateLoyaltyData(array $data)
    {
        $loyaltyData = $this->loyalty_data;
        $loyaltyData = array_merge($loyaltyData, $data);
        $this->notes = json_encode($loyaltyData);
        $this->save();
        return $this;
    }

    /**
     * MAIS UMA ESTADA — e, se vier valor, mais o que gastou.
     *
     * Conta a VISITA. Chama-se uma vez por estada, à entrada.
     */
    public function incrementStays($amount = 0)
    {
        return $this->somarFidelidade(1, (float) $amount);
    }

    /**
     * O QUE GASTOU, sem contar uma visita nova.
     *
     * A mesma estada passa por vários sítios que mexem em dinheiro — o
     * adiantamento na reserva, o pagamento no check-out — e todos chamavam
     * `incrementStays()`, que soma UMA VISITA de cada vez. Um hóspede que
     * pagasse sinal e depois a conta ficava com três estadas contadas por uma
     * noite passada cá, e o VIP automático (dez visitas) chegava a quem tinha
     * vindo três vezes.
     */
    public function registarGasto(float $amount)
    {
        return $this->somarFidelidade(0, $amount);
    }

    private function somarFidelidade(int $visitas, float $amount)
    {
        $data = $this->loyalty_data;
        $newVisits = ($data['total_visits'] ?? 0) + $visitas;
        $newSpent = ($data['total_spent'] ?? 0) + $amount;
        $newPoints = ($data['loyalty_points'] ?? 0) + floor($amount / 1000) * self::LOYALTY_POINTS_PER_1000;

        $mudanca = [
            'total_visits' => $newVisits,
            'total_spent' => $newSpent,
            'loyalty_points' => $newPoints,
        ];

        // A última visita é a data de quem CHEGA, e não a de quem paga.
        if ($visitas > 0) {
            $mudanca['last_visit_at'] = now()->toISOString();
        }

        $this->updateLoyaltyData($mudanca);

        // Auto VIP
        if (!$this->hotel_vip && ($newVisits >= self::VIP_MIN_VISITS || $newSpent >= self::VIP_MIN_SPENT)) {
            $this->update(['hotel_vip' => true]);
        }

        return $this;
    }

    /**
     * A SENHA COM QUE O HÓSPEDE ENTRA NA PÁGINA PÚBLICA DE RESERVAS.
     *
     * Vive na mesma caixa JSON da fidelidade, que é onde o hotel já guarda o
     * que não tem coluna própria.
     *
     * ISTO NÃO GUARDAVA NADA. A página pública lia e escrevia
     * `$cliente->hotel_data` — que não é coluna, nem acessor, nem está no
     * `fillable`: o Eloquent descartava a escrita em silêncio e a leitura dava
     * sempre nulo. Resultado: quem «criava conta com senha» ficava sem senha
     * nenhuma, e a entrada só pedia o TELEFONE — qualquer pessoa que soubesse
     * o número entrava na ficha do hóspede.
     */
    public function getSenhaDeReservasAttribute(): ?string
    {
        return $this->loyalty_data['senha_de_reservas'] ?? null;
    }

    public function definirSenhaDeReservas(string $senha): self
    {
        return $this->updateLoyaltyData([
            'senha_de_reservas' => \Illuminate\Support\Facades\Hash::make($senha),
            'registado_em' => now()->toISOString(),
        ]);
    }

    public function addLoyaltyPoints($points)
    {
        $data = $this->loyalty_data;
        $this->updateLoyaltyData([
            'loyalty_points' => ($data['loyalty_points'] ?? 0) + $points,
        ]);
        return $this;
    }

    public function useLoyaltyPoints($points)
    {
        $data = $this->loyalty_data;
        $current = $data['loyalty_points'] ?? 0;
        if ($points > $current) return false;
        $this->updateLoyaltyData(['loyalty_points' => $current - $points]);
        return true;
    }
    
    /**
     * Retorna código ISO 3166-1-alpha-2 do país para SAFT
     * 
     * @return string Código de 2 letras (ex: AO, PT, MZ)
     */
    public function getCountryCodeAttribute(): string
    {
        $countryMap = [
            'Angola' => 'AO',
            'Portugal' => 'PT',
            'Moçambique' => 'MZ',
            'Mozambique' => 'MZ',
            'Brasil' => 'BR',
            'Brazil' => 'BR',
            'Cabo Verde' => 'CV',
            'Guiné-Bissau' => 'GW',
            'São Tomé e Príncipe' => 'ST',
            'AO' => 'AO',
            'PT' => 'PT',
            'MZ' => 'MZ',
            'BR' => 'BR',
            'CV' => 'CV',
            'GW' => 'GW',
            'ST' => 'ST',
        ];
        
        $country = $this->country ?? 'AO';
        
        // Se já está no formato correto (2 letras)
        if (strlen($country) === 2) {
            return strtoupper($country);
        }
        
        // Buscar no mapa
        return $countryMap[$country] ?? 'AO';
    }
}
