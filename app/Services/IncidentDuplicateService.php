<?php

namespace App\Services;

use App\Models\Incident;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IncidentDuplicateService
{
    public function candidatesFor(Incident $incident, int $limit = 5): Collection
    {
        $incident->loadMissing('violences');
        $date = $incident->date_incident;

        $query = Incident::query()
            ->with(['province', 'territoire', 'zoneSante', 'evenement', 'violences'])
            ->where('id', '!=', $incident->id)
            ->whereNotIn('statut_incident', ['Archivé'])
            ->when($incident->code_province, fn($q) => $q->where('code_province', $incident->code_province))
            ->when($date, fn($q) => $q->whereBetween('date_incident', [
                $date->copy()->subDays(3)->startOfDay(),
                $date->copy()->addDays(3)->endOfDay(),
            ]))
            ->limit(80);

        return $query->get()
            ->map(fn(Incident $candidate) => $this->score($incident, $candidate))
            ->filter(fn(array $row) => $row['score'] >= 45)
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    private function score(Incident $source, Incident $candidate): array
    {
        $score = 0;
        $reasons = [];

        foreach ([
            'Même type d’événement' => $source->code_evenement && $source->code_evenement === $candidate->code_evenement,
            'Même zone de santé' => $source->code_zonesante && $source->code_zonesante === $candidate->code_zonesante,
            'Même territoire' => $source->code_territoire && $source->code_territoire === $candidate->code_territoire,
            'Même auteur présumé' => $this->sameText($source->auteur_presume, $candidate->auteur_presume),
            'Localité similaire' => $this->similarText($source->localite, $candidate->localite),
        ] as $reason => $match) {
            if ($match) {
                $score += match ($reason) {
                    'Même type d’événement', 'Même zone de santé' => 20,
                    'Localité similaire' => 18,
                    default => 12,
                };
                $reasons[] = $reason;
            }
        }

        $sharedViolences = $source->violences->pluck('id')->intersect($candidate->violences->pluck('id'))->count();
        if ($sharedViolences > 0) {
            $score += min(20, $sharedViolences * 8);
            $reasons[] = "{$sharedViolences} violence(s) en commun";
        }

        return [
            'incident' => $candidate,
            'score' => min(100, $score),
            'reasons' => $reasons,
        ];
    }

    private function sameText(?string $a, ?string $b): bool
    {
        return filled($a) && filled($b) && Str::lower(trim($a)) === Str::lower(trim($b));
    }

    private function similarText(?string $a, ?string $b): bool
    {
        if (!filled($a) || !filled($b)) {
            return false;
        }

        similar_text(Str::lower(trim($a)), Str::lower(trim($b)), $percent);

        return $percent >= 75;
    }
}
