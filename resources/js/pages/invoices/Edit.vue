<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Copy, LoaderCircle, Plus, Trash2 } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatRelativeTime } from '@/lib/format';
import { invoiceStatusClasses, invoiceStatusLabel } from '@/lib/invoiceStatus';
import {
    destroy,
    index as invoicesIndex,
    paid,
    refund,
    send,
    store,
    update,
    voidMethod,
} from '@/routes/invoices';
import { index as paymentsIndex } from '@/routes/payments';
import type { InvoiceDetail, Team } from '@/types';

const props = defineProps<{
    invoice: InvoiceDetail | null;
    leads: { id: number; name: string; email: string | null }[];
    defaultLeadId: number | null;
    defaultDueOn: string | null;
    providers: string[];
    kinds: { value: string; label: string }[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: props.currentTeam
                    ? invoicesIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const { copy } = useClipboard();

const isDraft = computed(
    () => !props.invoice || props.invoice.status === 'draft',
);

type Row = { description: string; quantity: number; unit_price: number | '' };

const form = useForm<{
    lead_id: number | '';
    kind: string;
    due_on: string;
    notes: string;
    items: Row[];
}>({
    lead_id: props.invoice?.leadId ?? props.defaultLeadId ?? '',
    kind: props.invoice?.kind ?? 'standard',
    due_on: props.invoice?.dueOn ?? props.defaultDueOn ?? '',
    notes: props.invoice?.notes ?? '',
    items: props.invoice?.items.length
        ? props.invoice.items.map((item) => ({
              description: item.description,
              quantity: item.quantity,
              unit_price: item.unitPrice,
          }))
        : [{ description: '', quantity: 1, unit_price: '' }],
});

const totalCents = computed(() =>
    form.items.reduce(
        (sum, row) =>
            sum +
            Math.round(Number(row.quantity || 0)) *
                Math.round(Number(row.unit_price || 0) * 100),
        0,
    ),
);

const money = (cents: number) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);

const addRow = () =>
    form.items.push({ description: '', quantity: 1, unit_price: '' });
const removeRow = (index: number) => form.items.splice(index, 1);

const submit = () => {
    if (props.invoice) {
        form.put(
            update.url({
                current_team: teamSlug.value,
                invoice: props.invoice.id,
            }),
            { preserveScroll: true },
        );

        return;
    }

    form.post(store.url(teamSlug.value));
};

const emailInvoice = ref(true);
const acting = ref(false);

const routeArgs = () => ({
    current_team: teamSlug.value,
    invoice: props.invoice!.id,
});

const act = (url: string, data: Record<string, boolean> = {}) => {
    router.post(url, data, {
        preserveScroll: true,
        onStart: () => (acting.value = true),
        onFinish: () => (acting.value = false),
        onError: (errors) =>
            toast.error(Object.values(errors)[0] ?? 'Something went wrong.'),
    });
};

const issue = () => act(send.url(routeArgs()), { email: emailInvoice.value });

const markPaid = () => {
    if (confirm('Record this invoice as paid outside the app?')) {
        act(paid.url(routeArgs()));
    }
};

const voidInvoice = () => {
    if (confirm('Void this invoice? Its pay link will stop working.')) {
        act(voidMethod.url(routeArgs()));
    }
};

const refundInvoice = () => {
    if (confirm('Refund this payment? This cannot be undone from here.')) {
        act(refund.url(routeArgs()));
    }
};

const remove = () => {
    if (confirm('Delete this draft?')) {
        router.delete(destroy.url(routeArgs()), {
            onError: (errors) =>
                toast.error(Object.values(errors)[0] ?? 'Could not delete.'),
        });
    }
};

const copyLink = async () => {
    if (props.invoice?.payUrl) {
        await copy(props.invoice.payUrl);
        toast.success('Pay link copied');
    }
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const itemError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string>)[`items.${index}.${field}`];
</script>

<template>
    <Head :title="invoice ? `Invoice ${invoice.number}` : 'New invoice'" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                :title="invoice ? `Invoice ${invoice.number}` : 'New invoice'"
                :description="
                    invoice
                        ? `For ${invoice.leadName}`
                        : 'Add what you are charging for. You can email it once it is saved.'
                "
            />
            <span
                v-if="invoice"
                class="rounded-full px-3 py-1 text-xs font-medium"
                :class="invoiceStatusClasses(invoice.status, invoice.isOverdue)"
                >{{
                    invoiceStatusLabel(
                        invoice.status,
                        invoice.statusLabel,
                        invoice.isOverdue,
                    )
                }}</span
            >
        </div>

        <section
            v-if="invoice && !isDraft"
            class="flex flex-col gap-4 panel p-5"
        >
            <p v-if="invoice.status === 'paid'" class="text-sm">
                Paid
                <span v-if="invoice.paidAt">{{
                    formatRelativeTime(invoice.paidAt)
                }}</span>
                <span v-if="invoice.paidVia" class="capitalize">
                    via {{ invoice.paidVia }}</span
                >.
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="ml-2"
                    :disabled="acting"
                    @click="refundInvoice"
                >
                    Refund
                </Button>
            </p>
            <p v-else-if="invoice.status === 'refunded'" class="text-sm">
                This invoice was refunded.
            </p>
            <p v-else-if="invoice.status === 'open'" class="text-sm">
                Waiting for payment<span v-if="invoice.sentAt">
                    · issued {{ formatRelativeTime(invoice.sentAt) }}</span
                >.
            </p>
            <p v-else class="text-sm">This invoice was voided.</p>

            <div
                v-if="invoice.payUrl && invoice.status !== 'void'"
                class="flex items-center gap-2"
            >
                <code
                    class="min-w-0 flex-1 truncate rounded-md bg-muted px-2 py-1.5 text-xs"
                    >{{ invoice.payUrl }}</code
                >
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="copyLink"
                >
                    <Copy /> Copy link
                </Button>
            </div>

            <div
                v-if="invoice.status === 'open'"
                class="flex flex-wrap items-center gap-2"
            >
                <label
                    v-if="invoice.leadEmail"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="emailInvoice"
                        type="checkbox"
                        class="size-4"
                    />
                    Email to {{ invoice.leadEmail }}
                </label>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="acting || !invoice.leadEmail"
                    @click="issue"
                >
                    Resend
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :disabled="acting"
                    @click="markPaid"
                >
                    Mark as paid
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="ghost"
                    class="text-destructive"
                    :disabled="acting"
                    @click="voidInvoice"
                >
                    Void
                </Button>
            </div>
        </section>

        <form class="flex flex-col gap-6" @submit.prevent="submit">
            <section class="grid gap-4 panel p-5 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="invoice-lead">Client</Label>
                    <select
                        id="invoice-lead"
                        v-model="form.lead_id"
                        :disabled="!isDraft"
                        :class="selectClass"
                    >
                        <option value="" disabled>Choose a lead…</option>
                        <option
                            v-for="lead in leads"
                            :key="lead.id"
                            :value="lead.id"
                        >
                            {{ lead.name }}
                        </option>
                    </select>
                    <InputError :message="form.errors.lead_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="invoice-kind">Type</Label>
                    <select
                        id="invoice-kind"
                        v-model="form.kind"
                        :disabled="!isDraft"
                        :class="selectClass"
                    >
                        <option
                            v-for="kind in kinds"
                            :key="kind.value"
                            :value="kind.value"
                        >
                            {{ kind.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-2">
                    <Label for="invoice-due">Due date</Label>
                    <Input
                        id="invoice-due"
                        v-model="form.due_on"
                        type="date"
                        :disabled="!isDraft"
                    />
                    <InputError :message="form.errors.due_on" />
                </div>
            </section>

            <section class="panel p-5">
                <h2 class="text-base font-semibold">Items</h2>
                <div class="mt-4 grid gap-3">
                    <div
                        v-for="(row, index) in form.items"
                        :key="index"
                        data-test="invoice-item"
                        class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_8rem_6rem_2rem] sm:items-start"
                    >
                        <div class="grid gap-1">
                            <Input
                                v-model="row.description"
                                :disabled="!isDraft"
                                placeholder="Website design and build"
                                aria-label="Description"
                            />
                            <InputError
                                :message="itemError(index, 'description')"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Input
                                v-model.number="row.quantity"
                                :disabled="!isDraft"
                                type="number"
                                min="1"
                                max="999"
                                aria-label="Quantity"
                            />
                            <InputError
                                :message="itemError(index, 'quantity')"
                            />
                        </div>
                        <div class="grid gap-1">
                            <Input
                                v-model.number="row.unit_price"
                                :disabled="!isDraft"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                aria-label="Unit price in dollars"
                            />
                            <InputError
                                :message="itemError(index, 'unit_price')"
                            />
                        </div>
                        <p class="pt-2 text-right text-sm font-medium">
                            {{
                                money(
                                    Math.round(Number(row.quantity || 0)) *
                                        Math.round(
                                            Number(row.unit_price || 0) * 100,
                                        ),
                                )
                            }}
                        </p>
                        <Button
                            v-if="isDraft && form.items.length > 1"
                            type="button"
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Remove item"
                            @click="removeRow(index)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </div>
                <InputError :message="form.errors.items" class="mt-2" />

                <div
                    class="mt-4 flex items-center justify-between border-t pt-4"
                >
                    <Button
                        v-if="isDraft"
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="addRow"
                    >
                        <Plus /> Add item
                    </Button>
                    <span v-else />
                    <p class="text-lg font-semibold">
                        Total {{ money(totalCents) }}
                        <span class="text-xs font-normal text-muted-foreground"
                            >USD</span
                        >
                    </p>
                </div>
            </section>

            <section class="grid gap-2 panel p-5">
                <Label for="invoice-notes">Notes (shown to the client)</Label>
                <textarea
                    id="invoice-notes"
                    v-model="form.notes"
                    :disabled="!isDraft"
                    rows="3"
                    maxlength="2000"
                    :class="textareaClass"
                    placeholder="Payment terms, thank-you message…"
                />
                <InputError :message="form.errors.notes" />
            </section>

            <div v-if="isDraft" class="flex flex-wrap items-center gap-3">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ invoice ? 'Save draft' : 'Create draft' }}
                </Button>
                <Button
                    v-if="invoice"
                    type="button"
                    variant="ghost"
                    class="text-destructive"
                    @click="remove"
                >
                    Delete draft
                </Button>
                <Button as-child variant="ghost">
                    <Link :href="invoicesIndex(teamSlug)">Cancel</Link>
                </Button>
            </div>
        </form>

        <section
            v-if="invoice && isDraft"
            class="flex flex-col gap-3 panel p-5"
        >
            <h2 class="text-base font-semibold">Issue invoice</h2>
            <p class="text-sm text-muted-foreground">
                Save your changes first. Issuing locks the invoice and creates
                the pay link.
                <template v-if="providers.length">
                    Clients can pay with {{ providers.join(' or ') }}.
                </template>
                <template v-else>
                    No payment method is connected yet,
                    <Link
                        :href="paymentsIndex(teamSlug)"
                        class="underline underline-offset-4"
                        >connect Stripe or PayPal</Link
                    >
                    so clients can pay online.
                </template>
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <label
                    v-if="invoice.leadEmail"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="emailInvoice"
                        type="checkbox"
                        class="size-4"
                    />
                    Email to {{ invoice.leadEmail }}
                </label>
                <Button
                    type="button"
                    :disabled="acting || form.isDirty"
                    @click="issue"
                >
                    {{
                        emailInvoice && invoice.leadEmail
                            ? 'Issue & email'
                            : 'Issue invoice'
                    }}
                </Button>
                <span v-if="form.isDirty" class="text-xs text-muted-foreground"
                    >Save your changes to continue.</span
                >
            </div>
        </section>
    </div>
</template>
