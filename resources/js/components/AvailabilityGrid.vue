<script setup lang="ts">
import { Plus, Trash2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import type { AvailabilityRange } from '@/types';

const props = defineProps<{
    errors?: Record<string, string>;
}>();

const ranges = defineModel<AvailabilityRange[]>({ required: true });

/** Monday first; `value` is the weekday number the server stores (0 = Sunday). */
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

function slots(fromMinutes: number, toMinutes: number): { value: string; label: string }[] {
    const result: { value: string; label: string }[] = [];

    for (let minutes = fromMinutes; minutes <= toMinutes; minutes += 30) {
        const value = `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;
        result.push({ value, label: formatTime(value) });
    }

    return result;
}

const startOptions = slots(0, 23 * 60 + 30);
const endOptions = slots(30, 24 * 60);

const selectClass =
    'h-10 rounded-sm border border-input bg-transparent px-2 text-sm outline-none focus-visible:border-brand focus-visible:ring-[3px] focus-visible:ring-brand/25 aria-invalid:border-destructive';

function indexesFor(day: number): number[] {
    return ranges.value.flatMap((range, index) => (range.day_of_week === day ? [index] : []));
}

function setAvailable(day: number, available: boolean): void {
    ranges.value = available
        ? [...ranges.value, { day_of_week: day, start_time: '17:00', end_time: '20:00' }]
        : ranges.value.filter((range) => range.day_of_week !== day);
}

function addRange(day: number): void {
    const last = indexesFor(day)
        .map((index) => ranges.value[index])
        .sort((a, b) => a.end_time.localeCompare(b.end_time))
        .at(-1);
    const start = last && last.end_time < '23:00' ? last.end_time : '17:00';
    const end = start < '23:00' ? `${String(Number(start.slice(0, 2)) + 1).padStart(2, '0')}${start.slice(2)}` : '24:00';

    ranges.value = [...ranges.value, { day_of_week: day, start_time: start, end_time: end }];
}

function removeRange(index: number): void {
    ranges.value = ranges.value.filter((_, position) => position !== index);
}

function update(index: number, field: 'start_time' | 'end_time', value: string): void {
    ranges.value = ranges.value.map((range, position) => (position === index ? { ...range, [field]: value } : range));
}

function errorFor(index: number): string | undefined {
    return (
        props.errors?.[`ranges.${index}.start_time`] ??
        props.errors?.[`ranges.${index}.end_time`] ??
        props.errors?.[`ranges.${index}.day_of_week`]
    );
}
</script>

<template>
    <div class="grid gap-3" data-test="availability-grid">
        <section
            v-for="day in days"
            :key="day.value"
            class="grid gap-3 rounded-lg border p-4 md:grid-cols-[10rem_1fr]"
            :aria-labelledby="`day-${day.value}`"
            :data-test="`day-${day.value}`"
        >
            <div class="flex items-center justify-between gap-3 md:block">
                <h3 :id="`day-${day.value}`" class="font-medium">{{ day.label }}</h3>
                <div class="flex items-center gap-2 md:mt-2">
                    <Switch
                        :id="`unavailable-${day.value}`"
                        :model-value="indexesFor(day.value).length === 0"
                        :data-test="`unavailable-${day.value}`"
                        @update:model-value="setAvailable(day.value, !$event)"
                    />
                    <label :for="`unavailable-${day.value}`" class="text-sm text-muted-foreground">Not available</label>
                </div>
            </div>

            <div class="grid content-start gap-2">
                <template v-if="indexesFor(day.value).length > 0">
                    <div v-for="index in indexesFor(day.value)" :key="index" class="grid gap-1">
                        <div class="flex flex-wrap items-center gap-2" role="group" :aria-label="`${day.label} time range`">
                            <label class="sr-only" :for="`start-${index}`">{{ day.label }} start time</label>
                            <select
                                :id="`start-${index}`"
                                :class="selectClass"
                                :value="ranges[index].start_time"
                                :aria-invalid="errorFor(index) ? 'true' : undefined"
                                :data-test="`start-${index}`"
                                @change="update(index, 'start_time', ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="option in startOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <span aria-hidden="true">to</span>
                            <label class="sr-only" :for="`end-${index}`">{{ day.label }} end time</label>
                            <select
                                :id="`end-${index}`"
                                :class="selectClass"
                                :value="ranges[index].end_time"
                                :aria-invalid="errorFor(index) ? 'true' : undefined"
                                :data-test="`end-${index}`"
                                @change="update(index, 'end_time', ($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="option in endOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                :aria-label="`Remove ${day.label} range ${formatTime(ranges[index].start_time)} to ${formatTime(ranges[index].end_time)}`"
                                :data-test="`remove-range-${index}`"
                                @click="removeRange(index)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <InputError :message="errorFor(index)" />
                    </div>
                    <div>
                        <Button type="button" variant="outline" size="sm" :data-test="`add-range-${day.value}`" @click="addRange(day.value)">
                            <Plus /> Add range
                        </Button>
                    </div>
                </template>
                <p v-else class="py-2 text-sm text-muted-foreground">Not available this day.</p>
            </div>
        </section>
    </div>
</template>
