<script setup lang="ts">
import { computed } from 'vue';
import { Form, Head, Link } from '@inertiajs/vue3';
import CopyCaptureUrl from '@/components/CopyCaptureUrl.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import MethodBadge from '@/components/MethodBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    hexDumpFromBase64,
    prettyJson,
    utf8FromBase64,
} from '@/lib/formatBody';
import { displayHeaderValue, isEncodedHeader } from '@/lib/headerValue';
import { dashboard } from '@/routes';
import { show as endpointShow } from '@/routes/endpoints';
import type { CaptureDetail, EndpointSummary, ReplaySummary } from '@/types';

const { endpoint, capture, replays, allow_private_targets } = defineProps<{
    endpoint: Pick<EndpointSummary, 'id' | 'name' | 'token'>;
    capture: CaptureDetail;
    replays: ReplaySummary[];
    allow_private_targets: boolean;
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

function snippetDisplay(base64: string | null): string {
    if (base64 === null || base64 === '') {
        return '';
    }

    const utf8 = utf8FromBase64(base64);

    if (utf8 !== null) {
        return prettyJson(utf8) ?? utf8;
    }

    return hexDumpFromBase64(base64);
}

function snippetIsBinary(base64: string | null): boolean {
    return base64 !== null && base64 !== '' && utf8FromBase64(base64) === null;
}
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

        <section class="space-y-3">
            <h2 class="text-sm font-medium">Replay</h2>
            <p
                v-if="allow_private_targets"
                class="text-muted-foreground text-xs"
            >
                Private and LAN targets are allowed on this instance (dev escape
                hatch). Production compose leaves this off.
            </p>
            <Form
                method="post"
                :action="`/endpoints/${endpoint.id}/requests/${capture.id}/replays`"
                class="flex flex-col gap-3 rounded-xl border p-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="target_url">Target URL</Label>
                    <Input
                        id="target_url"
                        name="target_url"
                        type="url"
                        required
                        maxlength="2048"
                        placeholder="https://example.test/webhook"
                    />
                    <InputError :message="errors.target_url" />
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input
                        id="forward_sensitive"
                        name="forward_sensitive"
                        type="checkbox"
                        value="1"
                        class="mt-1"
                    />
                    <span>
                        Forward Authorization, Cookie, and signature headers.
                        Off by default — those are credentials, not metadata.
                    </span>
                </label>
                <div>
                    <Button type="submit" :disabled="processing">
                        Replay
                    </Button>
                </div>
            </Form>

            <div
                v-if="replays.length === 0"
                class="text-muted-foreground text-sm"
            >
                No replays yet.
            </div>
            <article
                v-for="replay in replays"
                :key="replay.id"
                class="space-y-2 rounded-xl border p-4"
            >
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge :variant="replay.error ? 'destructive' : 'outline'">
                        {{
                            replay.status_code === null
                                ? 'No response'
                                : String(replay.status_code)
                        }}
                    </Badge>
                    <span class="font-mono text-xs break-all">{{
                        replay.target_url
                    }}</span>
                    <span
                        v-if="replay.duration_ms !== null"
                        class="text-muted-foreground text-xs"
                    >
                        {{ replay.duration_ms }} ms
                    </span>
                </div>
                <p
                    v-if="replay.status_code === 301 && replay.error === null"
                    class="text-muted-foreground text-xs"
                >
                    Redirect not followed. 301 is the recorded outcome.
                </p>
                <p
                    v-if="replay.error"
                    class="text-destructive font-mono text-xs"
                >
                    {{ replay.error }}
                </p>
                <p
                    v-if="replay.forwarded_headers.length > 0"
                    class="text-muted-foreground text-xs"
                >
                    Forwarded: {{ replay.forwarded_headers.join(', ') }}
                </p>
                <pre
                    v-if="replay.response_snippet"
                    class="bg-muted/40 overflow-x-auto rounded-lg border p-3 font-mono text-xs leading-5"
                    >{{ snippetDisplay(replay.response_snippet) }}</pre>
                <p
                    v-if="snippetIsBinary(replay.response_snippet)"
                    class="text-muted-foreground text-xs"
                >
                    Snippet stored as base64; shown as hex because the bytes are
                    not UTF-8.
                </p>
            </article>
        </section>
    </div>
</template>
