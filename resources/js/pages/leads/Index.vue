<script setup lang="ts">
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    Bookmark,
    CalendarClock,
    Download,
    ListChecks,
    Kanban,
    Globe,
    Link2,
    LoaderCircle,
    MapPin,
    Phone,
    Play,
    Search,
    Sparkles,
    Star,
    Trash2,
    X,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaginationBar from '@/components/PaginationBar.vue';
import LeadQuickActions from '@/components/leads/LeadQuickActions.vue';
import SiteBuildBar from '@/components/leads/SiteBuildBar.vue';
import LeadStageControls from '@/components/leads/LeadStageControls.vue';
import TruncatedText from '@/components/leads/TruncatedText.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import ScoreBadge from '@/components/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { EmptyState } from '@/components/ui/empty-state';
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
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { leadStatuses } from '@/lib/leadStatus';
import { store as storeImport } from '@/routes/lead-imports';
import {
    destroy as destroySearch,
    store as storeSearch,
} from '@/routes/lead-searches';
import {
    destroy as destroySavedSearch,
    run as runSavedSearch,
    store as storeSavedSearch,
} from '@/routes/saved-lead-searches';
import {
    board as leadsBoard,
    exportMethod as exportLeads,
    edit as reviewLead,
    index as leadsIndex,
    update as updateLead,
} from '@/routes/leads';
import { bulk as bulkEnrollLeads } from '@/routes/leads/enrollments';
import { destroy as bulkDestroyLeads } from '@/routes/leads/bulk';
import SiteDesignTemplatePicker from '@/components/sites/SiteDesignTemplatePicker.vue';
import { store as generateSite } from '@/routes/leads/site';
import type {
    Lead,
    LeadSearch,
    LeadStatus,
    Option,
    Paginated,
    Team,
} from '@/types';

type SavedSearch = {
    id: number;
    name: string;
    keyword: string;
    location: string;
    websiteFilter: string;
    minimumRating: number | null;
    maxResults: number;
};

const props = defineProps<{
    leads: Paginated<Lead>;
    searches: LeadSearch[];
    savedSearches: SavedSearch[];
    filters: {
        status: LeadStatus | null;
        search: string | null;
        sort: string;
        tier: string | null;
        follow_up: string | null;
        assigned: string | null;
        ideal: string | null;
        search_id: number | null;
    };
    websiteFilters: Option[];
    members: { id: number; name: string }[];
    hasIdealCustomer: boolean;
    sequences: { id: number; name: string }[];
    searchSuggestions: {
        keywords: string[];
        locations: string[];
    };
    leadUsage: {
        used: number;
        limit: number | null;
        remaining: number | null;
    };
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Leads',
                href: props.currentTeam
                    ? leadsIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const extraLocations = ref('');

const searchForm = useForm({
    keyword: '',
    location: '',
    website_filter: 'withoutWebsite',
    minimum_rating: 'any',
    max_results: 20,
    locations: [] as string[],
});

const searchCriteria = () => ({
    keyword: searchForm.keyword,
    location: searchForm.location,
    website_filter: searchForm.website_filter,
    minimum_rating:
        searchForm.minimum_rating === 'any'
            ? null
            : Number(searchForm.minimum_rating),
    max_results: Number(searchForm.max_results),
    locations: extraLocations.value
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean),
});

const submitSearch = () => {
    pendingDiscovery.value = true;

    searchForm
        .transform(() => searchCriteria())
        .post(storeSearch.url({ current_team: teamSlug.value }), {
            preserveScroll: true,
            onSuccess: () => {
                searchModalOpen.value = false;
                startPolling();
            },
            onError: () => {
                pendingDiscovery.value = false;
            },
        });
};

const saveSearchName = ref('');
const saveSearchForm = useForm({
    name: '',
    keyword: '',
    location: '',
    website_filter: 'withoutWebsite',
    minimum_rating: null as number | null,
    max_results: 20,
});

const saveCurrentSearch = () => {
    const name = saveSearchName.value.trim();

    if (!name) {
        toast.error('Give this search a name first.');
        return;
    }

    const criteria = searchCriteria();

    saveSearchForm
        .transform(() => ({
            name,
            keyword: criteria.keyword,
            location: criteria.location,
            website_filter: criteria.website_filter,
            minimum_rating: criteria.minimum_rating,
            max_results: criteria.max_results,
        }))
        .post(storeSavedSearch.url({ current_team: teamSlug.value }), {
            preserveScroll: true,
            onSuccess: () => {
                saveSearchName.value = '';
            },
        });
};

const rerunSavedSearch = (saved: SavedSearch) => {
    pendingDiscovery.value = true;

    router.post(
        runSavedSearch.url({
            current_team: teamSlug.value,
            savedLeadSearch: saved.id,
        }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => startPolling(),
            onError: () => {
                pendingDiscovery.value = false;
            },
            onFinish: () => {
                pendingDiscovery.value = false;
            },
        },
    );
};

const removeSavedSearch = (saved: SavedSearch) => {
    router.delete(
        destroySavedSearch.url({
            current_team: teamSlug.value,
            savedLeadSearch: saved.id,
        }),
        { preserveScroll: true },
    );
};

const importForm = useForm({ maps_url: '' });

const submitImport = () => {
    pendingDiscovery.value = true;

    importForm.post(storeImport.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => {
            importForm.reset();
            searchModalOpen.value = false;
            startPolling();
        },
        onError: () => {
            pendingDiscovery.value = false;
        },
    });
};

