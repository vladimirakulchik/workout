<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { impersonationHistory } from '@/routes/admin';

type LogRow = {
    id: number;
    admin: string | null;
    user: string | null;
    user_role: string | null;
    started_at: string;
    ended_at: string | null;
    duration_seconds: number | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

defineProps<{
    logs: {
        data: LogRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Impersonation history', href: impersonationHistory() },
        ],
    },
});

const dateFormat = new Intl.DateTimeFormat(undefined, {
    dateStyle: 'medium',
    timeStyle: 'short',
});

function formatDate(value: string | null): string {
    return value === null ? '—' : dateFormat.format(new Date(value));
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '—';
    }

    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const rest = seconds % 60;

    return [hours > 0 ? `${hours}h` : '', minutes > 0 ? `${minutes}m` : '', `${rest}s`]
        .filter(Boolean)
        .join(' ');
}
</script>

<template>
    <Head title="Impersonation history" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <h1 class="text-2xl font-semibold">Impersonation history</h1>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    Impersonation history
                </caption>
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Admin</th>
                        <th scope="col" class="px-4 py-3 font-medium">User</th>
                        <th scope="col" class="px-4 py-3 font-medium">Started</th>
                        <th scope="col" class="px-4 py-3 font-medium">Ended</th>
                        <th scope="col" class="px-4 py-3 font-medium">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-muted-foreground">
                            No impersonation sessions yet.
                        </td>
                    </tr>
                    <tr
                        v-for="log in logs.data"
                        :key="log.id"
                        class="border-b last:border-0"
                    >
                        <td class="px-4 py-3 font-medium">{{ log.admin }}</td>
                        <td class="px-4 py-3">
                            {{ log.user }}
                            <span v-if="log.user_role" class="text-muted-foreground">
                                ({{ log.user_role }})
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ formatDate(log.started_at) }}</td>
                        <td class="px-4 py-3">{{ formatDate(log.ended_at) }}</td>
                        <td class="px-4 py-3">{{ formatDuration(log.duration_seconds) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            class="flex flex-wrap items-center justify-between gap-2"
            aria-label="Pagination"
        >
            <p class="text-sm text-muted-foreground">
                <template v-if="logs.total > 0">
                    Showing {{ logs.from }}–{{ logs.to }} of {{ logs.total }}
                </template>
                <template v-else>0 results</template>
            </p>

            <ul v-if="logs.links.length > 3" class="flex flex-wrap gap-1">
                <li v-for="(link, i) in logs.links" :key="i">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                    >
                        <Link
                            :href="link.url"
                            :aria-current="link.active ? 'page' : undefined"
                        >
                            <span v-html="link.label" />
                        </Link>
                    </Button>
                    <Button v-else size="sm" variant="outline" disabled>
                        <span v-html="link.label" />
                    </Button>
                </li>
            </ul>
        </nav>
    </div>
</template>
