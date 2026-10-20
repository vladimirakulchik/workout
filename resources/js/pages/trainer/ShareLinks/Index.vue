<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { Copy, Plus } from '@lucide/vue';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import ShareLinkController from '@/actions/App/Http/Controllers/Trainer/ShareLinkController';
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
import { index } from '@/routes/trainer/share-links';

type ShareLinkRow = {
    id: number;
    url: string;
    type: string;
    name: string | null;
    target_email: string | null;
    message: string | null;
    expires_at: string | null;
    uses_count: number;
    max_uses: number | null;
    status: 'pending' | 'accepted' | 'expired' | 'revoked';
    can_deactivate: boolean;
};

defineProps<{
    playerLink: ShareLinkRow | null;
    coachLinks: ShareLinkRow[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Share Links', href: index() }],
    },
});

const createOpen = ref(false);
const processing = ref(false);

async function copy(url: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Link copied to clipboard.');
    } catch {
        toast.error('Could not copy the link. Select it and copy manually.');
    }
}

const options = {
    preserveScroll: true,
    onStart: () => (processing.value = true),
    onFinish: () => (processing.value = false),
};

function regenerate(hasActiveLink: boolean): void {
    if (hasActiveLink && !window.confirm('The current player link will stop working. Generate a new one?')) {
        return;
    }

    router.post(ShareLinkController.regenerate.url(), {}, options);
}

function deactivate(link: ShareLinkRow): void {
    router.delete(ShareLinkController.deactivate.url(link.id), options);
}

function statusVariant(status: ShareLinkRow['status']): 'default' | 'secondary' | 'destructive' {
    if (status === 'pending') {
        return 'default';
    }

    return status === 'revoked' ? 'destructive' : 'secondary';
}

function formatDate(value: string | null): string {
    return value === null ? '-' : new Date(value).toLocaleDateString();
}
</script>

<template>
    <Head title="Share Links" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <h1 class="text-2xl font-semibold">Share Links</h1>

        <section class="grid gap-3 rounded-lg border p-4" aria-labelledby="player-link-heading">
            <div>
                <h2 id="player-link-heading" class="text-lg font-medium">Player link</h2>
                <p class="text-sm text-muted-foreground">
                    One static link for players and parents to join you. It has no expiry and can be used any number of times.
                </p>
            </div>

            <template v-if="playerLink">
                <div class="flex flex-wrap items-end gap-2">
                    <div class="grid min-w-64 flex-1 gap-1.5">
                        <Label for="player-link-url">Link</Label>
                        <Input id="player-link-url" :model-value="playerLink.url" readonly data-test="player-link-url" />
                    </div>
                    <Button type="button" variant="secondary" data-test="copy-player-link" @click="copy(playerLink.url)">
                        <Copy /> Copy
                    </Button>
                    <Button type="button" variant="outline" :disabled="processing" data-test="regenerate-player-link" @click="regenerate(true)">
                        Regenerate
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="processing"
                        data-test="deactivate-player-link"
                        @click="deactivate(playerLink)"
                    >
                        Deactivate
                    </Button>
                </div>
                <p class="text-sm text-muted-foreground">Joined so far: {{ playerLink.uses_count }}</p>
            </template>
            <div v-else class="flex flex-wrap items-center gap-3">
                <p class="text-sm text-muted-foreground" data-test="no-player-link">There is no active player link.</p>
                <Button type="button" :disabled="processing" data-test="generate-player-link" @click="regenerate(false)">
                    Generate link
                </Button>
            </div>
        </section>

        <section class="grid gap-3" aria-labelledby="coach-links-heading">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 id="coach-links-heading" class="text-lg font-medium">Coach invitations</h2>
                    <p class="text-sm text-muted-foreground">Single-use links that expire after 7 days.</p>
                </div>

                <Dialog v-model:open="createOpen">
                    <DialogTrigger as-child>
                        <Button data-test="create-coach-link-button"><Plus /> Invite coach</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Invite coach</DialogTitle>
                            <DialogDescription>
                                Creates a single-use link valid for 7 days, tied to the coach's email address.
                            </DialogDescription>
                        </DialogHeader>

                        <Form
                            v-bind="ShareLinkController.store.form()"
                            class="grid gap-4"
                            reset-on-success
                            :options="{ preserveScroll: true }"
                            v-slot="{ errors, processing: submitting }"
                            @success="createOpen = false"
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
                                <Button type="submit" :disabled="submitting" data-test="submit-coach-link">
                                    Create link
                                </Button>
                            </DialogFooter>
                        </Form>
                    </DialogContent>
                </Dialog>
            </div>

            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Coach invitation links</caption>
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Coach</th>
                            <th scope="col" class="px-4 py-3 font-medium">Status</th>
                            <th scope="col" class="px-4 py-3 font-medium">Expires</th>
                            <th scope="col" class="px-4 py-3 font-medium">Uses</th>
                            <th scope="col" class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="coachLinks.length === 0">
                            <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                                No coach invitations yet.
                            </td>
                        </tr>
                        <tr v-for="link in coachLinks" :key="link.id" class="border-b last:border-0" :data-status="link.status">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ link.name ?? link.target_email }}</div>
                                <div v-if="link.name" class="text-muted-foreground">{{ link.target_email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(link.status)" class="capitalize">{{ link.status }}</Badge>
                            </td>
                            <td class="px-4 py-3">{{ formatDate(link.expires_at) }}</td>
                            <td class="px-4 py-3">{{ link.uses_count }} / {{ link.max_uses ?? '∞' }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        :aria-label="`Copy link for ${link.name ?? link.target_email}`"
                                        @click="copy(link.url)"
                                    >
                                        <Copy /> Copy
                                    </Button>
                                    <Button
                                        v-if="link.can_deactivate"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="processing"
                                        :aria-label="`Deactivate link for ${link.name ?? link.target_email}`"
                                        @click="deactivate(link)"
                                    >
                                        Deactivate
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
