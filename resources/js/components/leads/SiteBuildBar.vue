<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { SiteBuild } from '@/types';

const props = defineProps<{
    build: SiteBuild;
}>();

const now = ref(Date.now());
const stepSince = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 500);
});

onBeforeUnmount(() => clearInterval(timer));

watch(
    () => props.build.step,
    () => (stepSince.value = Date.now()),
);

/**
 * The server only reports finished steps, so the bar eases towards the next
 * step while the current one runs and never claims it is complete early.
 */
const displayPercent = computed(() => {
    const current = props.build.percent;
    const next =
        props.build.steps.find((step) => step.percent > current)?.percent ??
        100;
    const elapsedSeconds = Math.max(0, (now.value - stepSince.value) / 1000);
    const progress = 1 - Math.exp(-elapsedSeconds / 20);

    return current + Math.max(0, next - 3 - current) * progress;
});
</script>

<template>
    <div
        class="relative h-[3px] w-full overflow-hidden bg-primary/15"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="Math.round(displayPercent)"
        :aria-label="build.label"
    >
        <div
            class="build-flow relative h-full overflow-hidden rounded-r-full transition-[width] duration-700 ease-out"
            :style="{ width: `${displayPercent}%` }"
        >
            <span
                class="build-wave absolute inset-y-0 left-0 w-1/3 bg-gradient-to-r from-transparent via-white/70 to-transparent"
            />
        </div>
    </div>
</template>