const searchModalOpen = ref(false);
const searchModalTab = ref<'search' | 'import'>('search');

watch(searchModalOpen, (open) => {
    if (open) {
        searchModalTab.value = 'search';
    }
});

const pendingDiscovery = ref(false);

const searchLabel = (search: LeadSearch) =>
    search.placeUrl
        ? 'Maps link import'
        : `${search.keyword} · ${search.location}`;

const activeSearches = computed(() =>
    props.searches.filter(
        (search) => search.status === 'pending' || search.status === 'running',
    ),
);

const visibleSearches = computed(() =>
    props.searches.filter((search) => search.status !== 'failed'),
);

const hasActiveSearch = computed(() => activeSearches.value.length > 0);

const isDiscoveryBusy = computed(
    () =>
        pendingDiscovery.value ||
        hasActiveSearch.value ||
        searchForm.processing ||
        importForm.processing,
);

const isBuildingSite = computed(() =>
    props.leads.data.some((lead) => lead.siteGenerationStatus === 'queued'),
);

const shouldPollLeads = computed(
    () => isDiscoveryBusy.value || isBuildingSite.value,
);

const { start: startPolling, stop: stopPolling } = usePoll(
    2500,
    { only: ['searches', 'leads'] },
    { autoStart: false },
);

watch(
    shouldPollLeads,
    (isActive) => (isActive ? startPolling() : stopPolling()),
    { immediate: true },
);

const searchStatusSnapshot = ref<Record<number, string>>({});

const searchResultSummary = (search: LeadSearch) => {
    const created = search.leadsCreatedCount ?? 0;
    const updated = search.leadsUpdatedCount ?? 0;
    const skippedLimit = search.leadsSkippedLimitCount ?? 0;

    if (created > 0 && updated > 0) {
        return `${created} new · ${updated} already listed`;
    }

    if (created > 0) {
        return `${created} new`;
    }

    if (updated > 0) {
        return `${updated} already listed`;
    }

    if (skippedLimit > 0) {
        return `${skippedLimit} blocked by plan limit`;
    }

    return 'no matches';
};

const searchCompletionToast = (search: LeadSearch) => {
    const created = search.leadsCreatedCount ?? 0;
    const updated = search.leadsUpdatedCount ?? 0;
    const skippedLimit = search.leadsSkippedLimitCount ?? 0;
    const label = searchLabel(search);

    if (created > 0 && updated > 0) {
        return `${label} — ${created} new, ${updated} already in your list`;
    }

    if (created > 0) {
        return `${label} — ${created} new lead${created === 1 ? '' : 's'} added`;
    }

    if (updated > 0) {
        return `${label} — ${updated} already in your list (refreshed)`;
    }

    if (skippedLimit > 0) {
        return `${label} — found ${skippedLimit}, but your lead plan is full`;
    }

    return `${label} — no new matches`;
};

const dismissSearch = (search: LeadSearch, event: Event) => {
    event.stopPropagation();
    event.preventDefault();

    if (searchIdFilter.value === search.id) {
        searchIdFilter.value = null;
        sortOrder.value = 'score';
    }

    router.delete(
        destroySearch.url({
            current_team: teamSlug.value,
            leadSearch: search.id,
        }),
        {
            preserveScroll: true,
            only: ['searches', 'filters', 'leads'],
        },
    );
};

const atLeadLimit = computed(
    () => props.leadUsage.remaining !== null && props.leadUsage.remaining <= 0,
);

const searchText = ref(props.filters.search ?? '');
const statusFilter = ref<string>(props.filters.status ?? 'all');
const tierFilter = ref<string>(props.filters.tier ?? 'all');
const sortOrder = ref<string>(props.filters.sort ?? 'score');
const dueOnly = ref(props.filters.follow_up === 'due');
const idealOnly = ref(Boolean(props.filters.ideal));
const assignedFilter = ref<string>(props.filters.assigned ?? 'all');
const searchIdFilter = ref<number | null>(props.filters.search_id ?? null);

const viewSearchResults = (search: LeadSearch) => {
    if (search.status !== 'completed') {
        return;
    }

    searchIdFilter.value = search.id;
    sortOrder.value = 'newest';
};

watch(
    () => props.searches,
    (searches) => {
        if (hasActiveSearch.value) {
            pendingDiscovery.value = false;
        }

        for (const search of searches) {
            const previous = searchStatusSnapshot.value[search.id];
            const current = search.status;

            if (
                previous &&
                (previous === 'pending' || previous === 'running') &&
                current === 'completed'
            ) {
                toast.success(searchCompletionToast(search));
                viewSearchResults(search);
            }

            if (
                previous &&
                (previous === 'pending' || previous === 'running') &&
                current === 'failed'
            ) {
                toast.error(
                    search.error ??
                        `${searchLabel(search)} could not be completed. Please try again.`,
                );
            }

            searchStatusSnapshot.value[search.id] = current;
        }
    },
    { deep: true, immediate: true },
);

const sortOptions = [
    { value: 'score', label: 'Best opportunity' },
    { value: 'reviews', label: 'Most reviews' },
    { value: 'newest', label: 'Newest' },
    { value: 'follow_up', label: 'Follow-up date' },
];

const tiers = [
    { value: 'all', label: 'All' },
    { value: 'hot', label: 'Hot' },
    { value: 'warm', label: 'Warm' },
    { value: 'cold', label: 'Cold' },
];

