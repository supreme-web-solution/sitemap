<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useDebounceFn, useLocalStorage } from '@vueuse/core';
import {
    CalendarClock,
    ChevronDown,
    CircleHelp,
    ExternalLink,
    Eye,
    EyeOff,
    List,
    MapPin,
    Search,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ScoreBadge from '@/components/ScoreBadge.vue';
import SellFlowGuide from '@/components/sell/SellFlowGuide.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { leadStatuses } from '@/lib/leadStatus';
import {
    board as leadsBoard,
    edit as reviewLead,
    index as leadsIndex,
    update as updateLead,
} from '@/routes/leads';
import type { BoardColumn, Lead, LeadStatus, Team } from '@/types';

const props = defineProps<{
    columns: BoardColumn[];
    filters: {
        search: string | null;
        tier: string | null;
        sort: string;
        follow_up: string | null;
        assigned: string | null;
        ideal: string | null;
    };
    limits: Record<string, number>;
    step: number;
    totalLeads: number;
    members: { id: number; name: string }[];
    hasIdealCustomer: boolean;
}>();

/** Plain clone for local drag-and-drop state (Inertia props may be reactive proxies). */
const cloneColumns = (columns: BoardColumn[]): BoardColumn[] =>
    JSON.parse(JSON.stringify(columns)) as BoardColumn[];

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Pipeline',
                href: props.currentTeam
                    ? leadsBoard({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const board = ref<BoardColumn[]>(cloneColumns(props.columns));

watch(
    () => props.columns,
    (columns) => {
        board.value = cloneColumns(columns);
    },
);

const dotClasses: Record<LeadStatus, string> = {
    new: 'bg-sky-500',
    site_generated: 'bg-indigo-500',
    contacted: 'bg-amber-500',
    interested: 'bg-violet-500',
    won: 'bg-emerald-500',
    lost: 'bg-rose-400',
};

/* ---------- Filters ---------- */

const searchText = ref(props.filters.search ?? '');
const tierFilter = ref<string>(props.filters.tier ?? 'all');
const assignedFilter = ref<string>(props.filters.assigned ?? 'all');
const sortOrder = ref<string>(props.filters.sort ?? 'score');
const dueOnly = ref(props.filters.follow_up === 'due');
const idealOnly = ref(Boolean(props.filters.ideal));

const tiers = [
    { value: 'all', label: 'All' },
    { value: 'hot', label: 'Hot' },
    { value: 'warm', label: 'Warm' },
    { value: 'cold', label: 'Cold' },
];

const sortOptions = [
    { value: 'score', label: 'Best opportunity' },
    { value: 'follow_up', label: 'Follow-up date' },
    { value: 'newest', label: 'Newest' },
    { value: 'reviews', label: 'Most reviews' },
];

const filterQuery = computed(() => ({
    search: searchText.value.trim() || undefined,
    tier: tierFilter.value === 'all' ? undefined : tierFilter.value,
    assigned: assignedFilter.value === 'all' ? undefined : assignedFilter.value,
    sort: sortOrder.value === 'score' ? undefined : sortOrder.value,
    follow_up: dueOnly.value ? 'due' : undefined,
    ideal: idealOnly.value ? '1' : undefined,
}));

const activeFilterCount = computed(
    () =>
        Object.entries(filterQuery.value).filter(
            ([key, value]) => key !== 'sort' && value !== undefined,
        ).length,
);

const visit = (extra: Record<string, unknown> = {}) =>
    router.get(
        leadsBoard.url({ current_team: teamSlug.value }),
        { ...filterQuery.value, ...extra },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['columns', 'filters', 'limits'],
        },
    );

const applyFilters = () => visit();
const debouncedApplyFilters = useDebounceFn(applyFilters, 350);

watch(searchText, debouncedApplyFilters);
watch(
    [tierFilter, assignedFilter, sortOrder, dueOnly, idealOnly],
    applyFilters,
);

const clearFilters = () => {
    searchText.value = '';
    tierFilter.value = 'all';
    assignedFilter.value = 'all';
    dueOnly.value = false;
    idealOnly.value = false;
};

/* ---------- Columns ---------- */

const MAX_PER_COLUMN = 200;

const showLost = useLocalStorage('board-show-lost', false);
const dragged = ref<{ leadId: number; from: LeadStatus } | null>(null);
const hoveredColumn = ref<LeadStatus | null>(null);

/** Lost deals stay out of the way, but appear as a drop target while dragging. */
const visibleColumns = computed(() =>
    board.value.filter(
        (column) =>
            column.status !== 'lost' || showLost.value || dragged.value !== null,
    ),
);

const lostCount = computed(
    () => board.value.find((column) => column.status === 'lost')?.count ?? 0,
);

const matchingCount = computed(() =>
    board.value.reduce((sum, column) => sum + column.count, 0),
);

const remaining = (column: BoardColumn) => column.count - column.leads.length;

const loadMore = (column: BoardColumn) =>
    visit({
        limits: {
            ...props.limits,
            [column.status]: Math.min(
                column.leads.length + props.step,
                MAX_PER_COLUMN,
            ),
        },
    });

const listUrl = (column: BoardColumn) =>
    leadsIndex(
        { current_team: teamSlug.value },
        { query: { ...filterQuery.value, status: column.status } },
    ).url;

/* ---------- Moving leads ---------- */

const moveLead = (leadId: number, from: LeadStatus, to: LeadStatus) => {
    if (from === to) {
        return;
    }

    const source = board.value.find((column) => column.status === from);
    const target = board.value.find((column) => column.status === to);
    const lead = source?.leads.find((candidate) => candidate.id === leadId);

    if (!source || !target || !lead) {
        return;
    }

    source.leads = source.leads.filter((candidate) => candidate.id !== leadId);
    source.count -= 1;
    target.leads = [{ ...lead, status: to }, ...target.leads];
    target.count += 1;

    router.patch(
        updateLead.url({ current_team: teamSlug.value, lead: leadId }),
        { status: to },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['columns'],
            onError: () => {
                board.value = cloneColumns(props.columns);
            },
        },
    );
};

const onDrop = (to: LeadStatus) => {
    hoveredColumn.value = null;

    if (dragged.value) {
        moveLead(dragged.value.leadId, dragged.value.from, to);
    }

    dragged.value = null;
};

const followUpLabel = (
    lead: Lead,
): { text: string; overdue: boolean } | null => {
    if (!lead.nextFollowUpAt) {
        return null;
    }

    const due = new Date(`${lead.nextFollowUpAt}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return {
        text: due.toLocaleDateString(undefined, {
            month: 'short',
            day: 'numeric',
        }),
        overdue: due < today && lead.status !== 'won' && lead.status !== 'lost',
    };
};

/* ---------- Help ---------- */

const showGuide = useLocalStorage('board-show-guide', true);
</script>

<template>
    <Head title="Pipeline" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                variant="small"
                title="Pipeline"
                :description="
                    activeFilterCount > 0
                        ? `${matchingCount} of ${totalLeads} businesses match your filters`
                        : `${totalLeads} businesses · drag a card as the conversation moves forward`
                "
            />
            <div class="flex items-center gap-2">
                <Button
                    v-if="!showGuide"
                    variant="ghost"
                    size="sm"
                    data-test="board-show-guide"
                    @click="showGuide = true"
                >
                    <CircleHelp /> Sales path
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="leadsIndex({ current_team: teamSlug })">
                        <List /> List view
                    </Link>
                </Button>
            </div>
        </div>

        <SellFlowGuide
            v-if="showGuide"
            :team-slug="teamSlug"
            dismissible
            @close="showGuide = false"
        />

        <div
            class="flex flex-wrap items-center gap-2"
            role="search"
            aria-label="Filter pipeline"
            data-test="board-filters"
        >
            <div class="relative w-full sm:w-64">
                <Search
                    class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="searchText"
                    placeholder="Search name, city or category"
                    class="pl-8"
                    aria-label="Search leads"
                />
            </div>

            <div
                class="flex rounded-lg border p-0.5"
                role="group"
                aria-label="Opportunity score"
            >
                <Button
                    v-for="tier in tiers"
                    :key="tier.value"
                    size="sm"
                    class="h-8"
                    :variant="tierFilter === tier.value ? 'secondary' : 'ghost'"
                    :aria-pressed="tierFilter === tier.value"
                    @click="tierFilter = tier.value"
                >
                    {{ tier.label }}
                </Button>
            </div>

            <Button
                size="sm"
                class="h-9"
                :variant="dueOnly ? 'secondary' : 'outline'"
                :aria-pressed="dueOnly"
                data-test="board-due-toggle"
                @click="dueOnly = !dueOnly"
            >
                <CalendarClock /> Follow-ups due
            </Button>

            <Button
                v-if="hasIdealCustomer"
                size="sm"
                class="h-9"
                :variant="idealOnly ? 'secondary' : 'outline'"
                :aria-pressed="idealOnly"
                @click="idealOnly = !idealOnly"
            >
                <Sparkles /> Ideal customer
            </Button>

            <Select v-if="members.length > 1" v-model="assignedFilter">
                <SelectTrigger class="h-9 w-40" aria-label="Assigned to">
                    <SelectValue placeholder="Assigned" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">Anyone</SelectItem>
                    <SelectItem
                        v-for="member in members"
                        :key="member.id"
                        :value="String(member.id)"
                    >
                        {{ member.name }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Select v-model="sortOrder">
                <SelectTrigger class="h-9 w-44" aria-label="Sort cards by">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem
                        v-for="option in sortOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button
                v-if="activeFilterCount > 0"
                variant="ghost"
                size="sm"
                class="h-9"
                data-test="board-clear-filters"
                @click="clearFilters"
            >
                <X /> Clear ({{ activeFilterCount }})
            </Button>

            <Button
                variant="ghost"
                size="sm"
                class="ml-auto h-9 text-muted-foreground"
                :aria-pressed="showLost"
                data-test="board-toggle-lost"
                @click="showLost = !showLost"
            >
                <EyeOff v-if="showLost" />
                <Eye v-else />
                {{ showLost ? 'Hide' : 'Show' }} lost
                <span class="tabular-nums">({{ lostCount }})</span>
            </Button>
        </div>

        <div
            v-if="totalLeads === 0"
            class="panel flex flex-col items-center gap-2 px-6 py-14 text-center"
        >
            <p class="text-sm font-medium">No leads yet</p>
            <p class="max-w-sm text-sm text-muted-foreground">
                Search Google Maps for local businesses and they will show up
                here as New.
            </p>
            <Button size="sm" as-child>
                <Link :href="leadsIndex({ current_team: teamSlug })">
                    Find businesses
                </Link>
            </Button>
        </div>

        <div
            v-else-if="matchingCount === 0"
            class="panel flex flex-col items-center gap-2 px-6 py-14 text-center"
            data-test="board-no-matches"
        >
            <p class="text-sm font-medium">No leads match these filters</p>
            <Button variant="outline" size="sm" @click="clearFilters">
                Clear filters
            </Button>
        </div>

        <div
            v-else
            class="-mx-4 flex min-h-0 flex-1 items-start gap-3 overflow-x-auto px-4 pb-3 md:-mx-6 md:px-6"
        >
            <section
                v-for="column in visibleColumns"
                :key="column.status"
                :aria-label="`${column.label} stage`"
                :data-test="`board-column-${column.status}`"
                class="flex max-h-[calc(100dvh-15rem)] min-h-48 w-72 shrink-0 flex-col rounded-2xl border bg-muted/40 transition"
                :class="
                    hoveredColumn === column.status
                        ? 'border-primary/50 bg-accent/60 ring-4 ring-primary/10'
                        : ''
                "
                @dragover.prevent="hoveredColumn = column.status"
                @dragleave="hoveredColumn = null"
                @drop.prevent="onDrop(column.status)"
            >
                <header class="flex shrink-0 items-center gap-2 px-4 py-3">
                    <span
                        :class="[
                            'size-2.5 rounded-full',
                            dotClasses[column.status],
                        ]"
                    />
                    <h2 class="text-sm font-semibold">{{ column.label }}</h2>
                    <span
                        class="ml-auto rounded-full bg-card px-2 py-0.5 text-xs font-medium text-muted-foreground tabular-nums"
                        >{{ column.count }}</span
                    >
                </header>

                <div
                    class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto px-3 pb-3"
                >
                    <article
                        v-for="lead in column.leads"
                        :key="lead.id"
                        draggable="true"
                        class="group cursor-grab rounded-xl border bg-card p-3 shadow-sm transition hover:border-primary/30 hover:shadow-md active:cursor-grabbing"
                        data-test="board-card"
                        @dragstart="
                            dragged = { leadId: lead.id, from: column.status }
                        "
                        @dragend="
                            dragged = null;
                            hoveredColumn = null;
                        "
                    >
                        <div class="flex items-start justify-between gap-2">
                            <Link
                                :href="
                                    reviewLead({
                                        current_team: teamSlug,
                                        lead: lead.id,
                                    })
                                "
                                class="line-clamp-2 text-sm leading-snug font-medium hover:text-primary"
                                draggable="false"
                                >{{ lead.name }}</Link
                            >
                            <ScoreBadge
                                :score="lead.opportunityScore"
                                :tier="lead.opportunityTier"
                            />
                        </div>
                        <p
                            class="mt-1 flex items-center gap-1 truncate text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3 shrink-0" />
                            <span class="truncate">
                                {{ lead.category ?? 'Business' }}
                                <template v-if="lead.city">
                                    · {{ lead.city }}</template
                                >
                            </span>
                        </p>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-1.5 empty:hidden"
                        >
                            <Badge
                                v-if="!lead.website"
                                class="bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                                >No website</Badge
                            >
                            <Badge
                                v-else-if="lead.websiteAuditScore !== null"
                                variant="outline"
                                >Site {{ lead.websiteAuditScore }}/100</Badge
                            >
                            <a
                                v-if="lead.site"
                                :href="lead.site.previewUrl"
                                target="_blank"
                                rel="noopener"
                                draggable="false"
                                class="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700 hover:bg-violet-200 dark:bg-violet-500/15 dark:text-violet-300"
                                title="Open the preview site"
                            >
                                <ExternalLink class="size-3" /> Preview
                            </a>
                            <span
                                v-if="followUpLabel(lead)"
                                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    followUpLabel(lead)?.overdue
                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                <CalendarClock class="size-3" />
                                {{ followUpLabel(lead)?.text }}
                            </span>
                        </div>

                        <label class="mt-2 block">
                            <span class="sr-only"
                                >Move {{ lead.name }} to stage</span
                            >
                            <select
                                class="w-full rounded-md border bg-transparent px-2 py-1 text-xs text-muted-foreground opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100 focus:opacity-100"
                                :value="column.status"
                                @change="
                                    moveLead(
                                        lead.id,
                                        column.status,
                                        ($event.target as HTMLSelectElement)
                                            .value as LeadStatus,
                                    )
                                "
                            >
                                <option
                                    v-for="status in leadStatuses"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>
                    </article>

                    <p
                        v-if="column.leads.length === 0"
                        class="rounded-xl border border-dashed px-3 py-6 text-center text-xs text-muted-foreground"
                    >
                        {{
                            activeFilterCount > 0
                                ? 'Nothing here for these filters'
                                : 'Drop a lead here'
                        }}
                    </p>

                    <div
                        v-else-if="remaining(column) > 0"
                        class="flex flex-col gap-1 pt-1 text-center"
                    >
                        <Button
                            v-if="column.leads.length < MAX_PER_COLUMN"
                            variant="ghost"
                            size="sm"
                            class="h-8 text-xs"
                            :data-test="`board-more-${column.status}`"
                            @click="loadMore(column)"
                        >
                            <ChevronDown />
                            Show {{ Math.min(step, remaining(column)) }} more
                            <span class="text-muted-foreground"
                                >({{ remaining(column) }} left)</span
                            >
                        </Button>
                        <Link
                            :href="listUrl(column)"
                            class="text-xs font-medium text-primary hover:underline"
                        >
                            Open all {{ column.count }} in list view
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
