<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { computed, ref } from 'vue';
import PaginationBar from '@/components/admin/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
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
import { index, update } from '@/routes/admin/teams';
import type { AdminTeam, Paginated, PlanOption } from '@/types';

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
</script>

<template>
    <Head title="Teams" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Teams"
            :description="
                defaultPlan
                    ? `Teams with no plan use the default plan (${defaultPlan}).`
                    : 'No default plan is set, so teams without a plan are unlimited.'
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
                    </tr>
                    <tr v-if="!teams.data.length">
                        <td colspan="5" class="p-6 text-center text-muted-foreground">
                            No teams match.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationBar :paginator="teams" />
    </div>
</template>