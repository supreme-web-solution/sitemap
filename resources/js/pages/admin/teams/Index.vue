<script setup lang="ts">
import { Head, Link, router, useHttp } from '@inertiajs/vue3';
import { LoaderCircle, Search, Trash2, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PaginationBar from '@/components/admin/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SELECT_ALL, SELECT_DEFAULT } from '@/lib/selectSentinels';
import { dashboard as adminDashboard } from '@/routes/admin';
import { edit as editUser } from '@/routes/admin/users';
import { destroy, index, members as membersRoute, update } from '@/routes/admin/teams';
import type { AdminTeam, Paginated, PlanOption } from '@/types';

type TeamMember = {
    id: number;
    name: string;
    email: string;
    role: string;
    suspended_at: string | null;
    email_verified_at: string | null;
    is_admin: boolean;
};

const props = defineProps<{
    teams: Paginated<AdminTeam>;
    filters: { search: string; plan: string };
    plans: PlanOption[];
    defaultPlan: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Teams', href: index() },
        ],
    },
});

const search = ref(props.filters.search);
const plan = ref(props.filters.plan);
const membersTeam = ref<AdminTeam | null>(null);
const members = ref<TeamMember[]>([]);
const membersError = ref<string | null>(null);
const membersHttp = useHttp<
    Record<string, never>,
    { members: TeamMember[] }
>({});

const applyFilters = () =>
    router.get(
        index.url(),
        { search: search.value || undefined, plan: plan.value || undefined },
        { preserveState: true, replace: true },
    );

const setPlan = (team: AdminTeam, value: string) =>
    router.put(
        update.url(team.slug),
        { plan_id: value === '' ? null : Number(value) },
        { preserveScroll: true },
    );

const planFilterSelect = computed({
    get: () => (plan.value === '' ? SELECT_ALL : plan.value),
    set: (value: string) => {
        plan.value = value === SELECT_ALL ? '' : value;
        applyFilters();
    },
});

const teamPlanValue = (team: AdminTeam) =>
    team.plan_id ? String(team.plan_id) : SELECT_DEFAULT;

const membersOpen = computed({
    get: () => membersTeam.value !== null,
    set: (open: boolean) => {
        if (!open) {
            membersTeam.value = null;
            members.value = [];
            membersError.value = null;
        }
    },
});

const openMembers = async (team: AdminTeam) => {
    membersTeam.value = team;
    members.value = [];
    membersError.value = null;

    try {
        const response = await membersHttp.get(membersRoute.url(team.slug));
        members.value = response?.members ?? [];
    } catch {
        membersError.value = 'Could not load members.';
    }
};

watch(membersTeam, (team) => {
    if (!team) {
        members.value = [];
        membersError.value = null;
    }
});

const remove = (team: AdminTeam) => {
    if (
        confirm(
            `Delete ${team.name}? Members with no other teams will be deleted too. Admins are kept.`,
        )
    ) {
        router.delete(destroy.url(team.slug), { preserveScroll: true });
    }
};

const roleLabel = (role: string) =>
    role ? role.charAt(0).toUpperCase() + role.slice(1) : 'Member';
</script>

<template>
    <Head title="Teams" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Teams"
            :description="
                defaultPlan
                    ? `Assign a plan per team below. Teams with no plan use the default (${defaultPlan}).`
                    : 'Assign a plan per team below. No default plan is set, so teams without a plan are unlimited.'
            "
        />

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
                    placeholder="Search teams"
                    aria-label="Search teams"
                />
            </div>
            <Select v-model="planFilterSelect">
                <SelectTrigger class="h-9 w-44" aria-label="Plan filter">
                    <SelectValue placeholder="Any plan" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="SELECT_ALL">Any plan</SelectItem>
                    <SelectItem value="none">No plan assigned</SelectItem>
                    <SelectItem
                        v-for="p in plans"
                        :key="p.id"
                        :value="String(p.id)"
                    >
                        {{ p.name }}
                    </SelectItem>
                </SelectContent>
            </Select>
            <Button type="submit" variant="outline">Search</Button>
        </form>

        <div class="panel overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 font-medium">Team</th>
                        <th class="p-3 font-medium">Members</th>
                        <th class="p-3 font-medium">Leads</th>
                        <th class="p-3 font-medium">Sites</th>
                        <th class="p-3 font-medium">Plan</th>
                        <th class="p-3 font-medium text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="team in teams.data" :key="team.id">
                        <td class="p-3">
                            <p class="font-medium">{{ team.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ team.owner }}
                            </p>
                        </td>
                        <td class="p-3">{{ team.members_count }}</td>
                        <td class="p-3">{{ team.leads_count }}</td>
                        <td class="p-3">{{ team.sites_count }}</td>
                        <td class="p-3">
                            <Select
                                :model-value="teamPlanValue(team)"
                                @update:model-value="
                                    (value) => {
                                        const chosen = String(value ?? '');
                                        setPlan(
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
                                        v-for="p in plans"
                                        :key="p.id"
                                        :value="String(p.id)"
                                    >
                                        {{ p.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </td>
                        <td class="p-3">
                            <div class="flex flex-wrap items-center justify-end gap-1">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="openMembers(team)"
                                >
                                    <Users class="size-3.5" />
                                    Members
                                </Button>
                                <Button
                                    size="icon-sm"
                                    variant="ghost"
                                    aria-label="Delete team"
                                    @click="remove(team)"
                                >
                                    <Trash2 />
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!teams.data.length">
                        <td
                            colspan="6"
                            class="p-6 text-center text-muted-foreground"
                        >
                            No teams match.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :paginator="teams" />

        <Dialog v-model:open="membersOpen">
            <DialogScrollContent class="max-w-lg" data-test="team-members-modal">
                <DialogHeader>
                    <DialogTitle>
                        Members of {{ membersTeam?.name }}
                    </DialogTitle>
                    <DialogDescription>
                        Loaded when you open this panel.
                        {{ membersTeam?.members_count ?? 0 }} on the team.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3">
                    <div
                        v-if="membersHttp.processing"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <LoaderCircle class="size-4 animate-spin" />
                        Loading members…
                    </div>
                    <p v-else-if="membersError" class="text-sm text-rose-600">
                        {{ membersError }}
                    </p>
                    <p
                        v-else-if="!members.length"
                        class="text-sm text-muted-foreground"
                    >
                        No members on this team.
                    </p>
                    <ul v-else class="grid gap-2">
                        <li
                            v-for="member in members"
                            :key="member.id"
                            class="flex items-start justify-between gap-3 rounded-xl border px-4 py-3"
                        >
                            <div class="min-w-0">
                                <Link
                                    :href="editUser(member.id)"
                                    class="truncate text-sm font-medium hover:underline"
                                >
                                    {{ member.name }}
                                </Link>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ member.email }}
                                </p>
                            </div>
                            <div class="shrink-0 text-right text-xs text-muted-foreground">
                                <p>{{ roleLabel(member.role) }}</p>
                                <p v-if="member.suspended_at" class="text-rose-600">
                                    Suspended
                                </p>
                                <p v-else-if="member.is_admin">Admin</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
