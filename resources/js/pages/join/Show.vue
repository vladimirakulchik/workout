<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'You have been invited',
        description: 'Your invitation link is valid.',
    },
});

defineProps<{
    trainerName: string;
    type: string;
    typeLabel: string;
}>();
</script>

<template>
    <Head title="Join" />

    <div class="space-y-4 text-center" data-test="join-valid">
        <p class="text-sm">
            <span class="font-medium">{{ trainerName }}</span> invited you to
            join as a {{ typeLabel.toLowerCase() }}.
        </p>
        <TextLink :href="login()">Log in to continue</TextLink>
    </div>
</template>
