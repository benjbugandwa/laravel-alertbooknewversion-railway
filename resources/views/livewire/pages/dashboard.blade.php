<div>
    {{-- Spinner de chargement --}}
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
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-2xl font-bold">Dashboard</div>
            <div class="text-sm text-gray-600">
                @if ($chart['scope']['isSuper'])
                    Vue globale (toutes provinces).
                @else
                    Vue province :
                    <b>{{ $chart['scope']['nom_province'] ?? '-' }}</b>
                @endif
            </div>
        </div>

        <div class="flex items-center gap-2">
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

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <x-ui-card>
            <div class="text-sm text-gray-600">Utilisateurs actifs</div>
            <div class="mt-2 text-3xl font-bold">{{ $chart['users']['active'] }}</div>
        </x-ui-card>

        <x-ui-card>
            <div class="text-sm text-gray-600">En attente d’activation</div>
            <div class="mt-2 text-3xl font-bold">{{ $chart['users']['pending'] }}</div>
            <div class="mt-2 text-xs text-gray-500">
                (is_active = false)
            </div>
        </x-ui-card>

        <x-ui-card>
            <div class="text-sm text-gray-600">Alertes (période)</div>
            <div class="mt-2 text-3xl font-bold">
                {{ collect($chart['evolution']['data'])->sum() }}
            </div>
            <div class="mt-2 text-xs text-gray-500">
                Derniers {{ $this->days }} jours (date_incident)
            </div>
        </x-ui-card>

        <x-ui-card>
            <div class="text-sm text-gray-600">Taux de validation</div>
            <div class="mt-2 text-3xl font-bold">
                {{ number_format($chart['byStatus']['validatedPercentage'] ?? 0, 1) }}%
            </div>
            <div class="mt-2 text-xs text-gray-500">
                {{ $chart['byStatus']['validated'] ?? 0 }} validées, {{ $chart['byStatus']['pending'] ?? 0 }} en attente
            </div>
        </x-ui-card>
    </div>

    {{-- SLA operational panel --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <x-ui-card>
            <div class="text-sm text-gray-600">Incidents en retard SLA</div>
            <div class="mt-2 text-3xl font-bold text-red-700">{{ $slaSummary['total_overdue_incidents'] ?? 0 }}</div>
            <div class="mt-3 grid grid-cols-3 gap-2 text-xs">
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

        <x-ui-card class="lg:col-span-2">
            <div class="flex items-center justify-between gap-3 mb-3">
                <div class="font-semibold">À traiter en priorité</div>
                @if($chart['scope']['code_province'])
                    <a href="{{ route('briefings.province', ['province' => $chart['scope']['code_province']]) }}"
                        class="text-xs font-semibold text-onu hover:underline">
                        Briefing province PDF
                    </a>
                @else
                    <span class="text-xs text-gray-400">Sélectionner une province pour le briefing PDF</span>
                @endif
            </div>

            <div class="divide-y divide-gray-100">
                @forelse($slaOverdue as $lateIncident)
                    <div class="py-3 flex items-start justify-between gap-4">
                        <div>
                            <a href="{{ route('incidents.show', $lateIncident->id) }}" class="font-semibold text-gray-900 hover:underline">
                                {{ $lateIncident->code_incident }}
                            </a>
                            <div class="text-xs text-gray-500">
                                {{ $lateIncident->localite ?? '-' }} · {{ $lateIncident->zoneSante?->nom_zonesante ?? '-' }}
                            </div>
                        </div>
                        <div class="flex flex-wrap justify-end gap-1.5">
                            @foreach($lateIncident->sla['items'] as $item)
                                @if($item['is_overdue'])
                                    <span class="rounded-full bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700 border border-red-100">
                                        {{ $item['label'] }} +{{ $item['hours_late'] }}h
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-sm text-gray-500">Aucun retard SLA dans le périmètre affiché.</div>
                @endforelse
            </div>
        </x-ui-card>
    </div>

    {{-- Payload caché pour mise à jour JS --}}
    <div id="chart-data" class="hidden" data-payload="{{ json_encode($chart) }}"></div>

    {{-- Charts --}}
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
                <span class="font-semibold text-gray-900">
                    {{ number_format($chart['byStatus']['validatedPercentage'] ?? 0, 1) }}%
                </span>
                validées ({{ $chart['byStatus']['validated'] ?? 0 }} validées, {{ $chart['byStatus']['pending'] ?? 0 }} en attente)
            </div>
            <div class="mt-3">
                <canvas id="chartStatus" height="200"></canvas>
            </div>
        </x-ui-card>

        <x-ui-card>
            <div class="font-semibold">Alertes par type d'événement</div>
            <div class="mt-3">
                <canvas id="chartEventType" height="200"></canvas>
            </div>
        </x-ui-card>

        <x-ui-card class="lg:col-span-2">
            <div class="font-semibold">Alertes par province</div>
            <div class="mt-3">
                <canvas id="chartProvince" height="90">

                </canvas>
            </div>

            {{-- Tableau Incidents par province --}}
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
                                <td class="px-4 py-2 text-right font-semibold text-gray-900">
                                    {{ $row['total'] }}
                                </td>
                                <td class="px-4 py-2 text-right text-gray-700">
                                    {{ number_format($row['pct'], 1) }}%
                                </td>
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
                                <td class="px-4 py-2 text-right font-semibold">{{ $chart['byProvince']['sum'] ?? 0 }}
                                </td>
                                <td class="px-4 py-2 text-right font-medium">100%</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>


            <div class="mt-2 text-xs text-gray-500">
                Top 15 provinces (ou votre province si vous n’êtes pas superadmin).
            </div>
        </x-ui-card>

        {{-- Carte Territoires --}}
        <x-ui-card class="lg:col-span-2 relative overflow-hidden">
            <div class="flex items-center justify-between mb-4">
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
                    <div class="font-semibold text-gray-900">Aucune donnee cartographique</div>
                    <div class="mt-1 text-sm text-gray-500">Aucun incident valide avec territoire coordonne sur la periode affichee.</div>
                </div>
                
                <!-- Legend Overlay -->
                <div class="absolute bottom-8 left-8 z-[1000] bg-white/95 backdrop-blur-md border border-gray-200 rounded-xl p-4 shadow-xl min-w-[200px]">
                    <div class="text-[10px] text-gray-500 uppercase font-black tracking-widest mb-3 border-b pb-2">Intensité des incidents</div>
                    <div class="space-y-2">
                        <div class="flex items-center gap-3">
                            <span class="w-4 h-4 rounded-full border-2 shadow-sm" style="background-color: #dc2626; border-color: #93c5fd"></span>
                            <span class="text-xs font-medium text-gray-700">Volume eleve</span>
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
                            <span class="text-xs font-medium text-gray-500">Aucun point si aucune coordonnee</span>
                        </div>
                    </div>
                </div>
            </div>
        </x-ui-card>
    </div>

    {{-- Chart.js, Leaflet + Alpine helper --}}
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
                    province: null,
                },
                map: null,
                bubblesLayer: null,

                init() {
                    this.buildAll(this.payload);
                    this.initMap();

                    // Livewire 3: re-render charts après mise à jour du composant
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
                    this.renderTerritoryMap();
                },

                destroyAll() {
                    Object.values(this.charts).forEach(ch => {
                        if (ch) ch.destroy();
                    });
                    this.charts.evolution = this.charts.status = this.charts.eventType = this.charts.province = null;
                },



                buildAll(p) {
                    const ctxEvo = document.getElementById('chartEvolution');
                    const ctxStatus = document.getElementById('chartStatus');
                    const ctxEventType = document.getElementById('chartEventType');
                    const ctxProv = document.getElementById('chartProvince');

                    if (!ctxEvo || !ctxStatus || !ctxEventType || !ctxProv) return;

                    // --- Style global (grilles claires + textes doux) ---
                    const gridColor = 'rgba(17, 24, 39, 0.08)'; // gris très léger
                    const tickColor = 'rgba(17, 24, 39, 0.55)'; // gris doux
                    const borderColor = 'rgba(17, 24, 39, 0.10)';

                    // Palette douce (pas piquante)
                    const palette = [
                        '#2563EB', // bleu
                        '#10B981', // vert
                        '#F59E0B', // ambre
                        '#EF4444', // rouge doux
                        '#8B5CF6', // violet
                        '#06B6D4', // cyan
                        '#64748B', // slate
                        '#84CC16', // lime doux
                    ];

                    const commonScales = {
                        x: {
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: tickColor,
                                maxTicksLimit: 8
                            },
                            border: {
                                color: borderColor
                            },
                        },
                        y: {
                            grid: {
                                color: gridColor
                            },
                            ticks: {
                                color: tickColor,
                                precision: 0
                            },
                            border: {
                                color: borderColor
                            },
                            beginAtZero: true,
                        },
                    };

                    const commonTooltip = {
                        enabled: true,
                        backgroundColor: 'rgba(17, 24, 39, 0.95)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        padding: 12,
                        cornerRadius: 6,
                        displayColors: true,
                    };

                    // Evolution (line)
                    this.charts.evolution = new Chart(ctxEvo, {
                        type: 'line',
                        data: {
                            labels: p.evolution.labels,
                            datasets: [{
                                label: 'Incidents',
                                data: p.evolution.data,
                                borderColor: palette[0],
                                backgroundColor: 'rgba(37, 99, 235, 0.12)',
                                fill: true,
                                tension: 0.25,
                                pointRadius: 2,
                                pointHoverRadius: 5,
                            }],
                        },
                        options: {
                            responsive: true,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: commonTooltip,
                                // Afficher la valeur sur les points (discret)
                                datalabels: {
                                    color: tickColor,
                                    align: 'top',
                                    anchor: 'end',
                                    formatter: (v) => (v ? v : ''),
                                    font: {
                                        size: 10,
                                        weight: '600'
                                    },
                                }
                            },
                            scales: commonScales,
                        },
                        plugins: [ChartDataLabels],
                    });

                    // Status (doughnut) — afficher % + tooltip
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
                                cutout: '68%', // donut comme ton exemple
                            }],
                        },
                        options: {
                            responsive: true,
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        color: tickColor,
                                        boxWidth: 10,
                                        boxHeight: 10,
                                        usePointStyle: true
                                    }
                                },
                                tooltip: {
                                    ...commonTooltip,
                                    callbacks: {
                                        label: (ctx) => {
                                            const v = ctx.raw || 0;
                                            const pct = statusTotal ? Math.round((v / statusTotal) * 100) : 0;
                                            return ` ${ctx.label}: ${v} (${pct}%)`;
                                        }
                                    }
                                },
                                datalabels: {
                                    color: (ctx) => ctx.dataIndex === 0 ? '#fff' : '#374151',
                                    font: {
                                        weight: '700',
                                        size: 11
                                    },
                                    formatter: (value) => {
                                        if (!statusTotal) return '';
                                        const pct = Math.round((value / statusTotal) * 100);
                                        return pct >= 6 ? `${pct}%` :
                                            ''; // n’affiche pas les petits % (lisibilité)
                                    },
                                }
                            },
                        },
                        plugins: [ChartDataLabels],
                    });

                    // EventType (bar horizontal)
                    this.charts.eventType = new Chart(ctxEventType, {
                        type: 'bar',
                        data: {
                            labels: p.byEventType.labels,
                            datasets: [{
                                label: 'Incidents',
                                data: p.byEventType.data,
                                backgroundColor: 'rgba(239, 68, 68, 0.85)', // rouge doux
                                borderRadius: 4,
                                maxBarThickness: 24,
                            }],
                        },
                        options: {
                            responsive: true,
                            indexAxis: 'y', // Barres horizontales pour lire les textes longs
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    ...commonTooltip,
                                    callbacks: {
                                        label: (ctx) => ` Incidents: ${ctx.raw ?? 0}`
                                    }
                                },
                                datalabels: {
                                    color: tickColor,
                                    anchor: 'end',
                                    align: 'end',
                                    offset: 4,
                                    font: { size: 10, weight: '700' },
                                    formatter: (v) => (v ? v : ''),
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: gridColor },
                                    ticks: { color: tickColor, precision: 0 },
                                    border: { color: borderColor },
                                    beginAtZero: true,
                                },
                                y: {
                                    grid: { display: false },
                                    ticks: { color: tickColor, font: { size: 10 } },
                                    border: { color: borderColor },
                                }
                            },
                        },
                        plugins: [ChartDataLabels],
                    });

                    // Province (bar) — valeurs au-dessus des barres + tooltip
                    this.charts.province = new Chart(ctxProv, {
                        type: 'bar',
                        data: {
                            labels: p.byProvince.labels,
                            datasets: [{
                                label: 'Incidents',
                                data: p.byProvince.data,
                                backgroundColor: 'rgba(37, 99, 235, 0.85)',
                                borderRadius: 10,
                                maxBarThickness: 38,
                            }],
                        },
                        options: {
                            responsive: true,
                            interaction: {
                                mode: 'index',
                                intersect: false
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    ...commonTooltip,
                                    callbacks: {
                                        label: (ctx) => `Incidents: ${ctx.raw ?? 0}`
                                    }
                                },
                                datalabels: {
                                    color: tickColor,
                                    anchor: 'end',
                                    align: 'end',
                                    offset: 2,
                                    font: {
                                        size: 10,
                                        weight: '700'
                                    },
                                    formatter: (v) => (v ? v : ''),
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    }, // comme l’exemple (bar chart sans grille verticale)
                                    ticks: {
                                        color: tickColor
                                    },
                                    border: {
                                        color: borderColor
                                    },
                                },
                                y: {
                                    grid: {
                                        color: gridColor
                                    },
                                    ticks: {
                                        color: tickColor,
                                        precision: 0
                                    },
                                    border: {
                                        color: borderColor
                                    },
                                    beginAtZero: true,
                                }
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

                    points.forEach((territory) => {
                        const lat = Number(territory.latitude);
                        const lon = Number(territory.longitude);

                        if (!Number.isFinite(lat) || !Number.isFinite(lon)) return;

                        const count = Number(territory.total || 0);
                        const color = this.getTerritoryColor(count, max);
                        const marker = L.circleMarker([lat, lon], {
                            radius: this.getTerritoryRadius(count, max),
                            color: '#93c5fd',
                            weight: 4,
                            fillColor: color,
                            fillOpacity: 0.72,
                            opacity: 1,
                        }).addTo(this.bubblesLayer);

                        marker.on({
                            mouseover: (event) => {
                                event.target.setStyle({
                                    fillOpacity: 0.95,
                                    color: '#38bdf8',
                                    weight: 6,
                                });
                                event.target.bringToFront();
                            },
                            mouseout: (event) => {
                                event.target.setStyle({
                                    color: '#93c5fd',
                                    fillOpacity: 0.72,
                                    weight: 4,
                                });
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
                                ${events || '<div class="text-gray-500">Aucun type renseigne</div>'}
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
</div>
