<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store, update } from '@/routes/recurring-invoices';
import type { RecurringSchedule } from '@/types';

const props = defineProps<{
    teamSlug: string;
    leads: { id: number; name: string; email: string | null }[];
    intervals: { value: string; label: string }[];
    collectionModes: { value: string; label: string }[];
    hasStripe: boolean;
    hasPayPal: boolean;
    autoChargeProviders: { value: string; label: string }[];
    today: string;
    schedule?: RecurringSchedule;
}>();

const emit = defineEmits<{ saved: [] }>();

type Row = { description: string; quantity: number; unit_price: number | '' };

const form = useForm<{
    lead_id: number | '';
    interval: string;
    starts_on: string;
    ends_on: string;
    due_in_days: number;
    email_client: boolean;
    collection_mode: string;
    payment_provider: string;
    notes: string;
    items: Row[];
}>({
    lead_id: props.schedule?.leadId ?? '',
    interval: props.schedule?.interval ?? 'monthly',
    starts_on: props.schedule?.startsOn ?? props.today,
    ends_on: props.schedule?.endsOn ?? '',
    due_in_days: props.schedule?.dueInDays ?? 14,
    email_client: props.schedule?.emailClient ?? true,
    collection_mode: props.schedule?.collectionMode ?? 'invoice',
    payment_provider: props.schedule?.paymentProvider ?? 'stripe',
    notes: props.schedule?.notes ?? '',
    items: props.schedule?.items.length
        ? props.schedule.items.map((item) => ({
              description: item.description,
              quantity: item.quantity,
              unit_price: item.unitPrice,
          }))
        : [{ description: '', quantity: 1, unit_price: '' }],
});

const isEditing = computed(() => Boolean(props.schedule));

const canAutoCharge = computed(
    () => props.hasStripe || props.hasPayPal,
);

