<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name;
</script>

<template>
    <div
        class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
    >
        <div class="w-full max-w-sm">
            <div
                class="flex flex-col gap-8 rounded-lg border border-border bg-card p-6 shadow-card-soft md:p-8"
            >
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 rounded-md font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    >
                        <div
                            class="flex size-12 items-center justify-center rounded-md bg-brand"
                        >
                            <AppLogoIcon class="size-8 fill-current text-white" />
                        </div>
                        <span class="text-card-title text-foreground">{{
                            appName
                        }}</span>
                    </Link>
                    <div class="space-y-2 text-center">
                        <h1 class="text-block-title text-foreground">
                            {{ title }}
                        </h1>
                        <p class="text-center text-body text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
