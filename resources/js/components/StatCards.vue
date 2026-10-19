<script setup lang="ts">
import type { StatCard } from '@/types';

defineProps<{
    stats: StatCard[];
}>();
</script>

<template>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <section
            v-for="stat in stats"
            :key="stat.label"
            class="rounded-xl border border-sidebar-border/70 p-5 dark:border-sidebar-border"
        >
            <h2 class="text-sm text-muted-foreground">{{ stat.label }}</h2>
            <p class="mt-2 text-3xl font-semibold">{{ stat.value }}</p>
        </section>
    </div>
</template>
