<?php

namespace App\Models\Salon;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Professional extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'salon_professionals';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'nickname',
        'email',
        'phone',
        'document',
        'address',
        'photo',
        'specialization',
        'level',
        'bio',
        'birth_date',
        'hire_date',
        'working_days',
        'work_start',
        'work_end',
        'lunch_start',
        'lunch_end',
        'commission_percent',
        'hourly_rate',
        'daily_rate',
        'accepts_online_booking',
        'is_active',
        'is_available',
    ];

    protected $casts = [
        'working_days' => 'array',
        'work_start' => 'datetime:H:i',
        'work_end' => 'datetime:H:i',
        'lunch_start' => 'datetime:H:i',
        'lunch_end' => 'datetime:H:i',
        'commission_percent' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'daily_rate' => 'decimal:2',
        'accepts_online_booking' => 'boolean',
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'birth_date' => 'date',
        'hire_date' => 'date',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = activeTenantId();
            }
            if (empty($model->working_days)) {
                $model->working_days = [1, 2, 3, 4, 5, 6]; // Seg a Sab
            }
        });
    }

    // Accessors
    public function getDisplayNameAttribute()
    {
        return $this->nickname ?: $this->name;
    }

    public function getInitialsAttribute()
    {
        $words = explode(' ', $this->name);
        $initials = '';
        foreach (array_slice($words, 0, 2) as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return $initials;
    }

    // Methods
    public function worksOnDay($dayOfWeek)
    {
        return in_array($dayOfWeek, $this->working_days ?? []);
    }

    public function isAvailableAt(Carbon $dateTime)
    {
        if (!$this->worksOnDay($dateTime->dayOfWeek)) {
            return false;
        }

        $time = $dateTime->format('H:i');
        $workStart = Carbon::parse($this->work_start)->format('H:i');
        $workEnd = Carbon::parse($this->work_end)->format('H:i');

        if ($time < $workStart || $time >= $workEnd) {
            return false;
        }

        // Check lunch break
        if ($this->lunch_start && $this->lunch_end) {
            $lunchStart = Carbon::parse($this->lunch_start)->format('H:i');
            $lunchEnd = Carbon::parse($this->lunch_end)->format('H:i');
            if ($time >= $lunchStart && $time < $lunchEnd) {
                return false;
            }
        }

        return true;
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

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeAcceptsOnlineBooking($query)
    {
        return $query->where('accepts_online_booking', true);
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'salon_professional_services', 'professional_id', 'service_id')
            ->withPivot('custom_price', 'custom_duration')
            ->withTimestamps();
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'professional_id');
    }

    public function todayAppointments()
    {
        return $this->hasMany(Appointment::class, 'professional_id')
            ->whereDate('date', today());
    }
}
