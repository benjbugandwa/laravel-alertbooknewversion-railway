<div class="space-y-6">
    @php
        $minTo = $from ? \Illuminate\Support\Carbon::parse($from)->addDay()->toDateString() : null;
        $maxFrom = $to ? \Illuminate\Support\Carbon::parse($to)->subDay()->toDateString() : null;
    @endphp

    <div class="flex items-start justify-between gap-4">
        <div>
            <div class="text-2xl font-bold">Analyses</div>
            <div class="text-sm text-gray-600">
                Tableaux, graphiques et carte prets a telecharger au format PDF.
            </div>
        </div>
    </div>

    <form wire:submit="generate" class="space-y-6">
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

                @if (auth()->user()?->hasEffectiveRole('superadmin'))
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 text-sm">
                <div class="rounded-lg border border-red-100 bg-red-50 p-3">
                    <div class="font-semibold text-red-900">Zones chaudes</div>
                    <div class="mt-1 text-xs text-red-700">Zones de sante, pourcentages et carte.</div>
                </div>
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="font-semibold text-blue-900">Violences et victimes</div>
                    <div class="mt-1 text-xs text-blue-700">Victimes par violence et zone de sante.</div>
                </div>
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="font-semibold text-blue-900">Mouvements</div>
                    <div class="mt-1 text-xs text-blue-700">Provenance, accueil, menages et individus.</div>
                </div>
                <div class="rounded-lg border border-blue-100 bg-blue-50 p-3">
                    <div class="font-semibold text-blue-900">Typologie</div>
                    <div class="mt-1 text-xs text-blue-700">Repartition des types d'evenements.</div>
                </div>
            </div>
        </x-ui-card>

        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-end gap-3">
            @if($generatedUrl)
                <a href="{{ $generatedUrl }}" target="_blank"
                    class="inline-flex items-center justify-center gap-2 rounded-lg border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800 hover:bg-green-100">
                    <i data-lucide="file-down" class="w-4 h-4"></i>
                    Telecharger le PDF genere
                </a>
            @endif

            <x-ui-button type="submit" wire:loading.attr="disabled">
                <i data-lucide="file-text" class="w-4 h-4"></i>
                <span wire:loading.remove>Generer</span>
                <span wire:loading>Generation...</span>
            </x-ui-button>
        </div>
    </form>

    <x-ui-loading-overlay target="generate" />
</div>
