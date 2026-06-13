<?php

namespace App\Services;

use App\Models\Incident;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IncidentSlaService
{
    public const VALIDATION_HOURS = 24;
    public const RESPONSE_HOURS = 72;
    public const REFERRAL_DAYS = 7;

    public function statusFor(Incident $incident): array
    {
        if (!array_key_exists('reponses_count', $incident->getAttributes())) {
            $incident->loadCount('reponses');
        }
        if (!array_key_exists('referencements_count', $incident->getAttributes())) {
            $incident->loadCount('referencements');
        }

        $base = \Illuminate\Support\Carbon::parse($incident->created_at ?? $incident->date_incident ?? now());
        $validatedAt = \Illuminate\Support\Carbon::parse($incident->last_status_changed_at ?? $incident->created_at ?? now());

        $items = [
            'validation' => $this->buildItem(
                'Validation',
                $incident->statut_incident === 'En attente',
                $base->copy()->addHours(self::VALIDATION_HOURS),
                'Validation attendue sous 24h'
            ),
            'response' => $this->buildItem(
                'Réponse',
                $incident->statut_incident === 'Validé' && $incident->reponses_count === 0,
                $validatedAt->copy()->addHours(self::RESPONSE_HOURS),
                'Première réponse attendue sous 72h après validation'
            ),
            'referral' => $this->buildItem(
                'Référencement',
                $incident->statut_incident === 'Validé' && $incident->referencements_count === 0,
                $validatedAt->copy()->addDays(self::REFERRAL_DAYS),
                'Référencement attendu sous 7 jours après validation'
            ),
        ];

        $overdue = collect($items)->filter(fn(array $item) => $item['is_overdue'])->values();

        return [
            'items' => $items,
            'overdue_count' => $overdue->count(),
            'worst_due_at' => $overdue->min('due_at'),
            'has_overdue' => $overdue->isNotEmpty(),
        ];
    }

    public function overdueIncidents(?string $provinceCode = null, ?string $territoryCode = null, int $limit = 12): Collection
    {
        return $this->baseQuery($provinceCode, $territoryCode)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->map(function (Incident $incident) {
                $incident->sla = $this->statusFor($incident);
                return $incident;
            })
            ->filter(fn(Incident $incident) => $incident->sla['has_overdue'])
            ->sortBy(fn(Incident $incident) => $incident->sla['worst_due_at'])
            ->take($limit)
            ->values();
    }

    public function summary(?string $provinceCode = null, ?string $territoryCode = null): array
    {
        $incidents = $this->baseQuery($provinceCode, $territoryCode)->limit(500)->get();

        $summary = [
            'validation' => 0,
            'response' => 0,
            'referral' => 0,
            'total_overdue_incidents' => 0,
        ];

        foreach ($incidents as $incident) {
            $sla = $this->statusFor($incident);
            if ($sla['has_overdue']) {
                $summary['total_overdue_incidents']++;
            }
            foreach ($sla['items'] as $key => $item) {
                if ($item['is_overdue']) {
                    $summary[$key]++;
                }
            }
        }

        return $summary;
    }

    private function baseQuery(?string $provinceCode, ?string $territoryCode): Builder
    {
        return Incident::query()
            ->with(['province', 'territoire', 'zoneSante'])
            ->withCount(['reponses', 'referencements'])
            ->whereNotIn('statut_incident', ['Archivé', 'Cloturée'])
            ->when($provinceCode, fn(Builder $q) => $q->where('code_province', $provinceCode))
            ->when($territoryCode, fn(Builder $q) => $q->where('code_territoire', $territoryCode));
    }

    private function buildItem(string $label, bool $active, CarbonInterface $dueAt, string $description): array
    {
        $now = now();

        return [
            'label' => $label,
            'description' => $description,
            'active' => $active,
            'due_at' => $dueAt,
            'is_overdue' => $active && $dueAt->isPast(),
            'hours_late' => $active && $dueAt->isPast() ? $dueAt->diffInHours($now) : 0,
        ];
    }
}
