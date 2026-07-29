<div class="space-y-6">
    @php
        $minTo = $from ? \Illuminate\Support\Carbon::parse($from)->addDay()->toDateString() : null;
        $maxFrom = $to ? \Illuminate\Support\Carbon::parse($to)->subDay()->toDateString() : null;
    @endphp

    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-2xl font-bold">Exporter</div>
            <div class="text-sm text-gray-600">
                Export Excel des alertes validees et des donnees associees.
            </div>
        </div>
    </div>

    <form wire:submit="export" class="space-y-6">
        <x-ui-card>
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Du *</label>
                    <input type="date" wire:model.live="from" @if($maxFrom) max="{{ $maxFrom }}" @endif
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                    @error('from')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Au *</label>
                    <input type="date" wire:model.live="to" @if($minTo) min="{{ $minTo }}" @endif
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                    @error('to')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>

                @if (auth()->user()?->hasRole('superadmin'))
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700">Province</label>
                        <select wire:model.live="province"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white">
                            <option value="">Toutes les provinces</option>
                            @foreach ($this->provinces as $p)
                                <option value="{{ $p['code'] }}">{{ $p['name'] }}</option>
                            @endforeach
                        </select>
                        @error('province')
                            <div class="text-sm text-red-600">{{ $message }}</div>
                        @enderror
                    </div>
                @else
                    <div class="space-y-1">
                        <label class="text-sm font-medium text-gray-700">Province</label>
                        <div class="h-10 flex items-center px-3 rounded-lg border border-gray-200 bg-gray-50 text-sm text-gray-700">
                            {{ auth()->user()?->province?->nom_province ?? '-' }}
                        </div>
                    </div>
                @endif

                <div class="space-y-1">
                    <label class="text-sm font-medium text-gray-700">Territoire</label>
                    <select wire:model.live="territoire" @disabled(!$province)
                        class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm bg-white disabled:bg-gray-50 disabled:text-gray-500">
                        <option value="">Tous les territoires</option>
                        @foreach ($this->territoires as $t)
                            <option value="{{ $t['code'] }}">{{ $t['name'] }}</option>
                        @endforeach
                    </select>
                    @error('territoire')
                        <div class="text-sm text-red-600">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </x-ui-card>

        <x-ui-card>
            <div class="space-y-4">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Donnees a inclure</div>
                    <div class="text-xs text-gray-500">
                        La feuille Alertes est toujours incluse. Les autres feuilles restent reliees par code_incident et incident_id.
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm">
                        <input type="checkbox" wire:model="include_violences" class="mt-1 rounded border-gray-300">
                        <span>
                            <span class="block font-medium text-gray-900">Violences</span>
                            <span class="block text-xs text-gray-500">Une ligne par violence associee.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm">
                        <input type="checkbox" wire:model="include_victimes" class="mt-1 rounded border-gray-300">
                        <span>
                            <span class="block font-medium text-gray-900">Victimes</span>
                            <span class="block text-xs text-gray-500">Tranches d'age, sexe et total.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 text-sm">
                        <input type="checkbox" wire:model="include_reponses" class="mt-1 rounded border-gray-300">
                        <span>
                            <span class="block font-medium text-gray-900">Reponses</span>
                            <span class="block text-xs text-gray-500">Couverture, secteurs et impact.</span>
                        </span>
                    </label>
                </div>
            </div>
        </x-ui-card>

        <div class="flex justify-end">
            <x-ui-button type="submit" wire:loading.attr="disabled">
                <i data-lucide="download" class="w-4 h-4"></i>
                <span wire:loading.remove>Exporter Excel</span>
                <span wire:loading>Preparation...</span>
            </x-ui-button>
        </div>
    </form>

    <x-ui-loading-overlay target="export" />
</div>
