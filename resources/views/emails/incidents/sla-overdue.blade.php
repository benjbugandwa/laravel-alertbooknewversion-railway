<p>Bonjour {{ $recipientName }},</p>

<p>AlertBook a détecté des incidents en retard SLA.</p>

<ul>
    <li>Validation : {{ $summary['validation'] }}</li>
    <li>Réponse : {{ $summary['response'] }}</li>
    <li>Référencement : {{ $summary['referral'] }}</li>
</ul>

<table border="1" cellpadding="6" cellspacing="0" width="100%">
    <thead>
        <tr>
            <th align="left">Incident</th>
            <th align="left">Localité</th>
            <th align="left">Statut</th>
            <th align="left">Retards</th>
        </tr>
    </thead>
    <tbody>
        @foreach($incidents as $incident)
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
        @endforeach
    </tbody>
</table>

<p>Ouvrez le dashboard pour prioriser le traitement.</p>
