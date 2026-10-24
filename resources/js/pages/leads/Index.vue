<script setup lang="ts">
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    CalendarClock,
    ClipboardCheck,
    Download,
    ExternalLink,
    Kanban,
    Globe,
    Link2,
    LoaderCircle,
    MapPin,
    Pencil,
    Phone,
    RotateCcw,
    Search,
    Sparkles,
    Star,
} from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import ScoreBadge from '@/components/ScoreBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { leadStatusClasses, leadStatuses } from '@/lib/leadStatus';
import { store as storeImport } from '@/routes/lead-imports';
import { store as storeSearch } from '@/routes/lead-searches';
import {
    board as leadsBoard,
    edit as reviewLead,
    exportMethod as exportLeads,
    index as leadsIndex,
    update as updateLead,
} from '@/routes/leads';
import SiteDesignTemplatePicker from '@/components/sites/SiteDesignTemplatePicker.vue';
import { store as generateSite } from '@/routes/leads/site';
import { edit as editSite } from '@/routes/sites';
import type {
    Lead,
    LeadSearch,
    LeadStatus,
    Option,
    Paginated,
    Team,
} from '@/types';

const props = defineProps<{
    leads: Paginated<Lead>;
    searches: LeadSearch[];
    filters: {
        status: LeadStatus | null;
        search: string | null;
        sort: string;
        tier: string | null;
        follow_up: string | null;
        assigned: string | null;
        ideal: string | null;
    };
    websiteFilters: Option[];
    members: { id: number; name: string }[];
    hasIdealCustomer: boolean;
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

const submitSearch = () => {
    searchForm
        .transform((data) => ({
            ...data,
            minimum_rating:
                data.minimum_rating === 'any'
                    ? null
                    : Number(data.minimum_rating),
            locations: extraLocations.value
                .split(',')
                .map((part) => part.trim())
                .filter(Boolean),
        }))
        .post(storeSearch.url({ current_team: teamSlug.value }), {
            preserveScroll: true,
        });
};

const importForm = useForm({ maps_url: '' });

const submitImport = () => {
    importForm.post(storeImport.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => importForm.reset(),
    });
};

const hasActiveSearch = computed(() =>
    props.searches.some(
        (search) => search.status === 'pending' || search.status === 'running',
    ),
);

const isBuildingSite = computed(() =>
    props.leads.data.some((lead) => lead.siteGenerationStatus === 'queued'),
);

const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['searches', 'leads'] },
    { autoStart: false },
);

watch(
    () => hasActiveSearch.value || isBuildingSite.value,
    (isActive) => (isActive ? startPolling() : stopPolling()),
    { immediate: true },
);

const searchLabel = (search: LeadSearch) =>
    search.placeUrl
        ? 'Maps link import'
        : `${search.keyword} · ${search.location}`;

const searchText = ref(props.filters.search ?? '');
const statusFilter = ref<string>(props.filters.status ?? 'all');
const tierFilter = ref<string>(props.filters.tier ?? 'all');
const sortOrder = ref<string>(props.filters.sort ?? 'score');
const dueOnly = ref(props.filters.follow_up === 'due');
const idealOnly = ref(Boolean(props.filters.ideal));
const assignedFilter = ref<string>(props.filters.assigned ?? 'all');

const sortOptions = [
    { value: 'score', label: 'Best opportunity' },
    { value: 'reviews', label: 'Most reviews' },
    { value: 'newest', label: 'Newest' },
    { value: 'follow_up', label: 'Follow-up date' },
];

const activeQuery = computed(() => ({
    search: searchText.value || undefined,
    status: statusFilter.value === 'all' ? undefined : statusFilter.value,
    tier: tierFilter.value === 'all' ? undefined : tierFilter.value,
    sort: sortOrder.value === 'score' ? undefined : sortOrder.value,
    follow_up: dueOnly.value ? 'due' : undefined,
    assigned: assignedFilter.value === 'all' ? undefined : assignedFilter.value,
    ideal: idealOnly.value ? '1' : undefined,
}));

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
    [statusFilter, tierFilter, sortOrder, dueOnly, idealOnly, assignedFilter],
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
</script>

