@props([
    'title' => 'AlertBook',
])

@php
    $user = auth()->user();
    $canSeeMovements = ! $user?->hasRole('moniteur');
    $isSuperadmin = (bool) $user?->hasRole('superadmin');

    $navigationGroups = [
        [
            'label' => 'Gestion des alertes',
            'items' => [
                ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => 'dashboard', 'icon' => 'layout-dashboard'],
                ['label' => 'Alertes', 'href' => route('incidents.index'), 'active' => 'incidents.*', 'icon' => 'alert-triangle'],
                ['label' => 'Réponses aux incidents', 'href' => route('reponses.index'), 'active' => 'reponses.*', 'icon' => 'reply'],
                ['label' => 'Victimes des violations', 'href' => route('victimes.index'), 'active' => 'victimes.*', 'icon' => 'shield-alert'],
                ['label' => 'Déplacements', 'href' => route('mouvements.standalone'), 'active' => 'mouvements.*', 'icon' => 'route', 'visible' => $canSeeMovements],
            ],
        ],
        [
            'label' => 'Administration',
            'items' => [
                ['label' => 'Utilisateurs', 'href' => route('users.index'), 'active' => 'users.*', 'icon' => 'users'],
                ['label' => 'Assignations moniteurs', 'href' => route('monitor-assignments.index'), 'active' => 'monitor-assignments.*', 'icon' => 'user-check', 'visible' => $isSuperadmin],
                ['label' => 'Structures de prise en charge', 'href' => route('service-providers.index'), 'active' => 'service-providers.*', 'icon' => 'hospital'],
                ['label' => 'Organisations', 'href' => route('organisations.index'), 'active' => 'organisations.*', 'icon' => 'building-2'],
                ['label' => 'Auteurs présumés', 'href' => route('auteurs.index'), 'active' => 'auteurs.*', 'icon' => 'user-cog', 'visible' => $isSuperadmin],
                ['label' => 'Performance superviseurs', 'href' => route('supervision.performance'), 'active' => 'supervision.performance', 'icon' => 'chart-line'],
            ],
        ],
        [
            'label' => 'Documents',
            'items' => [
                ['label' => 'Documents', 'href' => route('documents.index'), 'active' => 'documents.*', 'icon' => 'folder-open'],
            ],
        ],
        [
            'label' => 'Export et Analyse',
            'items' => [
                ['label' => 'Exporter', 'href' => route('exports.index'), 'active' => 'exports.*', 'icon' => 'file-spreadsheet'],
                ['label' => 'Analyses', 'href' => route('analyses.index'), 'active' => 'analyses.*', 'icon' => 'chart-no-axes-combined'],
            ],
        ],
    ];

    $profileGroup = [
        'label' => 'Mon profil',
        'items' => [
            ['label' => 'Mon profil', 'href' => route('profile'), 'active' => 'profile', 'icon' => 'user-pen'],
        ],
    ];
@endphp

