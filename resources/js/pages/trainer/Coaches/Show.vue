<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { index } from '@/routes/trainer/coaches';
import type { AvailabilityRange } from '@/types';

const props = defineProps<{
    coach: {
        id: number;
        name: string;
        email: string;
        bio: string | null;
        credentials: string | null;
        certifications: string | null;
        is_public: boolean;
        joined_at: string | null;
    };
    ranges: AvailabilityRange[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Coaches', href: index() }],
    },
});

const days = [
    { value: 1, label: 'Monday' },
    { value: 2, label: 'Tuesday' },
    { value: 3, label: 'Wednesday' },
    { value: 4, label: 'Thursday' },
    { value: 5, label: 'Friday' },
    { value: 6, label: 'Saturday' },
    { value: 0, label: 'Sunday' },
];

function formatTime(time: string): string {
    const [hour, minute] = time.split(':').map(Number);
    const suffix = hour % 24 >= 12 ? 'PM' : 'AM';
    const display = hour % 12 === 0 ? 12 : hour % 12;

    return `${display}:${String(minute).padStart(2, '0')} ${suffix}`;
}

function rangesFor(day: number): string[] {
    return props.ranges
        .filter((range) => range.day_of_week === day)
        .map((range) => `${formatTime(range.start_time)} - ${formatTime(range.end_time)}`);
}
</script>

<template>
    <Head :title="coach.name" />

    <div class="flex flex-1 flex-col gap-6 p-4">
        <div class="grid gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-2xl font-semibold">{{ coach.name }}</h1>
                <Badge :variant="coach.is_public ? 'default' : 'secondary'" data-test="public-status">
                    {{ coach.is_public ? 'Public profile' : 'Private profile' }}
                </Badge>
            </div>
            <p class="text-sm text-muted-foreground">{{ coach.email }}</p>
        </div>

        <section class="grid max-w-4xl gap-2" aria-labelledby="about-heading">
            <h2 id="about-heading" class="text-lg font-medium">About</h2>
            <dl class="grid gap-3 text-sm">
                <div>
                    <dt class="font-medium">Bio</dt>
                    <dd class="text-muted-foreground">{{ coach.bio ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Credentials</dt>
                    <dd class="text-muted-foreground">{{ coach.credentials ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="font-medium">Certifications</dt>
                    <dd class="text-muted-foreground">{{ coach.certifications ?? '-' }}</dd>
                </div>
            </dl>
        </section>

        <section class="grid max-w-4xl gap-3" aria-labelledby="times-heading" data-test="coach-times">
            <h2 id="times-heading" class="text-lg font-medium">Times</h2>
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">
                        Weekly availability of {{ coach.name }}
                    </caption>
                    <thead class="border-b bg-muted/40">
                        <tr>
                            <th scope="col" class="px-4 py-3 font-medium">Day</th>
                            <th scope="col" class="px-4 py-3 font-medium">Available</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="day in days" :key="day.value" class="border-b last:border-0" :data-test="`day-${day.value}`">
                            <th scope="row" class="px-4 py-3 font-medium">{{ day.label }}</th>
                            <td class="px-4 py-3">
                                <template v-if="rangesFor(day.value).length > 0">
                                    <div v-for="text in rangesFor(day.value)" :key="text">{{ text }}</div>
                                </template>
                                <span v-else class="text-muted-foreground">Not available</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
