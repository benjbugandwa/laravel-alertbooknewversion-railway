<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Incident;
use Illuminate\Support\Collection;

class IncidentTimelineService
{
    public function forIncident(Incident $incident): Collection
    {
        $incident->loadMissing([
            'violences',
            'victimes.violence',
            'mouvements',
            'referencements.provider',
            'reponses',
            'caseNotes',
            'creator',
            'assignedTo',
        ]);

        $events = collect();

        $events->push($this->event('Création', 'Incident créé', $incident->created_at, 'incident', [
            'Par' => $incident->creator?->name ?? '-',
            'Statut initial' => $incident->statut_incident ?? '-',
        ]));

        if ($incident->assigned_at) {
            $events->push($this->event('Assignation', 'Incident assigné', $incident->assigned_at, 'assignment', [
                'Assigné à' => $incident->assignedTo?->name ?? '-',
            ]));
        }

        foreach ($incident->violences as $violence) {
            $events->push($this->event('Violence', $violence->violence_name, $violence->pivot?->created_at, 'violence'));
        }

        foreach ($incident->victimes as $victime) {
            $total = collect($victime->getAttributes())
                ->filter(fn($value, $key) => str_starts_with($key, 'nbre_'))
                ->sum(fn($value) => (int) $value);
            $events->push($this->event('Victimes', $victime->violence?->violence_name ?? 'Victimes enregistrées', $victime->create_at, 'victim', [
                'Profil' => $victime->profile_victimes ?? '-',
                'Total' => (string) $total,
            ]));
        }

        foreach ($incident->mouvements as $mouvement) {
            $events->push($this->event('Mouvement', $mouvement->type_mouvement ?? 'Mouvement de population', $mouvement->date_mouvement, 'movement', [
                'Personnes' => (string) ($mouvement->estim_nbre_personnes ?? 0),
                'Ménages' => (string) ($mouvement->estim_nbre_menages ?? 0),
            ]));
        }

        foreach ($incident->referencements as $ref) {
            $events->push($this->event('Référencement', $ref->provider?->provider_name ?? $ref->type_reponse ?? 'Référencement', $ref->date_referencement, 'referral', [
                'Statut' => $ref->statut_reponse ?? '-',
            ]));
        }

        foreach ($incident->reponses as $response) {
            $events->push($this->event('Réponse', $response->num_reponse . ' - ' . $response->type_reponse, $response->date_reponse, 'response', [
                'Fournie par' => $response->fournie_par ?? '-',
                'Individus couverts' => (string) ($response->nbre_individus_couverts ?? 0),
            ]));
        }

        foreach ($incident->caseNotes as $note) {
            $events->push($this->event('Note', $note->is_confidential ? 'Note confidentielle' : 'Note de dossier', $note->created_at, 'note'));
        }

        AuditLog::query()
            ->whereIn('model_type', ['incident', Incident::class])
            ->where('model_id', $incident->id)
            ->orderBy('created_at')
            ->get()
            ->each(function (AuditLog $log) use ($events) {
                $events->push($this->event(
                    'Audit',
                    str_replace('_', ' ', (string) $log->user_action),
                    $log->created_at,
                    'audit'
                ));
            });

        return $events
            ->filter(fn(array $event) => $event['date'])
            ->sortBy('date')
            ->values();
    }

    private function event(string $label, string $title, mixed $date, string $type, array $meta = []): array
    {
        return [
            'label' => $label,
            'title' => $title,
            'date' => $date ? \Illuminate\Support\Carbon::parse($date) : null,
            'type' => $type,
            'meta' => $meta,
        ];
    }
}
