<script setup lang="ts">
import { Flame } from '@lucide/vue';
import { computed } from 'vue';
import type { OpportunityTier } from '@/types';

const props = defineProps<{
    score: number | null;
    tier: OpportunityTier | null;
}>();

const tones: Record<OpportunityTier, { label: string; classes: string }> = {
    hot: {
        label: 'Hot',
        classes:
            'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
    },
    warm: {
        label: 'Warm',
        classes:
            'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300',
    },
    cold: {
        label: 'Cold',
        classes:
            'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
    },
};

const tone = computed(() => (props.tier ? tones[props.tier] : null));
</script>

<template>
    <span
        v-if="score !== null && tone"
        :class="[
            'inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold whitespace-nowrap tabular-nums',
            tone.classes,
        ]"
        :title="`Opportunity score ${score}/100`"
    >
        <Flame v-if="tier === 'hot'" class="size-3.5" />
        {{ score }}
        <span class="font-medium opacity-80">{{ tone.label }}</span>
    </span>
    <span v-else class="text-xs text-muted-foreground">-</span>
</template>
