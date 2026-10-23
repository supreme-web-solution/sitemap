<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { ref } from 'vue';
import PaginationBar from '@/components/admin/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
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
            <select
                v-model="plan"
                :class="selectClass"
                aria-label="Plan filter"
                @change="applyFilters"
            >
                <option value="">Any plan</option>
                <option value="none">No plan assigned</option>
                <option v-for="p in plans" :key="p.id" :value="String(p.id)">
                    {{ p.name }}
                </option>
            </select>
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
                            <select
                                :value="team.plan_id ?? ''"
                                :class="selectClass"
                                :aria-label="`Plan for ${team.name}`"
                                @change="
                                    setPlan(
                                        team,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">Default plan</option>
                                <option
                                    v-for="p in plans"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.name }}
                                </option>
                            </select>
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