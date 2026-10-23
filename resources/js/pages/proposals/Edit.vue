<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, Copy, LoaderCircle, Plus, Send, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy,
    index as proposalsIndex,
    send,
    store,
    update,
} from '@/routes/proposals';

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
    leads: { id: number; name: string; email: string | null }[];
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

const addPackage = (event: Event) => {
    const select = event.target as HTMLSelectElement;
    const found = props.packages.find((p) => p.id === Number(select.value));

    if (found) {
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
    }

    select.value = '';
};

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

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60 dark:bg-input/30';
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
                    <Button size="sm" :disabled="!proposal.leadEmail" @click="sendProposal(true)">
                        <Send /> {{ proposal.status === 'draft' ? 'Send by email' : 'Email again' }}
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
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="proposal-lead">Client</Label>
                    <select id="proposal-lead" v-model="form.lead_id" :disabled="Boolean(proposal)" :class="selectClass">
                        <option value="" disabled>Choose a lead…</option>
                        <option v-for="lead in leads" :key="lead.id" :value="lead.id">{{ lead.name }}</option>
                    </select>
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

            <div class="grid gap-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Label>Items</Label>
                    <select v-if="editable && packages.length" :class="[selectClass, 'w-56']" aria-label="Add a package" @change="addPackage">
                        <option value="">Add from packages…</option>
                        <option v-for="p in packages" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
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
                    <select v-model="row.interval" :class="selectClass" aria-label="Billing" :disabled="!editable">
                        <option value="">One time</option>
                        <option v-for="interval in intervals" :key="interval.value" :value="interval.value">{{ interval.label }}</option>
                    </select>
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