const rankingLabel = computed(() => {
    if (sortOrder.value === 'newest') {
        return 'newest first';
    }

    if (sortOrder.value === 'reviews') {
        return 'most reviews';
    }

    if (sortOrder.value === 'follow_up') {
        return 'follow-up date';
    }

    return 'ranked by opportunity';
});

const activeSearchFilter = computed(() =>
    searchIdFilter.value === null
        ? null
        : (props.searches.find((search) => search.id === searchIdFilter.value) ??
          null),
);

const activeQuery = computed(() => ({
    search: searchText.value || undefined,
    status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    tier: tierFilter.value === 'all' ? undefined : tierFilter.value,
    sort: sortOrder.value === 'score' ? undefined : sortOrder.value,
    follow_up: dueOnly.value ? 'due' : undefined,
    assigned: assignedFilter.value === 'all' ? undefined : assignedFilter.value,
    ideal: idealOnly.value ? '1' : undefined,
    search_id: searchIdFilter.value ?? undefined,
}));

const activeFilterCount = computed(
    () =>
        Object.entries(activeQuery.value).filter(
            ([key, value]) => key !== 'sort' && value !== undefined,
        ).length,
);

const leadsDescription = computed(() => {
    if (activeSearchFilter.value) {
        return `${props.leads.total} from ${searchLabel(activeSearchFilter.value)} · ${rankingLabel.value}`;
    }

    if (activeFilterCount.value > 0) {
        return `${props.leads.total} match your filters · ${rankingLabel.value}`;
    }

    return `${props.leads.total} businesses · ${rankingLabel.value}`;
});

const clearFilters = () => {
    searchText.value = '';
    statusFilter.value = 'all';
    tierFilter.value = 'all';
    assignedFilter.value = 'all';
    dueOnly.value = false;
    idealOnly.value = false;
    searchIdFilter.value = null;
    sortOrder.value = 'score';
};

const selectedLeadIds = ref<number[]>([]);

watch(
    () => props.leads.data,
    () => {
        const visible = new Set(props.leads.data.map((lead) => lead.id));
        selectedLeadIds.value = selectedLeadIds.value.filter((id) =>
            visible.has(id),
        );
    },
);

const pageLeadIds = computed(() => props.leads.data.map((lead) => lead.id));

const allOnPageSelected = computed(
    () =>
        pageLeadIds.value.length > 0 &&
        pageLeadIds.value.every((id) => selectedLeadIds.value.includes(id)),
);

const toggleAllOnPage = (checked: boolean | 'indeterminate') => {
    if (checked === true) {
        selectedLeadIds.value = [
            ...new Set([...selectedLeadIds.value, ...pageLeadIds.value]),
        ];
    } else {
        const onPage = new Set(pageLeadIds.value);
        selectedLeadIds.value = selectedLeadIds.value.filter(
            (id) => !onPage.has(id),
        );
    }
};

const toggleLead = (leadId: number, checked: boolean | 'indeterminate') => {
    if (checked === true) {
        if (!selectedLeadIds.value.includes(leadId)) {
            selectedLeadIds.value = [...selectedLeadIds.value, leadId];
        }

        return;
    }

    selectedLeadIds.value = selectedLeadIds.value.filter((id) => id !== leadId);
};

const bulkEnrollForm = useForm({
    lead_ids: [] as number[],
    sequence_id: (props.sequences[0]?.id ?? '') as number | '',
});

watch(
    selectedLeadIds,
    (ids) => {
        bulkEnrollForm.lead_ids = ids;
    },
    { deep: true },
);

watch(
    () => props.sequences,
    (sequences) => {
        if (!sequences.some((s) => s.id === bulkEnrollForm.sequence_id)) {
            bulkEnrollForm.sequence_id = sequences[0]?.id ?? '';
        }
    },
    { deep: true },
);

const bulkSequenceSelect = computed({
    get: () => String(bulkEnrollForm.sequence_id),
    set: (value: string) => {
        bulkEnrollForm.sequence_id = value ? Number(value) : '';
    },
});

const submitBulkEnroll = () => {
    bulkEnrollForm.post(bulkEnrollLeads.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        only: ['leads'],
        onSuccess: () => {
            selectedLeadIds.value = [];
        },
    });
};

const bulkDeleteForm = useForm({
    lead_ids: [] as number[],
});

watch(
    selectedLeadIds,
    (ids) => {
        bulkDeleteForm.lead_ids = ids;
    },
    { deep: true },
);

const submitBulkDelete = () => {
    const count = selectedLeadIds.value.length;

    if (
        !confirm(
            count === 1
                ? 'Delete this lead and everything saved for it?'
                : `Delete ${count} leads and everything saved for them?`,
        )
    ) {
        return;
    }

    bulkDeleteForm.delete(
        bulkDestroyLeads.url({ current_team: teamSlug.value }),
        {
            preserveScroll: true,
            only: ['leads'],
            onSuccess: () => {
                selectedLeadIds.value = [];
            },
        },
    );
};

