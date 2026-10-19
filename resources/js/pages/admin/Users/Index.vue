<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { ArrowDown, ArrowUp, ArrowUpDown, EllipsisVertical, Plus } from '@lucide/vue';
import { ref, watch } from 'vue';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
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
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { index } from '@/routes/admin/users';

type Option = { value: string; label: string };

type ManagedUserRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: string;
    role_label: string;
    status: string;
    status_label: string;
    created_at: string | null;
    can_deactivate: boolean;
    can_reactivate: boolean;
    can_delete: boolean;
};

type LifecycleAction = 'deactivate' | 'reactivate' | 'delete';

type PaginationLink = { url: string | null; label: string; active: boolean };

type Props = {
    users: {
        data: ManagedUserRow[];
        links: PaginationLink[];
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        role: string;
        status: string;
        sort: string;
        direction: 'asc' | 'desc';
    };
    roles: Option[];
    statuses: Option[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Users', href: index() }],
    },
});

const ALL = 'all';

const search = ref(props.filters.search);
const createOpen = ref(false);
const pendingAction = ref<{ action: LifecycleAction; user: ManagedUserRow } | null>(null);
const confirmOpen = ref(false);
const reason = ref('');
const processing = ref(false);
const actionError = ref('');

const warnings: Record<LifecycleAction, { title: string; description: string; confirm: string }> = {
    deactivate: {
        title: 'Deactivate user',
        description:
            'The user will be logged out immediately and will not be able to log in until reactivated. Their data is kept.',
        confirm: 'Deactivate',
    },
    reactivate: {
        title: 'Reactivate user',
        description: 'The user will be able to log in again with their existing password.',
        confirm: 'Reactivate',
    },
    delete: {
        title: 'Delete user',
        description:
            'This permanently anonymizes the user: name, email, phone, photo and profile details are erased and cannot be restored. History records are kept under "Deleted User". This action cannot be undone.',
        confirm: 'Delete permanently',
    },
};

function openAction(action: LifecycleAction, user: ManagedUserRow): void {
    pendingAction.value = { action, user };
    reason.value = '';
    actionError.value = '';
    confirmOpen.value = true;
}

function confirmAction(): void {
    if (pendingAction.value === null) {
        return;
    }

    const { action, user } = pendingAction.value;
    const options = {
        preserveScroll: true,
        onStart: () => (processing.value = true),
        onFinish: () => (processing.value = false),
        onSuccess: () => (confirmOpen.value = false),
        onError: (errors: Record<string, string>) => {
            actionError.value = errors.reason ?? errors.user ?? '';
        },
    };

    if (action === 'delete') {
        router.delete(UserController.destroy.url(user.id), {
            ...options,
            data: { reason: reason.value },
        });
    } else {
        router.post(UserController[action].url(user.id), {}, options);
    }
}

