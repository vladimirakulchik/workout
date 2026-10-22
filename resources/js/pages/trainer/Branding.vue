<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import BrandingController from '@/actions/App/Http/Controllers/Trainer/BrandingController';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { applyBrand, DEFAULT_BRAND, isValidHex } from '@/lib/branding';
import { edit } from '@/routes/trainer/branding';
import type { Branding } from '@/types/branding';

const props = defineProps<{
    current: Branding;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Branding', href: edit() }],
    },
});

const color = ref(props.current.color ?? '');
const previewLogoUrl = ref<string | null>(null);
const resetting = ref(false);

const colorPickerValue = computed(() =>
    isValidHex(color.value) && color.value.startsWith('#')
        ? color.value
        : DEFAULT_BRAND,
);
const colorIsValid = computed(
    () => color.value === '' || /^#[0-9A-Fa-f]{6}$/.test(color.value),
);
const displayLogoUrl = computed(
    () => previewLogoUrl.value ?? props.current.logo_url,
);

watch(
    color,
    (value) => applyBrand(colorIsValid.value && value !== '' ? value : null),
    { immediate: true },
);

watch(
    () => props.current,
    (current) => {
        color.value = current.color ?? '';
        previewLogoUrl.value = null;
    },
);

onBeforeUnmount(() => applyBrand(props.current.color));

function onLogoChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    previewLogoUrl.value = file ? URL.createObjectURL(file) : null;
}

function onPickerInput(event: Event): void {
    color.value = (event.target as HTMLInputElement).value.toUpperCase();
}

function reset(): void {
    router.delete(BrandingController.destroy.url(), {
        preserveScroll: true,
        onStart: () => (resetting.value = true),
        onFinish: () => (resetting.value = false),
    });
}
</script>

<template>
    <Head title="Branding" />

    <div class="flex flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Branding"
            description="Your logo and accent colour are shown to your coaches and players."
        />

        <div class="grid gap-8 lg:grid-cols-2">
            <Form
                v-bind="BrandingController.update.form()"
                enctype="multipart/form-data"
                class="space-y-6"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="logo">Logo</Label>
                    <Input
                        id="logo"
                        type="file"
                        name="logo"
                        accept="image/png,image/jpeg,image/svg+xml"
                        aria-describedby="logo-help"
                        @change="onLogoChange"
                    />
                    <p id="logo-help" class="text-xs text-muted-foreground">
                        PNG, JPG or SVG, up to 2 MB. Images are scaled down to
                        200×200.
                    </p>
                    <InputError :message="errors.logo" />
                </div>

                <div class="grid gap-2">
                    <Label for="brand_color">Primary colour</Label>
                    <div class="flex items-center gap-3">
                        <input
                            type="color"
                            aria-label="Pick primary colour"
                            :value="colorPickerValue"
                            class="size-10 shrink-0 cursor-pointer rounded-md border border-input bg-transparent p-1 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            @input="onPickerInput"
                        />
                        <Input
                            id="brand_color"
                            v-model="color"
                            name="brand_color"
                            placeholder="#00B300"
                            maxlength="7"
                            autocomplete="off"
                            :aria-invalid="!colorIsValid"
                            aria-describedby="color-help"
                            class="max-w-40 font-mono uppercase"
                        />
                    </div>
                    <p id="color-help" class="text-xs text-muted-foreground">
                        Six-digit hex value, for example #00B300. Leave empty
                        for the default colour.
                    </p>
                    <InputError
                        :message="
                            errors.brand_color ||
                            (colorIsValid
                                ? undefined
                                : 'Enter a hex colour such as #00B300.')
                        "
                    />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <Button type="submit" :disabled="processing || !colorIsValid"
                        >Save branding</Button
                    >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="resetting"
                        @click="reset"
                    >
                        Reset to default
                    </Button>
                </div>
            </Form>

            <section
                aria-labelledby="preview-heading"
                class="space-y-4 rounded-lg border border-border bg-card p-6"
            >
                <h2 id="preview-heading" class="text-card-title">Preview</h2>
                <div class="flex items-center gap-3">
                    <img
                        v-if="displayLogoUrl"
                        :src="displayLogoUrl"
                        alt="Logo preview"
                        class="size-12 rounded-md object-contain"
                    />
                    <div
                        v-else
                        class="flex size-12 items-center justify-center rounded-md bg-brand"
                    >
                        <AppLogoIcon class="size-8 fill-current text-white" />
                    </div>
                    <span class="font-semibold">Workout</span>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <span
                        class="rounded-md bg-brand px-4 py-2 text-sm font-medium text-white"
                        >Sample button</span
                    >
                    <span class="text-sm font-medium text-brand"
                        >Sample link</span
                    >
                </div>
            </section>
        </div>
    </div>
</template>
