<script setup lang="ts">
import { computed, ref, useId } from 'vue';

const props = withDefaults(
    defineProps<{
        labels: string[];
        values: number[];
        color?: string;
        unit?: string;
    }>(),
    {
        color: 'var(--chart-1)',
        unit: '',
    },
);

const WIDTH = 600;
const HEIGHT = 200;
const TOP = 14;
const BOTTOM = 6;

const gradientId = useId();
const hoverIndex = ref<number | null>(null);

const lastIndex = computed(() => Math.max(props.values.length - 1, 0));
const activeIndex = computed(() => hoverIndex.value ?? lastIndex.value);

const maxValue = computed(() =>
    Math.max(4, Math.ceil(Math.max(...props.values, 0) / 4) * 4),
);

const coordinates = computed(() =>
    props.values.map((value, index) => ({
        x:
            props.values.length > 1
                ? (index / (props.values.length - 1)) * WIDTH
                : WIDTH / 2,
        y: TOP + (1 - value / maxValue.value) * (HEIGHT - TOP - BOTTOM),
    })),
);

const linePath = computed(() =>
    coordinates.value.reduce((path, point, index, all) => {
        if (index === 0) {
            return `M ${point.x} ${point.y}`;
        }

        const previous = all[index - 1];
        const middle = (previous.x + point.x) / 2;

        return `${path} C ${middle} ${previous.y}, ${middle} ${point.y}, ${point.x} ${point.y}`;
    }, ''),
);

const areaPath = computed(() =>
    coordinates.value.length
        ? `${linePath.value} L ${WIDTH} ${HEIGHT} L 0 ${HEIGHT} Z`
        : '',
);

const gridLines = computed(() =>
    [0, 1, 2, 3, 4].map((step) => {
        const y = TOP + (step / 4) * (HEIGHT - TOP - BOTTOM);

        return {
            y,
            top: (y / HEIGHT) * 100,
            value: Math.round((maxValue.value * (4 - step)) / 4),
        };
    }),
);

const axisLabels = computed(() => {
    if (props.labels.length < 2) {
        return props.labels;
    }

    return [0, 1, 2, 3, 4].map(
        (step) => props.labels[Math.round((step * lastIndex.value) / 4)],
    );
});

const active = computed(() => {
    const point = coordinates.value[activeIndex.value];

    if (!point) {
        return null;
    }

    return {
        left: (point.x / WIDTH) * 100,
        top: (point.y / HEIGHT) * 100,
        value: props.values[activeIndex.value],
        label: props.labels[activeIndex.value],
        shift:
            activeIndex.value <= 1
                ? '0%'
                : activeIndex.value >= lastIndex.value - 1
                  ? '-100%'
                  : '-50%',
    };
});

const onPointerMove = (event: PointerEvent) => {
    const target = event.currentTarget as HTMLElement;
    const bounds = target.getBoundingClientRect();
    const ratio = Math.min(
        1,
        Math.max(0, (event.clientX - bounds.left) / bounds.width),
    );

    hoverIndex.value = Math.round(ratio * lastIndex.value);
};
</script>

<template>
    <div role="img" :aria-label="`Chart with ${values.length} data points`">
        <div class="relative h-52" :style="{ color }">
            <span
                v-for="line in gridLines"
                :key="line.top"
                class="absolute left-0 w-6 -translate-y-1/2 text-right text-[0.7rem] text-muted-foreground tabular-nums"
                :style="{ top: `${line.top}%` }"
            >
                {{ line.value }}
            </span>

            <div
                class="absolute inset-y-0 right-0 left-8 touch-pan-y"
                @pointermove="onPointerMove"
                @pointerleave="hoverIndex = null"
            >
                <svg
                    :viewBox="`0 0 ${WIDTH} ${HEIGHT}`"
                    preserveAspectRatio="none"
                    class="absolute inset-0 size-full overflow-visible"
                    aria-hidden="true"
                >
                    <defs>
                        <linearGradient
                            :id="gradientId"
                            x1="0"
                            y1="0"
                            x2="0"
                            y2="1"
                        >
                            <stop
                                offset="0%"
                                stop-color="currentColor"
                                stop-opacity="0.22"
                            />
                            <stop
                                offset="100%"
                                stop-color="currentColor"
                                stop-opacity="0"
                            />
                        </linearGradient>
                    </defs>
                    <line
                        v-for="line in gridLines"
                        :key="line.y"
                        x1="0"
                        :x2="WIDTH"
                        :y1="line.y"
                        :y2="line.y"
                        stroke="var(--border)"
                        stroke-dasharray="3 5"
                        vector-effect="non-scaling-stroke"
                    />
                    <path :d="areaPath" :fill="`url(#${gradientId})`" />
                    <path
                        :d="linePath"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.5"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        vector-effect="non-scaling-stroke"
                    />
                </svg>

                <template v-if="active">
                    <div
                        class="pointer-events-none absolute top-0 bottom-0 w-px bg-current opacity-25"
                        :style="{ left: `${active.left}%` }"
                    />
                    <div
                        class="pointer-events-none absolute size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-[2.5px] border-current bg-card shadow-sm"
                        :style="{
                            left: `${active.left}%`,
                            top: `${active.top}%`,
                        }"
                    />
                    <div
                        class="pointer-events-none absolute z-10 rounded-full bg-foreground px-3 py-1 text-xs font-semibold whitespace-nowrap text-background shadow-lg"
                        :style="{
                            left: `${active.left}%`,
                            top: `${active.top}%`,
                            transform: `translate(${active.shift}, calc(-100% - 14px))`,
                        }"
                    >
                        {{ active.value }} {{ unit }}
                        <span class="ml-1 font-normal opacity-70">{{
                            active.label
                        }}</span>
                    </div>
                </template>
            </div>
        </div>

        <div
            class="mt-2 ml-8 flex justify-between text-xs text-muted-foreground"
        >
            <span v-for="(label, index) in axisLabels" :key="index">{{
                label
            }}</span>
        </div>
    </div>
</template>
