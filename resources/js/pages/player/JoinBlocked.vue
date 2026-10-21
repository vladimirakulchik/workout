<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { dashboard } from '@/routes/player';

defineProps<{
    trainerName: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <Head title="Registration needs a parent" />
        <h1 class="text-2xl font-semibold">Registration needs a parent</h1>
        <p role="alert" class="text-muted-foreground" data-test="join-blocked">
            Ask your parent to register you with this trainer.
        </p>
        <p class="text-sm text-muted-foreground">
            We have let your parent know that you want to join {{ trainerName }}.
        </p>
        <div>
            <TextLink :href="dashboard()">Back to dashboard</TextLink>
        </div>
    </div>
</template>
