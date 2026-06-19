<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Briefing {{ $incident->code_incident }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { color: #0B4F8A; font-size: 22px; margin-bottom: 4px; }
        h2 { color: #0B4F8A; font-size: 14px; border-bottom: 1px solid #0B4F8A; padding-bottom: 4px; margin-top: 22px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 4px; background: #eef2ff; color: #3730a3; font-weight: bold; }
        .danger { background: #fee2e2; color: #991b1b; }
        .ok { background: #dcfce7; color: #166534; }
        .section { margin-bottom: 14px; }
    </style>
</head>
<body>
    <h1>Briefing note - {{ $incident->code_incident }}</h1>
    <div class="muted">Généré le {{ $generatedAt->format('d/m/Y H:i') }} par {{ $generatedBy->name ?? '-' }}</div>

    <h2>Résumé opérationnel</h2>
    <table>
        <tr><th>Statut</th><td>{{ $incident->statut_incident }}</td><th>Sévérité</th><td>{{ $incident->severite ?? '-' }}</td></tr>
        <tr><th>Événement</th><td>{{ $incident->evenement?->nom_evenement ?? '-' }}</td><th>Date</th><td>{{ optional($incident->date_incident)->format('d/m/Y') }}</td></tr>
        <tr><th>Localisation</th><td colspan="3">{{ $incident->province?->nom_province ?? '-' }} / {{ $incident->territoire?->nom_territoire ?? '-' }} / {{ $incident->zoneSante?->nom_zonesante ?? '-' }} / {{ $incident->localite ?? '-' }}</td></tr>
        <tr><th>Description</th><td colspan="3">{{ $incident->description_faits ?? '-' }}</td></tr>
    </table>

    <h2>SLA et qualité</h2>
    <p>
        Qualité des données :
        <span class="badge {{ $quality['missing_count'] ? 'danger' : 'ok' }}">{{ $quality['score'] }}% - {{ $quality['status'] }}</span>
        &nbsp; Retards SLA :
        <span class="badge {{ $sla['has_overdue'] ? 'danger' : 'ok' }}">{{ $sla['overdue_count'] }}</span>
    </p>
    @if($quality['issues']->isNotEmpty())
        <ul>
            @foreach($quality['issues'] as $issue)
                <li>{{ $issue['label'] }} ({{ $issue['severity'] }})</li>
            @endforeach
        </ul>
    @endif

    <h2>Victimes, réponses et gaps</h2>
    <table>
        <tr><th>Violences</th><td>{{ $incident->violences->count() }}</td><th>Victimes</th><td>{{ $incident->victimes->count() }}</td></tr>
        <tr><th>Référencements</th><td>{{ $incident->referencements->count() }}</td><th>Réponses</th><td>{{ $incident->reponses->count() }}</td></tr>
        <tr><th>Mouvements</th><td>{{ $incident->mouvements->count() }}</td><th>Notes</th><td>{{ $incident->caseNotes->count() }}</td></tr>
    </table>

    <h2>Timeline</h2>
    <table>
        <tr><th>Date</th><th>Type</th><th>Événement</th></tr>
        @foreach($timeline->take(20) as $event)
            <tr>
                <td>{{ $event['date']->format('d/m/Y H:i') }}</td>
                <td>{{ $event['label'] }}</td>
                <td>{{ $event['title'] }}</td>
            </tr>
        @endforeach
    </table>

    <h2>Doublons potentiels</h2>
    @if($duplicates->isEmpty())
        <p>Aucun doublon probable détecté.</p>
    @else
        <table>
            <tr><th>Incident</th><th>Score</th><th>Raisons</th></tr>
            @foreach($duplicates as $row)
                <tr>
                    <td>{{ $row['incident']->code_incident }}</td>
                    <td>{{ $row['score'] }}%</td>
                    <td>{{ implode(', ', $row['reasons']) }}</td>
                </tr>
            @endforeach
        </table>
    @endif
</body>
</html>
