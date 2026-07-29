<div>
    <div wire:loading class="fixed inset-0 z-50 flex items-center justify-center bg-white/60 backdrop-blur-sm">
        <div class="flex flex-col items-center bg-white p-6 rounded-xl shadow-xl">
            <svg class="h-10 w-10 animate-spin text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="mt-4 text-sm font-semibold text-gray-700">Actualisation des données...</span>
        </div>
    </div>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
                <div class="text-2xl font-bold">Dashboard</div>
                <div class="text-sm text-gray-600">
                    @if ($chart['scope']['isSuper'])
                        Vue globale{{ $chart['scope']['nom_province'] ? ' - '.$chart['scope']['nom_province'] : ' (toutes provinces)' }}.
                    @else
                        Vue province : <b>{{ $chart['scope']['nom_province'] ?? '-' }}</b>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($chart['scope']['isSuper'])
                    <select wire:model.live="selectedProvince" class="text-sm border-gray-300 rounded-md py-1 pr-8">
                        <option value="">Toutes les provinces</option>
                        @foreach($provinces as $prov)
                            <option value="{{ $prov['code_province'] }}">{{ $prov['nom_province'] }}</option>
                        @endforeach
                    </select>
                    @if($selectedProvince)
                        <select wire:model.live="selectedTerritoire" class="text-sm border-gray-300 rounded-md py-1 pr-8">
                            <option value="">Tous les territoires</option>
                            @foreach($territoires as $terr)
                                <option value="{{ $terr['code_territoire'] }}">{{ $terr['nom_territoire'] }}</option>
                            @endforeach
                        </select>
                    @endif
                @endif
                <x-ui-button size="sm" variant="secondary" wire:click="setDays(30)">30j</x-ui-button>
                <x-ui-button size="sm" variant="secondary" wire:click="setDays(90)">90j</x-ui-button>
                <x-ui-button size="sm" variant="secondary" wire:click="setDays(180)">180j</x-ui-button>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
            @if ($chart['scope']['isSuper'])
                <x-ui-card>
                    <div class="text-sm text-gray-600">Utilisateurs actifs</div>
                    <div class="mt-2 text-3xl font-bold">{{ $chart['users']['active'] }}</div>
                </x-ui-card>

                <x-ui-card>
                    <div class="text-sm text-gray-600">En attente d’activation</div>
                    <div class="mt-2 text-3xl font-bold">{{ $chart['users']['pending'] }}</div>
                    <div class="mt-2 text-xs text-gray-500">Comptes non activés</div>
                </x-ui-card>
            @endif

            <x-ui-card>
                <div class="text-sm text-gray-600">Alertes validées</div>
                <div class="mt-2 text-3xl font-bold">{{ collect($chart['evolution']['data'])->sum() }}</div>
                <div class="mt-2 text-xs text-gray-500">Derniers {{ $this->days }} jours</div>
            </x-ui-card>

            <x-ui-card>
                <div class="text-sm text-gray-600">Taux de validation</div>
                <div class="mt-2 text-3xl font-bold">{{ number_format($chart['byStatus']['validatedPercentage'] ?? 0, 1) }}%</div>
                <div class="mt-2 text-xs text-gray-500">
                    {{ $chart['byStatus']['validated'] ?? 0 }} validées sur {{ $chart['byStatus']['total'] ?? 0 }} alertes enregistrées
                </div>
            </x-ui-card>

            <x-ui-card>
                <div class="text-sm text-gray-600">Taux de réponses</div>
                <div class="mt-2 text-3xl font-bold text-blue-700">{{ number_format($chart['kpis']['response_rate'] ?? 0, 1) }}%</div>
                <div class="mt-2 text-xs text-gray-500">
                    {{ $chart['kpis']['responded_incidents'] ?? 0 }} / {{ $chart['kpis']['validated_incidents'] ?? 0 }} alertes validées
                </div>
            </x-ui-card>

            <x-ui-card>
                <div class="text-sm text-gray-600">Taux de référencements</div>
                <div class="mt-2 text-3xl font-bold text-cyan-700">{{ number_format($chart['kpis']['referral_rate'] ?? 0, 1) }}%</div>
                <div class="mt-2 text-xs text-gray-500">
                    {{ $chart['kpis']['referred_incidents'] ?? 0 }} / {{ $chart['kpis']['validated_incidents'] ?? 0 }} alertes validées
                </div>
            </x-ui-card>

            <x-ui-card>
                <div class="text-sm text-gray-600">Victimes affectées</div>
                <div class="mt-2 text-3xl font-bold text-red-700">{{ number_format($chart['kpis']['victims_total'] ?? 0, 0, ',', ' ') }}</div>
                <div class="mt-2 text-xs text-gray-500">Total documenté sur la période</div>
            </x-ui-card>

            <x-ui-card>
                <div class="text-sm text-gray-600">Déplacements des populations</div>
                <div class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                    <div>
                        <span class="text-3xl font-bold text-amber-700">{{ number_format($chart['kpis']['movement_households'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="text-xs text-gray-500">ménages</span>
                    </div>
                    <div>
                        <span class="text-3xl font-bold text-amber-900">{{ number_format($chart['kpis']['movement_people'] ?? 0, 0, ',', ' ') }}</span>
                        <span class="text-xs text-gray-500">personnes</span>
                    </div>
                </div>
            </x-ui-card>

            <x-ui-card>
                <div class="text-sm text-gray-600">Structures disponibles</div>
                <div class="mt-2 text-3xl font-bold text-emerald-700">{{ number_format($chart['kpis']['service_providers'] ?? 0, 0, ',', ' ') }}</div>
                <div class="mt-2 text-xs text-gray-500">Structures de prise en charge</div>
            </x-ui-card>
        </div>

        <x-ui-card>
            <div class="text-sm text-gray-600">Incidents en retard SLA</div>
            <div class="mt-2 text-3xl font-bold text-red-700">{{ $slaSummary['total_overdue_incidents'] ?? 0 }}</div>
            <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs">
                <div class="rounded-lg bg-red-50 p-2">
                    <div class="font-semibold text-red-900">{{ $slaSummary['validation'] ?? 0 }}</div>
                    <div class="text-red-700">Validation</div>
                </div>
                <div class="rounded-lg bg-amber-50 p-2">
                    <div class="font-semibold text-amber-900">{{ $slaSummary['response'] ?? 0 }}</div>
                    <div class="text-amber-700">Réponse</div>
                </div>
                <div class="rounded-lg bg-blue-50 p-2">
                    <div class="font-semibold text-blue-900">{{ $slaSummary['referral'] ?? 0 }}</div>
                    <div class="text-blue-700">Référencement</div>
                </div>
            </div>
        </x-ui-card>

        <div id="chart-data" class="hidden" data-payload="{{ json_encode($chart) }}"></div>

        <div x-data="dashboardCharts(@js($chart))" x-init="init()"
            x-on:livewire:navigated.window="rebuild(@js($chart))"
            class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <x-ui-card class="lg:col-span-2">
                <div class="font-semibold">Évolution des alertes ({{ $this->days }} jours)</div>
                <div class="mt-3">
                    <canvas id="chartEvolution" height="80"></canvas>
                </div>
            </x-ui-card>

            <x-ui-card>
                <div class="font-semibold">Alertes par statut</div>
                <div class="mt-1 text-sm text-gray-600">
                    <span class="font-semibold text-gray-900">{{ number_format($chart['byStatus']['validatedPercentage'] ?? 0, 1) }}%</span>
                    validées ({{ $chart['byStatus']['validated'] ?? 0 }} sur {{ $chart['byStatus']['total'] ?? 0 }} alertes enregistrées)
                </div>
                <div class="mt-3">
                    <canvas id="chartStatus" height="200"></canvas>
                </div>
            </x-ui-card>

            <x-ui-card>
                <div class="font-semibold">Alertes par type d'évènement</div>
                <div class="mt-3">
                    <canvas id="chartEventType" height="200"></canvas>
                </div>
            </x-ui-card>

            <x-ui-card class="lg:col-span-2">
                <div class="font-semibold">Alertes par organisation</div>
                <div class="mt-1 text-sm text-gray-600">Incidents rapportés, validés et en attente, sur la période affichée.</div>
                <div class="mt-3">
                    <canvas id="chartOrganisation" height="110"></canvas>
                </div>
            </x-ui-card>

            <x-ui-card class="lg:col-span-2">
                <div class="font-semibold">Alertes par province</div>
                <div class="mt-3">
                    <canvas id="chartProvince" height="90"></canvas>
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium">Province</th>
                                <th class="px-4 py-2 text-right font-medium">Total</th>
                                <th class="px-4 py-2 text-right font-medium">%</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse(($chart['byProvince']['table'] ?? []) as $row)
                                <tr class="hover:bg-gray-50/60">
                                    <td class="px-4 py-2">
                                        <div class="font-medium text-gray-900">{{ $row['label'] }}</div>
                                    </td>
                                    <td class="px-4 py-2 text-right font-semibold text-gray-900">{{ $row['total'] }}</td>
                                    <td class="px-4 py-2 text-right text-gray-700">{{ number_format($row['pct'], 1) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td class="px-4 py-3 text-gray-600" colspan="3">Aucune donnée.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        @if (!empty($chart['byProvince']['table']))
                            <tfoot class="bg-gray-50 text-gray-700">
                                <tr>
                                    <td class="px-4 py-2 font-medium">Total</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ $chart['byProvince']['sum'] ?? 0 }}</td>
                                    <td class="px-4 py-2 text-right font-medium">100%</td>
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>
            </x-ui-card>

            <x-ui-card class="lg:col-span-2 relative overflow-hidden">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                    <div class="font-bold text-gray-800 text-lg">Répartition géographique des incidents</div>
                    <div class="flex items-center gap-2 text-xs font-semibold">
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-blue-700 border border-blue-100">
                            {{ $chart['territoryMap']['total'] ?? 0 }} incidents
                        </span>
                        <span class="rounded-full bg-gray-50 px-3 py-1 text-gray-700 border border-gray-200">
                            {{ count($chart['territoryMap']['points'] ?? []) }} territoires
                        </span>
                    </div>
                </div>

                <div class="relative z-0 border border-gray-200 rounded-2xl overflow-hidden shadow-sm" wire:ignore>
                    <div id="mapTerritories" class="h-[620px] w-full z-[1]"></div>

                    <div id="territoryMapEmpty" class="hidden absolute left-1/2 top-1/2 z-[1000] w-[min(90%,26rem)] -translate-x-1/2 -translate-y-1/2 rounded-lg border border-gray-200 bg-white/95 px-5 py-4 text-center shadow-xl">
                        <div class="font-semibold text-gray-900">Aucune donnée cartographique</div>
                        <div class="mt-1 text-sm text-gray-500">Aucun incident validé avec territoire coordonné sur la période affichée.</div>
                    </div>

                    <div class="absolute bottom-8 left-8 z-[1000] bg-white/95 backdrop-blur-md border border-gray-200 rounded-xl p-4 shadow-xl min-w-[200px]">
                        <div class="text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3 border-b pb-2">Intensité des incidents</div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-3">
                                <span class="w-4 h-4 rounded-full border-2 shadow-sm" style="background-color: #dc2626; border-color: #93c5fd"></span>
                                <span class="text-xs font-medium text-gray-700">Volume élevé</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-4 h-4 rounded-full border-2 shadow-sm" style="background-color: #f59e0b; border-color: #93c5fd"></span>
                                <span class="text-xs font-medium text-gray-700">Volume moyen</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-4 h-4 rounded-full border-2 shadow-sm" style="background-color: #2563eb; border-color: #93c5fd"></span>
                                <span class="text-xs font-medium text-gray-700">Volume faible</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-3 h-3 rounded-full border-2 shadow-sm" style="background-color: #2563eb; border-color: #93c5fd"></span>
                                <span class="text-xs font-medium text-gray-700">Cercle proportionnel</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="w-4 h-4 rounded-full bg-gray-100 border border-gray-200"></span>
                                <span class="text-xs font-medium text-gray-500">Aucun point si aucune coordonnée</span>
                            </div>
                        </div>
                    </div>
                </div>
            </x-ui-card>
        </div>
    </div>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2"></script>
    <script>
        function dashboardCharts(payload) {
            return {
                payload,
                charts: {
                    evolution: null,
                    status: null,
                    eventType: null,
                    organisation: null,
                    province: null,
                },
                map: null,
                territoryBoundaryLayer: null,
                territoryGeoJsonData: null,
                bubblesLayer: null,

                init() {
                    this.buildAll(this.payload);
                    this.initMap();

                    document.addEventListener('livewire:initialized', () => {
                        Livewire.hook('commit', ({ succeed }) => {
                            succeed(() => {
                                setTimeout(() => {
                                    const el = document.getElementById('chart-data');
                                    if (el && el.dataset.payload) {
                                        const newData = JSON.parse(el.dataset.payload);
                                        if (JSON.stringify(this.payload) !== JSON.stringify(newData)) {
                                            this.rebuild(newData);
                                        }
                                    }
                                }, 50);
                            });
                        });
                    });
                },

                rebuild(newPayload) {
                    this.payload = newPayload;
                    this.destroyAll();
                    this.buildAll(this.payload);
                    this.renderTerritoryBoundaryLayer();
                    this.renderTerritoryMap();
                },

                destroyAll() {
                    Object.values(this.charts).forEach(ch => {
                        if (ch) ch.destroy();
                    });
                    this.charts.evolution = this.charts.status = this.charts.eventType = this.charts.organisation = this.charts.province = null;
                },

                buildAll(p) {
                    const ctxEvo = document.getElementById('chartEvolution');
                    const ctxStatus = document.getElementById('chartStatus');
                    const ctxEventType = document.getElementById('chartEventType');
                    const ctxOrg = document.getElementById('chartOrganisation');
                    const ctxProv = document.getElementById('chartProvince');

                    if (!ctxEvo || !ctxStatus || !ctxEventType || !ctxOrg || !ctxProv) return;

                    const gridColor = 'rgba(17, 24, 39, 0.08)';
                    const tickColor = 'rgba(17, 24, 39, 0.55)';
                    const borderColor = 'rgba(17, 24, 39, 0.10)';
                    const commonTooltip = {
                        enabled: true,
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 6,
                        displayColors: true,
                    };
                    const commonScales = {
                        x: {
                            grid: { color: gridColor },
                            ticks: { color: tickColor, maxTicksLimit: 8 },
                            border: { color: borderColor },
                        },
                        y: {
                            grid: { color: gridColor },
                            ticks: { color: tickColor, precision: 0 },
                            border: { color: borderColor },
                            beginAtZero: true,
                        },
                    };

                    this.charts.evolution = new Chart(ctxEvo, {
                        type: 'line',
                        data: {
                            labels: p.evolution.labels,
                            datasets: [{
                                label: 'Alertes',
                                data: p.evolution.data,
                                borderColor: '#2563eb',
                                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                                fill: true,
                                tension: 0.25,
                                pointRadius: 2,
                                pointHoverRadius: 5,
                            }],
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: commonTooltip,
                                datalabels: {
                                    color: tickColor,
                                    align: 'top',
                                    anchor: 'end',
                                    formatter: v => v ? v : '',
                                    font: { size: 10, weight: '600' },
                                },
                            },
                            scales: commonScales,
                        },
                        plugins: [ChartDataLabels],
                    });

                    const statusTotal = (p.byStatus.data || []).reduce((a, b) => a + b, 0);
                    this.charts.status = new Chart(ctxStatus, {
                        type: 'doughnut',
                        data: {
                            labels: p.byStatus.labels,
                            datasets: [{
                                data: p.byStatus.data,
                                backgroundColor: ['#16a34a', '#e5e7eb'],
                                borderColor: '#fff',
                                borderWidth: 2,
                                hoverOffset: 6,
                                cutout: '68%',
                            }],
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { color: tickColor, boxWidth: 10, boxHeight: 10, usePointStyle: true },
                                },
                                tooltip: {
                                    ...commonTooltip,
                                    callbacks: {
                                        label: ctx => {
                                            const v = ctx.raw || 0;
                                            const pct = statusTotal ? Math.round((v / statusTotal) * 100) : 0;
                                            return ` ${ctx.label}: ${v} (${pct}%)`;
                                        },
                                    },
                                },
                                datalabels: {
                                    color: ctx => ctx.dataIndex === 0 ? '#fff' : '#374151',
                                    font: { weight: '700', size: 11 },
                                    formatter: value => {
                                        if (!statusTotal) return '';
                                        const pct = Math.round((value / statusTotal) * 100);
                                        return pct >= 6 ? `${pct}%` : '';
                                    },
                                },
                            },
                        },
                        plugins: [ChartDataLabels],
                    });

                    this.charts.eventType = new Chart(ctxEventType, {
                        type: 'bar',
                        data: {
                            labels: p.byEventType.labels,
                            datasets: [{
                                label: 'Alertes',
                                data: p.byEventType.data,
                                backgroundColor: 'rgba(239, 68, 68, 0.85)',
                                borderRadius: 4,
                                maxBarThickness: 24,
                            }],
                        },
                        options: {
                            responsive: true,
                            indexAxis: 'y',
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: { ...commonTooltip, callbacks: { label: ctx => ` Alertes: ${ctx.raw ?? 0}` } },
                                datalabels: {
                                    color: tickColor,
                                    anchor: 'end',
                                    align: 'end',
                                    offset: 4,
                                    font: { size: 10, weight: '700' },
                                    formatter: v => v ? v : '',
                                },
                            },
                            scales: {
                                x: { grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 }, border: { color: borderColor }, beginAtZero: true },
                                y: { grid: { display: false }, ticks: { color: tickColor, font: { size: 10 } }, border: { color: borderColor } },
                            },
                        },
                        plugins: [ChartDataLabels],
                    });

                    this.charts.organisation = new Chart(ctxOrg, {
                        type: 'bar',
                        data: {
                            labels: p.byOrganisation.labels,
                            datasets: [
                                {
                                    label: 'Validées',
                                    data: p.byOrganisation.validated,
                                    backgroundColor: 'rgba(22, 163, 74, 0.82)',
                                    borderRadius: 4,
                                    maxBarThickness: 26,
                                },
                                {
                                    label: 'En attente',
                                    data: p.byOrganisation.pending,
                                    backgroundColor: 'rgba(245, 158, 11, 0.82)',
                                    borderRadius: 4,
                                    maxBarThickness: 26,
                                },
                            ],
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: { color: tickColor, boxWidth: 10, boxHeight: 10, usePointStyle: true },
                                },
                                tooltip: commonTooltip,
                                datalabels: {
                                    color: tickColor,
                                    anchor: 'end',
                                    align: 'end',
                                    formatter: v => v ? v : '',
                                    font: { size: 10, weight: '700' },
                                },
                            },
                            scales: {
                                x: { stacked: true, grid: { display: false }, ticks: { color: tickColor }, border: { color: borderColor } },
                                y: { stacked: true, grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 }, border: { color: borderColor }, beginAtZero: true },
                            },
                        },
                        plugins: [ChartDataLabels],
                    });

                    this.charts.province = new Chart(ctxProv, {
                        type: 'bar',
                        data: {
                            labels: p.byProvince.labels,
                            datasets: [{
                                label: 'Alertes',
                                data: p.byProvince.data,
                                backgroundColor: 'rgba(37, 99, 235, 0.85)',
                                borderRadius: 10,
                                maxBarThickness: 38,
                            }],
                        },
                        options: {
                            responsive: true,
                            interaction: { mode: 'index', intersect: false },
                            plugins: {
                                legend: { display: false },
                                tooltip: { ...commonTooltip, callbacks: { label: ctx => `Alertes: ${ctx.raw ?? 0}` } },
                                datalabels: {
                                    color: tickColor,
                                    anchor: 'end',
                                    align: 'end',
                                    offset: 2,
                                    font: { size: 10, weight: '700' },
                                    formatter: v => v ? v : '',
                                },
                            },
                            scales: {
                                x: { grid: { display: false }, ticks: { color: tickColor }, border: { color: borderColor } },
                                y: { grid: { color: gridColor }, ticks: { color: tickColor, precision: 0 }, border: { color: borderColor }, beginAtZero: true },
                            },
                        },
                        plugins: [ChartDataLabels],
                    });
                },

                initMap() {
                    const mapContainer = document.getElementById('mapTerritories');
                    if (!mapContainer) return;

                    if (this.map) {
                        this.map.remove();
                    }

                    this.map = L.map('mapTerritories', {
                        zoomControl: true,
                        scrollWheelZoom: true,
                    }).setView([-4.0383, 21.7587], 5);

                    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a>',
                        subdomains: 'abcd',
                        maxZoom: 20,
                    }).addTo(this.map);

                    this.bubblesLayer = L.layerGroup().addTo(this.map);

                    fetch('/cod_admin2_em.geojson')
                        .then(response => response.json())
                        .then(data => {
                            this.territoryGeoJsonData = data;
                            this.renderTerritoryBoundaryLayer();
                            this.renderTerritoryMap();
                        })
                        .catch(error => console.warn('Impossible de charger les limites des territoires.', error));

                    this.renderTerritoryMap();
                    setTimeout(() => this.map.invalidateSize(), 100);
                },

                getTerritoryColor(count, max) {
                    if (!count) return '#cbd5e1';
                    const ratio = max > 0 ? count / max : 0;
                    if (ratio >= 0.66) return '#dc2626';
                    if (ratio >= 0.33) return '#f59e0b';
                    return '#2563eb';
                },

                getTerritoryRadius(count, max) {
                    if (!count) return 8;
                    const ratio = max > 0 ? Math.sqrt(count / max) : 0;
                    return Math.max(10, Math.round(10 + (ratio * 28)));
                },

                renderTerritoryBoundaryLayer() {
                    if (!this.map || !this.territoryGeoJsonData) return;

                    if (this.territoryBoundaryLayer) {
                        this.map.removeLayer(this.territoryBoundaryLayer);
                    }

                    const territoryMap = this.payload.territoryMap || {};
                    const pointCodes = new Set((territoryMap.points || []).map(territory => territory.code_territoire));
                    const scope = this.payload.scope || {};
                    const features = (this.territoryGeoJsonData.features || []).filter(feature => {
                        const properties = feature.properties || {};
                        if (scope.code_territoire) return properties.adm2_pcode === scope.code_territoire;
                        if (scope.code_province) return properties.adm1_pcode === scope.code_province;
                        return true;
                    });

                    this.territoryBoundaryLayer = L.geoJSON({
                        ...this.territoryGeoJsonData,
                        features,
                    }, {
                        pane: 'overlayPane',
                        style: feature => {
                            const code = feature.properties?.adm2_pcode;
                            const isHighlighted = pointCodes.has(code);
                            return {
                                color: isHighlighted ? '#60a5fa' : '#cbd5e1',
                                weight: isHighlighted ? 3.5 : 1,
                                opacity: isHighlighted ? 0.95 : 0.45,
                                fillColor: isHighlighted ? '#dbeafe' : '#ffffff',
                                fillOpacity: isHighlighted ? 0.16 : 0.02,
                                dashArray: isHighlighted ? null : '4 4',
                            };
                        },
                        onEachFeature: (feature, layer) => {
                            const code = feature.properties?.adm2_pcode;
                            layer.on({
                                mouseover: event => {
                                    event.target.setStyle({
                                        color: pointCodes.has(code) ? '#0284c7' : '#93c5fd',
                                        weight: pointCodes.has(code) ? 5 : 2,
                                        opacity: 1,
                                        fillOpacity: pointCodes.has(code) ? 0.22 : 0.06,
                                    });
                                },
                                mouseout: event => this.territoryBoundaryLayer.resetStyle(event.target),
                            });
                        },
                    }).addTo(this.map);

                    if (this.bubblesLayer) {
                        this.bubblesLayer.eachLayer(layer => layer.bringToFront?.());
                    }
                },

                renderTerritoryMap() {
                    if (!this.map || !this.bubblesLayer) return;

                    const territoryMap = this.payload.territoryMap || {};
                    const points = territoryMap.points || [];
                    const max = territoryMap.max || 0;
                    const emptyEl = document.getElementById('territoryMapEmpty');

                    this.bubblesLayer.clearLayers();

                    if (emptyEl) {
                        emptyEl.classList.toggle('hidden', points.length > 0);
                    }

                    if (!points.length) {
                        this.map.setView([-4.0383, 21.7587], 5);
                        return;
                    }

                    const bounds = [];
                    points.forEach(territory => {
                        const lat = Number(territory.latitude);
                        const lon = Number(territory.longitude);
                        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

                        const count = Number(territory.total || 0);
                        const marker = L.circleMarker([lat, lon], {
                            radius: this.getTerritoryRadius(count, max),
                            color: '#93c5fd',
                            weight: 4,
                            fillColor: this.getTerritoryColor(count, max),
                            fillOpacity: 0.72,
                            opacity: 1,
                        }).addTo(this.bubblesLayer);

                        marker.on({
                            mouseover: event => {
                                event.target.setStyle({ fillOpacity: 0.95, color: '#38bdf8', weight: 6 });
                                event.target.bringToFront();
                            },
                            mouseout: event => {
                                event.target.setStyle({ color: '#93c5fd', fillOpacity: 0.72, weight: 4 });
                            },
                        });

                        marker.bindTooltip(this.territoryTooltip(territory), {
                            sticky: true,
                            className: 'territory-map-tooltip',
                            opacity: 1,
                        });

                        bounds.push([lat, lon]);
                    });

                    if (bounds.length === 1) {
                        this.map.setView(bounds[0], 8);
                    } else if (bounds.length > 1) {
                        this.map.fitBounds(bounds, { padding: [48, 48], maxZoom: 8 });
                    }
                },

                territoryTooltip(territory) {
                    const events = (territory.events || [])
                        .map(event => `
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-gray-600">${this.escapeHtml(event.label)}</span>
                                <span class="font-semibold text-gray-900">${event.total}</span>
                            </div>
                        `)
                        .join('');

                    return `
                        <div class="min-w-[220px] p-2">
                            <div class="font-bold text-gray-950">${this.escapeHtml(territory.nom_territoire)}</div>
                            <div class="mt-1 text-xs text-gray-500">${this.escapeHtml(territory.nom_province || '')}</div>
                            <div class="mt-3 flex items-baseline justify-between border-t border-gray-100 pt-2">
                                <span class="text-xs uppercase tracking-wide text-gray-500">Total incidents</span>
                                <span class="text-xl font-black text-gray-950">${territory.total}</span>
                            </div>
                            <div class="mt-2 space-y-1 border-t border-gray-100 pt-2">
                                ${events || '<div class="text-gray-500">Aucun type renseigné</div>'}
                            </div>
                        </div>
                    `;
                },

                escapeHtml(value) {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;')
                        .replace(/'/g, '&#039;');
                },
            };
        }
    </script>
</div>