const selectedLead = computed(() =>
    props.leads.find((lead) => lead.id === form.lead_id),
);

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
    form.transform((data) => ({
        ...data,
        ends_on: data.ends_on || null,
        notes: data.notes || null,
        payment_provider:
            data.collection_mode === 'auto_charge'
                ? data.payment_provider
                : null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('saved'),
    };

    if (props.schedule) {
        form.put(
            update.url({
                current_team: props.teamSlug,
                recurringInvoice: props.schedule.id,
            }),
            options,
        );

        return;
    }

    form.post(store.url(props.teamSlug), options);
};

const itemError = (index: number, field: string): string | undefined =>
    (form.errors as Record<string, string>)[`items.${index}.${field}`];

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:opacity-60 dark:bg-input/30';
const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <form
        class="grid gap-4 panel p-5"
        data-test="recurring-form"
        @submit.prevent="submit"
    >
        <h2 class="text-base font-semibold">
            {{
                isEditing
                    ? `Edit schedule for ${schedule?.leadName}`
                    : 'New recurring invoice'
            }}
        </h2>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="recurring-lead">Client</Label>
                <select
                    id="recurring-lead"
                    v-model="form.lead_id"
                    :disabled="isEditing"
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
                <Label for="recurring-interval">Repeats</Label>
                <select
                    id="recurring-interval"
                    v-model="form.interval"
                    :disabled="isEditing"
                    :class="selectClass"
                >
                    <option
                        v-for="interval in intervals"
                        :key="interval.value"
                        :value="interval.value"
                    >
                        {{ interval.label }}
                    </option>
                </select>
                <InputError :message="form.errors.interval" />
            </div>
            <div class="grid gap-2">
                <Label for="recurring-start">First invoice on</Label>
                <Input
                    id="recurring-start"
                    v-model="form.starts_on"
                    type="date"
                    :min="today"
                    :disabled="isEditing"
                />
                <InputError :message="form.errors.starts_on" />
            </div>
            <div class="grid gap-2">
                <Label for="recurring-end">Stop after (optional)</Label>
                <Input id="recurring-end" v-model="form.ends_on" type="date" />
                <InputError :message="form.errors.ends_on" />
            </div>
            <div class="grid gap-2">
                <Label for="recurring-due">Payment due in (days)</Label>
                <Input
                    id="recurring-due"
                    v-model.number="form.due_in_days"
                    type="number"
                    min="0"
                    max="90"
                />
                <InputError :message="form.errors.due_in_days" />
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="form.email_client"
                        type="checkbox"
                        class="size-4"
                    />
                    Email each invoice
                    <span
                        v-if="selectedLead && !selectedLead.email"
                        class="text-amber-600"
                    >
                        (no email on file)
                    </span>
                </label>
            </div>
            <div v-if="!isEditing" class="grid gap-2 sm:col-span-3">
                <Label for="recurring-collection">Collection</Label>
                <select
                    id="recurring-collection"
                    v-model="form.collection_mode"
                    :class="selectClass"
                    :disabled="!hasStripe && !hasPayPal"
                >
                    <option
                        v-for="mode in collectionModes"
                        :key="mode.value"
                        :value="mode.value"
                        :disabled="
                            !canAutoCharge && mode.value === 'auto_charge'
                        "
                    >
                        {{ mode.label }}
                    </option>
                </select>
                <div
                    v-if="form.collection_mode === 'auto_charge' && canAutoCharge"
                    class="grid gap-2"
                >
                    <Label for="recurring-provider">Payment provider</Label>
                    <select
                        id="recurring-provider"
                        v-model="form.payment_provider"
                        :class="selectClass"
                    >
                        <option
                            v-for="provider in autoChargeProviders"
                            :key="provider.value"
                            :value="provider.value"
                        >
                            {{ provider.label }}
                        </option>
                    </select>
                </div>
                <p
                    v-if="!canAutoCharge"
                    class="text-xs text-muted-foreground"
                >
                    Connect Stripe or PayPal on Payments to charge a saved
                    payment method automatically.
                </p>
                <p
                    v-else-if="form.collection_mode === 'auto_charge'"
                    class="text-xs text-muted-foreground"
                >
                    The first cycle emails a pay link that saves the payment
                    method. Later cycles charge automatically when one is on
                    file.
                </p>
            </div>
        </div>
        <div class="grid gap-3">
            <Label>Items on every invoice</Label>
            <div
                v-for="(row, index) in form.items"
                :key="index"
                class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_5rem_8rem_2rem] sm:items-start"
            >
                <div class="grid gap-1">
                    <Input
                        v-model="row.description"
                        placeholder="Hosting and maintenance"
                        aria-label="Description"
                    />
                    <InputError :message="itemError(index, 'description')" />
                </div>
                <div class="grid gap-1">
                    <Input
                        v-model.number="row.quantity"
                        type="number"
                        min="1"
                        max="999"
                        aria-label="Quantity"
                    />
                    <InputError :message="itemError(index, 'quantity')" />
                </div>
                <div class="grid gap-1">
                    <Input
                        v-model.number="row.unit_price"
                        type="number"
                        min="0.01"
                        step="0.01"
                        placeholder="0.00"
                        aria-label="Unit price in dollars"
                    />
                    <InputError :message="itemError(index, 'unit_price')" />
                </div>
                <Button
                    v-if="form.items.length > 1"
                    type="button"
                    size="icon-sm"
                    variant="ghost"
                    aria-label="Remove item"
                    @click="removeRow(index)"
                >
                    <Trash2 />
                </Button>
            </div>
            <InputError :message="form.errors.items" />
            <div class="flex items-center justify-between">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="addRow"
                >
                    <Plus /> Add item
                </Button>
                <p class="font-semibold">
                    {{ money(totalCents) }}
                    <span class="text-xs font-normal text-muted-foreground"
                        >per invoice, USD</span
                    >
                </p>
            </div>
        </div>

        <div class="grid gap-2">
            <Label for="recurring-notes">Notes (shown on every invoice)</Label>
            <textarea
                id="recurring-notes"
                v-model="form.notes"
                rows="2"
                maxlength="2000"
                :class="textareaClass"
            />
            <InputError :message="form.errors.notes" />
        </div>

        <div class="flex items-center gap-2">
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                {{ isEditing ? 'Save changes' : 'Start billing' }}
            </Button>
            <Button type="button" variant="ghost" @click="emit('saved')"
                >Cancel</Button
            >
        </div>
    </form>
</template>
