<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AvailabilityController from '@/actions/App/Http/Controllers/Coach/AvailabilityController';
import AvailabilityGrid from '@/components/AvailabilityGrid.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/coach/availability';
import type { AvailabilityRange } from '@/types';

const props = defineProps<{
    ranges: AvailabilityRange[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Times', href: edit() }],
    },
});

const form = useForm({
    ranges: props.ranges,
});

function submit(): void {
    form.put(AvailabilityController.update.url(), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4">
        <Head title="My Times" />

        <div>
            <h1 class="text-2xl font-semibold">My Times</h1>
            <p class="text-sm text-muted-foreground">
                Choose the days and hours you can coach. Trainers see these when planning sessions.
            </p>
        </div>

        <form class="grid max-w-4xl gap-6" data-test="availability-form" @submit.prevent="submit">
            <AvailabilityGrid v-model="form.ranges" :errors="form.errors" />

            <div>
                <Button type="submit" :disabled="form.processing" data-test="save-availability">
                    <Spinner v-if="form.processing" /> Save
                </Button>
            </div>
        </form>
    </div>
</template>
