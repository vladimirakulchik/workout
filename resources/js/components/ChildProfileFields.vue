<script setup lang="ts">
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export type ChildFormData = {
    first_name: string;
    last_name: string;
    birth_date: string;
    gender: string;
    school: string;
    photo: File | null;
    allow_token_spending: boolean;
};

const props = defineProps<{
    form: ChildFormData & { errors: Partial<Record<string, string>> };
    photoUrl?: string;
}>();

const previewUrl = ref<string | null>(null);

function onPhotoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;

    props.form.photo = file;
    previewUrl.value = file ? URL.createObjectURL(file) : null;
}

const selectClass =
    'border-input bg-transparent focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm shadow-xs outline-none focus-visible:ring-[3px]';
</script>

<template>
    <div class="grid gap-6">
        <div class="flex items-center gap-4">
            <Avatar class="size-20 rounded-full">
                <AvatarImage
                    :src="previewUrl ?? photoUrl ?? '/images/default-avatar.svg'"
                    alt="Child photo"
                />
            </Avatar>
            <div class="grid gap-2">
                <Label for="photo">Photo (optional)</Label>
                <Input
                    id="photo"
                    type="file"
                    accept="image/png,image/jpeg,image/webp"
                    aria-describedby="photo-help"
                    @change="onPhotoChange"
                />
                <p id="photo-help" class="text-xs text-muted-foreground">
                    PNG, JPG or WebP, up to 2 MB.
                </p>
                <InputError :message="form.errors.photo" />
            </div>
        </div>

        <div class="grid gap-6 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="first_name">First name</Label>
                <Input
                    id="first_name"
                    v-model="form.first_name"
                    required
                    autocomplete="off"
                />
                <InputError :message="form.errors.first_name" />
            </div>
            <div class="grid gap-2">
                <Label for="last_name">Last name</Label>
                <Input
                    id="last_name"
                    v-model="form.last_name"
                    required
                    autocomplete="off"
                />
                <InputError :message="form.errors.last_name" />
            </div>
            <div class="grid gap-2">
                <Label for="birth_date">Birth date</Label>
                <Input
                    id="birth_date"
                    v-model="form.birth_date"
                    type="date"
                    required
                />
                <InputError :message="form.errors.birth_date" />
            </div>
            <div class="grid gap-2">
                <Label for="gender">Gender</Label>
                <select
                    id="gender"
                    v-model="form.gender"
                    required
                    :class="selectClass"
                >
                    <option value="" disabled>Select…</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                    <option value="other">Other</option>
                </select>
                <InputError :message="form.errors.gender" />
            </div>
            <div class="grid gap-2 sm:col-span-2">
                <Label for="school">School (optional)</Label>
                <Input id="school" v-model="form.school" autocomplete="off" />
                <InputError :message="form.errors.school" />
            </div>
        </div>

        <div class="flex items-center gap-3">
            <Switch
                id="allow_token_spending"
                v-model="form.allow_token_spending"
            />
            <Label for="allow_token_spending">
                Allow this child to spend tokens
            </Label>
        </div>
    </div>
</template>
