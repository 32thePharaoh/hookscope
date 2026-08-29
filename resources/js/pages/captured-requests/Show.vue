<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import CopyCaptureUrl from '@/components/CopyCaptureUrl.vue';
import Heading from '@/components/Heading.vue';
import MethodBadge from '@/components/MethodBadge.vue';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { show as endpointShow } from '@/routes/endpoints';
import { displayHeaderValue, isEncodedHeader } from '@/lib/headerValue';
import { hexDumpFromBase64, prettyJson } from '@/lib/formatBody';
import type { CaptureDetail, EndpointSummary } from '@/types';

const { endpoint, capture } = defineProps<{
    endpoint: Pick<EndpointSummary, 'id' | 'name' | 'token'>;
    capture: CaptureDetail;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Request',
                href: '#',
            },
        ],
    },
});

const pretty = computed(() =>
    capture.body_encoding === 'utf-8' ? prettyJson(capture.body) : null,
);

const bodyLabel =
    capture.body_encoding === 'binary'
        ? 'Body (hex, stored as binary)'
        : 'Body';
</script>

<template>
    <Head :title="`${capture.method} ${capture.path}`" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <div class="space-y-2">
                <div class="flex items-center gap-2">
                    <MethodBadge :method="capture.method" />
                    <Heading :title="capture.path" />
                </div>
                <p class="text-muted-foreground text-sm">
                    <Link
                        :href="endpointShow(endpoint.id)"
                        class="hover:underline"
                    >
                        {{ endpoint.name }}
                    </Link>
                    · {{ capture.size_bytes }} bytes ·
                    {{ new Date(capture.received_at).toLocaleString() }}
                    <span v-if="capture.ip"> · {{ capture.ip }}</span>
                </p>
                <p
                    v-if="capture.content_type"
                    class="text-muted-foreground max-w-full truncate font-mono text-xs"
                    :title="capture.content_type"
                >
                    {{ capture.content_type }}
                </p>
            </div>
            <div class="w-full max-w-xl">
                <CopyCaptureUrl :token="endpoint.token" />
            </div>
        </div>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">Headers</h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(values, name) in capture.headers"
                            :key="name"
                            class="border-t"
                        >
                            <td
                                class="px-3 py-2 align-top font-mono text-xs whitespace-nowrap"
                            >
                                {{ name }}
                            </td>
                            <td class="px-3 py-2">
                                <div
                                    v-for="(value, index) in values"
                                    :key="index"
                                    class="flex flex-wrap items-center gap-2 font-mono text-xs break-all"
                                >
                                    <span>{{ displayHeaderValue(value) }}</span>
                                    <Badge
                                        v-if="isEncodedHeader(value)"
                                        variant="outline"
                                    >
                                        {{ value.encoding }}
                                    </Badge>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-2">
            <h2 class="text-sm font-medium">{{ bodyLabel }}</h2>
            <pre
                class="bg-muted/40 overflow-x-auto rounded-xl border p-4 font-mono text-xs leading-5"
                >{{
                    capture.body_encoding === 'binary'
                        ? hexDumpFromBase64(capture.body)
                        : (pretty ?? capture.body)
                }}</pre>
        </section>
    </div>
</template>
