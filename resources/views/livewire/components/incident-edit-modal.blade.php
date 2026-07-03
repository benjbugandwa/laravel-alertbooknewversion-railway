<div>
    @if ($open)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4" x-data
            x-on:keydown.escape.window="$wire.set('open', false)">
            <div class="absolute inset-0 bg-black/50" wire:click="$set('open', false)"></div>

            <div class="relative w-full max-w-3xl bg-white rounded-2xl shadow-xl border max-h-[85vh] flex flex-col">
                <div class="px-5 py-4 border-b flex items-center justify-between shrink-0">
                    <div class="font-semibold">Éditer l'alerte</div>
                    <button type="button" class="opacity-60 hover:opacity-100"
                        wire:click="$set('open', false)">✕</button>
                </div>

                <div class="p-5 space-y-6 overflow-y-auto">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Événement *</label>
                            <select wire:model.defer="form.code_evenement"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                <option value="">-- Sélectionner --</option>
                                @foreach ($this->evenements as $evenement)
                                    <option value="{{ $evenement['code'] }}">{{ $evenement['name'] }}</option>
                                @endforeach
                            </select>
                            @error('form.code_evenement')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Date de l'alerte *</label>
                            <input type="date" wire:model.defer="form.date_incident" max="{{ now()->toDateString() }}"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                            @error('form.date_incident')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Sévérité *</label>
                            <select wire:model.defer="form.severite"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                @foreach ($severityOptions as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('form.severite')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Statut</label>
                            <input type="text" value="{{ $form->statut_incident }}" readonly
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-gray-100 text-gray-600">
                            <div class="text-[10px] text-gray-500 italic">Le statut se modifie uniquement via les actions dédiées.</div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Confidentialité *</label>
                            <select wire:model.defer="form.confidentiality_level"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                @foreach ($confidentialityOptions as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('form.confidentiality_level')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="bg-gray-50 border border-gray-200 p-4 rounded-xl space-y-4">
                        <h3 class="text-sm font-bold text-gray-800 border-b pb-2 uppercase tracking-wide">Localisation</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Province *</label>
                                <select wire:model.live="form.code_province"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white"
                                    @disabled(!auth()->user()->hasRole('superadmin'))>
                                    @if (!auth()->user()->hasRole('superadmin'))
                                        <option value="{{ auth()->user()->code_province }}">{{ auth()->user()->province?->nom_province ?? '-' }}</option>
                                    @else
                                        <option value="">-- Sélectionner --</option>
                                        @foreach ($this->provinces as $province)
                                            <option value="{{ $province['code'] }}">{{ $province['name'] }}</option>
                                        @endforeach
                                    @endif
                                </select>
                                @error('form.code_province')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Territoire</label>
                                <select wire:model.live="form.code_territoire"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach ($this->territoires as $territoire)
                                        <option value="{{ $territoire['code'] }}">{{ $territoire['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.code_territoire')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Chefferie</label>
                                <select wire:model.live="form.code_chefferie"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach ($this->chefferies as $chefferie)
                                        <option value="{{ $chefferie['code'] }}">{{ $chefferie['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.code_chefferie')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Groupement</label>
                                <select wire:model.defer="form.code_groupement"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach ($this->groupements as $groupement)
                                        <option value="{{ $groupement['code'] }}">{{ $groupement['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.code_groupement')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Zone de santé</label>
                                <select wire:model.live="form.code_zonesante"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach ($this->zones as $zone)
                                        <option value="{{ $zone['code'] }}">{{ $zone['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.code_zonesante')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="space-y-1">
                                <label class="text-sm font-medium text-gray-700">Aire de santé</label>
                                <select wire:model.defer="form.code_airesante"
                                    class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                    <option value="">-- Sélectionner --</option>
                                    @foreach ($this->airesantes as $aire)
                                        <option value="{{ $aire['code'] }}">{{ $aire['name'] }}</option>
                                    @endforeach
                                </select>
                                @error('form.code_airesante')
                                    <div class="text-sm text-red-600">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="md:col-span-2">
                                <x-ui-input label="Localité" wire:model.defer="form.localite" name="localite" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Auteur présumé</label>
                            <input list="incident-edit-auteurs" wire:model.defer="form.auteur_presume"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white"
                                placeholder="Saisir ou choisir un auteur présumé...">
                            <datalist id="incident-edit-auteurs">
                                @foreach ($this->listAuteurs as $auteur)
                                    <option value="{{ $auteur->denomination_auteur }} ({{ $auteur->code_auteur }})"></option>
                                @endforeach
                            </datalist>
                            @error('form.auteur_presume')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Source d'information *</label>
                            <select wire:model.defer="form.source_info"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                                @foreach ($sourceInfoOptions as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                            @error('form.source_info')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Contact source</label>
                            <input type="text" inputmode="numeric" pattern="[0-9]{10}" maxlength="10"
                                wire:model.defer="form.contact_source"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white"
                                placeholder="10 chiffres">
                            @error('form.contact_source')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700">Description des faits</label>
                        <textarea wire:model.defer="form.description_faits" rows="4"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start">
                        <div class="space-y-1">
                            <label class="text-sm font-medium text-gray-700">Nouvelle photo</label>
                            <input type="file" wire:model="photo" class="block w-full text-sm" accept=".jpg,.jpeg,.png">
                            @error('photo')
                                <div class="text-sm text-red-600">{{ $message }}</div>
                            @enderror
                            <div class="text-xs text-gray-500">JPG/PNG — 2 Mo maximum.</div>
                        </div>

                        @if ($photo)
                            <img src="{{ $photo->temporaryUrl() }}"
                                class="w-full max-h-40 object-cover rounded-lg border" alt="Prévisualisation">
                        @endif
                    </div>

                    @if ($errors->any())
                        <div class="text-sm text-red-600">Veuillez corriger les champs en erreur.</div>
                    @endif
                </div>

                <div class="px-5 py-4 border-t bg-white shrink-0 flex justify-end gap-2">
                    <x-ui-button variant="secondary" wire:click="$set('open', false)">Annuler</x-ui-button>
                    <x-ui-button wire:click="save" wire:loading.attr="disabled">
                        <span wire:loading.remove>Enregistrer</span>
                        <span wire:loading>Traitement…</span>
                    </x-ui-button>
                </div>
            </div>
        </div>
    @endif
</div>
