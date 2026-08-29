<script setup lang="ts">
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Copy } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { captureUrl } from '@/lib/captureUrl';
import { copyText } from '@/lib/copyText';

const { token } = defineProps<{ token: string }>();

const url = computed(() => captureUrl(token));
const copied = ref(false);

async function copy(): Promise<void> {
    const ok = await copyText(url.value);

    if (ok) {
        copied.value = true;
        toast.success('Capture URL copied');
        window.setTimeout(() => {
            copied.value = false;
        }, 1500);

        return;
    }

    toast.error('Could not copy — select the URL and copy it manually');
}
</script>

<template>
    <div class="flex min-w-0 items-center gap-2">
        <Input
            :model-value="url"
            readonly
            class="font-mono text-xs"
            data-test="capture-url"
        />
        <Button
            type="button"
            variant="outline"
            size="sm"
            data-test="copy-capture-url"
            @click="copy"
        >
            <Copy />
            {{ copied ? 'Copied' : 'Copy' }}
        </Button>
    </div>
</template>
