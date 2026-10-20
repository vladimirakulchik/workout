<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    ClipboardList,
    Clock,
    History,
    LayoutGrid,
    Link as LinkIcon,
    Palette,
    UserRound,
    Users,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const icons: Record<string, LucideIcon> = {
    ClipboardCheck,
    ClipboardList,
    Clock,
    History,
    LayoutGrid,
    Link: LinkIcon,
    Palette,
    UserRound,
    Users,
};

const page = usePage();

const mainNavItems = computed<NavItem[]>(() =>
    page.props.navigation.map((item) => ({
        title: item.title,
        href: item.href,
        icon: icons[item.icon],
    })),
);

const homeHref = computed(() => mainNavItems.value[0]?.href ?? dashboard());
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
