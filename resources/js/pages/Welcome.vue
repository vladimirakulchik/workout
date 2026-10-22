<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarClock,
    ShieldCheck,
    Users,
    UsersRound,
} from '@lucide/vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import { dashboard, login } from '@/routes';

const page = usePage();
const name = page.props.name;

const features = [
    {
        icon: Users,
        title: 'Players & families',
        description:
            'Parents manage their children, switch between profiles and approve purchases in one place.',
    },
    {
        icon: UsersRound,
        title: 'Coaches',
        description:
            'Invite coaches, assign them to players and keep every session on schedule.',
    },
    {
        icon: CalendarClock,
        title: 'Availability',
        description:
            'Players and coaches share weekly availability, with conflicts flagged before they happen.',
    },
    {
        icon: ShieldCheck,
        title: 'Your own portal',
        description:
            'Each trainer gets a private, branded space with their own logo and invite links.',
    },
];
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col bg-background text-foreground">
        <header
            class="mx-auto flex w-full max-w-5xl items-center justify-between p-6"
        >
            <div class="flex items-center gap-2">
                <div
                    class="flex size-9 items-center justify-center rounded-md bg-brand"
                >
                    <AppLogoIcon class="size-5 fill-current text-white" />
                </div>
                <span class="text-block-title">{{ name }}</span>
            </div>
            <Button v-if="$page.props.auth.user" as-child variant="outline">
                <Link :href="dashboard()">Dashboard</Link>
            </Button>
        </header>

        <main
            class="mx-auto flex w-full max-w-5xl flex-1 flex-col items-center justify-center gap-12 px-6 py-12"
        >
            <section class="flex max-w-2xl flex-col items-center gap-6 text-center">
                <h1 class="text-4xl font-bold tracking-tight sm:text-5xl">
                    Run your training business with
                    <span class="text-brand">{{ name }}</span>
                </h1>
                <p class="text-body-lg text-muted-foreground">
                    One place for trainers, coaches, players and parents to
                    stay organised and on schedule.
                </p>
                <Button
                    as-child
                    size="lg"
                    class="h-14 px-12 text-lg font-semibold"
                >
                    <Link :href="$page.props.auth.user ? dashboard() : login()">
                        {{ $page.props.auth.user ? 'Go to dashboard' : 'Log in' }}
                    </Link>
                </Button>
            </section>

            <section class="grid w-full gap-4 sm:grid-cols-2">
                <div
                    v-for="feature in features"
                    :key="feature.title"
                    class="flex gap-4 rounded-lg border border-border bg-card p-6 shadow-card-soft"
                >
                    <component
                        :is="feature.icon"
                        class="mt-0.5 size-6 shrink-0 text-brand"
                    />
                    <div>
                        <h2 class="text-card-title">{{ feature.title }}</h2>
                        <p class="mt-1 text-body text-muted-foreground">
                            {{ feature.description }}
                        </p>
                    </div>
                </div>
            </section>
        </main>
    </div>
</template>
