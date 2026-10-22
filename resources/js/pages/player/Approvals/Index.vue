<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import ApprovalController from '@/actions/App/Http/Controllers/Player/ApprovalController';
import InputError from '@/components/InputError.vue';
import LocalDateTime from '@/components/LocalDateTime.vue';
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
import { Label } from '@/components/ui/label';
import { index } from '@/routes/player/approvals';

type Approval = {
    id: number;
    child_name: string;
    trainer_name: string;
    description: string;
    amount: string;
    payment_type: string;
    status: 'pending' | 'approved' | 'denied' | 'expired';
    status_label: string;
    requested_at: string;
    responded_at: string | null;
    expires_at: string;
    parent_notes: string | null;
    can_respond: boolean;
};

type Action = 'approve' | 'deny' | 'info';

defineProps<{
    pending: Approval[];
    history: Approval[];
    readOnly: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Approvals', href: index() }],
    },
});

const acting = ref<{ approval: Approval; action: Action } | null>(null);

const form = useForm<{ notes: string }>({ notes: '' });

const errorMessage = computed(() => {
    const errors: Record<string, string | undefined> = form.errors;

    return errors.notes ?? errors.note ?? errors.approval;
});

const titles: Record<Action, string> = {
    approve: 'Approve purchase',
    deny: 'Deny purchase',
    info: 'Request more information',
};

function openDialog(approval: Approval, action: Action): void {
    form.reset();
    form.clearErrors();
    acting.value = { approval, action };
}

function submit(): void {
    if (acting.value === null) {
        return;
    }

    const { approval, action } = acting.value;
    const params = { childPurchaseApproval: approval.id };
    const target =
        action === 'approve'
            ? ApprovalController.approve.url(params)
            : action === 'deny'
              ? ApprovalController.deny.url(params)
              : ApprovalController.requestInfo.url(params);

    form.transform((data) =>
        action === 'info' ? { note: data.notes } : { notes: data.notes },
    ).post(target, {
        preserveScroll: true,
        onSuccess: () => {
            acting.value = null;
        },
    });
}

const statusVariant = (status: Approval['status']) =>
    status === 'approved'
        ? 'default'
        : status === 'pending'
          ? 'secondary'
          : 'destructive';
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4">
        <Head title="Approvals" />

        <h1 class="text-2xl font-semibold">Approvals</h1>

        <p v-if="readOnly" class="text-sm text-muted-foreground">
            These are your purchase requests. Your parent decides on them.
        </p>

        <section class="grid gap-3" aria-labelledby="pending-heading">
            <h2 id="pending-heading" class="text-lg font-medium">Pending</h2>

            <p v-if="pending.length === 0" class="text-muted-foreground" data-test="no-pending">
                No pending requests.
            </p>

            <ul class="grid gap-3">
                <li
                    v-for="approval in pending"
                    :key="approval.id"
                    class="flex flex-wrap items-center gap-4 rounded-md border p-4"
                    :data-test="`approval-${approval.id}`"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ approval.child_name }} · {{ approval.description }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ approval.amount }} · {{ approval.trainer_name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            Expires <LocalDateTime :value="approval.expires_at" />
                        </p>
                        <p v-if="approval.parent_notes" class="mt-1 text-sm">
                            Note: {{ approval.parent_notes }}
                        </p>
                    </div>
                    <Badge v-if="!approval.can_respond" :variant="statusVariant(approval.status)">
                        {{ approval.status_label }}
                    </Badge>
                    <div v-if="approval.can_respond" class="flex flex-wrap gap-2">
                        <Button data-test="approve" @click="openDialog(approval, 'approve')">Approve</Button>
                        <Button variant="destructive" data-test="deny" @click="openDialog(approval, 'deny')">
                            Deny
                        </Button>
                        <Button variant="outline" data-test="request-info" @click="openDialog(approval, 'info')">
                            Request more info
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <section class="grid gap-3" aria-labelledby="history-heading">
            <h2 id="history-heading" class="text-lg font-medium">History</h2>

            <p v-if="history.length === 0" class="text-muted-foreground" data-test="no-history">
                Nothing here yet.
            </p>

            <ul class="grid gap-3">
                <li
                    v-for="approval in history"
                    :key="approval.id"
                    class="flex flex-wrap items-center gap-4 rounded-md border p-4"
                    :data-test="`approval-${approval.id}`"
                >
                    <div class="min-w-0 flex-1">
                        <p class="font-medium">
                            {{ approval.child_name }} · {{ approval.description }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ approval.amount }} · {{ approval.trainer_name }}
                            <template v-if="approval.responded_at">
                                · <LocalDateTime :value="approval.responded_at" />
                            </template>
                        </p>
                        <p v-if="approval.parent_notes" class="mt-1 text-sm">
                            Note: {{ approval.parent_notes }}
                        </p>
                    </div>
                    <Badge :variant="statusVariant(approval.status)">{{ approval.status_label }}</Badge>
                </li>
            </ul>
        </section>

        <Dialog :open="acting !== null" @update:open="(open) => !open && (acting = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ acting ? titles[acting.action] : '' }}</DialogTitle>
                    <DialogDescription>
                        {{ acting?.approval.child_name }} · {{ acting?.approval.description }} ·
                        {{ acting?.approval.amount }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="approval-notes">
                            {{ acting?.action === 'info' ? 'Your question' : 'Notes (optional)' }}
                        </Label>
                        <textarea
                            id="approval-notes"
                            v-model="form.notes"
                            rows="3"
                            maxlength="1000"
                            :required="acting?.action === 'info'"
                            class="rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        />
                        <InputError :message="errorMessage" />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" @click="acting = null">Cancel</Button>
                        <Button
                            type="submit"
                            :variant="acting?.action === 'deny' ? 'destructive' : 'default'"
                            :disabled="form.processing"
                            data-test="confirm-decision"
                        >
                            {{ acting?.action === 'info' ? 'Send' : acting?.action === 'deny' ? 'Deny' : 'Approve' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
