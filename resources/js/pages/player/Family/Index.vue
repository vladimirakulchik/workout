<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import ChildProfileController from '@/actions/App/Http/Controllers/Player/ChildProfileController';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/player/family';

type Child = {
    id: number;
    name: string;
    age: number | null;
    school: string | null;
    photo_url: string;
    has_login: boolean;
    allow_token_spending: boolean;
    trainers: string[];
};

defineProps<{
    children: Child[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Players / Family', href: index() }],
    },
});

function remove(child: Child): void {
    if (
        window.confirm(
            `Remove ${child.name}? Their profile${child.has_login ? ' and login' : ''} will be deactivated.`,
        )
    ) {
        router.delete(ChildProfileController.destroy.url({ childProfile: child.id }));
    }
}
</script>

<template>
    <div class="flex flex-1 flex-col gap-4 p-4">
        <Head title="Family" />

        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-semibold">Players / Family</h1>
            <Button as-child data-test="add-child">
                <Link :href="ChildProfileController.create.url()">
                    <Plus /> Add child
                </Link>
            </Button>
        </div>

        <p v-if="children.length === 0" class="text-muted-foreground">
            You have not added any children yet.
        </p>

        <ul class="grid gap-3">
            <li
                v-for="child in children"
                :key="child.id"
                class="flex flex-wrap items-center gap-4 rounded-md border p-4"
                :data-test="`child-${child.id}`"
            >
                <Avatar class="size-12 rounded-full">
                    <AvatarImage :src="child.photo_url" :alt="child.name" />
                </Avatar>
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ child.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        Age {{ child.age ?? '—' }}<template v-if="child.school"> · {{ child.school }}</template>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        Trainers:
                        {{ child.trainers.length ? child.trainers.join(', ') : 'none yet' }}
                    </p>
                </div>
                <Badge v-if="child.has_login" variant="secondary">Has login</Badge>
                <Button variant="outline" as-child>
                    <Link :href="ChildProfileController.edit.url({ childProfile: child.id })">
                        Edit
                    </Link>
                </Button>
                <Button variant="destructive" @click="remove(child)">
                    Remove
                </Button>
            </li>
        </ul>
    </div>
</template>
