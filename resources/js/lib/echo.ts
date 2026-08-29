import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

export function createEcho(key: string): Echo<'reverb'> {
    const tls = window.location.protocol === 'https:';
    const port = window.location.port
        ? Number(window.location.port)
        : tls
          ? 443
          : 80;

    return new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS: tls,
        enabledTransports: ['ws', 'wss'],
    });
}
