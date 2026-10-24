<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarClock, List, MapPin } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ScoreBadge from '@/components/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
}>();

/** Plain clone for local drag-and-drop state (Inertia props may be reactive proxies). */
const cloneColumns = (columns: BoardColumn[]): BoardColumn[] =>
    JSON.parse(JSON.stringify(columns)) as BoardColumn[];

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Leads',
                href: props.currentTeam
                    ? leadsIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
            {
                title: 'Pipeline board',
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
    site_generated: 'bg-blue-500',
    contacted: 'bg-amber-500',
    interested: 'bg-violet-500',
    won: 'bg-emerald-500',
    lost: 'bg-rose-400',
};

const dragged = ref<{ leadId: number; from: LeadStatus } | null>(null);
const hoveredColumn = ref<LeadStatus | null>(null);

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
</script>

<template>
    <Head title="Pipeline board" />

    <div class="flex h-full flex-1 flex-col gap-5 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                variant="small"
                title="Pipeline board"
                description="Drag a business between stages as the conversation moves forward."
            />
            <Button variant="outline" size="sm" as-child>
                <Link :href="leadsIndex({ current_team: teamSlug })">
                    <List /> List view
                </Link>
            </Button>
        </div>

        <div
            class="-mx-4 flex flex-1 gap-4 overflow-x-auto px-4 pb-4 md:-mx-6 md:px-6"
        >
            <section
                v-for="column in board"
                :key="column.status"
                :aria-label="`${column.label} stage`"
                class="flex w-72 shrink-0 flex-col rounded-2xl border bg-muted/40 transition"
                :class="
                    hoveredColumn === column.status
                        ? 'border-primary/50 bg-accent/60 ring-4 ring-primary/10'
                        : ''
                "
                @dragover.prevent="hoveredColumn = column.status"
                @dragleave="hoveredColumn = null"
                @drop.prevent="onDrop(column.status)"
            >
                <header class="flex items-center gap-2 px-4 py-3">
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

                <div class="flex flex-1 flex-col gap-2.5 px-3 pb-3">
                    <article
                        v-for="lead in column.leads"
                        :key="lead.id"
                        draggable="true"
                        class="group cursor-grab rounded-xl border bg-card p-3 shadow-sm transition hover:border-primary/30 hover:shadow-md active:cursor-grabbing"
                        @dragstart="
                            dragged = { leadId: lead.id, from: column.status }
                        "
                        @dragend="dragged = null"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <Link
                                :href="
                                    reviewLead({
                                        current_team: teamSlug,
                                        lead: lead.id,
                                    })
                                "
                                class="text-sm leading-snug font-medium hover:text-primary"
                                draggable="false"
                                >{{ lead.name }}</Link
                            >
                            <ScoreBadge
                                :score="lead.opportunityScore"
                                :tier="lead.opportunityTier"
                            />
                        </div>
                        <p
                            class="mt-1 flex items-center gap-1 text-xs text-muted-foreground"
                        >
                            <MapPin class="size-3" />
                            {{ lead.category ?? 'Business' }}
                            <template v-if="lead.city"
                                >· {{ lead.city }}</template
                            >
                        </p>

                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
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

                        <label class="mt-2.5 block">
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
                        Drop a lead here
                    </p>
                    <p
                        v-else-if="column.count > column.leads.length"
                        class="px-1 text-center text-xs text-muted-foreground"
                    >
                        Showing the top {{ column.leads.length }} of
                        {{ column.count }}.
                        <Link
                            :href="
                                leadsIndex(
                                    { current_team: teamSlug },
                                    { query: { status: column.status } },
                                )
                            "
                            class="font-medium text-primary hover:underline"
                            >See all</Link
                        >
                    </p>
                </div>
            </section>
        </div>
    </div>
</template>
