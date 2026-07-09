<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Rapport d'analyses</title>
    <style>
        @page {
            margin: 22mm 12mm 18mm 12mm;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5px;
            color: #1f2937;
            background: #f8fafc;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -10mm;
            color: #64748b;
            font-size: 8.5px;
            border-top: 1px solid #dbe5ef;
            padding-top: 5px;
        }

        .page-number:after {
            content: counter(page);
        }

        .banner {
            margin: -10mm 0 10px 0;
            padding: 16px 18px;
            color: #ffffff;
            background: #0b4f8a;
            border-radius: 10px;
        }

        .banner h1 {
            margin: 0;
            font-size: 23px;
            line-height: 1.2;
        }

        .banner .meta {
            margin-top: 8px;
            font-size: 10.5px;
            color: #dbeafe;
        }

        .grid-4 {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin: 0 -8px 10px -8px;
        }

        .metric {
            background: #ffffff;
            border: 1px solid #dbe5ef;
            border-radius: 8px;
            padding: 10px;
        }

        .metric .label {
            font-size: 8.5px;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .04em;
        }

        .metric .value {
            margin-top: 4px;
            font-size: 21px;
            font-weight: 800;
            color: #0b4f8a;
        }

        .section {
            page-break-inside: avoid;
            background: #ffffff;
            border: 1px solid #dbe5ef;
            border-radius: 9px;
            padding: 12px;
            margin: 10px 0;
        }

        .section h2 {
            margin: 0 0 8px 0;
            font-size: 14px;
            color: #0b4f8a;
        }

        .section.hot h2 {
            color: #b91c1c;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #e2e8f0;
            padding: 6px;
            vertical-align: middle;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
        }

        .right {
            text-align: right;
        }

        .bar {
            height: 9px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .bar span {
            display: block;
            height: 9px;
            border-radius: 999px;
        }

        .bar-red span {
            background: #fb7185;
        }

        .bar-blue span {
            background: #0e6593;
        }

        .row-hot-1 td { background: #fff1f2; }
        .row-hot-2 td { background: #ffe4e6; }
        .row-hot-3 td { background: #fecdd3; }

        .two-col {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-left: -10px;
        }

        .two-col > tbody > tr > td {
            width: 50%;
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .map-wrap {
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            background: #eff6ff;
            padding: 8px;
        }

        .map-canvas {
            position: relative;
            height: 172px;
            border-radius: 7px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            overflow: hidden;
        }

        .map-dot {
            position: absolute;
            border-radius: 999px;
            background: rgba(251, 113, 133, .72);
            border: 2px solid #b91c1c;
        }

        .map-label {
            position: absolute;
            font-size: 8px;
            color: #7f1d1d;
            white-space: nowrap;
        }

        .empty {
            padding: 12px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
        }

        .warning {
            margin: 0 0 10px 0;
            padding: 8px 10px;
            border: 1px solid #fed7aa;
            border-radius: 7px;
            color: #9a3412;
            background: #fff7ed;
            font-size: 9px;
        }
    </style>
</head>
<body>
    @php
        $filters = $report['filters'];
        $summary = $report['summary'];
        $userOrg = $generatedBy->organisation?->org_name
            ?? $generatedBy->organisation?->org_sigle
            ?? 'Organisation non renseignee';
        $scopeLabel = trim(($filters['province_name'] ?? 'Toutes provinces').' / '.($filters['territoire_name'] ?? 'Tous territoires'));
    @endphp

    <div class="footer">
        Imprime le {{ $generatedAt->format('d/m/Y H:i') }} - Genere par {{ $generatedBy->name ?? '-' }} / {{ $userOrg }}
        <span style="float: right;">Page <span class="page-number"></span></span>
    </div>

    <div class="banner">
        <h1>Rapport d'analyses operationnelles</h1>
        <div class="meta">
            Perimetre: {{ $scopeLabel }} - Periode: Du {{ $filters['from']->format('d/m/Y') }} au {{ $filters['to']->format('d/m/Y') }}
        </div>
    </div>

    <table class="grid-4">
        <tr>
            <td class="metric">
                <div class="label">Alertes validees</div>
                <div class="value">{{ number_format($summary['alerts'], 0, ',', ' ') }}</div>
            </td>
            <td class="metric">
                <div class="label">Zones de sante touchees</div>
                <div class="value">{{ number_format($summary['health_zones'], 0, ',', ' ') }}</div>
            </td>
            <td class="metric">
                <div class="label">Victimes documentees</div>
                <div class="value">{{ number_format($summary['victims'], 0, ',', ' ') }}</div>
            </td>
            <td class="metric">
                <div class="label">Individus en mouvement</div>
                <div class="value">{{ number_format($summary['movement_people'], 0, ',', ' ') }}</div>
            </td>
        </tr>
    </table>

    @if(!empty($report['warnings']))
        <div class="warning">
            Certaines sections n'ont pas pu etre calculees automatiquement: {{ implode(', ', $report['warnings']) }}.
        </div>
    @endif

    <table class="two-col">
        <tr>
            <td>
                <div class="section hot">
                    <h2>Zones chaudes - zones de sante</h2>
                    @if(count($report['hot_zones']) > 0)
                        <table>
                            <thead>
                                <tr>
                                    <th>Zone de sante</th>
                                    <th class="right">Alertes</th>
                                    <th class="right">%</th>
                                    <th>Intensite</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($report['hot_zones'] as $row)
                                    <tr class="@if($loop->iteration === 1) row-hot-3 @elseif($loop->iteration <= 3) row-hot-2 @elseif($loop->iteration <= 5) row-hot-1 @endif">
                                        <td>{{ $row['label'] }}</td>
                                        <td class="right">{{ $row['total'] }}</td>
                                        <td class="right">{{ number_format($row['percentage'], 1, ',', ' ') }}%</td>
                                        <td>
                                            <div class="bar bar-red">
                                                <span style="width: {{ min(100, max(2, $row['percentage'])) }}%;"></span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="empty">Aucune alerte validee dans le perimetre.</div>
                    @endif
                </div>
            </td>
            <td>
                <div class="section hot">
                    <h2>Carte des zones chaudes</h2>
                    @if(count($report['hot_territories']) > 0)
                        <div class="map-wrap">
                            <div class="map-canvas">
                                @foreach($report['hot_territories'] as $territory)
                                    @php
                                        $diameter = max(10, $territory['radius'] * 2.2);
                                        $offset = $diameter / 2;
                                        $labelTop = max(2, $territory['y'] - 9);
                                    @endphp
                                    <div class="map-dot" style="left: {{ $territory['x'] }}%; top: {{ $territory['y'] }}%; width: {{ $diameter }}px; height: {{ $diameter }}px; margin-left: -{{ $offset }}px; margin-top: -{{ $offset }}px;"></div>
                                    <div class="map-label" style="left: {{ $territory['x'] }}%; top: {{ $labelTop }}%; margin-left: 8px;">{{ $territory['name'] }} ({{ $territory['total'] }})</div>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <div class="empty">Aucun territoire avec coordonnees GPS pour cette periode.</div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>Violences et nombre de victimes par zone de sante</h2>
        @if(count($report['violence_by_zone']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Zone de sante</th>
                        <th>Violence</th>
                        <th class="right">Victimes</th>
                        <th class="right">%</th>
                        <th>Poids relatif</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['violence_by_zone'] as $row)
                        <tr>
                            <td>{{ $row['zone_label'] }}</td>
                            <td>{{ $row['violence_label'] }}</td>
                            <td class="right">{{ $row['total_victims'] }}</td>
                            <td class="right">{{ number_format($row['percentage'], 1, ',', ' ') }}%</td>
                            <td>
                                <div class="bar bar-blue">
                                    <span style="width: {{ min(100, max(2, $row['percentage'])) }}%;"></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">Aucune victime documentee dans le perimetre.</div>
        @endif
    </div>

    <div class="section">
        <h2>Analyse des mouvements des populations</h2>
        @if(count($report['movements']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Provenance</th>
                        <th>Accueil</th>
                        <th>Type</th>
                        <th class="right">Menages</th>
                        <th class="right">Individus</th>
                        <th class="right">Mouvements</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['movements'] as $row)
                        <tr>
                            <td>{{ $row['origin'] }}</td>
                            <td>{{ $row['destination'] }}</td>
                            <td>{{ $row['type'] }}</td>
                            <td class="right">{{ number_format($row['households'], 0, ',', ' ') }}</td>
                            <td class="right">{{ number_format($row['people'], 0, ',', ' ') }}</td>
                            <td class="right">{{ $row['total'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">Aucun mouvement lie aux alertes validees du perimetre.</div>
        @endif
    </div>

    <div class="section">
        <h2>Analyse complementaire - types d'evenements</h2>
        @if(count($report['event_types']) > 0)
            <table>
                <thead>
                    <tr>
                        <th>Type d'evenement</th>
                        <th class="right">Alertes</th>
                        <th class="right">%</th>
                        <th>Poids relatif</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($report['event_types'] as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td class="right">{{ $row['total'] }}</td>
                            <td class="right">{{ number_format($row['percentage'], 1, ',', ' ') }}%</td>
                            <td>
                                <div class="bar bar-blue">
                                    <span style="width: {{ min(100, max(2, $row['percentage'])) }}%;"></span>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">Aucun type d'evenement disponible.</div>
        @endif
    </div>
</body>
</html>
