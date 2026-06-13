<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Briefing province</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { color: #0B4F8A; font-size: 22px; }
        h2 { color: #0B4F8A; font-size: 14px; border-bottom: 1px solid #0B4F8A; padding-bottom: 4px; margin-top: 22px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
    </style>
</head>
<body>
    <h1>Briefing province - {{ $province?->nom_province ?? 'Province' }}</h1>
    <p>Généré le {{ $generatedAt->format('d/m/Y H:i') }} par {{ $generatedBy->name ?? '-' }}</p>

    <h2>Résumé SLA</h2>
    <table>
        <tr><th>Incidents en retard</th><td>{{ $slaSummary['total_overdue_incidents'] }}</td></tr>
        <tr><th>Validation en retard</th><td>{{ $slaSummary['validation'] }}</td></tr>
        <tr><th>Réponse en retard</th><td>{{ $slaSummary['response'] }}</td></tr>
        <tr><th>Référencement en retard</th><td>{{ $slaSummary['referral'] }}</td></tr>
    </table>

    <h2>Incidents en retard</h2>
    <table>
        <tr><th>Code</th><th>Localité</th><th>Statut</th><th>Retards</th></tr>
        @forelse($overdueIncidents as $incident)
            <tr>
                <td>{{ $incident->code_incident }}</td>
                <td>{{ $incident->localite ?? '-' }}</td>
                <td>{{ $incident->statut_incident }}</td>
                <td>
                    @foreach($incident->sla['items'] as $item)
                        @if($item['is_overdue'])
                            {{ $item['label'] }} ({{ $item['hours_late'] }}h)@if(!$loop->last), @endif
                        @endif
                    @endforeach
                </td>
            </tr>
        @empty
            <tr><td colspan="4">Aucun retard SLA.</td></tr>
        @endforelse
    </table>

    <h2>Derniers incidents</h2>
    <table>
        <tr><th>Code</th><th>Date</th><th>Statut</th><th>Victimes</th><th>Réponses</th><th>Référencements</th></tr>
        @foreach($incidents->take(30) as $incident)
            <tr>
                <td>{{ $incident->code_incident }}</td>
                <td>{{ optional($incident->date_incident)->format('d/m/Y') }}</td>
                <td>{{ $incident->statut_incident }}</td>
                <td>{{ $incident->victimes_count }}</td>
                <td>{{ $incident->reponses_count }}</td>
                <td>{{ $incident->referencements_count }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
