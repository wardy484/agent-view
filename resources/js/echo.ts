// REQ-M7-003: Bootstraps Laravel Echo on top of Pusher (over Reverb's
// pusher-compatible protocol). Importing this module once at app boot
// installs `window.Echo` and `window.Pusher` so any page can subscribe
// to private channels via `window.Echo.private('snapshot.{id}')`.
//
// Reverb is treated as an *optimisation*, not a hard dependency. If the
// configuration is absent or the websocket fails to connect, the page-level
// subscriber is responsible for catching the error and falling back to
// polling (see REQ-M6-016). This module should never throw at import time.
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        // Echo is generic over the broadcaster name. Reverb speaks the
        // pusher protocol, so the concrete connector shape lives under
        // the `'reverb'` driver key.
        Echo: Echo<'reverb'>;
    }
}

// pusher-js mounts itself onto `window.Pusher` when present — Echo expects it.
window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

// REQ-M7-003 contract: this module must never throw at import time. If the
// Reverb app key is absent (local dev without a configured Reverb server,
// or environments where the broadcast layer is intentionally disabled),
// skip Echo initialisation entirely. Page-level subscribers fall back to
// the polling path from REQ-M6-016.
if (reverbKey) {
    const reverbHost = import.meta.env.VITE_REVERB_HOST ?? window.location.hostname;
    const reverbPort = Number(import.meta.env.VITE_REVERB_PORT ?? 8080);
    const reverbScheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https';

    try {
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: reverbKey,
            wsHost: reverbHost,
            wsPort: reverbPort,
            wssPort: reverbPort,
            forceTLS: reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    } catch (err) {
        console.warn('[echo] failed to initialise; broadcasting disabled', err);
    }
}
