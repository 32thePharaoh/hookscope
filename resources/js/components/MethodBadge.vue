<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

defineProps<{ method: string }>();
</script>

<template>
    <Badge variant="outline" class="font-mono text-[11px]">{{ method }}</Badge>
</template>
