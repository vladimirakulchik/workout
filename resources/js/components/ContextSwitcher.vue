<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Player/ContextController';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { ContextOption } from '@/types';

const page = usePage();
const context = computed(() => page.props.context ?? null);

const keyOf = (option: ContextOption): string =>
    `${option.player_id}:${option.trainer_id}`;

const hasOptions = computed(
    () =>
        (context.value?.options.yours.length ?? 0) +
            (context.value?.options.children.length ?? 0) >
        0,
);

const currentKey = computed(() =>
    context.value?.current ? keyOf(context.value.current) : '',
);

const switchContext = (key: unknown): void => {
    const option = [
        ...(context.value?.options.yours ?? []),
        ...(context.value?.options.children ?? []),
    ].find((candidate) => keyOf(candidate) === key);

    if (!option || keyOf(option) === currentKey.value) {
        return;
    }

    router.post(
        update(),
        { player_id: option.player_id, trainer_id: option.trainer_id },
        { preserveScroll: true },
    );
};
</script>

<template>
    <DropdownMenu v-if="context && hasOptions">
        <DropdownMenuTrigger as-child>
            <Button
                variant="outline"
                size="sm"
                class="ml-auto max-w-64 justify-between gap-2"
                data-test="context-switcher"
                aria-label="Switch training context"
            >
                <span class="truncate">{{
                    context.current?.label ?? 'Select context'
                }}</span>
                <ChevronsUpDown class="size-4 shrink-0 opacity-60" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-64">
            <DropdownMenuRadioGroup
                :model-value="currentKey"
                @update:model-value="switchContext"
            >
                <DropdownMenuGroup v-if="context.options.yours.length">
                    <DropdownMenuLabel>Your Training</DropdownMenuLabel>
                    <DropdownMenuRadioItem
                        v-for="option in context.options.yours"
                        :key="keyOf(option)"
                        :value="keyOf(option)"
                    >
                        {{ option.label }}
                    </DropdownMenuRadioItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator
                    v-if="
                        context.options.yours.length &&
                        context.options.children.length
                    "
                />
                <DropdownMenuGroup v-if="context.options.children.length">
                    <DropdownMenuLabel>
                        Your Children's Training
                    </DropdownMenuLabel>
                    <DropdownMenuRadioItem
                        v-for="option in context.options.children"
                        :key="keyOf(option)"
                        :value="keyOf(option)"
                    >
                        {{ option.label }}
                    </DropdownMenuRadioItem>
                </DropdownMenuGroup>
            </DropdownMenuRadioGroup>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
