<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlarmClock,
    ArrowUpRight,
    CalendarClock,
    Check,
    CircleDollarSign,
    ExternalLink,
    Eye,
    Globe,
    Activity,
    MapPinned,
    Pencil,
    Rocket,
    Search,
    Share2,
    Sparkles,
    Trophy,
    Wand2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import DashboardHero from '@/components/dashboard/DashboardHero.vue';
import WorkTodayPanel from '@/components/dashboard/WorkTodayPanel.vue';
import GaugeArc from '@/components/dashboard/GaugeArc.vue';
import LineChart from '@/components/dashboard/LineChart.vue';
import MiniBars from '@/components/dashboard/MiniBars.vue';
import SegmentBar from '@/components/dashboard/SegmentBar.vue';
import StatCard from '@/components/dashboard/StatCard.vue';
import PendingInvitationsModal from '@/components/PendingInvitationsModal.vue';
import { Skeleton } from '@/components/ui/skeleton';
import {
    formatCompactNumber,
    formatCurrency,
    formatRelativeTime,
} from '@/lib/format';
import { dateFromToday } from '@/lib/dates';
import { leadStatusClasses, leadStatusLabel } from '@/lib/leadStatus';
import { dashboard } from '@/routes';
import {
    edit as editLead,
    index as leadsIndex,
    update as updateLead,
} from '@/routes/leads';
import { edit as editSite, index as sitesIndex } from '@/routes/sites';
import type {
    DashboardActivity,
    DashboardActivityType,
    DashboardChart,
    DashboardFollowUps,
    DashboardInvitation,
    DashboardPipeline,
    DashboardSite,
    DashboardStats,
    DashboardWorkToday,
    Team,
} from '@/types';

