<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhaseTemplate extends Model
{
    use HasFactory;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'project_type',
        'phase_name',
        'phase_description',
        'order_index',
        'default_weight',
        'default_duration_days',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_index' => 'integer',
            'default_weight' => 'decimal:2',
            'default_duration_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
