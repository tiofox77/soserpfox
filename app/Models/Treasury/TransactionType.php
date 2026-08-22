<?php

namespace App\Models\Treasury;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionType extends Model
{
    use BelongsToTenant;

    protected $table = 'treasury_transaction_types';

    protected $fillable = ['tenant_id', 'name', 'code', 'nature', 'description', 'color', 'icon', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'transaction_type_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(TransactionCategory::class, 'transaction_type_id');
    }

    public static function defaultSet(): array
    {
        return [
            ['name' => 'Entrada', 'code' => 'INCOME', 'nature' => 'income', 'description' => 'Recebimentos e entradas de dinheiro', 'color' => 'green', 'icon' => 'fa-arrow-down', 'sort_order' => 1],
            ['name' => 'Saída', 'code' => 'EXPENSE', 'nature' => 'expense', 'description' => 'Pagamentos e saídas de dinheiro', 'color' => 'red', 'icon' => 'fa-arrow-up', 'sort_order' => 2],
            ['name' => 'Transferência', 'code' => 'TRANSFER', 'nature' => 'transfer', 'description' => 'Movimentos entre contas ou caixas', 'color' => 'blue', 'icon' => 'fa-right-left', 'sort_order' => 3],
        ];
    }

    public static function seedDefaultsForTenant(int $tenantId): void
    {
        foreach (static::defaultSet() as $row) {
            static::withoutGlobalScopes()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $row['code']],
                $row + ['is_active' => true]
            );
        }
    }
}
