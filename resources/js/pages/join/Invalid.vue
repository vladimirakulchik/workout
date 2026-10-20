<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { home } from '@/routes';

defineOptions({
    layout: {
        title: 'Link unavailable',
        description: 'We could not open this invitation link.',
    },
});

defineProps<{
    message: string;
}>();
</script>

<template>
    <Head title="Link unavailable" />

    <div class="space-y-4 text-center">
        <p role="alert" class="text-sm text-muted-foreground" data-test="join-error">
            {{ message }}
        </p>
        <TextLink :href="home()">Back to home</TextLink>
    </div>
</template>
