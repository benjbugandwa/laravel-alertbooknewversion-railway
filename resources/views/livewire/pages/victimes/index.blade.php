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
                            Incident: {{ $incident->code_incident }}
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
                    <div class="text-xs text-gray-500 uppercase tracking-wide">Sévérité</div>
                    <div class="font-medium mt-1">
                        @php
                            $sevClass = match($incident->severite) {
                                'Élevée', 'Critique' => 'bg-red-50 text-red-700 border-red-200',
                                'Moyenne' => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                default => 'bg-blue-50 text-blue-700 border-blue-200'
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 text-xs font-medium rounded-full border {{ $sevClass }}">
                            {{ $incident->severite ?? '—' }}
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
        {{-- Section Title & Action Buttons --}}
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-bold">Victimes des violations</h2>
                <p class="text-sm text-gray-600">Renseignez et analysez le profil des victimes par sexe et tranche d'âge.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('exports.victimes', ['incident_id' => $incident->id]) }}" target="_blank"
                    class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 text-sm font-medium transition shadow-sm text-gray-700">
                    📥 Exporter Matrice Excel
                </a>
                @if ($this->canWrite())
                    <x-ui-button wire:click="openCreate">
                        + Ajouter Victimes
                    </x-ui-button>
                @endif
            </div>
        </div>

        {{-- Table --}}
        <div class="overflow-x-auto bg-white border border-gray-200 rounded-2xl shadow-sm">
            <table class="min-w-full text-sm border-collapse">
                <thead class="bg-gray-50 text-gray-600 uppercase text-xs">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 border-b border-r text-left font-semibold">Violence</th>
                        <th rowspan="2" class="px-4 py-3 border-b border-r text-left font-semibold">Profil</th>
                        <th colspan="5" class="px-4 py-2 border-b border-r text-center font-semibold bg-pink-50/70 text-pink-700">Femmes</th>
                        <th colspan="5" class="px-4 py-2 border-b border-r text-center font-semibold bg-blue-50/70 text-blue-700">Hommes</th>
                        <th rowspan="2" class="px-4 py-3 border-b border-r text-left font-semibold">Description des faits</th>
                        <th rowspan="2" class="px-4 py-3 border-b border-r text-left font-semibold">Enregistré le</th>
                        @if ($this->canWrite())
                            <th rowspan="2" class="px-4 py-3 border-b text-center font-semibold">Actions</th>
                        @endif
                    </tr>
                    <tr class="bg-gray-100/50 text-[10px] text-gray-500">
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-pink-50/20">0-4 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-pink-50/20">5-11 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-pink-50/20">12-17 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-pink-50/20">18-59 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-pink-50/20">60+ ans</th>
                        
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-blue-50/20">0-4 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-blue-50/20">5-11 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-blue-50/20">12-17 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-blue-50/20">18-59 ans</th>
                        <th class="px-2 py-2 border-b border-r text-center font-medium bg-blue-50/20">60+ ans</th>
                    </tr>
                </thead>
                <tbody class="divide-y text-gray-700">
                    @forelse($victimes as $vic)
                        <tr class="hover:bg-gray-50/50 transition">
                            <td class="px-4 py-3 border-r font-medium text-gray-900">
                                {{ $vic->violence?->violence_name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 border-r">
                                @php
                                    $pColor = match($vic->profile_victimes) {
                                        'Résidants' => 'bg-gray-100 text-gray-800 border-gray-200',
                                        'Réfugiés' => 'bg-blue-100 text-blue-800 border-blue-200',
                                        'Déplacés' => 'bg-purple-100 text-purple-800 border-purple-200',
                                        'Retournés' => 'bg-green-100 text-green-800 border-green-200',
                                        default => 'bg-yellow-100 text-yellow-800 border-yellow-200'
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded-full border {{ $pColor }}">
                                    {{ $vic->profile_victimes }}
                                </span>
                            </td>
                            
                            {{-- Femmes --}}
                            <td class="px-2 py-3 border-r text-center bg-pink-50/10 font-semibold">{{ $vic->nbre_femme_0a4ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-pink-50/10 font-semibold">{{ $vic->nbre_femme_5a11ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-pink-50/10 font-semibold">{{ $vic->nbre_femme_12a17ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-pink-50/10 font-semibold">{{ $vic->nbre_femme_18a59ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-pink-50/10 font-semibold">{{ $vic->nbre_femme_6Oansouplus ?? 0 }}</td>
                            
                            {{-- Hommes --}}
                            <td class="px-2 py-3 border-r text-center bg-blue-50/10 font-semibold">{{ $vic->nbre_homme_0a4ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-blue-50/10 font-semibold">{{ $vic->nbre_homme_5a11ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-blue-50/10 font-semibold">{{ $vic->nbre_homme_12a17ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-blue-50/10 font-semibold">{{ $vic->nbre_homme_18a59ans ?? 0 }}</td>
                            <td class="px-2 py-3 border-r text-center bg-blue-50/10 font-semibold">{{ $vic->nbre_homme_6Oansouplus ?? 0 }}</td>
                            
                            <td class="px-4 py-3 border-r text-gray-500 max-w-xs truncate" title="{{ $vic->description_faits }}">
                                {{ $vic->description_faits }}
                            </td>
                            <td class="px-4 py-3 border-r text-xs text-gray-400 whitespace-nowrap">
                                {{ optional($vic->create_at)->format('d/m/Y') }}<br>
                                <span class="text-[10px] text-gray-400">Par {{ $vic->creator?->name ?? '—' }}</span>
                            </td>
                            
                            @if ($this->canWrite())
                                <td class="px-4 py-3 text-center">
                                    <div class="flex items-center justify-center gap-1">
                                        <button type="button"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-blue-600"
                                            wire:click="openEdit({{ $vic->id }})" title="Modifier">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </button>
                                        <button type="button"
                                            class="h-8 w-8 inline-flex items-center justify-center rounded-lg border border-gray-200 hover:bg-red-50 text-red-600"
                                            wire:confirm="Êtes-vous sûr de vouloir supprimer cet enregistrement ?"
                                            wire:click="delete({{ $vic->id }})" title="Supprimer">
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
                            <td class="px-4 py-8 text-center text-gray-500" colspan="15">
                                Aucun groupe de victimes enregistré pour cet incident.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Modal Create/Edit --}}
        @if ($showModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4"
                x-on:keydown.escape.window="$wire.set('showModal', false)">
                <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>

                <div class="relative w-full max-w-4xl bg-white rounded-2xl shadow-xl border max-h-[90vh] flex flex-col">
                    <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
                        <div class="font-semibold text-lg text-gray-900">{{ $editing ? 'Modifier les victimes' : 'Enregistrer les victimes' }}</div>
                        <button type="button" class="opacity-60 hover:opacity-100 text-xl font-bold"
                            wire:click="$set('showModal', false)">✕</button>
                    </div>

                    <div class="p-6 space-y-6 overflow-y-auto flex-1">
                        {{-- 1. Violence & Profil --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Type de violence *</label>
                                <select wire:model.defer="form.violence_id"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                                    <option value="">-- Choisir une violence --</option>
                                    @foreach ($violences_options as $opt)
                                        <option value="{{ $opt['id'] }}">{{ $opt['violence_name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.violence_id') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Profil des victimes *</label>
                                <select wire:model.defer="form.profile_victimes"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white focus:outline-none focus:ring-1 focus:ring-onu">
                                    <option value="">-- Choisir un profil --</option>
                                    @foreach ($profiles as $p)
                                        <option value="{{ $p }}">{{ $p }}</option>
                                    @endforeach
                                </select>
                                @error('form.profile_victimes') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
                            </div>
                        </div>

                        {{-- 2. Grid count age group --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Femmes --}}
                            <div class="bg-pink-50/50 border border-pink-100 p-4 rounded-xl space-y-4">
                                <h3 class="text-sm font-bold text-pink-800 border-b border-pink-200 pb-2 uppercase tracking-wide">Femmes</h3>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">0 à 4 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_femme_0a4ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_femme_0a4ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">5 à 11 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_femme_5a11ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_femme_5a11ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">12 à 17 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_femme_12a17ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_femme_12a17ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">18 à 59 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_femme_18a59ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_femme_18a59ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1 col-span-2">
                                        <label class="text-xs font-semibold text-gray-600">60 ans ou plus</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_femme_6Oansouplus"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_femme_6Oansouplus') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Hommes --}}
                            <div class="bg-blue-50/50 border border-blue-100 p-4 rounded-xl space-y-4">
                                <h3 class="text-sm font-bold text-blue-800 border-b border-blue-200 pb-2 uppercase tracking-wide">Hommes</h3>
                                
                                <div class="grid grid-cols-2 gap-3">
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">0 à 4 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_homme_0a4ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_homme_0a4ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">5 à 11 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_homme_5a11ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_homme_5a11ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">12 à 17 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_homme_12a17ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_homme_12a17ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1">
                                        <label class="text-xs font-semibold text-gray-600">18 à 59 ans</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_homme_18a59ans"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_homme_18a59ans') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                    <div class="space-y-1 col-span-2">
                                        <label class="text-xs font-semibold text-gray-600">60 ans ou plus</label>
                                        <input type="number" min="0" wire:model.defer="form.nbre_homme_6Oansouplus"
                                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                        @error('form.nbre_homme_6Oansouplus') <div class="text-[10px] text-red-600">{{ $message }}</div> @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- 3. Description des faits --}}
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Description des faits *</label>
                            <textarea wire:model.defer="form.description_faits" rows="4"
                                placeholder="Donnez une description des faits détaillée..."
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu"></textarea>
                            @error('form.description_faits') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
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
    @else
        <div class="bg-white border rounded-xl p-8 text-center text-gray-500">
            Aucun incident disponible. Veuillez d'abord créer un incident.
        </div>
    @endif
</div>
