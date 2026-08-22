<?php

namespace App\Models\Treasury;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionCategory extends Model
{
    use BelongsToTenant;

    protected $table = 'treasury_transaction_categories';

    protected $fillable = ['tenant_id', 'transaction_type_id', 'name', 'code', 'description', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transaction_category_id');
    }

    public static function defaultSet(): array
    {
        return [
            ['name' => 'Venda', 'code' => 'sale', 'nature' => 'income'],
            ['name' => 'Recebimento de cliente', 'code' => 'customer_payment', 'nature' => 'income'],
            ['name' => 'Outras entradas', 'code' => 'other_income', 'nature' => 'income'],
            ['name' => 'Compra', 'code' => 'purchase', 'nature' => 'expense'],
            ['name' => 'Pagamento a fornecedor', 'code' => 'supplier_payment', 'nature' => 'expense'],
            ['name' => 'Salários', 'code' => 'salary', 'nature' => 'expense'],
            ['name' => 'Renda', 'code' => 'rent', 'nature' => 'expense'],
            ['name' => 'Água, luz e comunicações', 'code' => 'utilities', 'nature' => 'expense'],
            ['name' => 'Impostos', 'code' => 'tax', 'nature' => 'expense'],
            ['name' => 'Nota de crédito', 'code' => 'credit_note', 'nature' => 'expense'],
            ['name' => 'Transferência interna', 'code' => 'transfer', 'nature' => 'transfer'],
            ['name' => 'Outro', 'code' => 'other', 'nature' => null],
        ];
    }

    public static function seedDefaultsForTenant(int $tenantId): void
    {
        TransactionType::seedDefaultsForTenant($tenantId);
        $types = TransactionType::withoutGlobalScopes()->where('tenant_id', $tenantId)
            ->get()->keyBy('nature');
        foreach (static::defaultSet() as $order => $row) {
            $nature = $row['nature'];
            unset($row['nature']);
            static::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $row['code']],
                $row + [
                    'transaction_type_id' => $nature ? $types->get($nature)?->id : null,
                    'is_active' => true,
                    'sort_order' => $order + 1,
                ]
            );
        }
    }
}
