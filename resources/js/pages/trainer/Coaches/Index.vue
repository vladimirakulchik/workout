<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import { ref } from 'vue';
import CoachController from '@/actions/App/Http/Controllers/Trainer/CoachController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, show } from '@/routes/trainer/coaches';

type CoachRow = {
    id: number;
    name: string;
    email: string;
    joined_at: string | null;
};

type InvitationRow = {
    id: number;
    name: string | null;
    email: string;
    message: string | null;
    status: 'pending' | 'accepted' | 'expired' | 'revoked';
    expires_at: string | null;
    can_resend: boolean;
};

defineProps<{
    coaches: CoachRow[];
    invitations: InvitationRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Coaches', href: index() }],
    },
});

const inviteOpen = ref(false);
const processing = ref(false);

const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};

function resend(invitation: InvitationRow): void {
    router.post(CoachController.resend.url(invitation.id), {}, options);
}

const coachPendingRemoval = ref<CoachRow | null>(null);

function confirmRemove(): void {
    const coach = coachPendingRemoval.value;

    if (coach === null) {
        return;
    }

    router.delete(CoachController.destroy.url(coach.id), {
        ...options,
        onSuccess: () => (coachPendingRemoval.value = null),
    });
}

function statusVariant(status: InvitationRow['status']): 'default' | 'secondary' | 'destructive' {
    return status === 'pending' ? 'default' : status === 'expired' ? 'destructive' : 'secondary';
}

function formatDate(value: string | null): string {
    return value === null ? '-' : new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Coaches" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-semibold">Coaches</h1>

            <Dialog v-model:open="inviteOpen">
                <DialogTrigger as-child>
                    <Button data-test="invite-coach-button"><Plus /> Invite coach</Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Invite coach</DialogTitle>
                        <DialogDescription>
                            We email the coach a single-use link, valid for 7 days and tied to their email address.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-bind="CoachController.store.form()"
                        class="grid gap-4"
                        reset-on-success
                        :options="{ preserveScroll: true }"
                        v-slot="{ errors, processing: submitting }"
                        @success="inviteOpen = false"
                    >
                        <div class="grid gap-2">
                            <Label for="target_email">Coach email</Label>
                            <Input id="target_email" type="email" name="target_email" required autocomplete="off" />
                            <InputError :message="errors.target_email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="name">Coach name (optional)</Label>
                            <Input id="name" name="name" autocomplete="off" />
                            <InputError :message="errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="message">Message (optional)</Label>
                            <Input id="message" name="message" autocomplete="off" />
                            <InputError :message="errors.message" />
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="submitting" data-test="submit-invite-coach">
                                Send invitation
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <section class="grid gap-3" aria-labelledby="coaches-heading">
            <h2 id="coaches-heading" class="text-lg font-medium">Your coaches</h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Coaches on your team</caption>
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Coach</th>
                            <th scope="col" class="px-4 py-3 font-medium">Joined</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="coaches.length === 0">
                            <td colspan="3" class="px-4 py-8 text-center text-muted-foreground" data-test="no-coaches">
                                No coaches yet.
                            </td>
                        </tr>
                        <tr v-for="coach in coaches" :key="coach.id" class="border-b last:border-0" data-test="coach-row">
                            <td class="px-4 py-3">
                                <Link :href="show(coach.id)" class="font-medium underline-offset-4 hover:underline" data-test="coach-link">
                                    {{ coach.name }}
                                </Link>
                                <div class="text-muted-foreground">{{ coach.email }}</div>
                            </td>
                            <td class="px-4 py-3">{{ formatDate(coach.joined_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    :disabled="processing"
                                    :aria-label="`Remove ${coach.name}`"
                                    data-test="remove-coach"
                                    @click="coachPendingRemoval = coach"
                                >
                                    Remove
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-3" aria-labelledby="invitations-heading">
            <h2 id="invitations-heading" class="text-lg font-medium">Invitations</h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Coach invitations</caption>
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Invitee</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-4 py-3 font-medium">Expires</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="invitations.length === 0">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground" data-test="no-invitations">
                                No invitations yet.
                            </td>
                        </tr>
                        <tr
                            v-for="invitation in invitations"
                            :key="invitation.id"
                            class="border-b last:border-0"
                            :data-status="invitation.status"
                            data-test="invitation-row"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ invitation.name ?? invitation.email }}</div>
                                <div v-if="invitation.name" class="text-muted-foreground">{{ invitation.email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(invitation.status)" class="capitalize">{{ invitation.status }}</Badge>
                            </td>
                            <td class="px-4 py-3">{{ formatDate(invitation.expires_at) }}</td>
                            <td class="px-4 py-3 text-right">
                                <Button
                                    v-if="invitation.can_resend"
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="processing"
                                    :aria-label="`Resend invitation to ${invitation.email}`"
                                    data-test="resend-invitation"
                                    @click="resend(invitation)"
                                >
                                    Resend
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <Dialog :open="coachPendingRemoval !== null" @update:open="(open: boolean) => !open && (coachPendingRemoval = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Remove coach</DialogTitle>
                    <DialogDescription>
                        Remove {{ coachPendingRemoval?.name }} from your team? Their history is kept.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="coachPendingRemoval = null">Cancel</Button>
                    <Button type="button" variant="destructive" :disabled="processing" data-test="confirm-remove-coach" @click="confirmRemove">
                        Remove
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
