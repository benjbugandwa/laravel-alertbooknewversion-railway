<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Aide video - AlertBook</title>

    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/logo/favicons/apple-touch-icon.png') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/logo/favicons/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/logo/favicons/favicon-16x16.png') }}">
    <link rel="manifest" href="{{ asset('images/logo/favicons/site.webmanifest') }}">
    <link rel="shortcut icon" href="{{ asset('images/logo/favicons/favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50 text-gray-900">
    <div class="bg-[#1B4D8C] text-white">
        <div class="mx-auto flex h-10 max-w-7xl items-center justify-between px-4 text-xs sm:px-6 sm:text-sm">
            <div>Documentation video AlertBook</div>
            <div class="hidden text-white/80 sm:block">Guides pratiques pour les utilisateurs terrain</div>
        </div>
    </div>

    <header class="border-b bg-white">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
            <a href="{{ route('landing') }}" class="flex items-center gap-3">
                <img src="{{ asset('images/logo/logo-white.png') }}" class="h-9" alt="Logo AlertBook">
                <div>
                    <div class="font-semibold">AlertBook</div>
                    <div class="text-xs text-gray-500">Centre d'aide video</div>
                </div>
            </a>

            <div class="flex items-center gap-2">
                <a href="{{ route('landing') }}"
                    class="flex h-10 items-center rounded-lg border border-gray-200 px-4 text-sm font-medium hover:bg-gray-50">
                    Accueil
                </a>
                @auth
                    <a href="{{ route('dashboard') }}"
                        class="flex h-10 items-center rounded-lg bg-[#1B4D8C] px-4 text-sm font-semibold text-white hover:bg-[#164174]">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                        class="flex h-10 items-center rounded-lg bg-[#1B4D8C] px-4 text-sm font-semibold text-white hover:bg-[#164174]">
                        Connexion
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6">
        <section class="mb-6 border-b border-gray-200 pb-6">
            <div class="max-w-3xl">
                <div class="text-xs font-semibold uppercase tracking-wide text-[#1B4D8C]">Aide utilisateurs</div>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight text-gray-950 sm:text-4xl">
                    Documentation video AlertBook
                </h1>
                <p class="mt-3 text-gray-600">
                    Selectionnez une video pour l'ouvrir dans le lecteur principal. Utilisez la barre de lecture
                    native pour avancer, reculer ou reprendre exactement au moment voulu.
                </p>
            </div>
        </section>

        @if (!$sourceAvailable)
            <section class="rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                La source de documentation est introuvable ou inaccessible :
                <span class="font-semibold">{{ $source }}</span>
                @if ($sourceError)
                    <div class="mt-3 rounded-md border border-amber-200 bg-white/70 p-3 text-amber-950">
                        {{ $sourceError }}
                    </div>
                @endif
            </section>
        @elseif (empty($videos))
            <section class="rounded-lg border border-gray-200 bg-white p-5 text-sm text-gray-700">
                Aucune video n'a encore ete trouvee dans la source de documentation
                <span class="font-semibold">{{ $source }}</span>.
            </section>
        @else
            <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="min-w-0">
                    <div class="overflow-hidden rounded-lg bg-black shadow-sm">
                        <video id="helpVideoPlayer" class="aspect-video w-full bg-black" controls playsinline
                            preload="metadata" src="{{ $selectedVideo['url'] }}"></video>
                    </div>

                    <div class="mt-4 flex flex-col gap-4 border-b border-gray-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <h2 id="currentVideoTitle" class="text-2xl font-semibold text-gray-950">
                                {{ $selectedVideo['title'] }}
                            </h2>
                            <p id="currentVideoDescription" class="mt-2 text-sm leading-6 text-gray-600">
                                {{ $selectedVideo['description'] ?: 'Guide video AlertBook.' }}
                            </p>
                            <div id="currentVideoMeta" class="mt-2 text-xs font-medium text-gray-500">
                                {{ $selectedVideo['size'] }} · Mise a jour {{ $selectedVideo['updated_at'] }}
                            </div>
                        </div>

                        <button id="togglePlayback" type="button"
                            class="flex h-10 shrink-0 items-center justify-center rounded-lg bg-[#1B4D8C] px-4 text-sm font-semibold text-white hover:bg-[#164174]">
                            Stopper
                        </button>
                    </div>
                </div>

                <aside class="lg:sticky lg:top-4 lg:self-start">
                    <div class="mb-3 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-950">Toutes les videos</h3>
                        <span class="text-xs font-medium text-gray-500">{{ count($videos) }} video(s)</span>
                    </div>

                    <div class="space-y-3">
                        @foreach ($videos as $video)
                            <button type="button"
                                class="group grid w-full grid-cols-[132px_minmax(0,1fr)] gap-3 rounded-lg border border-gray-200 bg-white p-2 text-left shadow-sm transition hover:border-[#1B4D8C]/50 hover:bg-blue-50/40"
                                data-video-card data-key="{{ $video['key'] }}" data-title="{{ $video['title'] }}"
                                data-description="{{ $video['description'] ?: 'Guide video AlertBook.' }}"
                                data-url="{{ $video['url'] }}"
                                data-meta="{{ $video['size'] }} · Mise a jour {{ $video['updated_at'] }}">
                                <span class="relative block overflow-hidden rounded-md bg-black">
                                    <video class="aspect-video w-full object-cover opacity-90" muted playsinline
                                        preload="metadata" src="{{ $video['url'] }}#t=1" data-thumb-video></video>
                                    <span
                                        class="absolute inset-0 grid place-items-center bg-black/20 text-sm font-semibold text-white opacity-0 transition group-hover:opacity-100">
                                        Lire
                                    </span>
                                </span>

                                <span class="min-w-0 py-1">
                                    <span class="line-clamp-2 text-sm font-semibold text-gray-950">
                                        {{ $video['title'] }}
                                    </span>
                                    <span class="mt-1 line-clamp-2 block text-xs leading-5 text-gray-600">
                                        {{ $video['description'] ?: 'Guide video AlertBook.' }}
                                    </span>
                                    <span class="mt-2 block text-[11px] font-medium text-gray-500">
                                        {{ $video['size'] }}
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </aside>
            </section>
        @endif
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const player = document.getElementById('helpVideoPlayer');
            const toggle = document.getElementById('togglePlayback');
            const title = document.getElementById('currentVideoTitle');
            const description = document.getElementById('currentVideoDescription');
            const meta = document.getElementById('currentVideoMeta');
            const cards = Array.from(document.querySelectorAll('[data-video-card]'));

            if (!player || !toggle || cards.length === 0) {
                return;
            }

            const setActiveCard = (key) => {
                cards.forEach((card) => {
                    const active = card.dataset.key === key;
                    card.classList.toggle('border-[#1B4D8C]', active);
                    card.classList.toggle('bg-blue-50', active);
                    card.classList.toggle('shadow-md', active);
                });
            };

            const updateToggle = () => {
                toggle.textContent = player.paused ? 'Reprendre' : 'Stopper';
            };

            const selectVideo = (card) => {
                player.src = card.dataset.url;
                title.textContent = card.dataset.title;
                description.textContent = card.dataset.description;
                meta.textContent = card.dataset.meta;
                setActiveCard(card.dataset.key);
                player.load();
                player.play().catch(() => updateToggle());
            };

            cards.forEach((card) => {
                card.addEventListener('click', () => selectVideo(card));
            });

            toggle.addEventListener('click', () => {
                if (player.paused) {
                    player.play();
                } else {
                    player.pause();
                }
            });

            player.addEventListener('play', updateToggle);
            player.addEventListener('pause', updateToggle);
            player.addEventListener('ended', updateToggle);

            document.querySelectorAll('[data-thumb-video]').forEach((thumb) => {
                thumb.addEventListener('loadedmetadata', () => {
                    if (Number.isFinite(thumb.duration) && thumb.duration > 2) {
                        thumb.currentTime = 1;
                    }
                }, { once: true });
            });

            setActiveCard(cards[0].dataset.key);
            updateToggle();
        });
    </script>
</body>

</html>
