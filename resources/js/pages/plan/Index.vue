<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, X } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import type { UsageRow } from '@/types';

defineProps<{
    plan: { name: string; description: string | null; price: string } | null;
    usage: UsageRow[];
    features: { label: string; included: boolean }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Plan & usage', href: '#' }] },
});

const percent = (row: UsageRow) =>
    row.limit === null || row.limit === 0
        ? row.limit === 0
            ? 100
            : 0
        : Math.min(100, Math.round((row.used / row.limit) * 100));
</script>

<template>
    <Head title="Plan & usage" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Plan & usage"
            description="What your plan includes and how much you have used. Monthly allowances reset on the 1st."
        />

        <section class="panel p-5">
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
            <p class="mt-2 text-xs text-muted-foreground">
                To change your plan, contact your administrator.
            </p>
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
            <h2 class="mb-3 text-base font-semibold">Features</h2>
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