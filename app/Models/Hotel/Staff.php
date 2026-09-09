<?php

namespace App\Models\Hotel;

use App\Traits\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    use HasFactory, SoftDeletes, BelongsToTenant;

    protected $table = 'hotel_staff';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'hr_employee_id',
        'name',
        'email',
        'phone',
        'document',
        'photo',
        'position',
        'department',
        'address',
        'birth_date',
        'hire_date',
        'working_days',
        'work_start',
        'work_end',
        'hourly_rate',
        'monthly_salary',
        'notes',
        'skills',
        'is_active',
    ];

    protected $casts = [
        'working_days' => 'array',
        'work_start' => 'datetime:H:i',
        'work_end' => 'datetime:H:i',
        'hourly_rate' => 'decimal:2',
        'monthly_salary' => 'decimal:2',
        'is_active' => 'boolean',
        'birth_date' => 'date',
        'hire_date' => 'date',
    ];

    /*
     * AS FUNÇÕES E OS DEPARTAMENTOS — as CHAVES são as da base e não se
     * tocam; os RÓTULOS estavam sem acentos («Manutencao», «Seguranca»,
     * «Garcom», «Recepcao», «Administracao») e são o que aparece no ecrã.
     * Além disso, «Garçom» é brasileiro: em Angola diz-se «Empregado de mesa».
     */
    const POSITIONS = [
        'manager' => 'Gerente',
        'receptionist' => 'Recepcionista',
        'housekeeper' => 'Governanta',
        'cleaner' => 'Camareira',
        'maintenance' => 'Manutenção',
        'bellboy' => 'Mensageiro',
        'concierge' => 'Concierge',
        'security' => 'Segurança',
        'chef' => 'Chefe de cozinha',
        'waiter' => 'Empregado de mesa',
        'other' => 'Outro',
    ];

    const DEPARTMENTS = [
        'front_desk' => 'Recepção',
        'housekeeping' => 'Limpeza',
        'maintenance' => 'Manutenção',
        'food_service' => 'Restaurante/Bar',
        'security' => 'Segurança',
        'management' => 'Administração',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = activeTenantId();
            }
            if (empty($model->working_days)) {
                $model->working_days = [1, 2, 3, 4, 5, 6];
            }
        });
    }

    // Accessors
    public function getInitialsAttribute()
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }

    public function getPositionLabelAttribute()
    {
        return self::POSITIONS[$this->position] ?? $this->position;
    }

    public function getDepartmentLabelAttribute()
    {
        return self::DEPARTMENTS[$this->department] ?? $this->department;
    }

    public function getPhotoUrlAttribute()
    {
        if ($this->photo) {
            return asset('storage/' . $this->photo);
        }
        return null;
    }

    // Scopes
    public function scopeForTenant($query, $tenantId = null)
    {
        return $query->where('tenant_id', $tenantId ?? activeTenantId());
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $department)
    {
        return $query->where('department', $department);
    }

    public function scopeByPosition($query, $position)
    {
        return $query->where('position', $position);
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hrEmployee()
    {
        return $this->belongsTo(\App\Models\HR\Employee::class, 'hr_employee_id');
    }

    public function housekeepingTasks()
    {
        return $this->hasMany(HousekeepingTask::class, 'assigned_to');
    }
}
