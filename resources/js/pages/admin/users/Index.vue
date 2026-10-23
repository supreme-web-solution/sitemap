<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Search } from '@lucide/vue';
import { ref } from 'vue';
import PaginationBar from '@/components/admin/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit, index, store } from '@/routes/admin/users';
import type { AdminUser, Paginated, PlanOption } from '@/types';

const props = defineProps<{
    users: Paginated<AdminUser>;
    filters: { search: string; status: string };
    plans: PlanOption[];
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

const applyFilters = () =>
    router.get(
        index.url(),
        { search: search.value || undefined, status: status.value || undefined },
        { preserveState: true, replace: true },
    );

const creating = ref(false);

const form = useForm({
    name: '',
    email: '',
    password: '',
    verified: true,
    plan_id: '' as number | '',
});

const submit = () => {
    form.transform((data) => ({ ...data, plan_id: data.plan_id || null })).post(
        store.url(),
        { onSuccess: () => form.reset() },
    );
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Users" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Users"
                description="Create, edit, suspend or sign in as any user."
            />
            <Button @click="creating = !creating">
                <Plus /> New user
            </Button>
        </div>

        <form
            v-if="creating"
            class="grid gap-4 panel p-5 sm:grid-cols-2"
            data-test="create-user-form"
            @submit.prevent="submit"
        >
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
                <Input
                    id="new-password"
                    v-model="form.password"
                    type="password"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.password" />
            </div>
            <div class="grid gap-2">
                <Label for="new-plan">Plan</Label>
                <select id="new-plan" v-model="form.plan_id" :class="selectClass">
                    <option value="">Default plan</option>
                    <option v-for="plan in plans" :key="plan.id" :value="plan.id">
                        {{ plan.name }}
                    </option>
                </select>
                <InputError :message="form.errors.plan_id" />
            </div>
            <label class="flex items-center gap-2 text-sm sm:col-span-2">
                <input v-model="form.verified" type="checkbox" class="size-4" />
                Mark the email as verified
            </label>
            <div class="flex gap-2 sm:col-span-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Create user
                </Button>
                <Button type="button" variant="ghost" @click="creating = false"
                    >Cancel</Button
                >
            </div>
        </form>

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
            <select
                v-model="status"
                :class="[selectClass, 'w-44']"
                aria-label="Status"
                @change="applyFilters"
            >
                <option value="">All users</option>
                <option value="active">Active</option>
                <option value="suspended">Suspended</option>
                <option value="unverified">Unverified</option>
            </select>
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
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="p-3">
                            <Link
                                :href="edit(user.id)"
                                class="font-medium hover:underline"
                                >{{ user.name }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
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
                    </tr>
                    <tr v-if="!users.data.length">
                        <td colspan="4" class="p-6 text-center text-muted-foreground">
                            No users match.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :paginator="users" />
    </div>
</template>