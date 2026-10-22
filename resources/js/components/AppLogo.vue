<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
const name = page.props.name;
const logoUrl = computed(() => page.props.branding?.logo_url ?? null);
</script>

<template>
    <img
        v-if="logoUrl"
        :src="logoUrl"
        :alt="`${name} logo`"
        class="size-8 shrink-0 rounded-md object-contain"
    />
    <div
        v-else
        class="flex aspect-square size-8 items-center justify-center rounded-md bg-brand text-sidebar-primary-foreground"
    >
        <AppLogoIcon class="size-5 fill-current text-white" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
