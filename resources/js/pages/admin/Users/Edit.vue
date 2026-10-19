<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { index } from '@/routes/admin/users';

type RoleProfile = {
    school?: string | null;
    jersey_number?: string | null;
    emergency_contact_name?: string | null;
    emergency_contact_phone?: string | null;
    skill_level?: string | null;
    bio?: string | null;
    credentials?: string | null;
    certifications?: string | null;
    is_public?: boolean;
    business_name?: string | null;
    address?: string | null;
    website?: string | null;
    description?: string | null;
};

const props = defineProps<{
    managedUser: {
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        phone: string | null;
        role: string;
        role_label: string;
        status: string;
        created_at: string | null;
        photo_url: string;
    };
    roleProfile: RoleProfile | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Users', href: index() },
            { title: 'Edit user', href: '#' },
        ],
    },
});

const previewUrl = ref<string | null>(null);
const isPublic = ref(Boolean(props.roleProfile?.is_public));

function onPhotoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    previewUrl.value = file ? URL.createObjectURL(file) : null;
}

const textareaClass =
    'border-input bg-transparent placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full rounded-md border px-3 py-2 text-sm shadow-xs outline-none focus-visible:ring-[3px]';
</script>

<template>
    <Head title="Edit user" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <h1 class="text-2xl font-semibold">
            Edit {{ managedUser.first_name }} {{ managedUser.last_name }}
        </h1>

        <Form
            v-bind="UserController.update.form(managedUser.id)"
            class="max-w-3xl space-y-8"
            enctype="multipart/form-data"
            v-slot="{ errors, processing }"
        >
            <section class="space-y-6" aria-labelledby="account-heading">
                <Heading
                    id="account-heading"
                    variant="small"
                    title="Account"
                    description="Email and role cannot be changed"
                />

                <div class="flex items-center gap-4">
                    <Avatar class="size-20 rounded-full">
                        <AvatarImage
                            :src="previewUrl ?? managedUser.photo_url"
                            :alt="`${managedUser.first_name} ${managedUser.last_name}`"
                        />
                    </Avatar>
                    <div class="grid gap-2">
                        <Label for="photo">Photo</Label>
                        <Input
                            id="photo"
                            type="file"
                            name="photo"
                            accept="image/png,image/jpeg,image/webp"
                            aria-describedby="photo-help"
                            @change="onPhotoChange"
                        />
                        <p id="photo-help" class="text-xs text-muted-foreground">
                            PNG, JPG or WebP, up to 2 MB.
                        </p>
                        <InputError :message="errors.photo" />
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="first_name">First name</Label>
                        <Input
                            id="first_name"
                            name="first_name"
                            :default-value="managedUser.first_name"
                            required
                        />
                        <InputError :message="errors.first_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="last_name">Last name</Label>
                        <Input
                            id="last_name"
                            name="last_name"
                            :default-value="managedUser.last_name"
                            required
                        />
                        <InputError :message="errors.last_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Phone</Label>
                        <Input
                            id="phone"
                            type="tel"
                            name="phone"
                            :default-value="managedUser.phone ?? ''"
                            placeholder="+15551234567"
                        />
                        <InputError :message="errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            :model-value="managedUser.email"
                            readonly
                            aria-readonly="true"
                            class="opacity-70"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <Input
                            id="role"
                            :model-value="managedUser.role_label"
                            readonly
                            aria-readonly="true"
                            class="opacity-70"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="created_at">Member since</Label>
                        <Input
                            id="created_at"
                            :model-value="managedUser.created_at ?? ''"
                            readonly
                            aria-readonly="true"
                            class="opacity-70"
                        />
                    </div>
                </div>
            </section>

            <section
                v-if="managedUser.role === 'player' && roleProfile"
                class="space-y-6"
                aria-labelledby="player-heading"
            >
                <Heading
                    id="player-heading"
                    variant="small"
                    title="Player details"
                    description="School and emergency contact"
                />

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="school">School</Label>
                        <Input
                            id="school"
                            name="school"
                            :default-value="roleProfile.school ?? ''"
                        />
                        <InputError :message="errors.school" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="jersey_number">Jersey number</Label>
                        <Input
                            id="jersey_number"
                            name="jersey_number"
                            inputmode="numeric"
                            :default-value="roleProfile.jersey_number ?? ''"
                        />
                        <InputError :message="errors.jersey_number" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="emergency_contact_name">
                            Emergency contact name
                        </Label>
                        <Input
                            id="emergency_contact_name"
                            name="emergency_contact_name"
                            required
                            :default-value="roleProfile.emergency_contact_name ?? ''"
                        />
                        <InputError :message="errors.emergency_contact_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="emergency_contact_phone">
                            Emergency contact phone
                        </Label>
                        <Input
                            id="emergency_contact_phone"
                            type="tel"
                            name="emergency_contact_phone"
                            :default-value="roleProfile.emergency_contact_phone ?? ''"
                        />
                        <InputError :message="errors.emergency_contact_phone" />
                    </div>
                </div>
            </section>

            <section
                v-if="managedUser.role === 'coach'"
                class="space-y-6"
                aria-labelledby="coach-heading"
            >
                <Heading
                    id="coach-heading"
                    variant="small"
                    title="Coach profile"
                    description="Shown to players when the profile is public"
                />

                <div class="grid gap-2">
                    <Label for="bio">Bio</Label>
                    <textarea
                        id="bio"
                        name="bio"
                        :class="textareaClass"
                        :value="roleProfile?.bio ?? ''"
                    />
                    <InputError :message="errors.bio" />
                </div>

                <div class="grid gap-2">
                    <Label for="credentials">Credentials</Label>
                    <textarea
                        id="credentials"
                        name="credentials"
                        :class="textareaClass"
                        :value="roleProfile?.credentials ?? ''"
                    />
                    <InputError :message="errors.credentials" />
                </div>

                <div class="grid gap-2">
                    <Label for="certifications">Certifications</Label>
                    <textarea
                        id="certifications"
                        name="certifications"
                        :class="textareaClass"
                        :value="roleProfile?.certifications ?? ''"
                    />
                    <InputError :message="errors.certifications" />
                </div>

                <div class="flex items-center gap-3">
                    <input type="hidden" name="is_public" :value="isPublic ? 1 : 0" />
                    <Switch id="is_public" v-model="isPublic" />
                    <Label for="is_public">Public profile</Label>
                </div>
                <InputError :message="errors.is_public" />
            </section>

            <section
                v-if="managedUser.role === 'trainer'"
                class="space-y-6"
                aria-labelledby="trainer-heading"
            >
                <Heading
                    id="trainer-heading"
                    variant="small"
                    title="Business"
                    description="Training business details"
                />

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="business_name">Business name</Label>
                        <Input
                            id="business_name"
                            name="business_name"
                            required
                            :default-value="roleProfile?.business_name ?? ''"
                        />
                        <InputError :message="errors.business_name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="website">Website</Label>
                        <Input
                            id="website"
                            type="url"
                            name="website"
                            placeholder="https://"
                            :default-value="roleProfile?.website ?? ''"
                        />
                        <InputError :message="errors.website" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Address</Label>
                    <Input
                        id="address"
                        name="address"
                        :default-value="roleProfile?.address ?? ''"
                    />
                    <InputError :message="errors.address" />
                </div>

                <div class="grid gap-2">
                    <Label for="description">Description</Label>
                    <textarea
                        id="description"
                        name="description"
                        :class="textareaClass"
                        :value="roleProfile?.description ?? ''"
                    />
                    <InputError :message="errors.description" />
                </div>
            </section>

            <div class="flex items-center gap-4">
                <Button :disabled="processing" data-test="update-user-button">
                    Save
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Back to users</Link>
                </Button>
            </div>
        </Form>
    </div>
</template>
