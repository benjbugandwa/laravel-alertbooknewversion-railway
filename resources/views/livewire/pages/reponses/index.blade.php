<div class="space-y-6 relative" x-data>
    {{-- Loader --}}
    <div wire:loading
        class="absolute inset-0 z-50 flex items-center justify-center bg-white/50 backdrop-blur-sm rounded-2xl">
        <div class="flex flex-col items-center gap-2">
            <svg class="animate-spin h-8 w-8 text-onu" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
            </svg>
            <span class="text-sm font-medium text-gray-700">Chargement...</span>
        </div>
    </div>

    {{-- Incident Selector & Details --}}
    <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
        <div class="bg-gray-50 border-b px-6 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('incidents.index') }}" class="text-gray-500 hover:text-gray-900 bg-white border border-gray-200 rounded-lg p-2 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-xl font-bold text-gray-900">
                        @if($incident)
                            Incident : {{ $incident->code_incident }}
                        @else
                            Aucun Incident Sélectionné
                        @endif
                    </h1>
                </div>
            </div>
            
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-gray-700">Alerte :</span>
                <select wire:model.live="selectedIncidentId" class="rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                    <option value="">-- Choisir une alerte --</option>
                    @foreach($all_incidents as $inc)
                        <option value="{{ $inc['id'] }}">{{ $inc['code_incident'] }} - {{ $inc['localite'] }} ({{ \Carbon\Carbon::parse($inc['date_incident'])->format('d/m/Y') }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if($incident)
            <div class="p-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Date Incident</div>
                    <div class="font-medium mt-1">{{ optional($incident->date_incident)->format('d/m/Y') ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Type d'événement</div>
                    <div class="font-medium mt-1">{{ $incident->evenement?->nom_evenement ?? '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Statut de validation</div>
                    <div class="font-medium mt-1">
                        @php
                            $statusClass = match($incident->statut_incident) {
                                'Validé' => 'bg-green-50 text-green-700 border-green-200',
                                'Cloturée' => 'bg-gray-100 text-gray-700 border-gray-300',
                                'Archivé' => 'bg-orange-50 text-orange-700 border-orange-200',
                                default => 'bg-yellow-50 text-yellow-700 border-yellow-200'
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full border {{ $statusClass }}">
                            {{ $incident->statut_incident ?? '—' }}
                        </span>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Localisation (Incident)</div>
                    <div class="font-medium mt-1">{{ $incident->province?->nom_province ?? '—' }} / {{ $incident->localite ?? '—' }}</div>
                </div>
            </div>
        @endif
    </div>

    @if($incident)
        {{-- Verification constraint notice --}}
        @if ($incident->statut_incident !== 'Validé' && $incident->statut_incident !== 'Cloturée')
            <div class="p-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl flex items-start gap-3">
                <svg class="w-5 h-5 text-yellow-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
                <div>
                    <h3 class="font-bold text-sm">Action Requise</h3>
                    <p class="text-xs text-yellow-700 mt-1">L'incident sélectionné n'est pas encore validé ou est archivé. Seuls les incidents validés ou clôturés peuvent faire l'objet d'une réponse.</p>
                </div>
            </div>
        @endif

        {{-- Section Title & Action Buttons --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold">Réponses apportées à l'incident</h2>
                <p class="text-sm text-gray-600">Enregistrez et suivez les réponses humanitaires, militaires ou mixtes fournies.</p>
            </div>

            <div class="flex items-center gap-2 self-end sm:self-auto">
                <button type="button" wire:click="openExport"
                    class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 text-sm font-medium transition shadow-sm text-gray-700">
                    📥 Exporter Réponses Excel
                </button>
                @if ($this->canWrite() && ($incident->statut_incident === 'Validé' || $incident->statut_incident === 'Cloturée'))
                    <x-ui-button wire:click="openCreate">
                        + Nouvelle réponse
                    </x-ui-button>
                @endif
            </div>
        </div>

        {{-- Filters & Search --}}
        <div class="bg-white border rounded-xl p-4 shadow-sm grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="text-xs font-semibold text-gray-500 uppercase">Filtrer par Date</label>
                <input type="date" wire:model.live="f_date_reponse" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu bg-white">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-gray-500 uppercase">Filtrer par Acteur / Organisation</label>
                <input type="text" placeholder="Rechercher une organisation..." wire:model.live.debounce.300ms="f_fournie_par" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu bg-white">
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-gray-500 uppercase">Filtrer par Type de Réponse</label>
                <select wire:model.live="f_type_reponse" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu bg-white">
                    <option value="">Tous les types</option>
                    @foreach($types_options as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto bg-white border border-gray-200 rounded-2xl shadow-sm">
            <table class="min-w-full text-sm border-collapse">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3 border-b text-left font-semibold">N° Réponse</th>
                        <th class="px-4 py-3 border-b text-left font-semibold">Date réponse</th>
                        <th class="px-4 py-3 border-b text-left font-semibold">Fournie par</th>
                        <th class="px-4 py-3 border-b text-left font-semibold">Type</th>
                        <th class="px-4 py-3 border-b text-left font-semibold">Secteurs couverts</th>
                        <th class="px-4 py-3 border-b text-center font-semibold">Ménages</th>
                        <th class="px-4 py-3 border-b text-center font-semibold">Individus</th>
                        <th class="px-4 py-3 border-b text-center font-semibold">Rapport</th>
                        <th class="px-4 py-3 border-b text-left font-semibold">Enregistré le</th>
                        @if ($this->canWrite())
                            <th class="px-4 py-3 border-b text-center font-semibold">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y text-gray-700">
                    @forelse($reponses as $rep)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-4 py-3 font-semibold text-gray-900 whitespace-nowrap">
                                {{ $rep->num_reponse }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ optional($rep->date_reponse)->format('d/m/Y') }}
                            </td>
                            <td class="px-4 py-3 font-medium">
                                {{ $rep->fournie_par }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $typeColor = match($rep->type_reponse) {
                                        'Humanitaire' => 'bg-blue-50 text-blue-800 border-blue-200',
                                        'Militaire' => 'bg-red-50 text-red-800 border-red-200',
                                        'Mixte' => 'bg-purple-50 text-purple-800 border-purple-200',
                                        default => 'bg-gray-50 text-gray-800 border-gray-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full border {{ $typeColor }}">
                                    {{ $rep->type_reponse }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1 max-w-xs">
                                    @if(is_array($rep->secteurs_couverts))
                                        @foreach($rep->secteurs_couverts as $secteur)
                                            <span class="inline-flex items-center px-1.5 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-800 border border-emerald-200 rounded">
                                                {{ $secteur }}
                                            </span>
                                        @endforeach
                                    @else
                                        {{ $rep->secteurs_couverts }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-center font-semibold">
                                {{ $rep->nbre_menages_couverts ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center font-semibold">
                                {{ $rep->nbre_individus_couverts ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($rep->rapport)
                                    <button type="button" wire:click="downloadRapport({{ $rep->id }})" class="text-onu hover:underline font-medium inline-flex items-center gap-1">
                                        📄 Télécharger
                                    </button>
                                @else
                                    <span class="text-gray-400 text-xs">Aucun</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">
                                {{ optional($rep->create_at)->format('d/m/Y') }}<br>
                                <span class="text-[10px] text-gray-400">Par {{ $rep->creator?->name ?? '—' }}</span>
                            </td>
                            @if ($this->canWrite())
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-blue-600"
                                            wire:click="openEdit({{ $rep->id }})" title="Modifier">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button type="button"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-gray-200 hover:bg-red-50 text-red-600"
                                            wire:confirm="Êtes-vous sûr de vouloir supprimer cette réponse ?"
                                            wire:click="delete({{ $rep->id }})" title="Supprimer">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center text-gray-500" colspan="10">
                                Aucune réponse enregistrée pour cet incident.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($reponses->isNotEmpty())
            <div class="mt-4">
                {{ $reponses->links() }}
            </div>
        @endif

        {{-- Modal Create/Edit --}}
        @if ($showModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
                x-on:keydown.escape.window="$wire.set('showModal', false)">
                <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>

                <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-xl border max-h-[90vh] flex flex-col">
                    <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
                        <div class="font-semibold text-lg text-gray-900">{{ $editing ? 'Modifier la réponse aux incidents' : 'Enregistrer une réponse aux incidents' }}</div>
                        <button type="button" class="opacity-60 hover:opacity-100 text-xl font-bold"
                            wire:click="$set('showModal', false)">✕</button>
                    </div>

                    <div class="p-6 space-y-6 overflow-y-auto flex-1">
                        {{-- 1. Date response & Type response --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Date de la réponse *</label>
                                <input type="date" max="{{ now()->toDateString() }}" wire:model.defer="form.date_reponse"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                                @error('form.date_reponse') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Type de réponse *</label>
                                <select wire:model.defer="form.type_reponse"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                                    <option value="">-- Choisir un type --</option>
                                    @foreach ($types_options as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                                @error('form.type_reponse') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- 2. Provided by (fournie_par editable dropdown) --}}
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Fournie par (Organisation) *</label>
                            <input type="text" list="orgs-list" wire:model.defer="form.fournie_par" placeholder="Saisir ou choisir une organisation..."
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                            <datalist id="orgs-list">
                                @foreach($organisations as $org)
                                    <option value="{{ $org['org_name'] }}">{{ $org['org_name'] }} @if(!empty($org['org_sigle'])) ({{ $org['org_sigle'] }}) @endif</option>
                                @endforeach
                            </datalist>
                            @error('form.fournie_par') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                        </div>

                        {{-- 3. Sectors covered (multi-select) --}}
                        <div class="space-y-2">
                            <label class="text-sm font-medium text-gray-700 block">Secteurs couverts *</label>
                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 border rounded-xl p-4 bg-gray-50/55">
                                @foreach ($secteurs_options as $sec)
                                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                        <input type="checkbox" wire:model.defer="form.secteurs_couverts" value="{{ $sec }}"
                                            class="rounded border-gray-300 text-onu focus:ring-onu">
                                        <span>{{ $sec }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('form.secteurs_couverts') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                        </div>

                        {{-- 4. Household/individuals count --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Nbre de ménages couverts (supérieur à zéro)</label>
                                <input type="number" min="1" wire:model.defer="form.nbre_menages_couverts"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                @error('form.nbre_menages_couverts') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Nbre d'individus couverts (supérieur à zéro)</label>
                                <input type="number" min="1" wire:model.defer="form.nbre_individus_couverts"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                @error('form.nbre_individus_couverts') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- 5. Long texts (gaps and impact) --}}
                        <div class="space-y-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Impact de la réponse (Optionnel)</label>
                                <textarea wire:model.defer="form.impact_reponse" rows="3"
                                    placeholder="Décrire l'impact immédiat ou à long terme de la réponse..."
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu"></textarea>
                                @error('form.impact_reponse') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Observation / Gaps de la réponse (Optionnel)</label>
                                <textarea wire:model.defer="form.observation_gap" rows="3"
                                    placeholder="Décrire les besoins non couverts ou gaps identifiés..."
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu"></textarea>
                                @error('form.observation_gap') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- 6. File upload (rapport) --}}
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700 block">Rapport (Fichier: Word, PDF, Image; optionnel)</label>
                            <input type="file" wire:model="rapportFile" class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                            @if ($form->rapport && !$rapportFile)
                                <div class="text-xs text-gray-500 mt-1">Fichier actuel : {{ basename($form->rapport) }}</div>
                            @endif
                            <div wire:loading wire:target="rapportFile" class="text-xs text-blue-600 mt-1">Téléversement du fichier en cours...</div>
                            @error('rapportFile') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t bg-white shrink-0 flex justify-end gap-2">
                        <x-ui-button variant="secondary" wire:click="$set('showModal', false)">Annuler</x-ui-button>
                        <x-ui-button wire:click="save" wire:loading.attr="disabled">
                            <span wire:loading.remove>{{ $editing ? 'Enregistrer' : 'Créer' }}</span>
                            <span wire:loading>Traitement...</span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        @endif

        {{-- Export Modal --}}
        @if ($showExportModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
                x-on:keydown.escape.window="$wire.set('showExportModal', false)">
                <div class="absolute inset-0 bg-black/50" wire:click="$set('showExportModal', false)"></div>

                <div class="relative w-full max-w-md bg-white rounded-2xl shadow-xl border flex flex-col">
                    <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
                        <div class="font-semibold text-lg text-gray-900">Exporter les réponses en Excel</div>
                        <button type="button" class="opacity-60 hover:opacity-100 text-xl font-bold"
                            wire:click="$set('showExportModal', false)">✕</button>
                    </div>

                    <div class="p-6 space-y-4">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Date de début *</label>
                            <input type="date" wire:model.defer="exp_start_date"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                            @error('exp_start_date') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Date de fin *</label>
                            <input type="date" wire:model.defer="exp_end_date"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                            @error('exp_end_date') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t bg-white shrink-0 flex justify-end gap-2">
                        <x-ui-button variant="secondary" wire:click="$set('showExportModal', false)">Annuler</x-ui-button>
                        <x-ui-button wire:click="export" wire:loading.attr="disabled">
                            <span wire:loading.remove>Exporter</span>
                            <span wire:loading>Génération...</span>
                        </x-ui-button>
                    </div>
                </div>
            </div>
        @endif
    @else
        <div class="bg-white border rounded-xl p-8 text-center text-gray-500">
            Aucun incident disponible. Veuillez d'abord créer un incident.
        </div>
    @endif
</div>
