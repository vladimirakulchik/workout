<script setup lang="ts">
import { useForm, useHttp } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import CoachAssignmentController from '@/actions/App/Http/Controllers/Trainer/CoachAssignmentController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    coachProfileId: number;
    coachName: string;
    eventId: number;
    /** Local date-time as `YYYY-MM-DDTHH:mm`. */
    start: string;
    end: string;
}>();

const open = defineModel<boolean>('open', { default: false });
const emit = defineEmits<{ assigned: [] }>();

type CheckState = 'checking' | 'available' | 'conflict' | 'failed';

const state = ref<CheckState>('checking');

const check = useHttp<{ coach_profile_id: number; start: string; end: string }, { available: boolean }>({
    coach_profile_id: props.coachProfileId,
    start: props.start,
    end: props.end,
});

const form = useForm({
    coach_profile_id: props.coachProfileId,
    event_id: props.eventId,
    start: props.start,
    end: props.end,
    reason: '',
});

watch(open, async (isOpen) => {
    if (!isOpen) {
        return;
    }

    state.value = 'checking';
    form.reset('reason');
    form.clearErrors();
    check.coach_profile_id = props.coachProfileId;
    check.start = props.start;
    check.end = props.end;

    try {
        const response = await check.post(CoachAssignmentController.check.url());
        state.value = response.available ? 'available' : 'conflict';
    } catch {
        state.value = 'failed';
    }
});

function submit(): void {
    form.coach_profile_id = props.coachProfileId;
    form.event_id = props.eventId;
    form.start = props.start;
    form.end = props.end;

    form.post(CoachAssignmentController.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            open.value = false;
            emit('assigned');
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent data-test="coach-assignment-dialog">
            <DialogHeader>
                <DialogTitle>Assign {{ coachName }}</DialogTitle>
                <DialogDescription v-if="state === 'checking'">Checking the schedule of {{ coachName }}...</DialogDescription>
                <DialogDescription v-else-if="state === 'available'" data-test="coach-available">
                    {{ coachName }} is available at this time.
                </DialogDescription>
                <DialogDescription v-else-if="state === 'conflict'" data-test="coach-conflict">
                    Coach {{ coachName }} is not available at this time per their schedule. Continue anyway?
                </DialogDescription>
                <DialogDescription v-else role="alert">The schedule could not be checked. Close this dialog and try again.</DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submit">
                <div v-if="state === 'conflict'" class="grid gap-2">
                    <Label for="override-reason">Reason for assigning anyway</Label>
                    <textarea
                        id="override-reason"
                        v-model="form.reason"
                        name="reason"
                        rows="3"
                        maxlength="1000"
                        required
                        class="border-input placeholder:text-muted-foreground focus-visible:border-brand focus-visible:ring-brand/25 w-full rounded-sm border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]"
                        data-test="override-reason"
                    />
                    <InputError :message="form.errors.reason" />
                </div>
                <InputError :message="form.errors.event_id ?? form.errors.start ?? form.errors.end ?? form.errors.coach_profile_id" />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="open = false">Cancel</Button>
                    <Button
                        type="submit"
                        :disabled="form.processing || state === 'checking' || state === 'failed'"
                        data-test="confirm-assignment"
                    >
                        {{ state === 'conflict' ? 'Continue anyway' : 'Assign coach' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
