<script setup lang="ts">
import GaugeArc from '@/components/dashboard/GaugeArc.vue';
import ScoreBadge from '@/components/ScoreBadge.vue';
import type { LeadProfile } from '@/types';

defineProps<{
    lead: LeadProfile;
}>();
</script>

<template>
    <section class="panel p-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 class="text-base font-semibold">Opportunity score</h2>
                <p class="text-sm text-muted-foreground">
                    How promising this business is as a website client.
                </p>
            </div>
            <ScoreBadge
                :score="lead.opportunityScore"
                :tier="lead.opportunityTier"
            />
        </div>

        <div class="mt-4">
            <GaugeArc
                :value="(lead.opportunityScore ?? 0) / 100"
                label="of 100"
            />
        </div>

        <ul class="mt-5 flex flex-col gap-3">
            <li
                v-for="component in lead.opportunityBreakdown"
                :key="component.key"
            >
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">{{ component.label }}</span>
                    <span class="text-muted-foreground tabular-nums"
                        >{{ component.points }}/{{ component.max }}</span
                    >
                </div>
                <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-gradient-to-r from-violet-400 to-primary"
                        :style="{
                            width: `${component.max ? (component.points / component.max) * 100 : 0}%`,
                        }"
                    />
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ component.reason }}
                </p>
            </li>
        </ul>
    </section>
</template>
