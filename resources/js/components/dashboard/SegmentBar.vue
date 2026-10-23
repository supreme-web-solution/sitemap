<script setup lang="ts">
import { computed } from 'vue';

type Segment = {
    label: string;
    value: number;
    /** A Tailwind background class such as `bg-emerald-500`. */
    color: string;
};

const props = defineProps<{
    segments: Segment[];
}>();

const total = computed(() =>
    props.segments.reduce((sum, segment) => sum + segment.value, 0),
);

const visibleSegments = computed(() =>
    props.segments.filter((segment) => segment.value > 0),
);
</script>

<template>
    <div>
        <div class="flex h-2.5 gap-0.5 overflow-hidden rounded-full bg-muted">
            <span
                v-for="segment in visibleSegments"
                :key="segment.label"
                class="h-full first:rounded-l-full last:rounded-r-full"
                :class="segment.color"
                :style="{ width: `${(segment.value / total) * 100}%` }"
                :title="`${segment.label}: ${segment.value}`"
            />
        </div>
        <div
            class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-[0.7rem] text-muted-foreground"
        >
            <span
                v-for="segment in segments"
                :key="segment.label"
                class="flex items-center gap-1.5"
            >
                <span class="size-1.5 rounded-full" :class="segment.color" />
                {{ segment.label }}
                <span class="font-medium text-foreground tabular-nums">{{
                    segment.value
                }}</span>
            </span>
        </div>
    </div>
</template>