const props = defineProps<{
    pendingInvitations?: DashboardInvitation[];
    stats: DashboardStats;
    pipeline: DashboardPipeline;
    chart: DashboardChart;
    activity: DashboardActivity[];
    followUps: DashboardFollowUps;
    workToday: DashboardWorkToday;
    tasks: {
        dueCount: number;
        items: {
            id: number;
            leadId: number;
            title: string;
            leadName: string;
            dueOn: string | null;
            isOverdue: boolean;
            isDueToday: boolean;
        }[];
    };
    recentSites: DashboardSite[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const leadsUrl = computed(
    () => leadsIndex({ current_team: teamSlug.value }).url,
);
const sitesUrl = computed(
    () => sitesIndex({ current_team: teamSlug.value }).url,
);

const getStarted = computed(() => [
    {
        step: '01',
        title: 'Find local leads',
        description:
            'Search Google Maps for businesses that still have no website.',
        icon: Search,
        href: leadsUrl.value,
    },
    {
        step: '02',
        title: 'Generate their website',
        description:
            'Review the details, pick a vibe and let the AI build a tailored site.',
        icon: Wand2,
        href: leadsIndex(
            { current_team: teamSlug.value },
            { query: { status: 'new' } },
        ).url,
    },
    {
        step: '03',
        title: 'Share and close the deal',
        description:
            'Send the preview link, watch the views and track every deal.',
        icon: Share2,
        href: leadsIndex(
            { current_team: teamSlug.value },
            { query: { status: 'site_generated' } },
        ).url,
    },
]);

const lastDays = (values: number[], days: number): number[] =>
    values.slice(-days);

const sum = (values: number[]): number =>
    values.reduce((total, value) => total + value, 0);

const leadsThisWeek = computed(() => sum(lastDays(props.chart.leads, 7)));

const siteCoverage = computed(() =>
    props.stats.leads > 0 ? props.stats.sites / props.stats.leads : 0,
);

const averageViews = computed(() =>
    props.stats.sites > 0
        ? (props.stats.previewViews / props.stats.sites).toFixed(1)
        : '0',
);

const pipelineSegments = computed(() => [
    {
        label: 'New',
        value: props.pipeline.new + props.pipeline.site_generated,
        color: 'bg-sky-500',
    },
    {
        label: 'Contacted',
        value: props.pipeline.contacted,
        color: 'bg-amber-500',
    },
    {
        label: 'Interested',
        value: props.pipeline.interested,
        color: 'bg-violet-500',
    },
    { label: 'Won', value: props.pipeline.won, color: 'bg-emerald-500' },
    { label: 'Lost', value: props.pipeline.lost, color: 'bg-rose-400' },
]);

type SeriesKey = 'leads' | 'sites';

const seriesOptions: {
    key: SeriesKey;
    label: string;
    unit: string;
    color: string;
}[] = [
    { key: 'leads', label: 'Leads', unit: 'leads', color: 'var(--chart-1)' },
    { key: 'sites', label: 'Websites', unit: 'sites', color: 'var(--chart-2)' },
];

const rangeOptions = [7, 14, 30];

const activeSeries = ref<SeriesKey>('leads');
const activeRange = ref(14);

const currentSeries = computed(() =>
    seriesOptions.find((option) => option.key === activeSeries.value)!,
);

const chartValues = computed(() =>
    lastDays(props.chart[activeSeries.value], activeRange.value),
);

const chartLabels = computed(() =>
    props.chart.days.slice(-activeRange.value).map((day) => day.label),
);

const activityMeta: Record<
    DashboardActivityType,
    { label: string; icon: typeof Globe; tone: string }
> = {
    site_created: {
        label: 'Website generated',
        icon: Rocket,
        tone: 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
    },
    preview_viewed: {
        label: 'Preview viewed',
        icon: Eye,
        tone: 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
    },
    lead_found: {
        label: 'New lead found',
        icon: MapPinned,
        tone: 'bg-pink-100 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
    },
    deal_won: {
        label: 'Deal won',
        icon: Trophy,
        tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
};

const initial = (name: string): string => name.charAt(0).toUpperCase();

const setFollowUp = (leadId: number, date: string | null) => {
    router.patch(
        updateLead.url({ current_team: teamSlug.value, lead: leadId }),
        { next_follow_up_at: date },
        { preserveScroll: true, only: ['followUps'] },
    );
};
</script>

<template>
    <Head title="Dashboard" />

    <PendingInvitationsModal
        v-if="pendingInvitations && pendingInvitations.length > 0"
        :invitations="pendingInvitations"
    />

    <div
        class="mx-auto grid w-full max-w-[1500px] gap-6 p-4 md:p-6 xl:grid-cols-[minmax(0,1fr)_22rem]"
    >
        <div class="flex min-w-0 flex-col gap-8">
            <DashboardHero />

            <WorkTodayPanel :work-today="workToday" :team-slug="teamSlug" />

            <section>
                <h2 class="mb-4 text-lg font-semibold tracking-tight">
                    Get started
                </h2>
                <div class="grid gap-4 md:grid-cols-3">
                    <Link
                        v-for="card in getStarted"
                        :key="card.step"
                        :href="card.href"
                        class="group relative flex flex-col gap-3 panel p-5 transition hover:-translate-y-0.5 hover:border-primary/30"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-gradient-to-br from-violet-100 to-pink-100 text-primary dark:from-violet-500/20 dark:to-pink-500/20"
                            >
                                <component :is="card.icon" class="size-5" />
                            </span>
                            <span
                                class="text-xs font-semibold tracking-widest text-muted-foreground/70"
                                >{{ card.step }}</span
                            >
                        </div>
                        <div>
                            <h3 class="font-semibold">{{ card.title }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ card.description }}
                            </p>
                        </div>
                        <ArrowUpRight
                            class="absolute right-4 bottom-4 size-4 text-muted-foreground opacity-0 transition group-hover:opacity-100"
                        />
                    </Link>
                </div>
            </section>

            <section>
                <h2 class="mb-4 text-lg font-semibold tracking-tight">
                    Quick snapshot
                </h2>
                <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                    <StatCard
                        title="Leads found"
                        :value="formatCompactNumber(stats.leads)"
                        :caption="`${leadsThisWeek} added this week`"
                        :icon="MapPinned"
                    >
                        <MiniBars :values="lastDays(chart.leads, 14)" />
                    </StatCard>
                    <StatCard
                        title="Websites built"
                        :value="formatCompactNumber(stats.sites)"
                        caption="Share of leads with a site"
                        :icon="Globe"
                    >
                        <GaugeArc :value="siteCoverage" />
                    </StatCard>
                    <StatCard
                        title="Preview views"
                        :value="formatCompactNumber(stats.previewViews)"
                        :caption="`${averageViews} views per website`"
                        :icon="Eye"
                    >
                        <MiniBars :values="lastDays(chart.sites, 14)" />
                    </StatCard>
                    <StatCard
                        title="Revenue"
                        :value="formatCurrency(stats.revenue)"
                        :caption="`${stats.dealsWon} ${stats.dealsWon === 1 ? 'deal' : 'deals'} won`"
                        :icon="CircleDollarSign"
                    >
                        <SegmentBar :segments="pipelineSegments" />
                    </StatCard>
                </div>
            </section>

            <section class="overflow-hidden panel">
                <div class="flex items-center justify-between gap-3 p-5 pb-4">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">
                            Recent websites
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            The latest sites generated for your prospects.
                        </p>
                    </div>
                    <Link
                        :href="sitesUrl"
                        class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                    >
                        View all
                        <ArrowUpRight class="size-4" />
                    </Link>
                </div>

                <div v-if="recentSites.length" class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead
                            class="border-y bg-muted/50 text-left text-xs tracking-wide text-muted-foreground uppercase"
                        >
                            <tr>
                                <th class="px-5 py-2.5 font-medium">
                                    Business
                                </th>
                                <th class="px-3 py-2.5 font-medium">
                                    Location
                                </th>
                                <th class="px-3 py-2.5 font-medium">Created</th>
                                <th class="px-3 py-2.5 font-medium">Views</th>
                                <th class="px-3 py-2.5 font-medium">Stage</th>
                                <th class="px-5 py-2.5 text-right font-medium">
                                    <span class="sr-only">Actions</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="site in recentSites"
                                :key="site.id"
                                class="transition hover:bg-muted/40"
                            >
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="grid size-9 shrink-0 place-items-center rounded-full bg-gradient-to-br from-violet-100 to-pink-100 text-sm font-semibold text-primary dark:from-violet-500/20 dark:to-pink-500/20"
                                        >
                                            {{ initial(site.name) }}
                                        </span>
                                        <div class="min-w-0">
                                            <Link
                                                :href="
                                                    editSite({
                                                        current_team: teamSlug,
                                                        site: site.id,
                                                    })
                                                "
                                                class="block truncate font-medium hover:text-primary"
                                                >{{ site.name }}</Link
                                            >
                                            <span
                                                class="block truncate text-xs text-muted-foreground"
                                                >{{
                                                    site.category ??
                                                    site.template
                                                }}</span
                                            >
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-muted-foreground">
                                    {{ site.city ?? '-' }}
                                </td>
                                <td
                                    class="px-3 py-3 whitespace-nowrap text-muted-foreground"
                                >
                                    {{ formatRelativeTime(site.createdAt) }}
                                </td>
                                <td class="px-3 py-3 tabular-nums">
                                    {{ site.viewsCount }}
                                </td>
                                <td class="px-3 py-3">
                                    <span
                                        :class="[
                                            'rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap',
                                            leadStatusClasses[site.leadStatus],
                                        ]"
                                        >{{
                                            leadStatusLabel(site.leadStatus)
                                        }}</span
                                    >
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex justify-end gap-1">
                                        <a
                                            :href="site.previewUrl"
                                            target="_blank"
                                            rel="noopener"
                                            class="grid size-8 place-items-center rounded-lg text-muted-foreground transition hover:bg-accent hover:text-primary"
                                            :aria-label="`Open preview for ${site.name}`"
                                        >
                                            <ExternalLink class="size-4" />
                                        </a>
                                        <Link
                                            :href="
                                                editSite({
                                                    current_team: teamSlug,
                                                    site: site.id,
                                                })
                                            "
                                            class="grid size-8 place-items-center rounded-lg text-muted-foreground transition hover:bg-accent hover:text-primary"
                                            :aria-label="`Edit ${site.name}`"
                                        >
                                            <Pencil class="size-4" />
                                        </Link>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    v-else
                    class="mx-5 mb-5 flex flex-col items-center gap-3 rounded-2xl border border-dashed p-10 text-center"
                >
                    <span
                        class="grid size-12 place-items-center rounded-2xl bg-accent text-primary"
                    >
                        <Sparkles class="size-6" />
                    </span>
                    <div>
                        <p class="font-medium">No websites yet</p>
                        <p class="text-sm text-muted-foreground">
                            Run your first search above and generate a site for
                            a business in one click.
                        </p>
                    </div>
                    <Link
                        :href="leadsUrl"
                        class="text-sm font-medium text-primary hover:underline"
                        >Find your first leads</Link
                    >
                </div>
            </section>
        </div>

        <aside class="flex min-w-0 flex-col gap-6">
            <section class="panel p-5" data-test="follow-ups-panel">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold tracking-tight">
                        Follow-ups
                    </h2>
                    <span
                        v-if="followUps.dueCount > 0"
                        class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300"
                        >{{ followUps.dueCount }} due</span
                    >
                    <CalendarClock
                        v-else
                        class="size-4 text-muted-foreground"
                    />
                </div>

                <ul
                    v-if="followUps.items.length"
                    class="mt-4 flex flex-col divide-y"
                >
                    <li
                        v-for="item in followUps.items"
                        :key="item.id"
                        class="flex items-center gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="min-w-0 flex-1">
                            <Link
                                :href="
                                    editLead({
                                        current_team: teamSlug,
                                        lead: item.id,
                                    })
                                "
                                class="block truncate text-sm font-medium hover:text-primary"
                                >{{ item.name }}</Link
                            >
                            <p class="truncate text-xs text-muted-foreground">
                                <span
                                    :class="
                                        item.isOverdue
                                            ? 'font-medium text-rose-600'
                                            : item.isDueToday
                                              ? 'font-medium text-amber-600'
                                              : ''
                                    "
                                    >{{
                                        item.isOverdue
                                            ? 'Overdue'
                                            : item.isDueToday
                                              ? 'Today'
                                              : item.dueOn
                                    }}</span
                                >
                                · {{ leadStatusLabel(item.status) }}
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-1">
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-muted-foreground transition hover:bg-accent hover:text-emerald-600"
                                :aria-label="`Mark follow-up for ${item.name} as done`"
                                title="Done"
                                @click="setFollowUp(item.id, null)"
                            >
                                <Check class="size-4" />
                            </button>
                            <button
                                type="button"
                                class="grid size-8 place-items-center rounded-lg text-muted-foreground transition hover:bg-accent hover:text-primary"
                                :aria-label="`Snooze follow-up for ${item.name} by 3 days`"
                                title="Snooze 3 days"
                                @click="setFollowUp(item.id, dateFromToday(3))"
                            >
                                <AlarmClock class="size-4" />
                            </button>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    Nothing scheduled. Set a follow-up date on a lead and it
                    shows up here when it is due.
                </p>
            </section>

            <section class="panel p-5" data-test="tasks-panel">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold tracking-tight">Tasks</h2>
                    <span
                        v-if="tasks.dueCount > 0"
                        class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700 dark:bg-rose-500/15 dark:text-rose-300"
                        >{{ tasks.dueCount }} due</span
                    >
                </div>
                <ul v-if="tasks.items.length" class="mt-4 flex flex-col divide-y">
                    <li
                        v-for="task in tasks.items"
                        :key="task.id"
                        class="py-3 first:pt-0 last:pb-0"
                    >
                        <Link
                            :href="
                                editLead({
                                    current_team: teamSlug,
                                    lead: task.leadId,
                                })
                            "
                            class="block truncate text-sm font-medium hover:text-primary"
                            >{{ task.title }}</Link
                        >
                        <p class="text-xs text-muted-foreground">
                            {{ task.leadName }}
                            <template v-if="task.dueOn"> · {{ task.dueOn }}</template>
                        </p>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    Open tasks on leads show up here.
                </p>
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold tracking-tight">
                        Overview
                    </h2>
                    <div class="flex rounded-full bg-muted p-0.5 text-xs">
                        <button
                            v-for="range in rangeOptions"
                            :key="range"
                            type="button"
                            class="rounded-full px-2.5 py-1 font-medium transition"
                            :class="
                                activeRange === range
                                    ? 'bg-card text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeRange = range"
                        >
                            {{ range }}d
                        </button>
                    </div>
                </div>

                <div class="mt-4 flex gap-1 border-b">
                    <button
                        v-for="option in seriesOptions"
                        :key="option.key"
                        type="button"
                        class="-mb-px border-b-2 px-3 pb-2 text-sm font-medium transition"
                        :class="
                            activeSeries === option.key
                                ? 'border-primary text-foreground'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        @click="activeSeries = option.key"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="mt-4 flex items-baseline gap-2">
                    <span
                        class="text-3xl font-semibold tracking-tight tabular-nums"
                        >{{ sum(chartValues) }}</span
                    >
                    <span class="text-sm text-muted-foreground"
                        >{{ currentSeries.unit }} in the last
                        {{ activeRange }} days</span
                    >
                </div>

                <LineChart
                    class="mt-4"
                    :labels="chartLabels"
                    :values="chartValues"
                    :color="currentSeries.color"
                    :unit="currentSeries.unit"
                />
            </section>

            <section class="panel p-5">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold tracking-tight">
                        Activity log
                    </h2>
                    <Activity class="size-4 text-muted-foreground" />
                </div>

                <ul v-if="activity.length" class="mt-4 flex flex-col gap-4">
                    <li
                        v-for="(event, index) in activity"
                        :key="`${event.type}-${index}`"
                        class="flex items-start gap-3"
                    >
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-xl"
                            :class="activityMeta[event.type].tone"
                        >
                            <component
                                :is="activityMeta[event.type].icon"
                                class="size-4"
                            />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ event.title }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ activityMeta[event.type].label
                                }}<template v-if="event.detail">
                                    · {{ event.detail }}</template
                                >
                            </p>
                        </div>
                        <span
                            class="shrink-0 pt-0.5 text-xs whitespace-nowrap text-muted-foreground"
                            >{{ formatRelativeTime(event.at) }}</span
                        >
                    </li>
                </ul>

                <div v-else class="mt-4 flex flex-col gap-3" aria-hidden="true">
                    <Skeleton v-for="n in 3" :key="n" class="h-10 w-full" />
                    <p class="text-center text-sm text-muted-foreground">
                        Activity shows up here after your first search.
                    </p>
                </div>
            </section>
        </aside>
    </div>
</template>
