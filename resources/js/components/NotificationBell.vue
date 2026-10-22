<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell } from '@lucide/vue';
import { computed } from 'vue';
import { read, readAll } from '@/actions/App/Http/Controllers/NotificationController';
import LocalDateTime from '@/components/LocalDateTime.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { NotificationItem } from '@/types';

const page = usePage();
const notifications = computed(() => page.props.notifications ?? null);
const unreadCount = computed(() => notifications.value?.unread_count ?? 0);

const open = (notification: NotificationItem): void => {
    router.post(read.url({ notification: notification.id }));
};

const markAllRead = (): void => {
    router.post(readAll.url(), {}, { preserveScroll: true });
};

</script>

<template>
    <DropdownMenu v-if="notifications">
        <DropdownMenuTrigger as-child>
            <Button
                variant="ghost"
                size="icon"
                class="relative"
                data-test="notification-bell"
                :aria-label="`Notifications, ${unreadCount} unread`"
            >
                <Bell class="size-5" />
                <span
                    v-if="unreadCount > 0"
                    class="absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] leading-4 font-semibold text-primary-foreground"
                    data-test="notification-count"
                >
                    {{ unreadCount > 99 ? '99+' : unreadCount }}
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80">
            <div class="flex items-center justify-between pr-2">
                <DropdownMenuLabel>Notifications</DropdownMenuLabel>
                <Button
                    v-if="unreadCount > 0"
                    variant="link"
                    size="sm"
                    class="h-auto p-0"
                    data-test="mark-all-read"
                    @click="markAllRead"
                >
                    Mark all read
                </Button>
            </div>
            <DropdownMenuSeparator />
            <p
                v-if="notifications.recent.length === 0"
                class="px-2 py-4 text-center text-sm text-muted-foreground"
            >
                No notifications yet.
            </p>
            <DropdownMenuItem
                v-for="notification in notifications.recent"
                :key="notification.id"
                class="flex cursor-pointer flex-col items-start gap-1 whitespace-normal"
                :data-test="`notification-${notification.id}`"
                @select="open(notification)"
            >
                <span :class="notification.read ? 'text-muted-foreground' : 'font-medium'">
                    {{ notification.message }}
                </span>
                <span class="text-xs text-muted-foreground">
                    <LocalDateTime :value="notification.created_at" />
                </span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
