<div class="space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-2xl font-bold">Auteurs présumés</div>
            <div class="text-sm text-gray-600">Gestion des auteurs présumés d'incidents (réservé aux super-administrateurs).</div>
        </div>

        <x-ui-button wire:click="openCreate">
            + Nouvel auteur
        </x-ui-button>
    </div>

    <x-ui-card>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <x-ui-input label="Recherche" placeholder="Code, dénomination, observation..." wire:model.live="q" />

            <div class="space-y-1">
                <label class="text-sm font-medium text-gray-700">Pagination</label>
                <select wire:model.live="perPage"
                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                </select>
            </div>
        </div>
    </x-ui-card>

    <x-ui-table :headers="['Code', 'Dénomination', 'Observations', 'Créé par', 'Date de création', 'Actions']">
        @forelse($auteurs as $a)
            <tr class="hover:bg-gray-50 transition-colors">
                <td class="px-4 py-3 font-semibold text-gray-800">{{ $a->code_auteur }}</td>
                <td class="px-4 py-3">{{ $a->denomination_auteur }}</td>
                <td class="px-4 py-3 text-sm text-gray-600 max-w-xs truncate" title="{{ $a->observation }}">
                    {{ $a->observation ?? '—' }}
                </td>
                <td class="px-4 py-3 text-sm">
                    {{ $a->creator->name ?? '—' }}
                </td>
                <td class="px-4 py-3 text-sm text-gray-500">
                    {{ $a->create_at ? \Carbon\Carbon::parse($a->create_at)->format('d/m/Y') : '—' }}
                </td>
                <td class="px-4 py-3">
                    <x-ui-button size="sm" variant="secondary" wire:click="openEdit('{{ $a->code_auteur }}')">
                        Éditer
                    </x-ui-button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="6" class="px-4 py-6 text-center text-gray-600">Aucun auteur présumé trouvé.</td>
            </tr>
        @endforelse
    </x-ui-table>

    <div>
        {{ $auteurs->links() }}
    </div>

    {{-- Modal Create/Edit --}}
    @if ($showModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data
            x-on:keydown.escape.window="$wire.set('showModal', false)">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('showModal', false)"></div>

            <div class="relative w-full max-w-2xl bg-white rounded-2xl shadow-xl border max-h-[85vh] flex flex-col">
                <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
                    <div class="font-semibold">{{ $editing ? 'Modifier l\'auteur présumé' : 'Nouvel auteur présumé' }}</div>
                    <button type="button" class="opacity-60 hover:opacity-100"
                        wire:click="$set('showModal', false)">✕</button>
                </div>

                <div class="p-5 space-y-4 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <x-ui-input 
                            label="Code de l'auteur *" 
                            wire:model.defer="form.code_auteur"
                            placeholder="ex: FARDC, M23, FDLR..." 
                            :disabled="$editing" 
                        />
                        <x-ui-input 
                            label="Dénomination / Nom *" 
                            wire:model.defer="form.denomination_auteur"
                            placeholder="ex: Forces Armées de la RDC" 
                        />
                    </div>

                    @error('form.code_auteur')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                    @error('form.denomination_auteur')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700">Observations / Description</label>
                        <textarea 
                            wire:model.defer="form.observation"
                            rows="4"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-onu"
                            placeholder="Saisir des observations ou informations complémentaires..."></textarea>
                        @error('form.observation')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>

                    @if ($errors->any())
                        <div class="text-sm text-red-600 font-medium">Veuillez corriger les champs en erreur.</div>
                    @endif
                </div>

                <div class="px-5 py-4 border-t bg-white shrink-0 flex justify-end gap-2">
                    <x-ui-button variant="secondary" wire:click="$set('showModal', false)">Annuler</x-ui-button>
                    <x-ui-button wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>{{ $editing ? 'Enregistrer' : 'Créer' }}</span>
                        <span wire:loading>Traitement…</span>
                    </x-ui-button>
                </div>
            </div>
        </div>
    @endif
</div>
