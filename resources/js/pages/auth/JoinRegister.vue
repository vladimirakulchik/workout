<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import JoinController from '@/actions/App/Http/Controllers/JoinController';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';

defineOptions({
    layout: {
        title: 'Join your trainer',
        description: 'Create your account to connect with your trainer.',
    },
});

const props = defineProps<{
    trainerName: string;
    code: string;
    passwordRules: string;
}>();

const isParent = ref(false);
const alsoTrains = ref(false);
</script>

<template>
    <Head title="Join" />

    <Form
        v-bind="JoinController.register.form({ code: props.code })"
        :transform="
            (data) => ({
                ...data,
                is_parent: isParent,
                also_trains: isParent && alsoTrains,
            })
        "
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        data-test="join-register-form"
    >
        <p class="text-center text-sm" data-test="join-valid">
            <span class="font-medium">{{ trainerName }}</span> invited you to
            join.
        </p>

        <div class="grid gap-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="first_name">First name</Label>
                    <Input
                        id="first_name"
                        name="first_name"
                        required
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
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
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
                    :passwordrules="passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Label for="is_parent" class="flex items-center space-x-3">
                <Checkbox id="is_parent" v-model="isParent" />
                <span>I am registering my child</span>
            </Label>

            <Label
                v-if="isParent"
                for="also_trains"
                class="flex items-center space-x-3"
            >
                <Checkbox id="also_trains" v-model="alsoTrains" />
                <span>I also train</span>
            </Label>

            <div v-if="isParent" class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="player_first_name">Child's first name</Label>
                    <Input
                        id="player_first_name"
                        name="player_first_name"
                        required
                        placeholder="First name"
                    />
                    <InputError :message="errors.player_first_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="player_last_name">Child's last name</Label>
                    <Input
                        id="player_last_name"
                        name="player_last_name"
                        required
                        placeholder="Last name"
                    />
                    <InputError :message="errors.player_last_name" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="birth_date">
                        {{ isParent ? "Child's birth date" : 'Birth date' }}
                    </Label>
                    <Input id="birth_date" type="date" name="birth_date" required />
                    <InputError :message="errors.birth_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="gender">
                        {{ isParent ? "Child's gender" : 'Gender' }}
                    </Label>
                    <select
                        id="gender"
                        name="gender"
                        required
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    >
                        <option value="" disabled selected>Select</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                    <InputError :message="errors.gender" />
                </div>
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :disabled="processing"
                data-test="join-register-button"
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
