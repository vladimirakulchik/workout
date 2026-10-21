<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import JoinController from '@/actions/App/Http/Controllers/JoinController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Join as a coach',
        description: 'Create your coach account.',
    },
});

const props = defineProps<{
    trainerName: string;
    code: string;
    email: string;
    name: string | null;
    passwordRules: string;
}>();

const [firstName = '', ...lastNameParts] = (props.name ?? '').trim().split(/\s+/);
const lastName = lastNameParts.join(' ');
</script>

<template>
    <Head title="Join as a coach" />

    <Form
        v-bind="JoinController.registerCoach.form({ code: props.code })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        data-test="join-coach-register-form"
    >
        <p class="text-center text-sm" data-test="join-valid">
            <span class="font-medium">{{ trainerName }}</span> invited you to
            join as a coach.
        </p>

        <div class="grid gap-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="first_name">First name</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        required
                        :default-value="firstName"
                        autocomplete="given-name"
                        placeholder="First name"
                    />
                    <InputError :message="errors.first_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="last_name">Last name</Label>
                    <Input
                        id="last_name"
                        name="last_name"
                        required
                        :default-value="lastName"
                        autocomplete="family-name"
                        placeholder="Last name"
                    />
                    <InputError :message="errors.last_name" />
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="email">Email address</Label>
                <Input
                    id="email"
                    type="email"
                    :model-value="email"
                    readonly
                    aria-describedby="email-hint"
                    autocomplete="email"
                />
                <p id="email-hint" class="text-xs text-muted-foreground">
                    The invitation was sent to this address, so it cannot be changed.
                </p>
            </div>

            <div class="grid gap-2">
                <Label for="phone">Phone (optional)</Label>
                <Input
                    id="phone"
                    type="tel"
                    name="phone"
                    autocomplete="tel"
                    placeholder="+1 555 123 4567"
                />
                <InputError :message="errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    placeholder="Password"
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
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="join-coach-register-button"
            >
                <Spinner v-if="processing" />
                Create account
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink :href="login()">Log in</TextLink>
        </div>
    </Form>
</template>
