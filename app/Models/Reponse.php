<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class Reponse extends Model
{
    protected $table = 'reponses';

    protected $fillable = [
        'num_reponse',
        'date_reponse',
        'fournie_par',
        'type_reponse',
        'secteurs_couverts',
        'nbre_menages_couverts',
        'nbre_individus_couverts',
        'impact_reponse',
        'observation_gap',
        'rapport',
        'alerte_id',
        'create_at',
        'created_by',
    ];

    protected $casts = [
        'date_reponse' => 'date',
        'create_at' => 'date',
        'secteurs_couverts' => 'array',
        'nbre_menages_couverts' => 'integer',
        'nbre_individus_couverts' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($reponse) {
            if (empty($reponse->num_reponse)) {
                if (DB::getDriverName() === 'pgsql') {
                    $row = DB::selectOne("SELECT nextval('reponse_code_seq') as n");
                    $n = (int) ($row->n ?? 1);
                } else {
                    $maxId = (int) self::max('id');
                    $n = $maxId + 1;
                }
                $reponse->num_reponse = 'REP-' . str_pad((string) $n, 6, '0', STR_PAD_LEFT);
            }

            if (empty($reponse->create_at)) {
                $reponse->create_at = now()->toDateString();
            }

            if (empty($reponse->created_by) && Auth::check()) {
                $reponse->created_by = Auth::id();
            }
        });
    }

    /**
     * Relation avec l'incident (l'alerte)
     */
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class, 'alerte_id');
    }

    /**
     * Relation avec l'utilisateur qui a créé la réponse
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