function visit(overrides: Partial<Props['filters']>): void {
    const query = { ...props.filters, ...overrides };

    router.get(
        index.url(),
        {
            search: query.search || undefined,
            role: query.role || undefined,
            status: query.status || undefined,
            sort: query.sort,
            direction: query.direction,
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

function sortBy(column: string): void {
    const direction =
        props.filters.sort === column && props.filters.direction === 'asc'
            ? 'desc'
            : 'asc';

    visit({ sort: column, direction });
}

function ariaSort(column: string): 'ascending' | 'descending' | 'none' {
    if (props.filters.sort !== column) {
        return 'none';
    }

    return props.filters.direction === 'asc' ? 'ascending' : 'descending';
}

function statusVariant(status: string): 'default' | 'secondary' | 'destructive' {
    if (status === 'active') {
        return 'default';
    }

    return status === 'deleted' ? 'destructive' : 'secondary';
}

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'role', label: 'Role' },
    { key: 'status', label: 'Status' },
    { key: 'created_at', label: 'Created' },
];
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-semibold">Users</h1>

            <Dialog v-model:open="createOpen">
                <DialogTrigger as-child>
                    <Button data-test="create-trainer-button">
                        <Plus /> Create trainer
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create trainer</DialogTitle>
                        <DialogDescription>
                            The trainer receives an email with a temporary
                            password and a verification link.
                        </DialogDescription>
                    </DialogHeader>

                    <Form
                        v-bind="UserController.store.form()"
                        class="grid gap-4"
                        reset-on-success
                        :options="{ preserveScroll: true }"
                        v-slot="{ errors, processing }"
                        @success="createOpen = false"
                    >
                        <div class="grid gap-2">
                            <Label for="create-role">Role</Label>
                            <Input
                                id="create-role"
                                model-value="Trainer"
                                readonly
                                aria-readonly="true"
                                class="opacity-70"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="business_name">Business name</Label>
                            <Input
                                id="business_name"
                                name="business_name"
                                required
                            />
                            <InputError :message="errors.business_name" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="first_name">First name</Label>
                                <Input
                                    id="first_name"
                                    name="first_name"
                                    required
                                    autocomplete="off"
                                />
                                <InputError :message="errors.first_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="last_name">Last name</Label>
                                <Input
                                    id="last_name"
                                    name="last_name"
                                    required
                                    autocomplete="off"
                                />
                                <InputError :message="errors.last_name" />
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="email">Email address</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autocomplete="off"
                            />
                            <InputError :message="errors.email" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="phone">Phone</Label>
                            <Input
                                id="phone"
                                type="tel"
                                name="phone"
                                autocomplete="off"
                                placeholder="+15551234567"
                            />
                            <InputError :message="errors.phone" />
                        </div>

                        <DialogFooter>
                            <Button
                                type="submit"
                                :disabled="processing"
                                data-test="submit-create-trainer"
                            >
                                Create trainer
                            </Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>

        <div class="flex flex-wrap items-end gap-3">
            <div class="grid min-w-64 flex-1 gap-1.5">
                <Label for="user-search">Search</Label>
                <Input
                    id="user-search"
                    v-model="search"
                    type="search"
                    placeholder="First name, last name or email"
                />
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-role">Role</Label>
                <Select
                    :model-value="filters.role || ALL"
                    @update:model-value="
                        (value) => visit({ role: value === ALL ? '' : String(value) })
                    "
                >
                    <SelectTrigger id="filter-role" class="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All roles</SelectItem>
                        <SelectItem
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-1.5">
                <Label for="filter-status">Status</Label>
                <Select
                    :model-value="filters.status || ALL"
                    @update:model-value="
                        (value) => visit({ status: value === ALL ? '' : String(value) })
                    "
                >
                    <SelectTrigger id="filter-status" class="w-44">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem :value="ALL">All statuses</SelectItem>
                        <SelectItem
                            v-for="status in statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">
                    Users
                </caption>
                <thead class="border-b bg-muted/40">
                    <tr>
                        <th
                            v-for="column in columns"
                            :key="column.key"
                            scope="col"
                            class="px-4 py-3 font-medium"
                            :aria-sort="ariaSort(column.key)"
                        >
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-sm focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @click="sortBy(column.key)"
                            >
                                {{ column.label }}
                                <ArrowUp
                                    v-if="ariaSort(column.key) === 'ascending'"
                                    class="size-3.5"
                                />
                                <ArrowDown
                                    v-else-if="ariaSort(column.key) === 'descending'"
                                    class="size-3.5"
                                />
                                <ArrowUpDown
                                    v-else
                                    class="size-3.5 opacity-50"
                                />
                            </button>
                        </th>
                        <th scope="col" class="px-4 py-3">
                            <span class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="users.data.length === 0">
                        <td
                            :colspan="columns.length + 1"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No users found.
                        </td>
                    </tr>
                    <tr
                        v-for="user in users.data"
                        :key="user.id"
                        class="border-b last:border-0"
                        :class="{ 'opacity-60': user.status !== 'active' }"
                        :data-status="user.status"
                    >
                        <td class="px-4 py-3 font-medium">{{ user.name }}</td>
                        <td class="px-4 py-3">{{ user.email }}</td>
                        <td class="px-4 py-3">{{ user.role_label }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(user.status)">
                                {{ user.status_label }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">{{ user.created_at }}</td>
                        <td class="px-4 py-3 text-right">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="`Actions for ${user.name}`"
                                    >
                                        <EllipsisVertical />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem
                                        v-if="user.status !== 'deleted'"
                                        as-child
                                    >
                                        <Link
                                            :href="UserController.edit.url(user.id)"
                                        >
                                            Edit
                                        </Link>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="user.can_deactivate"
                                        @select="openAction('deactivate', user)"
                                    >
                                        Deactivate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="user.can_reactivate"
                                        @select="openAction('reactivate', user)"
                                    >
                                        Reactivate
                                    </DropdownMenuItem>
                                    <DropdownMenuItem
                                        v-if="user.can_delete"
                                        class="text-destructive focus:text-destructive"
                                        @select="openAction('delete', user)"
                                    >
                                        Delete
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav
            class="flex flex-wrap items-center justify-between gap-2"
            aria-label="Pagination"
        >
            <p class="text-sm text-muted-foreground">
                <template v-if="users.total > 0">
                    Showing {{ users.from }}–{{ users.to }} of {{ users.total }}
                </template>
                <template v-else>0 results</template>
            </p>

            <ul v-if="users.links.length > 3" class="flex flex-wrap gap-1">
                <li v-for="(link, i) in users.links" :key="i">
                    <Button
                        v-if="link.url"
                        as-child
                        size="sm"
                        :variant="link.active ? 'default' : 'outline'"
                    >
                        <Link
                            :href="link.url"
                            preserve-scroll
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

        <Dialog v-model:open="confirmOpen">
            <DialogContent v-if="pendingAction">
                <DialogHeader>
                    <DialogTitle>
                        {{ warnings[pendingAction.action].title }}:
                        {{ pendingAction.user.name }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ warnings[pendingAction.action].description }}
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="confirmAction">
                    <div
                        v-if="pendingAction.action === 'delete'"
                        class="grid gap-2"
                    >
                        <Label for="delete-reason">Reason for deletion</Label>
                        <textarea
                            id="delete-reason"
                            v-model="reason"
                            name="reason"
                            rows="3"
                            required
                            maxlength="2000"
                            class="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    </div>
                    <InputError :message="actionError" />

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="confirmOpen = false"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :variant="
                                pendingAction.action === 'reactivate'
                                    ? 'default'
                                    : 'destructive'
                            "
                            :disabled="processing"
                            data-test="confirm-lifecycle-action"
                        >
                            {{ warnings[pendingAction.action].confirm }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
