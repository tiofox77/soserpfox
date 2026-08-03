<?php

namespace App\Models\HR;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenant;

class Position extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'hr_positions';

    protected $fillable = [
        'tenant_id', 'title', 'code', 'department_id', 
        'description', 'min_salary', 'max_salary', 'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employees()
    {
        return $this->hasMany(Employee::class, 'position_id');
    }
}
