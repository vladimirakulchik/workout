<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/trainer/players';

type PlayerRow = {
    id: number;
    name: string;
    age: number | null;
    skill_level: string | null;
    photo_url: string;
    best_times: string;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type Filters = { search: string; day: string; from: string; to: string };

const props = defineProps<{
    players: {
        data: PlayerRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: Filters;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Players', href: index() }],
    },
});

const days = [
    { value: '1', label: 'Monday' },
    { value: '2', label: 'Tuesday' },
    { value: '3', label: 'Wednesday' },
    { value: '4', label: 'Thursday' },
    { value: '5', label: 'Friday' },
    { value: '6', label: 'Saturday' },
    { value: '0', label: 'Sunday' },
];

function slots(fromMinutes: number, toMinutes: number): { value: string; label: string }[] {
    const result: { value: string; label: string }[] = [];

    for (let minutes = fromMinutes; minutes <= toMinutes; minutes += 30) {
        const hour = Math.floor(minutes / 60);
        const value = `${String(hour).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
        const display = hour % 12 === 0 ? 12 : hour % 12;
        const label = `${display}:${String(minutes % 60).padStart(2, '0')} ${hour % 24 >= 12 ? 'PM' : 'AM'}`;
        result.push({ value, label });
    }

    return result;
}

const fromOptions = slots(0, 23 * 60 + 30);
const toOptions = slots(30, 24 * 60);

const selectClass =
    'h-10 rounded-sm border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-brand focus-visible:ring-[3px] focus-visible:ring-brand/25';

const search = ref(props.filters.search);
const day = ref(props.filters.day);
const from = ref(props.filters.from);
const to = ref(props.filters.to);

function visit(overrides: Partial<Filters> = {}): void {
    const query = { search: search.value, day: day.value, from: from.value, to: to.value, ...overrides };
    const hasWindow = query.day !== '' && query.from !== '' && query.to !== '';

    router.get(
        index.url(),
        {
            search: query.search || undefined,
            day: hasWindow ? query.day : undefined,
            from: hasWindow ? query.from : undefined,
            to: hasWindow ? query.to : undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const debouncedSearch = useDebounceFn((term: string) => visit({ search: term }), 300);

watch(search, (term) => {
    if (term !== props.filters.search) {
        debouncedSearch(term);
    }
});

watch([day, from, to], () => visit());

function clearWindow(): void {
    day.value = '';
    from.value = '';
    to.value = '';
}
</script>

<template>
    <Head title="Players" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <h1 class="text-2xl font-semibold">Players</h1>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid gap-1.5">
                <Label for="player-search">Search</Label>
                <Input id="player-search" v-model="search" type="search" placeholder="Name" class="w-64" data-test="player-search" />
            </div>

            <fieldset class="flex flex-wrap items-end gap-3" aria-label="Available during">
                <div class="grid gap-1.5">
                    <Label for="filter-day">Available on</Label>
                    <select id="filter-day" v-model="day" :class="selectClass" data-test="filter-day">
                        <option value="">Any day</option>
                        <option v-for="option in days" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="filter-from">From</Label>
                    <select id="filter-from" v-model="from" :class="selectClass" data-test="filter-from">
                        <option value="">-</option>
                        <option v-for="option in fromOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="filter-to">To</Label>
                    <select id="filter-to" v-model="to" :class="selectClass" data-test="filter-to">
                        <option value="">-</option>
                        <option v-for="option in toOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                </div>
                <Button v-if="day || from || to" type="button" variant="outline" data-test="clear-window" @click="clearWindow">
                    Clear
                </Button>
            </fieldset>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th scope="col" class="px-3 py-2 font-medium">Player</th>
                        <th scope="col" class="px-3 py-2 font-medium">Age</th>
                        <th scope="col" class="px-3 py-2 font-medium">Skill level</th>
                        <th scope="col" class="px-3 py-2 font-medium">Best Times</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="players.data.length === 0">
                        <td colspan="4" class="px-3 py-6 text-center text-muted-foreground" data-test="no-players">No players found.</td>
                    </tr>
                    <tr v-for="player in players.data" :key="player.id" class="border-b last:border-0" data-test="player-row">
                        <td class="px-3 py-2">
                            <div class="flex items-center gap-2">
                                <img :src="player.photo_url" alt="" class="size-8 rounded-full object-cover" />
                                <span class="font-medium">{{ player.name }}</span>
                            </div>
                        </td>
                        <td class="px-3 py-2">{{ player.age ?? '-' }}</td>
                        <td class="px-3 py-2">{{ player.skill_level ?? '-' }}</td>
                        <td class="px-3 py-2">
                            <span v-if="player.best_times" data-test="best-times">{{ player.best_times }}</span>
                            <Badge v-else variant="secondary">Not set</Badge>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Pagination">
            <p class="text-sm text-muted-foreground">
                <template v-if="players.total > 0">Showing {{ players.from }}–{{ players.to }} of {{ players.total }}</template>
                <template v-else>0 results</template>
            </p>

            <ul v-if="players.links.length > 3" class="flex flex-wrap gap-1">
                <li v-for="(link, i) in players.links" :key="i">
                    <Button v-if="link.url" as-child size="sm" :variant="link.active ? 'default' : 'outline'">
                        <Link :href="link.url" preserve-scroll :aria-current="link.active ? 'page' : undefined">
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