<template>
    <Head title="Leads" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <section class="panel p-5">
            <Heading
                variant="small"
                title="Find local businesses"
                description="Search Google Maps for businesses in any city, then build them a website in one click."
            />

            <form
                class="mt-5 grid gap-4 md:grid-cols-12"
                @submit.prevent="submitSearch"
            >
                <div class="grid gap-2 md:col-span-3">
                    <Label for="keyword">Industry or keyword</Label>
                    <Input
                        id="keyword"
                        v-model="searchForm.keyword"
                        placeholder="e.g. plumbers"
                        required
                    />
                    <InputError :message="searchForm.errors.keyword" />
                </div>

                <div class="grid gap-2 md:col-span-3">
                    <Label for="location">Location</Label>
                    <Input
                        id="location"
                        v-model="searchForm.location"
                        placeholder="e.g. Austin, Texas"
                        required
                    />
                    <InputError :message="searchForm.errors.location" />
                </div>

                <div class="grid gap-2 md:col-span-12">
                    <Label for="extra-locations">More cities (optional)</Label>
                    <Input
                        id="extra-locations"
                        v-model="extraLocations"
                        placeholder="Dallas, Houston — searched with the same keyword"
                    />
                </div>

                <div class="grid gap-2 md:col-span-2">
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

                <div class="grid gap-2 md:col-span-2">
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

                <div class="grid gap-2 md:col-span-1">
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

                <div class="flex items-end md:col-span-1">
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
                        Search
                    </Button>
                </div>
            </form>

            <form
                class="mt-4 flex flex-col gap-2 border-t pt-4 md:flex-row md:items-start"
                @submit.prevent="submitImport"
            >
                <div class="grid flex-1 gap-2">
                    <Label for="maps_url">Or paste a Google Maps link</Label>
                    <Input
                        id="maps_url"
                        v-model="importForm.maps_url"
                        placeholder="https://maps.app.goo.gl/…"
                        required
                    />
                    <InputError :message="importForm.errors.maps_url" />
                </div>
                <Button
                    type="submit"
                    variant="outline"
                    class="md:mt-[1.375rem]"
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

            <div v-if="searches.length" class="mt-5 flex flex-wrap gap-2">
                <Badge
                    v-for="search in searches"
                    :key="search.id"
                    :variant="searchStatusVariant(search.status)"
                    :title="search.error ?? undefined"
                    class="gap-1.5 py-1"
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
                        ({{ search.resultsCount }} found)
                    </span>
                    <span v-else-if="search.status === 'failed'">
                        (failed)
                    </span>
                </Badge>
            </div>
            <p
                v-for="search in searches.filter(
                    (search) => search.status === 'failed' && search.error,
                )"
                :key="`error-${search.id}`"
                class="mt-2 text-sm text-destructive"
            >
                {{ searchLabel(search) }}: {{ search.error }}
            </p>
        </section>

        <section class="flex flex-col gap-4">
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <Heading
                    variant="small"
                    title="Your leads"
                    :description="`${leads.total} businesses, ranked by opportunity`"
                />
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" class="h-10" as-child>
                        <Link :href="leadsBoard({ current_team: teamSlug })">
                            <Kanban /> Board
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" class="h-10" as-child>
                        <a :href="exportUrl"><Download /> CSV</a>
                    </Button>
                    <Button
                        :variant="dueOnly ? 'default' : 'outline'"
                        size="sm"
                        class="h-10"
                        data-test="due-follow-ups-toggle"
                        @click="dueOnly = !dueOnly"
                    >
                        <CalendarClock /> Follow-ups due
                    </Button>
                    <Button
                        v-if="hasIdealCustomer"
                        :variant="idealOnly ? 'default' : 'outline'"
                        size="sm"
                        class="h-10"
                        @click="idealOnly = !idealOnly"
                    >
                        Ideal customer
                    </Button>
                    <Select v-model="assignedFilter">
                        <SelectTrigger class="w-40">
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
                    <Input
                        v-model="searchText"
                        placeholder="Filter by name, city or category"
                        class="md:w-64"
                    />
                    <Select v-model="tierFilter">
                        <SelectTrigger class="w-36">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All scores</SelectItem>
                            <SelectItem value="hot">Hot</SelectItem>
                            <SelectItem value="warm">Warm</SelectItem>
                            <SelectItem value="cold">Cold</SelectItem>
                        </SelectContent>
                    </Select>
                    <Select v-model="sortOrder">
                        <SelectTrigger class="w-44">
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
                    <Select v-model="statusFilter">
                        <SelectTrigger class="w-44">
                            <SelectValue />
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
                </div>
            </div>

            <div class="overflow-x-auto panel">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-3 font-medium">Business</th>
                            <th class="px-4 py-3 font-medium">Score</th>
                            <th class="px-4 py-3 font-medium">Rating</th>
                            <th class="px-4 py-3 font-medium">Contact</th>
                            <th class="px-4 py-3 font-medium">Website</th>
                            <th class="px-4 py-3 font-medium">Stage</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="lead in leads.data"
                            :key="lead.id"
                            data-test="lead-row"
                            class="border-t align-top"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ lead.name }}</div>
                                <div
                                    class="flex items-center gap-1 text-muted-foreground"
                                >
                                    <MapPin class="size-3.5" />
                                    {{ lead.category ?? 'Business' }}
                                    <template v-if="lead.city">
                                        · {{ lead.city }}</template
                                    >
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <ScoreBadge
                                    :score="lead.opportunityScore"
                                    :tier="lead.opportunityTier"
                                />
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
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
                                <span v-else class="text-muted-foreground"
                                    >No rating</span
                                >
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    v-if="lead.phone"
                                    :href="`tel:${lead.phone}`"
                                    class="inline-flex items-center gap-1 whitespace-nowrap hover:underline"
                                >
                                    <Phone class="size-3.5" />
                                    {{ lead.phone }}
                                </a>
                                <div
                                    v-if="lead.email"
                                    class="text-muted-foreground"
                                >
                                    {{ lead.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    v-if="lead.website"
                                    :href="lead.website"
                                    target="_blank"
                                    rel="noopener"
                                    class="inline-flex items-center gap-1 text-muted-foreground hover:underline"
                                >
                                    <Globe class="size-3.5" /> Has website
                                </a>
                                <Badge
                                    v-else
                                    class="bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                                    >No website</Badge
                                >
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-2">
                                    <Select
                                        :model-value="lead.status"
                                        @update:model-value="
                                            (status) =>
                                                updateLeadDetails(lead, {
                                                    status: String(status),
                                                })
                                        "
                                    >
                                        <SelectTrigger
                                            size="sm"
                                            :class="[
                                                'w-40 border-0',
                                                leadStatusClasses[lead.status],
                                            ]"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="status in leadStatuses"
                                                :key="status.value"
                                                :value="status.value"
                                            >
                                                {{ status.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <span
                                        v-if="followUpState(lead)"
                                        :class="[
                                            'inline-flex w-fit items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium',
                                            followUpState(lead) === 'upcoming'
                                                ? 'bg-muted text-muted-foreground'
                                                : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
                                        ]"
                                    >
                                        <CalendarClock class="size-3" />
                                        {{
                                            followUpState(lead) === 'overdue'
                                                ? 'Overdue'
                                                : followUpState(lead) ===
                                                    'today'
                                                  ? 'Due today'
                                                  : lead.nextFollowUpAt
                                        }}
                                    </span>
                                    <Input
                                        v-if="lead.status === 'won'"
                                        type="number"
                                        min="0"
                                        placeholder="Deal value ($)"
                                        class="h-8 w-40"
                                        :default-value="lead.dealValue ?? ''"
                                        @change="
                                            (event: Event) =>
                                                updateLeadDetails(lead, {
                                                    deal_value:
                                                        (
                                                            event.target as HTMLInputElement
                                                        ).value || null,
                                                })
                                        "
                                    />
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <Button size="sm" variant="ghost" as-child>
                                        <Link
                                            :href="
                                                reviewLead({
                                                    current_team: teamSlug,
                                                    lead: lead.id,
                                                })
                                            "
                                            :title="
                                                lead.detailsConfirmed
                                                    ? 'Details confirmed'
                                                    : 'Review details, logo and photos'
                                            "
                                        >
                                            <ClipboardCheck
                                                :class="
                                                    lead.detailsConfirmed
                                                        ? 'text-emerald-600'
                                                        : ''
                                                "
                                            />
                                            Review
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="
                                            lead.siteGenerationStatus ===
                                            'queued'
                                        "
                                        size="sm"
                                        disabled
                                    >
                                        <LoaderCircle class="animate-spin" />
                                        Building…
                                    </Button>
                                    <template v-else-if="lead.site">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            as-child
                                        >
                                            <a
                                                :href="lead.site.previewUrl"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                <ExternalLink /> Preview
                                            </a>
                                        </Button>
                                        <Button size="sm" as-child>
                                            <Link
                                                :href="
                                                    editSite({
                                                        current_team: teamSlug,
                                                        site: lead.site.id,
                                                    })
                                                "
                                            >
                                                <Pencil /> Edit site
                                            </Link>
                                        </Button>
                                    </template>
                                    <Button
                                        v-else
                                        size="sm"
                                        data-test="generate-site-button"
                                        :disabled="generatingLeadId !== null"
                                        @click="
                                            () => {
                                                generatingLeadId = lead.id;
                                                openDesignPicker(lead);
                                            }
                                        "
                                    >
                                        <LoaderCircle
                                            v-if="generatingLeadId === lead.id"
                                            class="animate-spin"
                                        />
                                        <RotateCcw
                                            v-else-if="
                                                lead.siteGenerationStatus ===
                                                'failed'
                                            "
                                        />
                                        <Sparkles v-else />
                                        {{
                                            lead.siteGenerationStatus ===
                                            'failed'
                                                ? 'Try again'
                                                : 'Create website'
                                        }}
                                    </Button>
                                </div>
                                <p
                                    v-if="lead.siteGenerationError"
                                    class="mt-1 text-right text-xs text-destructive"
                                >
                                    {{ lead.siteGenerationError }}
                                </p>
                            </td>
                        </tr>
                        <tr v-if="leads.data.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-12 text-center text-muted-foreground"
                            >
                                <template v-if="hasActiveSearch">
                                    Searching Google Maps… leads will appear
                                    here automatically.
                                </template>
                                <template v-else>
                                    No leads yet. Run a search above to find
                                    businesses.
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="leads.last_page > 1"
                class="flex items-center justify-between text-sm text-muted-foreground"
            >
                <span>
                    Showing {{ leads.from }}–{{ leads.to }} of
                    {{ leads.total }}
                </span>
                <div class="flex gap-2">
                    <Button
                        v-if="leads.prev_page_url"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link :href="leads.prev_page_url" preserve-scroll
                            >Previous</Link
                        >
                    </Button>
                    <Button
                        v-if="leads.next_page_url"
                        size="sm"
                        variant="outline"
                        as-child
                    >
                        <Link :href="leads.next_page_url" preserve-scroll
                            >Next</Link
                        >
                    </Button>
                </div>
            </div>
        </section>

        <SiteDesignTemplatePicker
            v-if="designPickerLead"
            :open="designPickerOpen"
            :team-slug="teamSlug"
            :lead-id="designPickerLead.id"
            :lead-name="designPickerLead.name"
            :lead-category="designPickerLead.category"
            :generate-url="generateSiteUrl(designPickerLead)"
            @close="closeDesignPicker"
        />
    </div>
</template>
