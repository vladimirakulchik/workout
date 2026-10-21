<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import StatCards from '@/components/StatCards.vue';
import { dashboard } from '@/routes/player';
import type { StatCard } from '@/types';

defineProps<{
    stats: StatCard[];
    wallet: {
        read_only: boolean;
        balance: number | null;
        can_purchase: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <Head title="Player dashboard" />
        <h1 class="text-2xl font-semibold">Player dashboard</h1>
        <StatCards :stats="stats" />
        <section
            v-if="wallet.read_only"
            aria-labelledby="wallet-heading"
            class="rounded-md border p-4"
            data-test="wallet-placeholder"
        >
            <h2 id="wallet-heading" class="text-lg font-medium">Tokens</h2>
            <p class="text-sm text-muted-foreground">
                Your token balance will appear here.
            </p>
        </section>
    </div>
</template>
