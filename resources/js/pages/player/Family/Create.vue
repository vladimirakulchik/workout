<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import ChildProfileController from '@/actions/App/Http/Controllers/Player/ChildProfileController';
import ChildProfileFields from '@/components/ChildProfileFields.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Switch } from '@/components/ui/switch';
import { index } from '@/routes/player/family';

type Trainer = { id: number; name: string };

const props = defineProps<{
    parentLastName: string;
    trainers: Trainer[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Players / Family', href: index() },
            { title: 'Add child', href: '#' },
        ],
    },
});

const form = useForm({
    first_name: '',
    last_name: props.parentLastName,
    birth_date: '',
    gender: '',
    school: '',
    photo: null as File | null,
    allow_token_spending: false,
    trainer_ids: (props.trainers.length === 1 ? [props.trainers[0].id] : []) as number[],
    has_login: false,
    email: '',
    password: '',
    password_confirmation: '',
    confirm: false,
});

const duplicateWarning = ref<string | null>(null);

function toggleTrainer(id: number, checked: boolean | 'indeterminate'): void {
    form.trainer_ids =
        checked === true
            ? [...form.trainer_ids, id]
            : form.trainer_ids.filter((value) => value !== id);
}

function submit(confirm = false): void {
    form.confirm = confirm;
    form.post(ChildProfileController.store.url(), {
        forceFormData: true,
        onError: (errors) => {
            duplicateWarning.value = errors.duplicate ?? null;
        },
        onSuccess: () => {
            duplicateWarning.value = null;
        },
    });
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4">
        <Head title="Add child" />
        <h1 class="text-2xl font-semibold">Add child</h1>

        <form
            class="grid max-w-2xl gap-6"
            data-test="create-child-form"
            @submit.prevent="submit(false)"
        >
            <ChildProfileFields :form="form" />

            <fieldset v-if="trainers.length === 1" class="grid gap-2">
                <legend class="text-sm font-medium">Trainer</legend>
                <div class="flex items-center gap-3">
                    <Checkbox
                        id="trainer-single"
                        :model-value="form.trainer_ids.length > 0"
                        @update:model-value="toggleTrainer(trainers[0].id, $event)"
                    />
                    <Label for="trainer-single">
                        Connect this child to {{ trainers[0].name }}
                    </Label>
                </div>
            </fieldset>

            <fieldset v-else-if="trainers.length > 1" class="grid gap-2">
                <legend class="text-sm font-medium">
                    Which trainers should this child train with?
                </legend>
                <div
                    v-for="trainer in trainers"
                    :key="trainer.id"
                    class="flex items-center gap-3"
                >
                    <Checkbox
                        :id="`trainer-${trainer.id}`"
                        :model-value="form.trainer_ids.includes(trainer.id)"
                        @update:model-value="toggleTrainer(trainer.id, $event)"
                    />
                    <Label :for="`trainer-${trainer.id}`">{{ trainer.name }}</Label>
                </div>
            </fieldset>
            <InputError :message="form.errors.trainer_ids" />

            <div class="grid gap-4 rounded-md border p-4">
                <div class="flex items-center gap-3">
                    <Switch id="has_login" v-model="form.has_login" />
                    <Label for="has_login">
                        Give {{ form.first_name || 'this child' }} a login
                    </Label>
                </div>

                <template v-if="form.has_login">
                    <div class="grid gap-2">
                        <Label for="email">Email</Label>
                        <Input
                            id="email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                            required
                        />
                        <p class="text-xs text-muted-foreground">
                            A verification email is sent to this address.
                        </p>
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password">Password</Label>
                        <PasswordInput
                            id="password"
                            v-model="form.password"
                            autocomplete="new-password"
                            required
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="password_confirmation">Confirm password</Label>
                        <PasswordInput
                            id="password_confirmation"
                            v-model="form.password_confirmation"
                            autocomplete="new-password"
                            required
                        />
                    </div>
                </template>
            </div>

            <div
                v-if="duplicateWarning"
                role="alert"
                class="grid gap-3 rounded-md border border-amber-500/50 p-4"
                data-test="duplicate-warning"
            >
                <p class="text-sm">{{ duplicateWarning }}</p>
                <div class="flex gap-3">
                    <Button
                        type="button"
                        :disabled="form.processing"
                        data-test="confirm-duplicate"
                        @click="submit(true)"
                    >
                        Create anyway
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="duplicateWarning = null"
                    >
                        Go back
                    </Button>
                </div>
            </div>

            <div v-else class="flex gap-3">
                <Button type="submit" :disabled="form.processing" data-test="save-child">
                    <Spinner v-if="form.processing" />
                    Add child
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
