<?php

namespace App\Services;

use App\Models\Incident;

class IncidentQualityService
{
    public function report(Incident $incident): array
    {
        $incident->loadMissing(['violences', 'victimes', 'reponses', 'referencements', 'mouvements', 'caseNotes']);

        $checks = [
            $this->check((bool) $incident->date_incident, 'Date de l’incident manquante', 'high'),
            $this->check((bool) $incident->code_province, 'Province non renseignée', 'high'),
            $this->check((bool) $incident->code_territoire, 'Territoire non renseigné', 'high'),
            $this->check((bool) $incident->code_zonesante, 'Zone de santé non renseignée', 'medium'),
            $this->check((bool) $incident->localite, 'Localité non renseignée', 'medium'),
            $this->check((bool) $incident->code_evenement, 'Type d’événement non renseigné', 'high'),
            $this->check((bool) $incident->auteur_presume, 'Auteur présumé non renseigné', 'medium'),
            $this->check((bool) $incident->description_faits, 'Description des faits absente', 'medium'),
            $this->check((bool) ($incident->latitude && $incident->longitude), 'Coordonnées GPS absentes', 'medium'),
            $this->check($incident->violences->isNotEmpty(), 'Aucun type de violence lié', 'high'),
            $this->check($incident->victimes->isNotEmpty(), 'Aucune victime enregistrée', 'high'),
            $this->check($incident->reponses->isNotEmpty(), 'Aucune réponse enregistrée', 'medium'),
            $this->check($incident->referencements->isNotEmpty(), 'Aucun référencement enregistré', 'medium'),
            $this->check($this->hasEvidence($incident), 'Aucune pièce justificative/photo/rapport', 'low'),
        ];

        $issues = collect($checks)->filter(fn(array $check) => !$check['ok'])->values();
        $score = max(0, 100 - $issues->sum(fn(array $issue) => match ($issue['severity']) {
            'high' => 12,
            'medium' => 7,
            default => 4,
        }));

        return [
            'score' => $score,
            'status' => $score >= 85 ? 'Bon' : ($score >= 65 ? 'À compléter' : 'Critique'),
            'issues' => $issues,
            'missing_count' => $issues->count(),
        ];
    }

    private function check(bool $ok, string $label, string $severity): array
    {
        return compact('ok', 'label', 'severity');
    }

    private function hasEvidence(Incident $incident): bool
    {
        return (bool) $incident->photo_url
            || $incident->reponses->contains(fn($response) => filled($response->rapport))
            || $incident->caseNotes->contains(fn($note) => filled($note->file_path));
    }
}
