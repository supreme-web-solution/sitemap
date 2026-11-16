<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Search } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PaginationBar from '@/components/admin/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    emptyToSelect,
    SELECT_ALL,
    SELECT_DEFAULT,
    selectToEmpty,
} from '@/lib/selectSentinels';
import { dashboard as adminDashboard } from '@/routes/admin';
import { update as updateTeam } from '@/routes/admin/teams';
import {
    destroy,
    impersonate,
    index,
    restore,
    store,
    suspend,
    update,
} from '@/routes/admin/users';
import type { AdminUser, AdminUserTeam, Paginated, PlanOption } from '@/types';

const props = defineProps<{
    users: Paginated<AdminUser>;
    filters: { search: string; status: string };
    plans: PlanOption[];
    rolesEnabled: boolean;
    assignableRoles: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Users', href: index() },
        ],
    },
});

const search = ref(props.filters.search);
const status = ref(props.filters.status);
const creating = ref(false);
const editingId = ref<number | null>(null);

const applyFilters = () =>
    router.get(
        index.url(),
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );

const form = useForm({
    name: '',
    email: '',
    password: '',
    verified: true,
    plan_id: '' as number | '',
    roles: ['FE'] as string[],
});

const editForm = useForm({
    name: '',
    email: '',
    password: '',
    verified: false,
    roles: [] as string[],
});

const editingUser = computed(
    () => props.users.data.find((user) => user.id === editingId.value) ?? null,
);

watch(creating, (isOpen) => {
    if (!isOpen) {
        form.reset();
        form.clearErrors();
        form.verified = true;
        form.roles = ['FE'];
    }
});

const startCreating = () => {
    form.reset();
    form.clearErrors();
    form.verified = true;
    form.roles = ['FE'];
    creating.value = true;
};

const startEditing = (user: AdminUser) => {
    editForm.defaults({
        name: user.name,
        email: user.email,
        password: '',
        verified: user.email_verified_at !== null,
        roles: [...(user.roles ?? [])],
    });
    editForm.reset();
    editForm.clearErrors();
    editingId.value = user.id;
};

watch(editingId, (id) => {
    if (id === null) {
        editForm.reset();
        editForm.clearErrors();
    }
});

