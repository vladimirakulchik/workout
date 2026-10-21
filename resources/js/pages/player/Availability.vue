<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AvailabilityController from '@/actions/App/Http/Controllers/Player/AvailabilityController';
import AvailabilityGrid from '@/components/AvailabilityGrid.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { edit } from '@/routes/player/availability';
import type { AvailabilityRange } from '@/types';

type ProfileOption = { id: number; name: string };

const props = defineProps<{
    profile: ProfileOption | null;
    profiles: ProfileOption[];
    ranges: AvailabilityRange[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Best Times', href: edit() }],
    },
});

const form = useForm({
    profile_id: props.profile?.id ?? null,
    ranges: props.ranges,
});

function switchProfile(event: Event): void {
    router.get(edit.url({ query: { profile: (event.target as HTMLSelectElement).value } }));
}

function submit(): void {
    form.put(AvailabilityController.update.url(), { preserveScroll: true });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4">
        <Head title="Best Times" />

        <div>
            <h1 class="text-2xl font-semibold">Best Times</h1>
            <p class="text-sm text-muted-foreground">
                Choose the days and hours that work best for training. Trainers see these when planning sessions.
            </p>
        </div>

        <p v-if="!profile" class="text-sm text-muted-foreground" data-test="no-profile">
            There is no player profile to set times for yet.
        </p>

        <form v-else class="grid max-w-4xl gap-6" data-test="availability-form" @submit.prevent="submit">
            <div v-if="profiles.length > 1" class="grid max-w-xs gap-2">
                <Label for="profile-switcher">Setting times for</Label>
                <select
                    id="profile-switcher"
                    class="h-10 rounded-sm border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-brand focus-visible:ring-[3px] focus-visible:ring-brand/25"
                    :value="profile.id"
                    data-test="profile-switcher"
                    @change="switchProfile"
                >
                    <option v-for="option in profiles" :key="option.id" :value="option.id">{{ option.name }}</option>
                </select>
            </div>
            <p v-else class="text-sm">
                Setting times for <strong>{{ profile.name }}</strong>
            </p>

            <AvailabilityGrid v-model="form.ranges" :errors="form.errors" />

            <div>
                <Button type="submit" :disabled="form.processing" data-test="save-availability">
                    <Spinner v-if="form.processing" /> Save
                </Button>
            </div>
        </form>
    </div>
</template>
