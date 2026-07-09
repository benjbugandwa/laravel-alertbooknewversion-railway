<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Territoire extends Model
{
    protected $table = 'territoires';
    protected $primaryKey = 'code_territoire';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['code_territoire', 'nom_territoire', 'code_province', 'latitude', 'longitude'];

    protected $casts = [
        'latitude' => 'decimal:6',
        'longitude' => 'decimal:6',
    ];

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class, 'code_province', 'code_province')
            ->withoutGlobalScope('active');
    }

    public function zonesSantes(): HasMany
    {
        return $this->hasMany(ZoneSante::class, 'code_territoire', 'code_territoire');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class, 'code_territoire', 'code_territoire');
    }
}
