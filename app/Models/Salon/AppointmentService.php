<?php

namespace App\Models\Salon;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentService extends Model
{
    use HasFactory;

    protected $table = 'salon_appointment_services';

    protected $fillable = [
        'appointment_id',
        'service_id',
        'professional_id',
        'duration',
        'price',
        'discount',
        'total',
        'commission',
        'notes',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'commission' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->total = $model->price - $model->discount;
        });

        static::saved(function ($model) {
            $model->appointment->calculateTotal();
        });

        static::deleted(function ($model) {
            $model->appointment->calculateTotal();
        });
    }

    // Relationships
    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    public function professional()
    {
        return $this->belongsTo(Professional::class, 'professional_id');
    }

    /**
     * O NOME DO QUE FOI FEITO — sempre uma frase, nunca um erro.
     *
     * A relação `service()` aponta para `Salon\Service`, que estende `Product`
     * com um escopo global: `type = 'servico'` E `module = 'salon'`. Basta o
     * artigo deixar de cumprir isso — foi editado no catálogo, mudou de módulo,
     * ou a linha nasceu noutro sítio — para a relação vir a NULO.
     *
     * E `$svc->service->name` deitava a listagem inteira abaixo com «Attempt to
     * read property "name" on null»: um artigo mal classificado escondia TODAS
     * as marcações. Nesta base eram 56 linhas em 56.
     *
     * Como o artigo em si costuma continuar lá, procura-se por ele — dentro da
     * empresa, que a tabela de artigos é partilhada por todas.
     */
    public function getNomeDoServicoAttribute(): string
    {
        if ($this->service) {
            return (string) $this->service->name;
        }

        if (! $this->service_id) {
            return __('Serviço removido');
        }

        // Uma consulta por artigo e não uma por linha: numa listagem com
        // dezenas de linhas partidas seriam dezenas de idas à base.
        static $nomes = [];

        if (! array_key_exists($this->service_id, $nomes)) {
            $empresa = activeTenantId() ?: $this->appointment?->tenant_id;

            $nomes[$this->service_id] = \App\Models\Product::where('id', $this->service_id)
                ->when($empresa, fn ($q) => $q->where('tenant_id', $empresa))
                ->value('name');
        }

        return $nomes[$this->service_id] ?: __('Serviço removido');
    }
}
