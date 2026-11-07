<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    LoaderCircle,
    Mail,
    Package,
    Plus,
    Send,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import BillingIntervalSelect from '@/components/forms/BillingIntervalSelect.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { requestHeaders } from '@/lib/siteEditor/http';
import {
    destroy,
    draftCopy,
    edit as editProposal,
    index as proposalsIndex,
    previewEmail,
    send,
    store,
    update,
} from '@/routes/proposals';
import { index as packagesIndex } from '@/routes/packages';

type Row = {
    description: string;
    quantity: number;
    unit_price: number | '';
    interval: string;
};

type ProposalData = {
    id: number;
    number: string;
    leadId: number;
    leadName: string;
    leadEmail: string | null;
    title: string;
    intro: string | null;
    terms: string | null;
    validUntil: string | null;
    status: string;
    statusLabel: string;
    editable: boolean;
    publicUrl: string | null;
    sentAt: string | null;
    viewedAt: string | null;
    acceptedAt: string | null;
    acceptedName: string | null;
    items: {
        description: string;
        quantity: number;
        unitPrice: number;
        interval: string;
    }[];
};

const props = defineProps<{
    proposal: ProposalData | null;
    leads: { id: number; name: string; email: string | null; proposalId: number | null }[];
    packages: {
        id: number;
        name: string;
        description: string | null;
        unitPrice: number;
        interval: string;
    }[];
    intervals: { value: string; label: string }[];
    today: string;
    defaultLeadId: number | null;
    canDraftWithAi?: boolean;
}>();

