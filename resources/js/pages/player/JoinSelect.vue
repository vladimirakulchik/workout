<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import JoinController from '@/actions/App/Http/Controllers/JoinController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Choose who joins',
        description: 'Select who will train with this trainer.',
    },
});

type Profile = {
    id: number;
    name: string;
    is_child: boolean;
    connected: boolean;
};

const props = defineProps<{
    trainerName: string;
    code: string;
    profiles: Profile[];
}>();

const form = useForm<{ profile_ids: number[] }>({
    profile_ids: props.profiles
        .filter((profile) => !profile.connected)
        .map((profile) => profile.id),
});

function toggle(id: number, checked: boolean | 'indeterminate'): void {
    form.profile_ids = checked === true
        ? [...form.profile_ids, id]
        : form.profile_ids.filter((value) => value !== id);
}

function submit(): void {
    form.submit(JoinController.associate({ code: props.code }));
}
</script>

<template>
    <Head title="Choose who joins" />

    <form
        class="flex flex-col gap-6"
        data-test="join-select-form"
        @submit.prevent="submit"
    >
        <h2 class="text-center text-lg font-medium">
            Who will train with {{ trainerName }}?
        </h2>

        <ul class="grid gap-3">
            <li v-for="profile in profiles" :key="profile.id">
                <Label
                    :for="`profile-${profile.id}`"
                    class="flex items-center gap-3 rounded-md border p-3"
                >
                    <Checkbox
                        :id="`profile-${profile.id}`"
                        :model-value="form.profile_ids.includes(profile.id)"
                        :disabled="profile.connected"
                        @update:model-value="toggle(profile.id, $event)"
                    />
                    <span class="flex-1">
                        {{ profile.is_child ? profile.name : `Me (${profile.name})` }}
                    </span>
                    <span
                        v-if="profile.connected"
                        class="text-xs text-muted-foreground"
                    >
                        Already connected
                    </span>
                </Label>
            </li>
        </ul>

        <InputError :message="form.errors.profile_ids" />

        <Button
            type="submit"
            class="w-full"
            :disabled="form.processing"
            data-test="join-select-button"
        >
            <Spinner v-if="form.processing" />
            Continue
        </Button>
    </form>
</template>
