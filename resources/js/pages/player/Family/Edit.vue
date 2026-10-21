<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import ChildProfileController from '@/actions/App/Http/Controllers/Player/ChildProfileController';
import ChildProfileFields from '@/components/ChildProfileFields.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { index } from '@/routes/player/family';

const props = defineProps<{
    child: {
        id: number;
        first_name: string;
        last_name: string;
        birth_date: string | null;
        gender: string;
        school: string | null;
        photo_url: string;
        allow_token_spending: boolean;
        has_login: boolean;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Players / Family', href: index() },
            { title: 'Edit child', href: '#' },
        ],
    },
});

const form = useForm({
    first_name: props.child.first_name,
    last_name: props.child.last_name,
    birth_date: props.child.birth_date ?? '',
    gender: props.child.gender,
    school: props.child.school ?? '',
    photo: null as File | null,
    allow_token_spending: props.child.allow_token_spending,
});

function submit(): void {
    form.transform((data) => ({ ...data, _method: 'put' })).post(
        ChildProfileController.update.url({ childProfile: props.child.id }),
        { forceFormData: true },
    );
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4">
        <Head title="Edit child" />
        <h1 class="text-2xl font-semibold">Edit {{ child.first_name }}</h1>

        <form
            class="grid max-w-2xl gap-6"
            data-test="edit-child-form"
            @submit.prevent="submit"
        >
            <ChildProfileFields :form="form" :photo-url="child.photo_url" />

            <div class="flex gap-3">
                <Button type="submit" :disabled="form.processing" data-test="save-child">
                    <Spinner v-if="form.processing" />
                    Save
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="index()">Cancel</Link>
                </Button>
            </div>
        </form>
    </div>
</template>
