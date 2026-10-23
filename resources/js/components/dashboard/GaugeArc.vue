<script setup lang="ts">
import { computed, useId } from 'vue';

const props = defineProps<{
    /** A ratio between 0 and 1. */
    value: number;
    label?: string;
}>();

const gradientId = useId();
const percent = computed(() =>
    Math.round(Math.min(1, Math.max(0, props.value)) * 100),
);
</script>

<template>
    <div class="relative mx-auto w-full max-w-36">
        <svg viewBox="0 0 100 56" class="w-full" aria-hidden="true">
            <defs>
                <linearGradient :id="gradientId" x1="0" y1="0" x2="1" y2="0">
                    <stop offset="0%" stop-color="var(--chart-3)" />
                    <stop offset="100%" stop-color="var(--chart-1)" />
                </linearGradient>
            </defs>
            <path
                d="M 10 50 A 40 40 0 0 1 90 50"
                fill="none"
                stroke="var(--muted)"
                stroke-width="9"
                stroke-linecap="round"
                pathLength="100"
            />
            <path
                v-if="percent > 0"
                d="M 10 50 A 40 40 0 0 1 90 50"
                fill="none"
                :stroke="`url(#${gradientId})`"
                stroke-width="9"
                stroke-linecap="round"
                pathLength="100"
                :stroke-dasharray="`${percent} 100`"
                class="transition-[stroke-dasharray] duration-700 ease-out"
            />
        </svg>
        <div
            class="absolute inset-x-0 bottom-0 flex flex-col items-center leading-none"
        >
            <span class="text-lg font-semibold tabular-nums"
                >{{ percent }}%</span
            >
            <span
                v-if="label"
                class="mt-1 text-[0.7rem] text-muted-foreground"
                >{{ label }}</span
            >
        </div>
    </div>
</template>
