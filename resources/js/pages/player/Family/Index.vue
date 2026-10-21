<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import ChildProfileController from '@/actions/App/Http/Controllers/Player/ChildProfileController';
import ChildTrainerController from '@/actions/App/Http/Controllers/Player/ChildTrainerController';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/player/family';

type ChildTrainer = {
    id: number;
    name: string;
    connected_at: string;
};

type MyTrainer = {
    id: number;
    name: string;
};

type Child = {
    id: number;
    name: string;
    age: number | null;
    school: string | null;
    photo_url: string;
    has_login: boolean;
    allow_token_spending: boolean;
    trainers: ChildTrainer[];
};

defineProps<{
    children: Child[];
    myTrainers: MyTrainer[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Players / Family', href: index() }],
    },
});

const addingFor = ref<Child | null>(null);
const removing = ref<{ child: Child; trainer: ChildTrainer } | null>(null);
const removingChild = ref<Child | null>(null);
const addMode = ref<'code' | 'trainer'>('code');

const addForm = useForm<{ code: string; trainer_id: string }>({
    code: '',
    trainer_id: '',
});

function openAdd(child: Child): void {
    addForm.reset();
    addForm.clearErrors();
    addMode.value = 'code';
    addingFor.value = child;
}

function submitAdd(): void {
    if (addingFor.value === null) {
        return;
    }

    addForm
        .transform((data) =>
            addMode.value === 'code'
                ? { code: data.code }
                : { trainer_id: data.trainer_id },
        )
        .post(ChildTrainerController.store.url({ childProfile: addingFor.value.id }), {
            onSuccess: () => {
                addingFor.value = null;
            },
        });
}

function confirmRemoveTrainer(): void {
    if (removing.value === null) {
        return;
    }

    router.delete(
        ChildTrainerController.destroy.url({
            childProfile: removing.value.child.id,
            trainer: removing.value.trainer.id,
        }),
        {
            onFinish: () => {
                removing.value = null;
            },
        },
    );
}

function confirmRemoveChild(): void {
    if (removingChild.value === null) {
        return;
    }

    router.delete(ChildProfileController.destroy.url({ childProfile: removingChild.value.id }), {
        onFinish: () => {
            removingChild.value = null;
        },
    });
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
                    <p class="mt-2 text-sm text-muted-foreground">Trainers:</p>
                    <p v-if="child.trainers.length === 0" class="text-sm text-muted-foreground">none yet</p>
                    <ul v-else class="mt-1 grid gap-1">
                        <li
                            v-for="trainer in child.trainers"
                            :key="trainer.id"
                            class="flex flex-wrap items-center gap-2 text-sm"
                            :data-test="`child-${child.id}-trainer-${trainer.id}`"
                        >
                            <span>{{ trainer.name }}</span>
                            <span class="text-muted-foreground">connected {{ trainer.connected_at }}</span>
                            <Button
                                variant="ghost"
                                size="sm"
                                data-test="remove-trainer"
                                @click="removing = { child, trainer }"
                            >
                                Remove
                            </Button>
                        </li>
                    </ul>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-2"
                        data-test="add-trainer"
                        @click="openAdd(child)"
                    >
                        <Plus /> Add Trainer
                    </Button>
                </div>
                <Badge v-if="child.has_login" variant="secondary">Has login</Badge>
                <Button variant="outline" as-child>
                    <Link :href="ChildProfileController.edit.url({ childProfile: child.id })">
                        Edit
                    </Link>
                </Button>
                <Button variant="destructive" data-test="remove-child" @click="removingChild = child">
                    Remove
                </Button>
            </li>
        </ul>

        <Dialog :open="addingFor !== null" @update:open="(open) => !open && (addingFor = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Trainer for {{ addingFor?.name }}</DialogTitle>
                    <DialogDescription>
                        Paste a trainer's invite link or code, or pick one of your trainers.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submitAdd">
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            :variant="addMode === 'code' ? 'default' : 'outline'"
                            size="sm"
                            @click="addMode = 'code'"
                        >
                            Link or code
                        </Button>
                        <Button
                            type="button"
                            :variant="addMode === 'trainer' ? 'default' : 'outline'"
                            size="sm"
                            :disabled="myTrainers.length === 0"
                            @click="addMode = 'trainer'"
                        >
                            My Trainers
                        </Button>
                    </div>

                    <div v-if="addMode === 'code'" class="grid gap-2">
                        <Label for="trainer-code">ShareLink or code</Label>
                        <Input id="trainer-code" v-model="addForm.code" placeholder="https://…/join/AbC123xyz" />
                        <InputError :message="addForm.errors.code" />
                    </div>

                    <div v-else class="grid gap-2">
                        <Label for="my-trainer">Trainer</Label>
                        <select
                            id="my-trainer"
                            v-model="addForm.trainer_id"
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option value="" disabled>Choose a trainer</option>
                            <option v-for="trainer in myTrainers" :key="trainer.id" :value="trainer.id">
                                {{ trainer.name }}
                            </option>
                        </select>
                        <InputError :message="addForm.errors.trainer_id" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="addingFor = null">Cancel</Button>
                        <Button type="submit" :disabled="addForm.processing" data-test="confirm-add-trainer">Add</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="removing !== null" @update:open="(open) => !open && (removing = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove trainer</DialogTitle>
                    <DialogDescription data-test="remove-trainer-message">
                        Remove {{ removing?.child.name }} from {{ removing?.trainer.name }}? This will cancel all
                        upcoming RSVPs.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="removing = null">Cancel</Button>
                    <Button variant="destructive" data-test="confirm-remove-trainer" @click="confirmRemoveTrainer">
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="removingChild !== null" @update:open="(open) => !open && (removingChild = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove child</DialogTitle>
                    <DialogDescription data-test="remove-child-message">
                        Remove {{ removingChild?.name }}? Their profile{{ removingChild?.has_login ? ' and login' : '' }}
                        will be deactivated.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" @click="removingChild = null">Cancel</Button>
                    <Button variant="destructive" data-test="confirm-remove-child" @click="confirmRemoveChild">
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
