<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MonitorSupervisorAssignment extends Model
{
    protected $fillable = [
        'monitor_id',
        'supervisor_id',
        'code_province',
        'created_by',
        'updated_by',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'monitor_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'code_province', 'code_province');
    }
}
