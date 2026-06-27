<div class="space-y-6 relative">
    <div wire:loading class="absolute inset-0 z-50 flex items-center justify-center bg-white/50 backdrop-blur-sm rounded-2xl">
        <div class="flex flex-col items-center gap-2">
            <svg class="animate-spin h-8 w-8 text-onu" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium text-gray-700">Chargement...</span>
        </div>
    </div>

    <div>
        <div class="text-2xl font-bold">Assignations automatiques</div>
        <div class="text-sm text-gray-600">Associer les moniteurs aux superviseurs de la meme province.</div>
    </div>

    <x-ui-card>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700">Province</label>
                <select wire:model.live="province" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                    <option value="">-- Selectionner --</option>
                    @foreach ($this->provinces as $p)
                        <option value="{{ $p->code_province }}">{{ $p->nom_province }}</option>
                    @endforeach
                </select>
                @error('province') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700">Superviseur</label>
                <select wire:model.defer="supervisorId" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                    <option value="">-- Selectionner --</option>
                    @foreach ($this->supervisors as $s)
                        <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->email }})</option>
                    @endforeach
                </select>
                @error('supervisorId') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
            </div>

            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700">Pagination</label>
                <select wire:model.live="perPage" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                </select>
            </div>
        </div>

        <div class="mt-5 space-y-2">
            <div class="text-sm font-medium text-gray-700">Moniteurs</div>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2 max-h-72 overflow-y-auto pr-1">
                @forelse ($this->monitors as $monitor)
                    @php
                        $current = $monitor->monitorAssignments->first();
                    @endphp
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm">
                        <input type="checkbox" class="mt-1 rounded border-gray-300" wire:model.defer="monitorIds" value="{{ $monitor->id }}">
                        <span>
                            <span class="block font-medium text-gray-900">{{ $monitor->name }}</span>
                            <span class="block text-xs text-gray-500">{{ $monitor->email }}</span>
                            @if ($current?->supervisor)
                                <span class="block text-xs text-onu mt-1">Actuel: {{ $current->supervisor->name }}</span>
                            @endif
                        </span>
                    </label>
                @empty
                    <div class="text-sm text-gray-500">Aucun moniteur actif dans cette province.</div>
                @endforelse
            </div>
            @error('monitorIds') <div class="text-sm text-red-600">{{ $message }}</div> @enderror
        </div>

        <div class="mt-5 flex justify-end">
            <x-ui-button wire:click="assign" wire:loading.attr="disabled">
                <span wire:loading.remove>Enregistrer l'assignation</span>
                <span wire:loading>Traitement...</span>
            </x-ui-button>
        </div>
    </x-ui-card>

    <x-ui-card>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
            <x-ui-input label="Recherche" placeholder="Moniteur ou superviseur..." wire:model.live="q" />
        </div>

        <x-ui-table :headers="['Moniteur', 'Superviseur', 'Province', 'Mise a jour', 'Actions']">
            @forelse ($assignments as $assignment)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $assignment->monitor?->name ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ $assignment->monitor?->email }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <div class="font-medium">{{ $assignment->supervisor?->name ?? '-' }}</div>
                        <div class="text-xs text-gray-500">{{ $assignment->supervisor?->email }}</div>
                    </td>
                    <td class="px-4 py-3">{{ $assignment->province?->nom_province ?? $assignment->code_province }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">{{ optional($assignment->updated_at)->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">
                        <x-ui-button size="sm" variant="danger" wire:click="removeAssignment({{ $assignment->id }})" wire:confirm="Retirer cette affectation ?">
                            Retirer
                        </x-ui-button>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-600">Aucune affectation trouvee.</td>
                </tr>
            @endforelse
        </x-ui-table>

        <div class="mt-4">{{ $assignments->links() }}</div>
    </x-ui-card>
</div>
