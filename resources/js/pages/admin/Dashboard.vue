<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StatCards from '@/components/StatCards.vue';
import { dashboard } from '@/routes/admin';
import type { StatCard } from '@/types';

defineProps<{
    stats: StatCard[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <Head title="Admin dashboard" />
        <h1 class="text-2xl font-semibold">Admin dashboard</h1>
        <StatCards :stats="stats" />
    </div>
</template>
