<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { dashboard as adminDashboard } from '@/routes/admin';
import { index as plansIndex } from '@/routes/admin/plans';
import { edit as editUser, index as usersIndex } from '@/routes/admin/users';

defineProps<{
    stats: {
        users: number;
        newUsers: number;
        suspendedUsers: number;
        teams: number;
        plans: number;
        leads: number;
        sites: number;
        paidInvoices: number;
    };
    monthlyUsage: { label: string; count: number }[];
    recentUsers: {
        id: number;
        name: string;
        email: string;
        created_at: string;
        suspended_at: string | null;
    }[];
    planBreakdown: { id: number; name: string; teams_count: number }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin', href: adminDashboard() }],
    },
});
</script>

<template>
    <Head title="Admin" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Admin"
            description="Platform-wide numbers. Manage users, teams and plans from here."
        />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Users</p>
                <p class="text-2xl font-semibold">{{ stats.users }}</p>
                <p class="text-xs text-muted-foreground">
                    {{ stats.newUsers }} new in 30 days
                    <template v-if="stats.suspendedUsers">
                        · {{ stats.suspendedUsers }} suspended
                    </template>
                </p>
            </div>
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Teams</p>
                <p class="text-2xl font-semibold">{{ stats.teams }}</p>
            </div>
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Leads · Websites</p>
                <p class="text-2xl font-semibold">
                    {{ stats.leads }} · {{ stats.sites }}
                </p>
            </div>
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Paid invoices</p>
                <p class="text-2xl font-semibold">{{ stats.paidInvoices }}</p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <section class="panel p-5">
                <h2 class="mb-3 text-base font-semibold">Usage this month</h2>
                <ul class="grid gap-2 text-sm">
                    <li
                        v-for="row in monthlyUsage"
                        :key="row.label"
                        class="flex justify-between"
                    >
                        <span class="text-muted-foreground">{{
                            row.label
                        }}</span>
                        <span class="font-medium">{{ row.count }}</span>
                    </li>
                </ul>
            </section>

            <section class="panel p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold">Plans</h2>
                    <Link
                        :href="plansIndex()"
                        class="text-sm text-primary hover:underline"
                        >Manage</Link
                    >
                </div>
                <p
                    v-if="!planBreakdown.length"
                    class="text-sm text-muted-foreground"
                >
                    No plans yet, so every team is unlimited.
                </p>
                <ul v-else class="grid gap-2 text-sm">
                    <li
                        v-for="plan in planBreakdown"
                        :key="plan.id"
                        class="flex justify-between"
                    >
                        <span>{{ plan.name }}</span>
                        <span class="text-muted-foreground"
                            >{{ plan.teams_count }} teams</span
                        >
                    </li>
                </ul>
            </section>
        </div>

        <section class="panel p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-base font-semibold">Newest users</h2>
                <Link
                    :href="usersIndex()"
                    class="text-sm text-primary hover:underline"
                    >All users</Link
                >
            </div>
            <ul class="divide-y text-sm">
                <li
                    v-for="user in recentUsers"
                    :key="user.id"
                    class="flex items-center justify-between py-2"
                >
                    <Link
                        :href="editUser(user.id)"
                        class="hover:underline"
                        >{{ user.name }}</Link
                    >
                    <span class="text-muted-foreground">{{ user.email }}</span>
                </li>
            </ul>
        </section>
    </div>
</template>