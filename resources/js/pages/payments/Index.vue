<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import PaymentProviderCard from '@/components/payments/PaymentProviderCard.vue';
import { index as paymentsIndex } from '@/routes/payments';
import type { PaymentProviderSummary, Team } from '@/types';

defineProps<{
    canManage: boolean;
    providers: PaymentProviderSummary[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Payments',
                href: props.currentTeam
                    ? paymentsIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
</script>

<template>
    <Head title="Payments" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Payments"
            description="Connect Stripe, PayPal, Paystack or Flutterwave with your own keys. Money goes straight to you. Invoices are in US dollars."
        />

        <PaymentProviderCard
            v-for="provider in providers"
            :key="provider.value"
            :provider="provider"
            :team-slug="teamSlug"
            :can-manage="canManage"
        />
    </div>
</template>
