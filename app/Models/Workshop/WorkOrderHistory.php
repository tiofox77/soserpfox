<?php

namespace App\Models\Workshop;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class WorkOrderHistory extends Model
{
    protected $table = 'workshop_work_order_history';

    protected $fillable = [
        'work_order_id',
        'user_id',
        'action',
        'field_name',
        'old_value',
        'new_value',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    // Tipos de ação
    const ACTION_CREATED = 'created';
    const ACTION_UPDATED = 'updated';
    const ACTION_STATUS_CHANGED = 'status_changed';
    const ACTION_ITEM_ADDED = 'item_added';
    const ACTION_ITEM_UPDATED = 'item_updated';
    const ACTION_ITEM_REMOVED = 'item_removed';
    const ACTION_PAYMENT_ADDED = 'payment_added';
    const ACTION_INVOICED = 'invoiced';
    const ACTION_COMMENT = 'comment';

    // Relationships
    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Helper methods
    public static function logAction($workOrderId, $action, $description, $metadata = [])
    {
        return static::create([
            'work_order_id' => $workOrderId,
            'user_id' => auth()->id(),
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }

    public static function logFieldChange($workOrderId, $fieldName, $oldValue, $newValue, $description = null)
    {
        return static::create([
            'work_order_id' => $workOrderId,
            'user_id' => auth()->id(),
            'action' => static::ACTION_UPDATED,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description ?? "Campo '{$fieldName}' alterado",
        ]);
    }

    public function getIconAttribute()
    {
        return match($this->action) {
            self::ACTION_CREATED => 'plus-circle',
            self::ACTION_UPDATED => 'edit',
            self::ACTION_STATUS_CHANGED => 'exchange-alt',
            self::ACTION_ITEM_ADDED => 'plus',
            self::ACTION_ITEM_UPDATED => 'pen',
            self::ACTION_ITEM_REMOVED => 'trash',
            self::ACTION_PAYMENT_ADDED => 'money-bill-wave',
            self::ACTION_INVOICED => 'file-invoice-dollar',
            self::ACTION_COMMENT => 'comment',
            default => 'info-circle',
        };
    }

    public function getColorAttribute()
    {
        return match($this->action) {
            self::ACTION_CREATED => 'green',
            self::ACTION_UPDATED => 'blue',
            self::ACTION_STATUS_CHANGED => 'purple',
            self::ACTION_ITEM_ADDED => 'teal',
            self::ACTION_ITEM_UPDATED => 'indigo',
            self::ACTION_ITEM_REMOVED => 'red',
            self::ACTION_PAYMENT_ADDED => 'green',
            self::ACTION_INVOICED => 'yellow',
            self::ACTION_COMMENT => 'gray',
            default => 'gray',
        };
    }
}
