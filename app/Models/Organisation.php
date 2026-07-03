<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organisation extends Model
{
    protected $table = 'organisations';

    protected $fillable = [
        'org_sigle',
        'org_name',
        'org_secteur_activite',
        'org_categorie',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected function orgSecteurActivite(): Attribute
    {
        return Attribute::make(
            get: function ($value): array {
                if ($value === null || $value === '') {
                    return [];
                }

                $decoded = json_decode((string) $value, true);
                $sectors = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;

                return $this->normalizeSectors($sectors);
            },
            set: fn ($value): string => json_encode(
                $this->normalizeSectors($value),
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            ),
        );
    }

    private function normalizeSectors($value): array
    {
        $values = is_array($value) ? $value : [$value];

        return collect($values)
            ->map(fn ($sector) => trim((string) $sector))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'org_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
