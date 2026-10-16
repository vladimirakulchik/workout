<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import ForcePasswordChangeController from '@/actions/App/Http/Controllers/Auth/ForcePasswordChangeController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';

defineOptions({
    layout: {
        title: 'Change your password',
        description:
            'For security reasons you must choose a new password before continuing.',
    },
});

defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Change your password" />

    <Form
        v-bind="ForcePasswordChangeController.update.form()"
        :reset-on-error="[
            'current_password',
            'password',
            'password_confirmation',
        ]"
        class="flex flex-col gap-6"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-2">
            <Label for="current_password">Current password</Label>
            <PasswordInput
                id="current_password"
                name="current_password"
                required
                v-focus
                autocomplete="current-password"
                placeholder="Current password"
            />
            <InputError :message="errors.current_password" />
        </div>

        <div class="grid gap-2">
            <Label for="password">New password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="new-password"
                placeholder="New password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-2">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                required
                autocomplete="new-password"
                placeholder="Confirm password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            class="w-full"
            :disabled="processing"
            data-test="force-password-change-button"
        >
            <Spinner v-if="processing" />
            Change password
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
