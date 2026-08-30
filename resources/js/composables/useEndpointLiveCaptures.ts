import { onUnmounted, watch, type Ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type Echo from 'laravel-echo';
import { createEcho } from '@/lib/echo';
import { insertCapture } from '@/lib/insertCapture';
import type { CaptureListItem } from '@/types';

export function useEndpointLiveCaptures(
    endpointId: () => number,
    onFirstPage: () => boolean,
    rows: Ref<CaptureListItem[]>,
    perPage: () => number,
): void {
    const page = usePage();
    let echo: Echo<'reverb'> | null = null;

    function stop(): void {
        echo?.disconnect();
        echo = null;
    }

    function start(): void {
        stop();

        if (!onFirstPage()) {
            return;
        }

        const key = page.props.reverbKey;

        if (typeof key !== 'string' || key === '') {
            return;
        }

        echo = createEcho(key);
        echo.private(`endpoints.${endpointId()}`).listen(
            '.RequestCaptured',
            (incoming: CaptureListItem) => {
                rows.value = insertCapture(rows.value, incoming, perPage());
            },
        );
    }

    watch(
        () => [endpointId(), onFirstPage(), page.props.reverbKey],
        () => start(),
        { immediate: true },
    );

    onUnmounted(stop);
}