defineOptions({
    layout: (props: { currentTeam?: { slug: string } | null }) => ({
        breadcrumbs: [
            {
                title: 'Proposals',
                href: props.currentTeam
                    ? proposalsIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const editable = computed(() => props.proposal?.editable ?? true);
const clientQuery = ref('');
const clientOpen = ref(false);

const filteredLeads = computed(() => {
    const query = clientQuery.value.trim().toLowerCase();

    if (query === '') {
        return props.leads;
    }

    return props.leads.filter((lead) => lead.name.toLowerCase().includes(query));
});

watch(clientOpen, (open) => {
    if (!open) {
        clientQuery.value = '';
    }
});

const form = useForm<{
    lead_id: number | '';
    title: string;
    intro: string;
    terms: string;
    valid_until: string;
    items: Row[];
}>({
    lead_id: props.proposal?.leadId ?? props.defaultLeadId ?? '',
    title: props.proposal?.title ?? 'Website and hosting proposal',
    intro: props.proposal?.intro ?? '',
    terms: props.proposal?.terms ?? '',
    valid_until: props.proposal?.validUntil ?? '',
    items: props.proposal?.items.length
        ? props.proposal.items.map((item) => ({
              description: item.description,
              quantity: item.quantity,
              unit_price: item.unitPrice,
              interval: item.interval,
          }))
        : [{ description: '', quantity: 1, unit_price: '', interval: '' }],
});

const money = (cents: number) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);

const lineCents = (row: Row) =>
    Math.round(Number(row.quantity || 0)) *
    Math.round(Number(row.unit_price || 0) * 100);

const oneTimeCents = computed(() =>
    form.items
        .filter((row) => !row.interval)
        .reduce((sum, row) => sum + lineCents(row), 0),
);

const recurring = computed(() =>
    props.intervals
        .map((interval) => ({
            label: interval.label,
            cents: form.items
                .filter((row) => row.interval === interval.value)
                .reduce((sum, row) => sum + lineCents(row), 0),
        }))
        .filter((entry) => entry.cents > 0),
);

const addRow = () =>
    form.items.push({
        description: '',
        quantity: 1,
        unit_price: '',
        interval: '',
    });
const removeRow = (index: number) => form.items.splice(index, 1);

const packageIntervalLabel = (interval: string) =>
    props.intervals.find((item) => item.value === interval)?.label ?? 'One time';

const addPackage = (packageId: number) => {
    const found = props.packages.find((item) => item.id === packageId);

    if (!found || !editable.value) {
        return;
    }

    const row: Row = {
        description: found.name,
        quantity: 1,
        unit_price: found.unitPrice,
        interval: found.interval,
    };
    const blank =
        form.items.length === 1 &&
        !form.items[0].description &&
        form.items[0].unit_price === '';

    if (blank) {
        form.items[0] = row;
    } else {
        form.items.push(row);
    }

    toast.success(`Added “${found.name}”`);
};

const leadIdSelect = computed({
    get: () =>
        form.lead_id === '' ? undefined : String(form.lead_id),
    set: (value: string | undefined) => {
        form.lead_id = value ? Number(value) : '';
    },
});

watch(leadIdSelect, (value) => {
    if (props.proposal || !value) {
        return;
    }

    const lead = props.leads.find((item) => String(item.id) === value);

    if (lead?.proposalId) {
        router.visit(
            editProposal.url({
                current_team: teamSlug.value,
                proposal: lead.proposalId,
            }),
        );
    }
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        intro: data.intro || null,
        terms: data.terms || null,
        valid_until: data.valid_until || null,
        items: data.items.map((row) => ({
            ...row,
            interval: row.interval || null,
        })),
    }));

    if (props.proposal) {
        form.put(
            update.url({
                current_team: teamSlug.value,
                proposal: props.proposal.id,
            }),
            { preserveScroll: true },
        );

        return;
    }

    form.post(store.url(teamSlug.value));
};

const itemError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string>)[`items.${index}.${field}`];

const routeArgs = computed(() => ({
    current_team: teamSlug.value,
    proposal: props.proposal?.id ?? 0,
}));

const sendProposal = (email: boolean) =>
    router.post(send.url(routeArgs.value), { email }, { preserveScroll: true });

const sendPreviewToMe = () =>
    router.post(previewEmail.url(routeArgs.value), {}, { preserveScroll: true });

const drafting = ref(false);
const draftError = ref<string | null>(null);

const draftWithAi = async () => {
    if (!props.canDraftWithAi || form.lead_id === '') {
        draftError.value = form.lead_id === ''
            ? 'Choose a client before drafting with AI.'
            : 'AI drafting is not configured on this server.';

        return;
    }

    drafting.value = true;
    draftError.value = null;

    try {
        const response = await fetch(
            draftCopy.url({ current_team: teamSlug.value }),
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    ...requestHeaders(),
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    lead_id: form.lead_id,
                    title: form.title,
                    items: form.items.map((row) => ({
                        description: row.description,
                        quantity: row.quantity,
                        unit_price: row.unit_price === '' ? 0 : row.unit_price,
                        interval: row.interval || null,
                    })),
                }),
            },
        );

        const body = (await response.json().catch(() => null)) as
            | { intro?: string; terms?: string; message?: string; errors?: Record<string, string[]> }
            | null;

        if (!response.ok) {
            const first =
                body && 'errors' in body && body.errors
                    ? Object.values(body.errors)[0]?.[0]
                    : undefined;
            draftError.value =
                first ??
                (body && 'message' in body ? body.message : null) ??
                'Could not draft the proposal copy.';

            return;
        }

        if (!body?.intro || !body.terms) {
            draftError.value = 'Unexpected response from the AI service.';

            return;
        }

        form.intro = body.intro;
        form.terms = body.terms;
        toast.success('Introduction and terms drafted', {
            description: 'Review the wording, then save when it looks right.',
        });
    } finally {
        drafting.value = false;
    }
};

const removeDraft = () => {
    if (confirm('Delete this draft?')) {
        router.delete(destroy.url(routeArgs.value));
    }
};

