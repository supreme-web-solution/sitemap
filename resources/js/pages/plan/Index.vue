<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { index as packagesIndex } from '@/routes/packages';
import { change as changePlan } from '@/routes/plan';
import type { UsageRow } from '@/types';

type PlanCard = {
    id: number;
    name: string;
    description: string | null;
    price: string;
    priceCents: number;
    isCurrent: boolean;
    requiresCheckout: boolean;
    checkoutReady: boolean;
    features: { label: string; included: boolean }[];
};

const props = defineProps<{
    plan: { id: number; name: string; description: string | null; price: string; priceCents: number } | null;
    plans: PlanCard[];
    canChangePlan: boolean;
    billingConfigured: boolean;
    usage: UsageRow[];
    features: { label: string; included: boolean }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Plan & usage', href: '#' }] },
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const percent = (row: UsageRow) =>
    row.limit === null || row.limit === 0
        ? row.limit === 0
            ? 100
            : 0
        : Math.min(100, Math.round((row.used / row.limit) * 100));

const selectPlan = (option: PlanCard) => {
    if (!props.canChangePlan || option.isCurrent) {
        return;
    }

    router.post(
        changePlan.url({ current_team: teamSlug.value, plan: option.id }),
        {},
        { preserveScroll: true },
    );
};

const planActionLabel = (option: PlanCard) => {
    if (option.isCurrent) {
        return 'Current plan';
    }

    if (option.requiresCheckout && option.checkoutReady) {
        return 'Upgrade & pay';
    }

    if (option.requiresCheckout) {
        return 'Ask admin';
    }

    return 'Switch';
};
</script>

<template>
    <Head title="Plan & usage" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Plan & usage"
            description="Your MapMatic plan caps how much you can run on the platform. Monthly allowances reset on the 1st."
        />

        <section class="panel space-y-3 p-5">
            <template v-if="plan">
                <p class="text-lg font-semibold">
                    {{ plan.name }}
                    <span class="text-sm font-normal text-muted-foreground">
                        {{ plan.price }}
                    </span>
                </p>
                <p v-if="plan.description" class="text-sm text-muted-foreground">
                    {{ plan.description }}
                </p>
            </template>
            <p v-else class="text-sm text-muted-foreground">
                No plan limits apply to your team.
            </p>
            <p class="text-sm leading-relaxed text-muted-foreground">
                <strong class="font-medium text-foreground">Websites</strong> and
                <strong class="font-medium text-foreground">Custom domains</strong>
                are your live hosting capacity on MapMatic — how many client sites you
                can publish on our servers. Fees you charge clients for Hosting or
                Maintenance are priced under
                <Link
                    :href="packagesIndex.url({ current_team: teamSlug })"
                    class="font-medium text-foreground underline-offset-2 hover:underline"
                >
                    Packages
                </Link>
                and paid to you, not to MapMatic.
            </p>
            <p v-if="!canChangePlan" class="text-xs text-muted-foreground">
                Ask a team admin or owner to change plans.
            </p>
            <p
                v-else-if="!billingConfigured"
                class="text-xs text-muted-foreground"
            >
                Free plans can be switched here. Paid checkout needs Stripe keys
                and a Stripe price on the plan.
            </p>
        </section>

        <section v-if="plans.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <article
                v-for="option in plans"
                :key="option.id"
                class="panel flex flex-col gap-3 p-5"
                :class="option.isCurrent ? 'ring-2 ring-primary/40' : ''"
                data-test="plan-card"
            >
                <div>
                    <p class="text-base font-semibold">{{ option.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ option.price }}</p>
                    <p v-if="option.description" class="mt-1 text-xs text-muted-foreground">
                        {{ option.description }}
                    </p>
                </div>
                <ul class="grid gap-1.5 text-sm">
                    <li
                        v-for="feature in option.features"
                        :key="feature.label"
                        class="flex items-center gap-2"
                    >
                        <Check v-if="feature.included" class="size-3.5 text-emerald-600" />
                        <X v-else class="size-3.5 text-muted-foreground" />
                        <span :class="feature.included ? '' : 'text-muted-foreground'">
                            {{ feature.label }}
                        </span>
                    </li>
                </ul>
                <Button
                    class="mt-auto"
                    size="sm"
                    :variant="option.isCurrent ? 'secondary' : 'default'"
                    :disabled="!canChangePlan || option.isCurrent || (option.requiresCheckout && !option.checkoutReady)"
                    data-test="plan-select"
                    @click="selectPlan(option)"
                >
                    {{ planActionLabel(option) }}
                </Button>
            </article>
        </section>

        <section class="panel p-5">
            <h2 class="mb-3 text-base font-semibold">Usage</h2>
            <ul class="grid gap-4">
                <li v-for="row in usage" :key="row.key">
                    <div class="flex justify-between text-sm">
                        <span>
                            {{ row.label }}
                            <span class="text-xs text-muted-foreground">
                                {{ row.period === 'month' ? 'this month' : 'in total' }}
                            </span>
                        </span>
                        <span class="font-medium">
                            {{ row.used }} /
                            {{ row.limit === null ? 'Unlimited' : row.limit }}
                        </span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full"
                            :class="percent(row) >= 100 ? 'bg-rose-500' : percent(row) >= 80 ? 'bg-amber-500' : 'bg-emerald-500'"
                            :style="{ width: `${percent(row)}%` }"
                        />
                    </div>
                </li>
            </ul>
        </section>

        <section class="panel p-5">
            <h2 class="mb-3 text-base font-semibold">Features on your plan</h2>
            <ul class="grid gap-2 text-sm">
                <li
                    v-for="feature in features"
                    :key="feature.label"
                    class="flex items-center gap-2"
                >
                    <Check v-if="feature.included" class="size-4 text-emerald-600" />
                    <X v-else class="size-4 text-muted-foreground" />
                    <span :class="feature.included ? '' : 'text-muted-foreground'">
                        {{ feature.label }}
                    </span>
                </li>
            </ul>
        </section>
    </div>
</template>
