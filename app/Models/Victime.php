<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Victime extends Model
{
    protected $table = 'victimes';

    protected $fillable = [
        'incident_id',
        'violence_id',
        'profile_victimes',
        'nbre_femme_0a4ans',
        'nbre_femme_5a11ans',
        'nbre_femme_12a17ans',
        'nbre_femme_18a59ans',
        'nbre_femme_6Oansouplus', // note: letter O!
        'nbre_homme_0a4ans',
        'nbre_homme_5a11ans',
        'nbre_homme_12a17ans',
        'nbre_homme_18a59ans',
        'nbre_homme_6Oansouplus', // note: letter O!
        'description_faits',
        'create_at',
        'created_by',
    ];

    protected $casts = [
        'create_at' => 'date',
        'nbre_femme_0a4ans' => 'integer',
        'nbre_femme_5a11ans' => 'integer',
        'nbre_femme_12a17ans' => 'integer',
        'nbre_femme_18a59ans' => 'integer',
        'nbre_femme_6Oansouplus' => 'integer',
        'nbre_homme_0a4ans' => 'integer',
        'nbre_homme_5a11ans' => 'integer',
        'nbre_homme_12a17ans' => 'integer',
        'nbre_homme_18a59ans' => 'integer',
        'nbre_homme_6Oansouplus' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($victime) {
            if (empty($victime->create_at)) {
                $victime->create_at = now()->toDateString();
            }
            if (empty($victime->created_by)) {
                $victime->created_by = auth()->id();
            }
        });
    }

    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'incident_id');
    }

    public function violence(): BelongsTo
    {
        return $this->belongsTo(Violence::class, 'violence_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
