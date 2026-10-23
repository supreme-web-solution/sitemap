<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Plus, Repeat } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { invoiceStatusClasses, invoiceStatusLabel } from '@/lib/invoiceStatus';
import { create, edit, index as invoicesIndex } from '@/routes/invoices';
import { index as recurringIndex } from '@/routes/recurring-invoices';
import { index as paymentsIndex } from '@/routes/payments';
import type { InvoiceSummary, Paginated, Team } from '@/types';

const props = defineProps<{
    status: string | null;
    statuses: { value: string; label: string }[];
    stats: {
        outstandingCents: number;
        overdueCount: number;
        paidThisMonthCents: number;
    };
    hasPaymentAccount: boolean;
    invoices: Paginated<InvoiceSummary>;
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

const money = (cents: number) =>
    new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(cents / 100);

const cards = computed(() => [
    { label: 'Outstanding', value: money(props.stats.outstandingCents) },
    { label: 'Overdue invoices', value: String(props.stats.overdueCount) },
    {
        label: 'Paid this month',
        value: money(props.stats.paidThisMonthCents),
    },
]);
</script>

<template>
    <Head title="Invoices" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Invoices"
                description="Bill clients for the websites you build and get paid online."
            />
            <div class="flex gap-2">
                <Button as-child size="sm" variant="outline">
                    <Link :href="recurringIndex(teamSlug)"
                        ><Repeat /> Recurring billing</Link
                    >
                </Button>
                <Button as-child size="sm">
                    <Link :href="create(teamSlug)"><Plus /> New invoice</Link>
                </Button>
            </div>
        </div>

        <div
            v-if="!hasPaymentAccount"
            class="rounded-xl border border-amber-300/60 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            Clients can't pay online yet.
            <Link
                :href="paymentsIndex(teamSlug)"
                class="font-medium underline underline-offset-4"
                >Connect Stripe or PayPal</Link
            >
            to add a pay button to your invoices.
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div v-for="card in cards" :key="card.label" class="panel p-4">
                <p class="text-sm text-muted-foreground">{{ card.label }}</p>
                <p class="mt-1 text-2xl font-semibold">{{ card.value }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <Link
                :href="invoicesIndex(teamSlug)"
                class="rounded-full border px-3 py-1 text-sm"
                :class="!status ? 'bg-accent font-medium' : ''"
                >All</Link
            >
            <Link
                v-for="option in statuses"
                :key="option.value"
                :href="
                    invoicesIndex(teamSlug, { query: { status: option.value } })
                "
                class="rounded-full border px-3 py-1 text-sm"
                :class="status === option.value ? 'bg-accent font-medium' : ''"
                >{{ option.label }}</Link
            >
        </div>

        <div v-if="invoices.data.length" class="overflow-x-auto panel">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-muted-foreground">
                        <th class="px-4 py-3 font-medium">Invoice</th>
                        <th class="px-4 py-3 font-medium">Client</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Due</th>
                        <th class="px-4 py-3 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="invoice in invoices.data"
                        :key="invoice.id"
                        data-test="invoice-row"
                        class="border-b last:border-0 hover:bg-accent/40"
                    >
                        <td class="px-4 py-3">
                            <Link
                                :href="
                                    edit({
                                        current_team: teamSlug,
                                        invoice: invoice.id,
                                    })
                                "
                                class="font-medium hover:underline"
                                >{{ invoice.number }}</Link
                            >
                            <p class="text-xs text-muted-foreground">
                                {{ formatRelativeTime(invoice.createdAt) }}
                            </p>
                        </td>
                        <td class="px-4 py-3">{{ invoice.leadName }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="
                                    invoiceStatusClasses(
                                        invoice.status,
                                        invoice.isOverdue,
                                    )
                                "
                                >{{
                                    invoiceStatusLabel(
                                        invoice.status,
                                        invoice.statusLabel,
                                        invoice.isOverdue,
                                    )
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ invoice.dueOn ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-medium">
                            {{ invoice.total }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p v-else class="panel p-8 text-center text-sm text-muted-foreground">
            No invoices yet. Create one from here or from a lead's page.
        </p>

        <div
            v-if="invoices.last_page > 1"
            class="flex items-center justify-between text-sm"
        >
            <Link
                v-if="invoices.prev_page_url"
                :href="invoices.prev_page_url"
                class="underline"
                >Previous</Link
            >
            <span v-else />
            <span class="text-muted-foreground"
                >Page {{ invoices.current_page }} of
                {{ invoices.last_page }}</span
            >
            <Link
                v-if="invoices.next_page_url"
                :href="invoices.next_page_url"
                class="underline"
                >Next</Link
            >
            <span v-else />
        </div>
    </div>
</template>