<div x-data="{
    sidebarOpen: false,
    close() { this.sidebarOpen = false },
    open() { this.sidebarOpen = true },
    toggle() { this.sidebarOpen = !this.sidebarOpen }
}" x-on:keydown.escape.window="close()" class="min-h-screen bg-gray-50 text-gray-900">
    <!-- Topbar -->
    <header class="sticky top-0 z-40 bg-onu text-white border-b border-white/10">
        <div class="h-14 px-4 lg:px-6 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <!-- Burger (mobile) -->
                <button type="button"
                    class="lg:hidden inline-flex items-center justify-center h-10 w-10 rounded-lg hover:bg-gray-100"
                    @click="toggle()" aria-label="Ouvrir le menu">
                    <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>

                <div class="font-bold tracking-tight">
                    <div class="flex items-center gap-3">
                        <x-logo size="36" />
                        <div class="font-semibold tracking-tight">
                            {{ $title ?? 'AlertBook' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="hidden sm:block text-sm text-white font-medium">
                    {{ auth()->user()->name ?? 'Invité' }}
                </div>

                <div
                    class="h-9 w-9 rounded-full bg-gray-200 grid place-items-center text-xs font-semibold text-gray-700">
                    {{ strtoupper(substr(auth()->user()->name ?? 'GB', 0, 2)) }}
                </div>

                <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                    @csrf
                    <x-ui-button variant="secondary" size="sm" type="submit">
                        Déconnexion
                    </x-ui-button>
                </form>
            </div>
        </div>
    </header>

    <div class="flex">
        <!-- Sidebar desktop -->
        <aside class="hidden lg:block w-64 bg-white border-r min-h-[calc(100vh-3.5rem)]">
            <div class="h-[calc(100vh-3.5rem)] p-4 flex flex-col">
                <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Navigation</div>

                <nav class="flex-1 overflow-y-auto pr-1 space-y-4">
                    @foreach ($navigationGroups as $group)
                        @php
                            $items = collect($group['items'])->filter(fn ($item) => $item['visible'] ?? true);
                        @endphp

                        @if ($items->isNotEmpty())
                            <div class="{{ $loop->first ? '' : 'pt-4 border-t border-gray-100' }}">
                                <div class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                    {{ $group['label'] }}
                                </div>
                                <div class="space-y-1">
                                    @foreach ($items as $item)
                                        <x-nav-link href="{{ $item['href'] }}" :active="request()->routeIs($item['active'])"
                                            icon="{{ $item['icon'] }}">{{ $item['label'] }}</x-nav-link>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach
                </nav>

                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                        {{ $profileGroup['label'] }}
                    </div>
                    @foreach ($profileGroup['items'] as $item)
                        <x-nav-link href="{{ $item['href'] }}" :active="request()->routeIs($item['active'])"
                            icon="{{ $item['icon'] }}">{{ $item['label'] }}</x-nav-link>
                    @endforeach
                </div>
            </div>
        </aside>

        <!-- Mobile sidebar (drawer) -->
        <div class="lg:hidden fixed inset-0 z-50" x-show="sidebarOpen" x-cloak>
            <div class="absolute inset-0 bg-black/50" @click="close()"></div>

            <aside class="absolute left-0 top-0 h-full w-72 max-w-[85%] bg-white border-r shadow-xl"
                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="-translate-x-full"
                x-transition:enter-end="translate-x-0" x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full">
                <div class="h-14 px-4 flex items-center justify-between border-b">
                    <div class="font-bold">
                        <x-logo size="32" />
                        {{ $title }}
                    </div>
                    <button class="h-10 w-10 rounded-lg hover:bg-gray-100" @click="close()" aria-label="Fermer">
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 h-[calc(100%-3.5rem)] flex flex-col">
                    <div class="text-xs uppercase tracking-wide text-gray-500 mb-2">Navigation</div>

                    <nav class="flex-1 overflow-y-auto pr-1 space-y-4">
                        @foreach ($navigationGroups as $group)
                            @php
                                $items = collect($group['items'])->filter(fn ($item) => $item['visible'] ?? true);
                            @endphp

                            @if ($items->isNotEmpty())
                                <div class="{{ $loop->first ? '' : 'pt-4 border-t border-gray-100' }}">
                                    <div class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                                        {{ $group['label'] }}
                                    </div>
                                    <div class="space-y-1">
                                        @foreach ($items as $item)
                                            <x-nav-link href="{{ $item['href'] }}" :active="request()->routeIs($item['active'])"
                                                icon="{{ $item['icon'] }}" @click="close()">{{ $item['label'] }}</x-nav-link>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </nav>

                    <div class="mt-4 pt-4 border-t border-gray-200">
                        <div class="px-3 mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
                            {{ $profileGroup['label'] }}
                        </div>
                        @foreach ($profileGroup['items'] as $item)
                            <x-nav-link href="{{ $item['href'] }}" :active="request()->routeIs($item['active'])"
                                icon="{{ $item['icon'] }}" @click="close()">{{ $item['label'] }}</x-nav-link>
                        @endforeach
                    </div>

                    <div class="mt-6 pt-4 border-t">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-ui-button variant="secondary" class="w-full" type="submit">
                                Déconnexion
                            </x-ui-button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>

        <!-- Main content -->
        <main class="flex-1 p-4 lg:p-6">
            {{ $slot }}
        </main>
    </div>

    <x-ui-globaloading />
</div>
