<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;
use App\Models\Tenant;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_employees';

    protected $fillable = [
        'tenant_id',
        'employee_number',
        'user_id',
        'first_name',
        'last_name',
        'birth_date',
        'gender',
        'nif',
        'bi_number',
        'bi_expiry_date',
        'passport_number',
        'passport_expiry_date',
        'work_permit_number',
        'work_permit_expiry_date',
        'residence_permit_number',
        'residence_permit_expiry_date',
        'driver_license_number',
        'driver_license_expiry_date',
        'driver_license_category',
        'health_insurance_number',
        'health_insurance_expiry_date',
        'health_insurance_provider',
        'social_security_number',
        'contract_expiry_date',
        'probation_end_date',
        'email',
        'phone',
        'mobile',
        'address',
        'city',
        'province',
        'department_id',
        'position_id',
        'manager_id',
        'hire_date',
        'termination_date',
        'employment_type',
        'status',
        'salary',
        'bonus',
        'transport_allowance',
        'meal_allowance',
        'bank_name',
        'bank_account',
        'iban',
        'beneficiaries',
        'photo',
        'notes',
        // Document Paths
        'bi_document_path',
        'passport_document_path',
        'work_permit_document_path',
        'residence_permit_document_path',
        'driver_license_document_path',
        'health_insurance_document_path',
        'contract_document_path',
        'probation_document_path',
        'criminal_record_document_path',
        // Criminal Record
        'criminal_record_number',
        'criminal_record_issue_date',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'bi_expiry_date' => 'date',
        'passport_expiry_date' => 'date',
        'work_permit_expiry_date' => 'date',
        'residence_permit_expiry_date' => 'date',
        'driver_license_expiry_date' => 'date',
        'health_insurance_expiry_date' => 'date',
        'contract_expiry_date' => 'date',
        'probation_end_date' => 'date',
        'hire_date' => 'date',
        'termination_date' => 'date',
        'criminal_record_issue_date' => 'date',
        'beneficiaries' => 'array',
        'salary' => 'decimal:2',
        'bonus' => 'decimal:2',
        'transport_allowance' => 'decimal:2',
        'meal_allowance' => 'decimal:2',
    ];

    protected $appends = ['full_name', 'age', 'years_of_service', 'total_compensation'];

    // Relationships
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function activeContract()
    {
        return $this->hasOne(Contract::class)->where('status', 'active')->latest();
    }

    public function payrollItems()
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAgeAttribute()
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getYearsOfServiceAttribute()
    {
        if (!$this->hire_date) {
            return 0;
        }
        
        $endDate = $this->termination_date ?? now();
        return $this->hire_date->diffInYears($endDate);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    // Methods
    public function isActive()
    {
        return $this->status === 'active';
    }

    public function getCurrentSalary()
    {
        $contract = $this->activeContract;
        return $contract ? $contract->base_salary : 0;
    }

    public function getTotalCompensationAttribute()
    {
        return ($this->salary ?? 0) +
               ($this->bonus ?? 0) +
               ($this->transport_allowance ?? 0) +
               ($this->meal_allowance ?? 0);
    }

    public function getFormattedSalaryAttribute()
    {
        return $this->salary ? number_format($this->salary, 2, ',', '.') . ' Kz' : 'Não definido';
    }

    public function getFormattedTotalCompensationAttribute()
    {
        return number_format($this->total_compensation, 2, ',', '.') . ' Kz';
    }

    // Verificar documentos vencidos ou próximos ao vencimento
    public function getExpiredDocumentsAttribute()
    {
        $expired = [];
        $today = now();

        $documents = [
            'BI' => $this->bi_expiry_date,
            'Passaporte' => $this->passport_expiry_date,
            'Autorização de Trabalho' => $this->work_permit_expiry_date,
            'Autorização de Residência' => $this->residence_permit_expiry_date,
            'Carta de Condução' => $this->driver_license_expiry_date,
            'Seguro de Saúde' => $this->health_insurance_expiry_date,
            'Contrato' => $this->contract_expiry_date,
        ];

        foreach ($documents as $name => $date) {
            if ($date && $date->isPast()) {
                $expired[] = [
                    'name' => $name,
                    'date' => $date,
                    'days' => $today->diffInDays($date),
                ];
            }
        }

        return $expired;
    }

    public function getExpiringDocumentsAttribute()
    {
        $expiring = [];
        $today = now();
        $warningDays = 30; // Avisar 30 dias antes

        $documents = [
            'BI' => $this->bi_expiry_date,
            'Passaporte' => $this->passport_expiry_date,
            'Autorização de Trabalho' => $this->work_permit_expiry_date,
            'Autorização de Residência' => $this->residence_permit_expiry_date,
            'Carta de Condução' => $this->driver_license_expiry_date,
            'Seguro de Saúde' => $this->health_insurance_expiry_date,
            'Contrato' => $this->contract_expiry_date,
        ];

        foreach ($documents as $name => $date) {
            if ($date && $date->isFuture() && $date->diffInDays($today) <= $warningDays) {
                $expiring[] = [
                    'name' => $name,
                    'date' => $date,
                    'days' => $today->diffInDays($date),
                ];
            }
        }

        return $expiring;
    }

    public function hasExpiredDocuments()
    {
        return count($this->expired_documents) > 0;
    }

    public function hasExpiringDocuments()
    {
        return count($this->expiring_documents) > 0;
    }
}