const applyFilters = () => {
    router.get(
        leadsIndex.url({ current_team: teamSlug.value }),
        activeQuery.value,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const debouncedApplyFilters = useDebounceFn(applyFilters, 350);

watch(searchText, debouncedApplyFilters);
watch(
    [
        statusFilter,
        tierFilter,
        sortOrder,
        dueOnly,
        idealOnly,
        assignedFilter,
        searchIdFilter,
    ],
    applyFilters,
);

const exportUrl = computed(
    () =>
        exportLeads(
            { current_team: teamSlug.value },
            { query: activeQuery.value },
        ).url,
);

const followUpState = (lead: Lead): 'overdue' | 'today' | 'upcoming' | null => {
    if (
        !lead.nextFollowUpAt ||
        lead.status === 'won' ||
        lead.status === 'lost'
    ) {
        return null;
    }

    const today = new Date().toLocaleDateString('en-CA');

    return lead.nextFollowUpAt < today
        ? 'overdue'
        : lead.nextFollowUpAt === today
          ? 'today'
          : 'upcoming';
};

const updateLeadDetails = (
    lead: Lead,
    data: { status?: string; deal_value?: string | null },
) => {
    router.patch(
        updateLead.url({ current_team: teamSlug.value, lead: lead.id }),
        data,
        { preserveScroll: true, preserveState: true, only: ['leads'] },
    );
};

const generatingLeadId = ref<number | null>(null);
const designPickerLead = ref<Lead | null>(null);

const openDesignPicker = (lead: Lead) => {
    designPickerLead.value = lead;
};

const closeDesignPicker = () => {
    if (designPickerLead.value) {
        generatingLeadId.value = null;
    }
    designPickerLead.value = null;
};

const designPickerOpen = computed(() => designPickerLead.value !== null);

const generateSiteUrl = (lead: Lead) =>
    generateSite.url({ current_team: teamSlug.value, lead: lead.id });

const searchStatusVariant = (status: LeadSearch['status']) =>
    status === 'failed'
        ? 'destructive'
        : status === 'completed'
          ? 'secondary'
          : 'outline';

const leadSubtitle = (lead: Lead) =>
    [lead.category ?? 'Business', lead.city].filter(Boolean).join(' · ');

const followUpMeta = (lead: Lead) => {
    const state = followUpState(lead);

    if (!state) {
        return { label: null, tone: null as 'upcoming' | 'due' | null };
    }

    const label =
        state === 'overdue'
            ? 'Overdue'
            : state === 'today'
              ? 'Due today'
              : (lead.nextFollowUpAt ?? '');

    const tone: 'upcoming' | 'due' = state === 'upcoming' ? 'upcoming' : 'due';

    return { label, tone };
};

const onPickDesign = (lead: Lead) => {
    generatingLeadId.value = lead.id;
    openDesignPicker(lead);
};
</script>

<template>
    <Head title="Leads" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                variant="small"
                title="Leads"
                :description="leadsDescription"
            />
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    data-test="new-lead-search"
                    :disabled="isDiscoveryBusy"
                    @click="searchModalOpen = true"
                >
                    <LoaderCircle v-if="isDiscoveryBusy" class="animate-spin" />
                    <Sparkles v-else />
                    {{ isDiscoveryBusy ? 'Search running…' : 'New search' }}
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="leadsBoard({ current_team: teamSlug })">
                        <Kanban /> Board
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl"><Download /> CSV</a>
                </Button>
            </div>
        </div>

        <Alert
            v-if="isDiscoveryBusy"
            data-test="lead-search-in-progress"
            class="border-primary/25 bg-primary/5"
        >
            <LoaderCircle
                class="animate-spin text-primary"
                aria-hidden="true"
            />
            <AlertTitle>Finding businesses</AlertTitle>
            <AlertDescription>
                {{
                    pendingDiscovery && !hasActiveSearch
                        ? 'Starting your search…'
                        : 'Results will appear in the list automatically.'
                }}
                <span
                    v-for="search in activeSearches"
                    :key="search.id"
                    class="mt-1 block text-foreground/80"
                >
                    {{ searchLabel(search) }}
                </span>
            </AlertDescription>
        </Alert>

        <Alert
            v-else-if="isBuildingSite"
            class="border-primary/25 bg-primary/5"
        >
            <LoaderCircle
                class="animate-spin text-primary"
                aria-hidden="true"
            />
            <AlertTitle>Building websites</AlertTitle>
            <AlertDescription>
                Preview sites are generating in the background. Rows update when
                each site is ready.
            </AlertDescription>
        </Alert>

        <div
            v-if="savedSearches.length"
            class="panel flex flex-col gap-2 p-3 md:p-4"
            data-test="saved-searches"
        >
            <p class="text-xs font-medium text-muted-foreground">
                Saved searches
            </p>
            <ul class="flex flex-col gap-2">
                <li
                    v-for="saved in savedSearches"
                    :key="saved.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border/70 px-3 py-2"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            {{ saved.name }}
                        </p>
                        <p class="truncate text-xs text-muted-foreground">
                            {{ saved.keyword }} · {{ saved.location }}
                        </p>
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button
                            size="sm"
                            variant="secondary"
                            :disabled="isDiscoveryBusy"
                            data-test="saved-search-run"
                            @click="rerunSavedSearch(saved)"
                        >
                            <Play class="size-3.5" />
                            Run
                        </Button>
                        <Button
                            size="sm"
                            variant="ghost"
                            data-test="saved-search-delete"
                            @click="removeSavedSearch(saved)"
                        >
                            <Trash2 class="size-3.5" />
                        </Button>
                    </div>
                </li>
            </ul>
        </div>

        <div
            v-if="visibleSearches.length || isDiscoveryBusy"
            class="flex flex-col gap-2"
        >
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="search in visibleSearches"
                    :key="search.id"
                    class="inline-flex items-center"
                >
                    <button
                        type="button"
                        class="rounded-l-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        :disabled="search.status !== 'completed'"
                        :aria-pressed="searchIdFilter === search.id"
                        :data-test="`lead-search-pill-${search.id}`"
                        @click="viewSearchResults(search)"
                    >
                        <Badge
                            :variant="
                                searchIdFilter === search.id
                                    ? 'default'
                                    : searchStatusVariant(search.status)
                            "
                            class="gap-1.5 rounded-r-none py-1 pr-1.5"
                            :class="
                                search.status === 'completed'
                                    ? 'cursor-pointer'
                                    : 'cursor-default'
                            "
                        >
                            <LoaderCircle
                                v-if="
                                    search.status === 'pending' ||
                                    search.status === 'running'
                                "
                                class="animate-spin"
                            />
                            {{ searchLabel(search) }}
                            <span v-if="search.status === 'completed'">
                                ({{ searchResultSummary(search) }})
                            </span>
                        </Badge>
                    </button>
                    <button
                        type="button"
                        class="inline-flex size-7 items-center justify-center rounded-r-full border border-l-0 border-border bg-muted/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        :aria-label="`Remove ${searchLabel(search)}`"
                        :data-test="`dismiss-lead-search-${search.id}`"
                        @click="dismissSearch(search, $event)"
                    >
                        <X class="size-3.5" />
                    </button>
                </div>
                <Button
                    v-if="searchIdFilter !== null"
                    size="sm"
                    variant="ghost"
                    data-test="clear-search-filter"
                    @click="
                        searchIdFilter = null;
                        sortOrder = 'score';
                    "
                >
                    <X class="size-3.5" />
                    Show all leads
                </Button>
            </div>
        </div>

        <Dialog v-model:open="searchModalOpen">
            <DialogScrollContent
                class="max-w-2xl"
                data-test="lead-search-modal"
            >
                <DialogHeader>
                    <DialogTitle>Find local businesses</DialogTitle>
                    <DialogDescription>
                        {{
                            searchModalTab === 'search'
                                ? 'Run a keyword search in one or more cities.'
                                : 'Add a single business from a shared Google Maps URL.'
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div
                    class="inline-flex w-full gap-1 rounded-lg bg-muted p-1"
                    role="tablist"
                    aria-label="How to add leads"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="searchModalTab === 'search'"
                        data-test="lead-search-tab-search"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            searchModalTab === 'search'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="searchModalTab = 'search'"
                    >
                        <Search class="size-4 shrink-0" aria-hidden="true" />
                        Search Maps
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="searchModalTab === 'import'"
                        data-test="lead-search-tab-import"
                        class="flex flex-1 items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            searchModalTab === 'import'
                                ? 'bg-background text-foreground shadow-xs'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="searchModalTab = 'import'"
                    >
                        <Link2 class="size-4 shrink-0" aria-hidden="true" />
                        Import link
                    </button>
                </div>

                <form
                    v-show="searchModalTab === 'search'"
                    class="grid gap-4 sm:grid-cols-2"
                    @submit.prevent="submitSearch"
                >
                    <Alert
                        v-if="atLeadLimit"
                        class="sm:col-span-2 border-amber-500/30 bg-amber-500/5"
                        data-test="lead-limit-warning"
                    >
                        <AlertTitle>Lead plan is full</AlertTitle>
                        <AlertDescription>
                            You are using
                            {{ leadUsage.used }}
                            of
                            {{ leadUsage.limit }}
                            leads. New searches can still refresh businesses you
                            already have, but new ones will not be added until
                            you delete leads or upgrade.
                        </AlertDescription>
                    </Alert>

                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="keyword">Industry or keyword</Label>
                        <Input
                            id="keyword"
                            v-model="searchForm.keyword"
                            list="lead-search-keywords"
                            placeholder="e.g. plumber"
                            required
                        />
                        <datalist id="lead-search-keywords">
                            <option
                                v-for="keyword in searchSuggestions.keywords"
                                :key="keyword"
                                :value="keyword"
                            />
                        </datalist>
                        <p class="text-xs text-muted-foreground">
                            Type freely or pick a common trade from the list.
                        </p>
                        <InputError :message="searchForm.errors.keyword" />
                    </div>

                    <div class="grid gap-2 sm:col-span-1">
                        <Label for="location">Location</Label>
                        <Input
                            id="location"
                            v-model="searchForm.location"
                            list="lead-search-locations"
                            placeholder="e.g. Dallas, Texas"
                            required
                        />
                        <datalist id="lead-search-locations">
                            <option
                                v-for="location in searchSuggestions.locations"
                                :key="location"
                                :value="location"
                            />
                        </datalist>
                        <p class="text-xs text-muted-foreground">
                            Use City, Region (Dallas, Texas or London, United
                            Kingdom). Pick from the list or type any city
                            worldwide.
                        </p>
                        <InputError :message="searchForm.errors.location" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="extra-locations"
                            >More cities (optional)</Label
                        >
                        <Input
                            id="extra-locations"
                            v-model="extraLocations"
                            list="lead-search-locations"
                            placeholder="Houston, Texas — same keyword, comma-separated"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label>Website</Label>
                        <Select v-model="searchForm.website_filter">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="filter in websiteFilters"
                                    :key="filter.value"
                                    :value="filter.value"
                                >
                                    {{ filter.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label>Minimum rating</Label>
                        <Select v-model="searchForm.minimum_rating">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="any">Any rating</SelectItem>
                                <SelectItem
                                    v-for="rating in ['3', '3.5', '4', '4.5']"
                                    :key="rating"
                                    :value="rating"
                                >
                                    {{ rating }}+ stars
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="max_results">Results</Label>
                        <Input
                            id="max_results"
                            v-model="searchForm.max_results"
                            type="number"
                            min="1"
                            max="100"
                        />
                        <InputError :message="searchForm.errors.max_results" />
                    </div>

                    <div class="sm:col-span-2 grid gap-2">
                        <Button
                            type="submit"
                            class="w-full"
                            :disabled="searchForm.processing"
                        >
                            <LoaderCircle
                                v-if="searchForm.processing"
                                class="animate-spin"
                            />
                            <Search v-else />
                            Run search
                        </Button>
                        <div class="flex gap-2">
                            <Input
                                v-model="saveSearchName"
                                placeholder="Name this search to reuse it"
                                maxlength="80"
                                data-test="save-search-name"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="saveSearchForm.processing"
                                data-test="save-search-submit"
                                @click="saveCurrentSearch"
                            >
                                <Bookmark class="size-4" />
                                Save
                            </Button>
                        </div>
                        <InputError :message="saveSearchForm.errors.name" />
                    </div>
                </form>

                <form
                    v-show="searchModalTab === 'import'"
                    class="grid gap-4"
                    @submit.prevent="submitImport"
                >
                    <div class="grid gap-2">
                        <Label for="maps_url">Google Maps link</Label>
                        <Input
                            id="maps_url"
                            v-model="importForm.maps_url"
                            placeholder="https://maps.app.goo.gl/…"
                            required
                            autocomplete="off"
                        />
                        <p class="text-xs text-muted-foreground">
                            Open a business in Google Maps, tap Share, and paste
                            the link here.
                        </p>
                        <InputError :message="importForm.errors.maps_url" />
                    </div>
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="importForm.processing"
                    >
                        <LoaderCircle
                            v-if="importForm.processing"
                            class="animate-spin"
                        />
                        <Link2 v-else />
                        Import business
                    </Button>
                </form>
            </DialogScrollContent>
        </Dialog>

        <div
            class="panel flex flex-wrap items-center gap-2 p-3 md:p-4"
            role="search"
            aria-label="Filter leads"
            data-test="leads-filters"
        >
            <div class="relative w-full min-w-[12rem] flex-1 sm:max-w-xs">
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

            <Select v-model="statusFilter">
                <SelectTrigger class="h-9 w-40" aria-label="Pipeline stage">
                    <SelectValue placeholder="Stage" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="all">All stages</SelectItem>
                    <SelectItem
                        v-for="status in leadStatuses"
                        :key="status.value"
                        :value="status.value"
                    >
                        {{ status.label }}
                    </SelectItem>
                </SelectContent>
            </Select>

            <Button
                size="sm"
                class="h-9"
                :variant="dueOnly ? 'secondary' : 'outline'"
                :aria-pressed="dueOnly"
                data-test="due-follow-ups-toggle"
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
                <SelectTrigger class="h-9 w-44" aria-label="Sort list by">
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
                class="h-9 text-destructive hover:bg-destructive/10 hover:text-destructive"
                data-test="leads-clear-filters"
                @click="clearFilters"
            >
                <X /> Clear ({{ activeFilterCount }})
            </Button>
        </div>

        <div
            v-if="selectedLeadIds.length > 0"
            class="panel flex flex-col gap-3 border-primary/30 bg-primary/5 p-3 sm:flex-row sm:items-center sm:justify-between"
            data-test="leads-bulk-action-bar"
        >
            <p class="flex items-center gap-2 text-sm font-medium">
                <ListChecks class="size-4 text-primary" />
                {{ selectedLeadIds.length }} selected
            </p>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <template v-if="sequences.length">
                    <Select v-model="bulkSequenceSelect">
                        <SelectTrigger
                            class="h-9 min-w-[12rem]"
                            aria-label="Follow-up sequence"
                        >
                            <SelectValue placeholder="Choose sequence" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="sequence in sequences"
                                :key="sequence.id"
                                :value="String(sequence.id)"
                            >
                                {{ sequence.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <Button
                        size="sm"
                        :disabled="bulkEnrollForm.processing"
                        data-test="leads-bulk-enroll-submit"
                        @click="submitBulkEnroll"
                    >
                        Enroll in sequence
                    </Button>
                </template>
                <Button
                    size="sm"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    :disabled="bulkDeleteForm.processing"
                    data-test="leads-bulk-delete-submit"
                    @click="submitBulkDelete"
                >
                    <Trash2 class="size-4" />
                    Delete
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    class="text-muted-foreground"
                    @click="selectedLeadIds = []"
                >
                    Clear selection
                </Button>
            </div>
        </div>

        <TooltipProvider :delay-duration="200">
            <section class="flex flex-col gap-4">
                <div v-if="leads.data.length === 0" class="panel">
                    <EmptyState
                        v-if="isDiscoveryBusy"
                        variant="plain"
                        title="Searching Google Maps"
                        description="New businesses will appear here automatically — no refresh needed."
                    >
                        <template #icon>
                            <LoaderCircle class="size-6 animate-spin" />
                        </template>
                    </EmptyState>
                    <EmptyState
                        v-else
                        variant="plain"
                        title="No leads yet"
                        description="Find local businesses without a website, then build and sell them a preview site."
                        :icon="Search"
                    >
                        <Button size="sm" @click="searchModalOpen = true">
                            <Sparkles /> New search
                        </Button>
                    </EmptyState>
                </div>

                <ul v-else class="panel divide-y md:hidden">
                    <li
                        v-for="lead in leads.data"
                        :key="`m-${lead.id}`"
                        class="relative space-y-3 p-4"
                        data-test="lead-row"
                    >
                        <div
                            v-if="lead.siteBuild"
                            class="absolute inset-x-0 top-0"
                            aria-hidden="true"
                        >
                            <SiteBuildBar :build="lead.siteBuild" />
                        </div>
                        <div class="flex items-start gap-3">
                            <Checkbox
                                v-if="sequences.length"
                                :model-value="selectedLeadIds.includes(lead.id)"
                                :aria-label="`Select ${lead.name}`"
                                class="mt-0.5"
                                @update:model-value="
                                    (checked) => toggleLead(lead.id, checked)
                                "
                            />
                            <div class="min-w-0 flex-1">
                                <Link
                                    :href="
                                        reviewLead({
                                            current_team: teamSlug,
                                            lead: lead.id,
                                        })
                                    "
                                    class="block min-w-0 font-medium hover:underline"
                                >
                                    <TruncatedText :text="lead.name" />
                                </Link>
                                <div
                                    class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                                >
                                    <MapPin
                                        class="size-3.5 shrink-0"
                                        aria-hidden="true"
                                    />
                                    <TruncatedText
                                        :text="leadSubtitle(lead)"
                                        class="min-w-0 flex-1"
                                    />
                                </div>
                                <Badge
                                    v-if="lead.activeSequences?.length"
                                    variant="secondary"
                                    class="mt-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                                >
                                    Sequence ·
                                    {{ lead.activeSequences[0]?.name }}
                                </Badge>
                            </div>
                            <ScoreBadge
                                :score="lead.opportunityScore"
                                :tier="lead.opportunityTier"
                            />
                        </div>
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span
                                v-if="lead.rating"
                                class="inline-flex items-center gap-1"
                            >
                                <Star
                                    class="size-3.5 fill-amber-400 text-amber-400"
                                />
                                {{ lead.rating.toFixed(1) }}
                                <span class="text-muted-foreground"
                                    >({{ lead.reviewsCount }})</span
                                >
                            </span>
                            <Badge
                                v-if="!lead.website"
                                class="bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                            >
                                No website
                            </Badge>
                            <a
                                v-else
                                :href="lead.website"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 text-muted-foreground"
                            >
                                <Globe class="size-3.5" /> Website
                            </a>
                            <a
                                v-if="lead.phone"
                                :href="`tel:${lead.phone}`"
                                class="inline-flex items-center gap-1"
                            >
                                <Phone class="size-3.5" />
                                <TruncatedText
                                    :text="lead.phone"
                                    class="max-w-[8rem]"
                                />
                            </a>
                        </div>
                        <div
                            class="flex flex-wrap items-end justify-between gap-3"
                        >
                            <LeadStageControls
                                :lead="lead"
                                :follow-up-label="followUpMeta(lead).label"
                                :follow-up-tone="followUpMeta(lead).tone"
                                @update:status="
                                    (status) =>
                                        updateLeadDetails(lead, { status })
                                "
                                @update:deal-value="
                                    (deal_value) =>
                                        updateLeadDetails(lead, { deal_value })
                                "
                            />
                            <LeadQuickActions
                                :lead="lead"
                                :team-slug="teamSlug"
                                :generating-lead-id="generatingLeadId"
                                @pick-design="onPickDesign"
                            />
                        </div>
                        <p
                            v-if="lead.siteGenerationError && !lead.site"
                            class="text-xs text-destructive"
                        >
                            {{ lead.siteGenerationError }}
                        </p>
                    </li>
                </ul>

                <div
                    v-if="leads.data.length > 0"
                    class="hidden overflow-hidden panel md:block"
                >
                    <table class="w-full table-fixed text-sm">
                        <colgroup>
                            <col class="w-10" />
                            <col class="w-[26%]" />
                            <col class="w-[10%]" />
                            <col class="w-[9%]" />
                            <col class="w-[13%]" />
                            <col class="w-[9%]" />
                            <col class="w-[13%]" />
                            <col class="w-[12%]" />
                        </colgroup>
                        <thead
                            class="bg-muted/50 text-left text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-2 py-2.5">
                                    <Checkbox
                                        v-if="sequences.length"
                                        :model-value="
                                            allOnPageSelected ? true : false
                                        "
                                        aria-label="Select all on this page"
                                        @update:model-value="toggleAllOnPage"
                                    />
                                </th>
                                <th class="px-3 py-2.5 font-medium">
                                    Business
                                </th>
                                <th class="px-2 py-2.5 font-medium">Score</th>
                                <th
                                    class="hidden px-2 py-2.5 font-medium sm:table-cell"
                                >
                                    Rating
                                </th>
                                <th
                                    class="hidden px-2 py-2.5 font-medium lg:table-cell"
                                >
                                    Contact
                                </th>
                                <th class="px-2 py-2.5 font-medium">Web</th>
                                <th class="px-2 py-2.5 font-medium">Stage</th>
                                <th class="px-2 py-2.5 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="lead in leads.data" :key="lead.id">
                                <tr
                                    v-if="lead.siteBuild"
                                    aria-hidden="true"
                                    data-test="lead-build-bar"
                                >
                                    <td colspan="8" class="border-t p-0">
                                        <SiteBuildBar :build="lead.siteBuild" />
                                    </td>
                                </tr>
                                <tr
                                    data-test="lead-row"
                                    class="align-middle"
                                    :class="
                                        lead.siteBuild ? undefined : 'border-t'
                                    "
                                >
                                    <td class="px-2 py-2.5">
                                        <Checkbox
                                            v-if="sequences.length"
                                            :model-value="
                                                selectedLeadIds.includes(
                                                    lead.id,
                                                )
                                            "
                                            :aria-label="`Select ${lead.name}`"
                                            @update:model-value="
                                                (checked) =>
                                                    toggleLead(lead.id, checked)
                                            "
                                        />
                                    </td>
                                    <td class="min-w-0 px-3 py-2.5">
                                        <Link
                                            :href="
                                                reviewLead({
                                                    current_team: teamSlug,
                                                    lead: lead.id,
                                                })
                                            "
                                            class="block min-w-0 font-medium hover:underline"
                                        >
                                            <TruncatedText :text="lead.name" />
                                        </Link>
                                        <div
                                            class="mt-0.5 flex items-center gap-1 text-xs text-muted-foreground"
                                        >
                                            <MapPin
                                                class="size-3.5 shrink-0"
                                                aria-hidden="true"
                                            />
                                            <TruncatedText
                                                :text="leadSubtitle(lead)"
                                                class="min-w-0 flex-1"
                                            />
                                        </div>
                                        <Badge
                                            v-if="lead.activeSequences?.length"
                                            variant="secondary"
                                            class="mt-1 bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200"
                                        >
                                            Sequence ·
                                            {{ lead.activeSequences[0]?.name }}
                                        </Badge>
                                        <a
                                            v-if="lead.phone"
                                            :href="`tel:${lead.phone}`"
                                            class="mt-1 inline-flex max-w-full items-center gap-1 text-xs lg:hidden"
                                        >
                                            <Phone class="size-3 shrink-0" />
                                            <TruncatedText
                                                :text="lead.phone"
                                                class="min-w-0"
                                            />
                                        </a>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <ScoreBadge
                                            :score="lead.opportunityScore"
                                            :tier="lead.opportunityTier"
                                        />
                                    </td>
                                    <td
                                        class="hidden px-2 py-2.5 sm:table-cell"
                                    >
                                        <span
                                            v-if="lead.rating"
                                            class="inline-flex items-center gap-0.5 text-xs"
                                        >
                                            <Star
                                                class="size-3.5 shrink-0 fill-amber-400 text-amber-400"
                                            />
                                            {{ lead.rating.toFixed(1) }}
                                        </span>
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td
                                        class="hidden min-w-0 px-2 py-2.5 lg:table-cell"
                                    >
                                        <a
                                            v-if="lead.phone"
                                            :href="`tel:${lead.phone}`"
                                            class="inline-flex max-w-full items-center gap-1 text-xs hover:underline"
                                        >
                                            <Phone class="size-3.5 shrink-0" />
                                            <TruncatedText
                                                :text="lead.phone"
                                                class="min-w-0"
                                            />
                                        </a>
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                            >—</span
                                        >
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <Tooltip v-if="lead.website">
                                            <TooltipTrigger as-child>
                                                <a
                                                    :href="lead.website"
                                                    target="_blank"
                                                    rel="noopener"
                                                    class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted"
                                                >
                                                    <Globe class="size-4" />
                                                    <span class="sr-only"
                                                        >Has website</span
                                                    >
                                                </a>
                                            </TooltipTrigger>
                                            <TooltipContent
                                                class="max-w-xs break-all"
                                            >
                                                {{ lead.website }}
                                            </TooltipContent>
                                        </Tooltip>
                                        <Badge
                                            v-else
                                            class="px-1.5 py-0 text-[10px] leading-tight bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                                        >
                                            None
                                        </Badge>
                                    </td>
                                    <td class="min-w-0 px-2 py-2.5">
                                        <LeadStageControls
                                            :lead="lead"
                                            :follow-up-label="
                                                followUpMeta(lead).label
                                            "
                                            :follow-up-tone="
                                                followUpMeta(lead).tone
                                            "
                                            @update:status="
                                                (status) =>
                                                    updateLeadDetails(lead, {
                                                        status,
                                                    })
                                            "
                                            @update:deal-value="
                                                (deal_value) =>
                                                    updateLeadDetails(lead, {
                                                        deal_value,
                                                    })
                                            "
                                        />
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <LeadQuickActions
                                            :lead="lead"
                                            :team-slug="teamSlug"
                                            :generating-lead-id="
                                                generatingLeadId
                                            "
                                            @pick-design="onPickDesign"
                                        />
                                        <p
                                            v-if="
                                                lead.siteGenerationError &&
                                                !lead.site
                                            "
                                            class="mt-1 text-right text-[10px] leading-tight text-destructive"
                                        >
                                            {{ lead.siteGenerationError }}
                                        </p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <PaginationBar :paginator="leads" />
            </section>
        </TooltipProvider>

        <SiteDesignTemplatePicker
            v-if="designPickerLead"
            :open="designPickerOpen"
            :team-slug="teamSlug"
            :lead-id="designPickerLead.id"
            :lead-name="designPickerLead.name"
            :lead-category="designPickerLead.category"
            :generate-url="generateSiteUrl(designPickerLead)"
            :reload-only="['leads']"
            @close="closeDesignPicker"
        />
    </div>
</template>