const saveEdit = () => {
    if (editingId.value === null) {
        return;
    }

    editForm.put(update.url(editingId.value), {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
};

const setTeamPlan = (team: AdminUserTeam, value: string) =>
    router.put(
        updateTeam.url(team.slug),
        { plan_id: value === '' ? null : Number(value) },
        { preserveScroll: true, preserveState: true },
    );

const submit = () => {
    form.transform((data) => ({ ...data, plan_id: data.plan_id || null })).post(
        store.url(),
        {
            onSuccess: () => {
                creating.value = false;
            },
        },
    );
};

const remove = (user: AdminUser) => {
    if (!user.can_manage) {
        return;
    }

    if (
        confirm(
            `Delete ${user.name}? Teams where they are the only member are deleted too.`,
        )
    ) {
        router.delete(destroy.url(user.id));
    }
};

const protectedReason = (user: AdminUser) =>
    user.is_admin
        ? 'Administrators cannot be impersonated, suspended or deleted.'
        : 'You cannot impersonate, suspend or delete your own account.';

const planIdSelect = computed({
    get: () =>
        form.plan_id === '' ? SELECT_DEFAULT : String(form.plan_id),
    set: (value: string) => {
        form.plan_id = value === SELECT_DEFAULT ? '' : Number(value);
    },
});

const statusFilterSelect = computed({
    get: () => emptyToSelect(status.value),
    set: (value: string) => {
        status.value = selectToEmpty(value);
        applyFilters();
    },
});
</script>

<template>
    <Head title="Users" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Users"
                description="Create, edit, suspend, sign in as any user, and set the product access they bought."
            />
            <Button @click="startCreating">
                <Plus /> New user
            </Button>
        </div>

        <form
            class="flex flex-wrap items-center gap-2"
            @submit.prevent="applyFilters"
        >
            <div class="relative min-w-56 flex-1">
                <Search
                    class="absolute top-2.5 left-2.5 size-4 text-muted-foreground"
                />
                <Input
                    v-model="search"
                    class="pl-8"
                    placeholder="Search name or email"
                    aria-label="Search users"
                />
            </div>
            <Select v-model="statusFilterSelect">
                <SelectTrigger class="h-9 w-44" aria-label="Status">
                    <SelectValue placeholder="All users" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="SELECT_ALL">All users</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="suspended">Suspended</SelectItem>
                    <SelectItem value="unverified">Unverified</SelectItem>
                </SelectContent>
            </Select>
            <Button type="submit" variant="outline">Search</Button>
        </form>

        <div class="panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 font-medium">User</th>
                        <th class="p-3 font-medium">Teams</th>
                        <th class="p-3 font-medium">Joined</th>
                        <th class="p-3 font-medium">Status</th>
                        <th class="p-3 font-medium">Access</th>
                        <th class="p-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="p-3">
                            <button
                                type="button"
                                class="font-medium hover:underline"
                                @click="startEditing(user)"
                            >
                                {{ user.name }}
                            </button>
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                            <p
                                v-if="user.reseller_email"
                                class="text-xs text-muted-foreground"
                            >
                                via reseller {{ user.reseller_email }}
                            </p>
                        </td>
                        <td class="p-3">{{ user.teams_count }}</td>
                        <td class="p-3">
                            {{ new Date(user.created_at).toLocaleDateString() }}
                        </td>
                        <td class="p-3">
                            <span
                                v-if="user.suspended_at"
                                class="rounded-full bg-rose-100 px-2 py-0.5 text-xs text-rose-700"
                                >Suspended</span
                            >
                            <span
                                v-else-if="user.is_admin"
                                class="rounded-full bg-violet-100 px-2 py-0.5 text-xs text-violet-700"
                                >Admin</span
                            >
                            <span
                                v-else-if="!user.email_verified_at"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700"
                                >Unverified</span
                            >
                            <span
                                v-else
                                class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs text-emerald-700"
                                >Active</span
                            >
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap gap-1">
                                <span
                                    v-for="role in user.roles?.length
                                        ? user.roles
                                        : ['Core app']"
                                    :key="role"
                                    class="rounded-full bg-muted px-2 py-0.5 text-xs"
                                >
                                    {{ role }}
                                </span>
                            </div>
                        </td>
                        <td class="p-3">
                            <div
                                class="flex flex-wrap items-center justify-end gap-1"
                            >
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="startEditing(user)"
                                >
                                    Edit
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    :disabled="!user.can_manage"
                                    :title="
                                        user.can_manage
                                            ? undefined
                                            : protectedReason(user)
                                    "
                                    @click="
                                        user.can_manage &&
                                        router.post(impersonate.url(user.id))
                                    "
                                >
                                    Sign in as
                                </Button>
                                <Button
                                    v-if="!user.suspended_at"
                                    size="sm"
                                    variant="outline"
                                    :disabled="!user.can_manage"
                                    :title="
                                        user.can_manage
                                            ? undefined
                                            : protectedReason(user)
                                    "
                                    @click="
                                        user.can_manage &&
                                        router.post(
                                            suspend.url(user.id),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    "
                                >
                                    Suspend
                                </Button>
                                <Button
                                    v-else
                                    size="sm"
                                    variant="outline"
                                    :disabled="!user.can_manage"
                                    :title="
                                        user.can_manage
                                            ? undefined
                                            : protectedReason(user)
                                    "
                                    @click="
                                        user.can_manage &&
                                        router.delete(restore.url(user.id), {
                                            preserveScroll: true,
                                        })
                                    "
                                >
                                    Reactivate
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    :disabled="!user.can_manage"
                                    :title="
                                        user.can_manage
                                            ? undefined
                                            : protectedReason(user)
                                    "
                                    @click="remove(user)"
                                >
                                    Delete
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!users.data.length">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No users match.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :paginator="users" />

        <Dialog v-model:open="creating">
            <DialogScrollContent class="max-w-lg" data-test="create-user-form">
                <DialogHeader>
                    <DialogTitle>New user</DialogTitle>
                    <DialogDescription>
                        Creates a personal team. Pick a plan for that team, or leave
                        Default plan so they inherit the platform default.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="new-name">Name</Label>
                        <Input id="new-name" v-model="form.name" autocomplete="off" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-email">Email</Label>
                        <Input
                            id="new-email"
                            v-model="form.email"
                            type="email"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="new-password">Password</Label>
                        <PasswordInput
                            id="new-password"
                            v-model="form.password"
                            autocomplete="new-password"
                        />
                        <InputError :message="form.errors.password" />
                    </div>
                    <div class="grid gap-2">
                        <Label>Plan</Label>
                        <Select v-model="planIdSelect">
                            <SelectTrigger class="w-full" aria-label="Plan">
                                <SelectValue placeholder="Default plan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="SELECT_DEFAULT"
                                    >Default plan</SelectItem
                                >
                                <SelectItem
                                    v-for="plan in plans"
                                    :key="plan.id"
                                    :value="String(plan.id)"
                                >
                                    {{ plan.name }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.plan_id" />
                    </div>
                    <label class="flex items-center gap-2 text-sm sm:col-span-2">
                        <input
                            v-model="form.verified"
                            type="checkbox"
                            class="size-4"
                        />
                        Mark the email as verified
                    </label>
                    <div
                        v-if="rolesEnabled && assignableRoles.length"
                        class="grid gap-2 sm:col-span-2"
                    >
                        <Label>Product access</Label>
                        <label
                            v-for="role in assignableRoles"
                            :key="role"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.roles"
                                type="checkbox"
                                class="size-4"
                                :value="role"
                            />
                            {{ role }}
                        </label>
                        <InputError :message="form.errors.roles" />
                    </div>
                    <div class="flex gap-2 sm:col-span-2">
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle
                                v-if="form.processing"
                                class="animate-spin"
                            />
                            Create user
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="creating = false"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>

        <Dialog
            :open="editingUser !== null"
            @update:open="(open) => { if (!open) editingId = null; }"
        >
            <DialogScrollContent class="max-w-2xl" data-test="edit-user-form">
                <DialogHeader v-if="editingUser">
                    <DialogTitle>Edit {{ editingUser.name }}</DialogTitle>
                    <DialogDescription>{{ editingUser.email }}</DialogDescription>
                </DialogHeader>

                <form
                    v-if="editingUser"
                    class="grid gap-4"
                    @submit.prevent="saveEdit"
                >
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="edit-name">Name</Label>
                            <Input id="edit-name" v-model="editForm.name" />
                            <InputError :message="editForm.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-email">Email</Label>
                            <Input
                                id="edit-email"
                                v-model="editForm.email"
                                type="email"
                            />
                            <InputError :message="editForm.errors.email" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit-password">New password</Label>
                            <PasswordInput
                                id="edit-password"
                                v-model="editForm.password"
                                autocomplete="new-password"
                                placeholder="Leave blank to keep the current one"
                            />
                            <InputError :message="editForm.errors.password" />
                        </div>
                        <label class="flex items-end gap-2 pb-2 text-sm">
                            <input
                                v-model="editForm.verified"
                                type="checkbox"
                                class="size-4"
                            />
                            Email verified
                        </label>
                    </div>
                    <div
                        v-if="rolesEnabled && assignableRoles.length"
                        class="grid gap-2"
                    >
                        <Label>Product access</Label>
                        <p class="text-xs text-muted-foreground">
                            Leave every box unchecked to keep core app access
                            only. Fast-pass and the full bundle both use the
                            Bundle role.
                        </p>
                        <label
                            v-for="role in assignableRoles"
                            :key="role"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="editForm.roles"
                                type="checkbox"
                                class="size-4"
                                :value="role"
                            />
                            {{ role }}
                        </label>
                        <InputError :message="editForm.errors.roles" />
                    </div>
                    <div v-if="editingUser.teams?.length" class="grid gap-2">
                        <Label>Teams and plans</Label>
                        <ul class="divide-y text-sm">
                            <li
                                v-for="team in editingUser.teams"
                                :key="team.id"
                                class="flex flex-wrap items-center justify-between gap-2 py-3"
                            >
                                <div>
                                    <p class="font-medium">{{ team.name }}</p>
                                    <p class="text-xs text-muted-foreground capitalize">
                                        {{ team.role }}
                                        <template v-if="team.is_personal">
                                            · personal</template
                                        >
                                    </p>
                                </div>
                                <Select
                                    :model-value="
                                        team.plan_id
                                            ? String(team.plan_id)
                                            : SELECT_DEFAULT
                                    "
                                    @update:model-value="
                                        (value) => {
                                            const chosen = String(value ?? '');
                                            setTeamPlan(
                                                team,
                                                chosen === SELECT_DEFAULT
                                                    ? ''
                                                    : chosen,
                                            );
                                        }
                                    "
                                >
                                    <SelectTrigger
                                        class="h-9 w-44"
                                        :aria-label="`Plan for ${team.name}`"
                                    >
                                        <SelectValue placeholder="Default plan" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem :value="SELECT_DEFAULT">
                                            Default plan
                                        </SelectItem>
                                        <SelectItem
                                            v-for="plan in plans"
                                            :key="plan.id"
                                            :value="String(plan.id)"
                                        >
                                            {{ plan.name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </li>
                        </ul>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="editForm.processing">
                            <LoaderCircle
                                v-if="editForm.processing"
                                class="animate-spin"
                            />
                            Save changes
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            @click="editingId = null"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
