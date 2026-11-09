<script setup lang="ts">
import {
    ChevronDown,
    Circle,
    CircleCheck,
    LoaderCircle,
    TriangleAlert,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import SiteBuildBar from '@/components/leads/SiteBuildBar.vue';
import type { SiteBuild } from '@/types';

const props = defineProps<{
    build: SiteBuild;
}>();

const open = ref(true);
const now = ref(Date.now());
let timer: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
    timer = setInterval(() => (now.value = Date.now()), 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const elapsed = computed(() => {
    if (!props.build.startedAt) {
        return null;
    }

    const seconds = Math.max(
        0,
        Math.floor(
            (now.value - new Date(props.build.startedAt).getTime()) / 1000,
        ),
    );

    return seconds < 60
        ? `${seconds}s`
        : `${Math.floor(seconds / 60)}m ${String(seconds % 60).padStart(2, '0')}s`;
});

const stepNumber = computed(
    () => props.build.steps.findIndex((step) => step.state === 'active') + 1,
);
</script>

<template>
    <div class="overflow-hidden panel" data-test="site-build-progress">
        <SiteBuildBar :build="build" />

        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 p-4 text-left"
            :aria-expanded="open"
            @click="open = !open"
        >
            <span class="flex min-w-0 items-center gap-2 text-sm font-medium">
                <TriangleAlert
                    v-if="build.stalled"
                    class="size-4 shrink-0 text-amber-500"
                />
                <LoaderCircle v-else class="size-4 shrink-0 animate-spin" />
                <span class="truncate">
                    Building the website · {{ build.label }}
                </span>
            </span>
            <span
                class="flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
            >
                <span v-if="stepNumber > 0">
                    Step {{ stepNumber }} of {{ build.steps.length }}
                </span>
                <span v-if="elapsed" class="tabular-nums">{{ elapsed }}</span>
                <ChevronDown
                    class="size-4 transition-transform"
                    :class="open ? 'rotate-180' : undefined"
                />
            </span>
        </button>

        <div v-if="open" class="space-y-3 border-t px-4 py-3">
            <ol class="space-y-2">
                <li
                    v-for="step in build.steps"
                    :key="step.key"
                    class="flex items-center gap-2 text-sm"
                    :class="
                        step.state === 'pending'
                            ? 'text-muted-foreground'
                            : undefined
                    "
                >
                    <CircleCheck
                        v-if="step.state === 'done'"
                        class="size-4 shrink-0 text-emerald-600"
                    />
                    <LoaderCircle
                        v-else-if="step.state === 'active'"
                        class="size-4 shrink-0 animate-spin text-primary"
                    />
                    <Circle v-else class="size-4 shrink-0 opacity-40" />
                    <span :class="step.state === 'active' ? 'font-medium' : ''">
                        {{ step.label }}
                    </span>
                </li>
            </ol>

            <p
                v-if="build.stalled"
                class="rounded-md bg-amber-500/10 px-3 py-2 text-xs text-amber-700 dark:text-amber-400"
            >
                Still working — this one’s taking a bit longer than usual. Feel
                free to leave this page; we’ll finish in the background.
            </p>
            <p v-else class="text-xs text-muted-foreground">
                This usually takes under a minute. Feel free to leave this page;
                we’ll finish in the background.
            </p>
        </div>
    </div>
</template>
