<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { Webhook } from '@lucide/vue';
import EndpointController from '@/actions/App/Http/Controllers/EndpointController';
import CopyCaptureUrl from '@/components/CopyCaptureUrl.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { show } from '@/routes/endpoints';
import type { EndpointSummary } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    endpoints: EndpointSummary[];
}>();
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Endpoints"
            description="Create a capture URL, then point a webhook at it."
        />

        <Card>
            <CardHeader>
                <CardTitle>New endpoint</CardTitle>
                <CardDescription>
                    A random 256-bit token is generated for the public URL.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <Form
                    v-bind="EndpointController.store.form()"
                    class="flex flex-col gap-4 sm:flex-row sm:items-end"
                    v-slot="{ errors, processing }"
                    data-test="create-endpoint-form"
                >
                    <div class="grid min-w-0 flex-1 gap-2">
                        <Label for="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            required
                            maxlength="255"
                            placeholder="Stripe"
                            data-test="endpoint-name"
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid w-full gap-2 sm:w-36">
                        <Label for="retention_days">Retention (days)</Label>
                        <Input
                            id="retention_days"
                            name="retention_days"
                            type="number"
                            min="1"
                            max="365"
                            :default-value="7"
                        />
                        <InputError :message="errors.retention_days" />
                    </div>
                    <Button
                        :disabled="processing"
                        data-test="create-endpoint-button"
                    >
                        Create
                    </Button>
                </Form>
            </CardContent>
        </Card>

        <div
            v-if="endpoints.length === 0"
            class="text-muted-foreground flex flex-col items-center gap-2 rounded-xl border border-dashed p-10 text-center text-sm"
        >
            <Webhook class="size-8 opacity-50" />
            <p>No endpoints yet. Create one to get a capture URL.</p>
        </div>

        <div v-else class="grid gap-4 md:grid-cols-2">
            <Card v-for="endpoint in endpoints" :key="endpoint.id">
                <CardHeader>
                    <CardTitle>
                        <Link :href="show(endpoint.id)" class="hover:underline">
                            {{ endpoint.name }}
                        </Link>
                    </CardTitle>
                    <CardDescription>
                        Keep {{ endpoint.retention_days }} days
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <CopyCaptureUrl :token="endpoint.token" />
                </CardContent>
            </Card>
        </div>
    </div>
</template>
