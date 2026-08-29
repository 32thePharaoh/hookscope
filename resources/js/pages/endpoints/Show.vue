<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CopyCaptureUrl from '@/components/CopyCaptureUrl.vue';
import Heading from '@/components/Heading.vue';
import MethodBadge from '@/components/MethodBadge.vue';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';
import { show as captureShow } from '@/routes/captured-requests';
import type { CaptureListItem, CursorPage, EndpointSummary } from '@/types';

const { endpoint, captures, drops_in_last_24h, on_first_page } = defineProps<{
    endpoint: EndpointSummary;
    captures: CursorPage<CaptureListItem>;
    drops_in_last_24h: number;
    on_first_page: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Endpoint',
                href: '#',
            },
        ],
    },
});

function formatBytes(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    return `${(bytes / 1024).toFixed(1)} KB`;
}

function formatPath(capture: CaptureListItem): string {
    return capture.query ? `${capture.path}?${capture.query}` : capture.path;
}

function formatTime(iso: string): string {
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head :title="endpoint.name" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                :title="endpoint.name"
                :description="`Dropped in the last 24h: ${drops_in_last_24h}`"
            />
            <div class="w-full max-w-xl">
                <CopyCaptureUrl :token="endpoint.token" />
            </div>
        </div>

        <div
            class="overflow-x-auto rounded-xl border"
            :data-on-first-page="on_first_page"
        >
            <table class="w-full min-w-[40rem] text-left text-sm">
                <thead class="bg-muted/50 text-muted-foreground">
                    <tr>
                        <th class="px-3 py-2 font-medium">Method</th>
                        <th class="px-3 py-2 font-medium">Path</th>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Size</th>
                        <th class="px-3 py-2 font-medium">Received</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="captures.data.length === 0">
                        <td
                            colspan="5"
                            class="text-muted-foreground px-3 py-8 text-center"
                        >
                            No requests yet. Send traffic to the capture URL.
                        </td>
                    </tr>
                    <tr
                        v-for="capture in captures.data"
                        :key="capture.id"
                        class="hover:bg-muted/40 border-t"
                    >
                        <td class="px-3 py-2">
                            <MethodBadge :method="capture.method" />
                        </td>
                        <td class="px-3 py-2">
                            <Link
                                :href="captureShow([endpoint.id, capture.id])"
                                class="font-mono text-xs hover:underline"
                            >
                                {{ formatPath(capture) }}
                            </Link>
                        </td>
                        <td
                            class="text-muted-foreground max-w-[12rem] truncate px-3 py-2 font-mono text-xs"
                            :title="capture.content_type ?? undefined"
                        >
                            {{ capture.content_type ?? '—' }}
                        </td>
                        <td
                            class="text-muted-foreground px-3 py-2 tabular-nums"
                        >
                            {{ formatBytes(capture.size_bytes) }}
                        </td>
                        <td
                            class="text-muted-foreground px-3 py-2 whitespace-nowrap"
                        >
                            {{ formatTime(capture.received_at) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between gap-4">
            <Button
                v-if="captures.prev_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="captures.prev_page_url">Previous</Link>
            </Button>
            <span v-else />
            <Button
                v-if="captures.next_page_url"
                variant="outline"
                size="sm"
                as-child
            >
                <Link :href="captures.next_page_url">Next</Link>
            </Button>
        </div>
    </div>
</template>
