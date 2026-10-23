<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { index as reportsIndex } from '@/routes/reports';
import { formatCurrency } from '@/lib/format';
import type { Team } from '@/types';

defineProps<{
    revenue: {
        paidThisMonthCents: number;
        outstandingCents: number;
        refundedThisMonthCents: number;
        recurringMonthlyCents: number;
    };
    pipeline: { status: string; label: string; count: number }[];
    lostReasons: { reason: string; label: string; count: number }[];
    conversion: { wonRate: number; sample: number };
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Reports',
                href: props.currentTeam
                    ? reportsIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="Reports" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Reports"
            description="Revenue, outstanding invoices, forecast recurring billing and why deals are lost."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <section class="panel p-5">
                <p class="text-sm text-muted-foreground">Paid this month</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ formatCurrency(revenue.paidThisMonthCents / 100) }}
                </p>
            </section>
            <section class="panel p-5">
                <p class="text-sm text-muted-foreground">Outstanding</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ formatCurrency(revenue.outstandingCents / 100) }}
                </p>
            </section>
            <section class="panel p-5">
                <p class="text-sm text-muted-foreground">Refunded this month</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ formatCurrency(revenue.refundedThisMonthCents / 100) }}
                </p>
            </section>
            <section class="panel p-5">
                <p class="text-sm text-muted-foreground">Recurring / cycle</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ formatCurrency(revenue.recurringMonthlyCents / 100) }}
                </p>
            </section>
        </div>

        <section class="panel p-5">
            <h2 class="text-base font-semibold">Won rate</h2>
            <p class="mt-2 text-3xl font-semibold">{{ conversion.wonRate }}%</p>
            <p class="text-sm text-muted-foreground">
                Across {{ conversion.sample }} leads.
            </p>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="panel p-5">
                <h2 class="text-base font-semibold">Pipeline</h2>
                <ul class="mt-3 grid gap-2 text-sm">
                    <li
                        v-for="stage in pipeline"
                        :key="stage.status"
                        class="flex justify-between"
                    >
                        <span>{{ stage.label }}</span>
                        <span class="font-medium">{{ stage.count }}</span>
                    </li>
                </ul>
            </section>
            <section class="panel p-5">
                <h2 class="text-base font-semibold">Lost reasons</h2>
                <ul class="mt-3 grid gap-2 text-sm">
                    <li
                        v-for="row in lostReasons"
                        :key="row.reason"
                        class="flex justify-between"
                    >
                        <span>{{ row.label }}</span>
                        <span class="font-medium">{{ row.count }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </div>
</template>
