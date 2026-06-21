const PAGE_CACHE = 'alertbook-video-help-pages-v1';
const VIDEO_CACHE = 'alertbook-help-videos-v1';
const HELP_PATH = '/aide/videos';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(PAGE_CACHE)
            .then((cache) => cache.add(HELP_PATH))
            .catch(() => undefined)
    );
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
    const url = new URL(event.request.url);

    if (event.request.mode === 'navigate' && url.pathname === HELP_PATH) {
        event.respondWith(
            fetch(event.request)
                .then((response) => {
                    const copy = response.clone();
                    caches.open(PAGE_CACHE).then((cache) => cache.put(event.request, copy));
                    return response;
                })
                .catch(() => caches.match(event.request).then((cached) => cached || caches.match(HELP_PATH)))
        );
        return;
    }

    if (url.pathname.startsWith('/aide/videos/') && url.pathname.endsWith('/offline')) {
        event.respondWith(
            caches.match(event.request).then((cached) => cached || fetch(event.request))
        );
    }
});