const copied = ref(false);
const copyLink = async () => {
    if (props.proposal?.publicUrl) {
        await navigator.clipboard.writeText(props.proposal.publicUrl);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    }
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head :title="proposal ? proposal.number : 'New proposal'" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="proposal ? `${proposal.title}` : 'New proposal'"
            :description="
                proposal
                    ? `${proposal.number} for ${proposal.leadName} · ${proposal.statusLabel}`
                    : 'Choose a client, add what you are selling and save a draft.'
            "
        />

        <section v-if="proposal" class="grid gap-3 panel p-5" data-test="proposal-share">
            <p v-if="proposal.status === 'accepted'" class="text-sm text-emerald-700 dark:text-emerald-300">
                Accepted by {{ proposal.acceptedName }}. The invoice and recurring billing were created.
            </p>
            <template v-else>
                <div class="flex flex-wrap gap-2">
                    <Button
                        size="sm"
                        :disabled="!proposal.leadEmail"
                        data-test="proposal-send-client"
                        @click="sendProposal(true)"
                    >
                        <Send />
                        {{
                            proposal.status === 'draft'
                                ? 'Email to client'
                                : 'Email client again'
                        }}
                    </Button>
                    <Button
                        size="sm"
                        variant="outline"
                        data-test="proposal-send-me"
                        @click="sendPreviewToMe"
                    >
                        <Mail /> Send to me
                    </Button>
                    <Button size="sm" variant="outline" @click="sendProposal(false)">
                        {{ proposal.status === 'draft' ? 'Publish link only' : 'Reopen' }}
                    </Button>
                    <Button v-if="proposal.status === 'draft'" size="sm" variant="ghost" @click="removeDraft">
                        <Trash2 /> Delete draft
                    </Button>
                </div>
                <p v-if="!proposal.leadEmail" class="text-xs text-amber-700">
                    This lead has no email address, so publish the link and share it yourself.
                </p>
                <p class="text-xs text-muted-foreground">
                    “Send to me” emails a preview to your inbox without marking the proposal as sent.
                </p>
            </template>
            <div v-if="proposal.publicUrl" class="flex items-center gap-2">
                <input :value="proposal.publicUrl" readonly class="h-9 min-w-0 flex-1 rounded-md border border-input bg-transparent px-3 text-xs" aria-label="Proposal link" />
                <Button size="icon-sm" variant="outline" aria-label="Copy link" @click="copyLink">
                    <Check v-if="copied" />
                    <Copy v-else />
                </Button>
            </div>
            <p v-if="proposal.viewedAt" class="text-xs text-muted-foreground">
                The client opened it on {{ new Date(proposal.viewedAt).toLocaleString() }}.
            </p>
        </section>

        <form class="grid gap-5 panel p-5" data-test="proposal-form" @submit.prevent="submit">
            <div
                v-if="editable"
                class="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-muted/30 px-4 py-3"
            >
                <div class="min-w-0">
                    <p class="text-sm font-medium">Draft introduction &amp; terms</p>
                    <p class="text-xs text-muted-foreground">
                        AI fills both fields from the client and line items so you are not starting from a blank page.
                    </p>
                </div>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="drafting || !canDraftWithAi"
                    data-test="proposal-draft-ai"
                    @click="draftWithAi"
                >
                    <LoaderCircle v-if="drafting" class="animate-spin" />
                    <Sparkles v-else />
                    {{ drafting ? 'Drafting…' : 'Draft with AI' }}
                </Button>
            </div>
            <p
                v-if="editable && !canDraftWithAi"
                class="text-xs text-amber-700 dark:text-amber-300"
            >
                Add ANTHROPIC_API_KEY on the server to enable AI drafting.
            </p>
            <InputError :message="draftError ?? undefined" />

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label>Client</Label>
                    <Select
                        v-model="leadIdSelect"
                        v-model:open="clientOpen"
                        :disabled="Boolean(proposal)"
                    >
                        <SelectTrigger class="w-full" aria-label="Client">
                            <SelectValue placeholder="Choose a lead…" />
                        </SelectTrigger>
                        <SelectContent>
                            <div
                                class="sticky top-0 z-10 bg-popover p-1"
                                @keydown.stop
                                @pointerdown.stop
                            >
                                <Input
                                    v-model="clientQuery"
                                    placeholder="Search clients"
                                    aria-label="Search clients"
                                    class="h-8"
                                    @keydown.stop
                                />
                            </div>
                            <SelectItem
                                v-for="lead in filteredLeads"
                                :key="lead.id"
                                :value="String(lead.id)"
                            >
                                {{ lead.name }}
                            </SelectItem>
                            <p
                                v-if="filteredLeads.length === 0"
                                class="px-2 py-1.5 text-sm text-muted-foreground"
                            >
                                No clients match that search.
                            </p>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.lead_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="proposal-valid">Valid until (optional)</Label>
                    <Input id="proposal-valid" v-model="form.valid_until" type="date" :min="today" :disabled="!editable" />
                    <InputError :message="form.errors.valid_until" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="proposal-title">Title</Label>
                    <Input id="proposal-title" v-model="form.title" :disabled="!editable" />
                    <InputError :message="form.errors.title" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="proposal-intro">Introduction</Label>
                    <textarea id="proposal-intro" v-model="form.intro" rows="3" maxlength="5000" :disabled="!editable" :class="textareaClass" placeholder="Thanks for taking the time to talk. Here is what we suggest…" />
                    <InputError :message="form.errors.intro" />
                </div>
            </div>

            <div class="grid gap-4">
                <div class="grid gap-1">
                    <Label>Items</Label>
                    <p class="text-sm text-muted-foreground">
                        Add a saved package with one click, or type a custom line.
                    </p>
                </div>

                <div
                    v-if="editable"
                    class="rounded-xl border border-violet-200 bg-violet-50/70 p-3 dark:border-violet-900/60 dark:bg-violet-950/30"
                >
                    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-violet-950 dark:text-violet-100">
                            <Package class="size-4" />
                            Your packages
                        </p>
                        <Link
                            :href="packagesIndex.url(teamSlug)"
                            class="text-xs font-medium text-violet-700 underline-offset-2 hover:underline dark:text-violet-300"
                        >
                            Manage packages
                        </Link>
                    </div>
                    <div
                        v-if="packages.length"
                        class="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Add a package to this proposal"
                    >
                        <button
                            v-for="pkg in packages"
                            :key="pkg.id"
                            type="button"
                            class="inline-flex max-w-full flex-col items-start gap-0.5 rounded-lg border border-violet-300 bg-white px-3 py-2 text-left shadow-sm transition hover:border-violet-500 hover:bg-violet-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-violet-500 dark:border-violet-800 dark:bg-violet-950 dark:hover:bg-violet-900"
                            @click="addPackage(pkg.id)"
                        >
                            <span class="truncate text-sm font-semibold text-violet-950 dark:text-violet-50">
                                {{ pkg.name }}
                            </span>
                            <span class="text-xs text-violet-700 dark:text-violet-300">
                                {{ money(Math.round(pkg.unitPrice * 100)) }}
                                ·
                                {{ packageIntervalLabel(pkg.interval) }}
                            </span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="text-sm text-violet-800 dark:text-violet-200"
                    >
                        No packages yet.
                        <Link
                            :href="packagesIndex.url(teamSlug)"
                            class="font-medium underline underline-offset-2"
                        >
                            Create one
                        </Link>
                        to reuse pricing here and on site previews.
                    </p>
                </div>

                <div v-for="(row, index) in form.items" :key="index" class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_4.5rem_7rem_9rem_2rem] sm:items-start">
                    <div class="grid gap-1">
                        <Input v-model="row.description" placeholder="Website design and build" aria-label="Description" :disabled="!editable" />
                        <InputError :message="itemError(index, 'description')" />
                    </div>
                    <div class="grid gap-1">
                        <Input v-model.number="row.quantity" type="number" min="1" max="999" aria-label="Quantity" :disabled="!editable" />
                        <InputError :message="itemError(index, 'quantity')" />
                    </div>
                    <div class="grid gap-1">
                        <Input v-model.number="row.unit_price" type="number" min="0.01" step="0.01" placeholder="0.00" aria-label="Unit price in dollars" :disabled="!editable" />
                        <InputError :message="itemError(index, 'unit_price')" />
                    </div>
                    <BillingIntervalSelect
                        v-model="row.interval"
                        :intervals="intervals"
                        :disabled="!editable"
                    />
                    <Button v-if="editable && form.items.length > 1" type="button" size="icon-sm" variant="ghost" aria-label="Remove item" @click="removeRow(index)">
                        <Trash2 />
                    </Button>
                </div>
                <InputError :message="form.errors.items" />
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <Button v-if="editable" type="button" size="sm" variant="outline" @click="addRow"><Plus /> Add item</Button>
                    <div class="ml-auto text-right text-sm">
                        <p v-if="oneTimeCents > 0" class="font-semibold">{{ money(oneTimeCents) }} <span class="text-xs font-normal text-muted-foreground">due on acceptance</span></p>
                        <p v-for="entry in recurring" :key="entry.label" class="font-semibold">
                            {{ money(entry.cents) }} <span class="text-xs font-normal text-muted-foreground">{{ entry.label.toLowerCase() }}</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="grid gap-2">
                <Label for="proposal-terms">Terms</Label>
                <textarea id="proposal-terms" v-model="form.terms" rows="3" maxlength="5000" :disabled="!editable" :class="textareaClass" />
                <InputError :message="form.errors.terms" />
            </div>

            <div v-if="editable">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ proposal ? 'Save changes' : 'Save draft' }}
                </Button>
            </div>
        </form>
    </div>
</template>