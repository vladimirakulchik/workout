<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { leave } from '@/routes/impersonate';

const page = usePage();
const impersonating = computed(() => page.props.auth.impersonating);
const processing = ref(false);

function exitImpersonation(): void {
    router.delete(leave.url(), {
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
    });
}
</script>

<template>
    <div
        v-if="impersonating"
        role="status"
        class="sticky top-0 z-50 flex flex-wrap items-center justify-center gap-3 bg-orange-600 px-4 py-2 text-sm font-medium text-white"
        data-test="impersonation-banner"
    >
        <span>Viewing as {{ impersonating.name }}</span>
        <span aria-hidden="true">|</span>
        <Button
            size="sm"
            variant="secondary"
            :disabled="processing"
            data-test="exit-impersonation"
            @click="exitImpersonation"
        >
            Exit Impersonation
        </Button>
    </div>
</template>
