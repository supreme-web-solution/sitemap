<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    values: number[];
}>();

const bars = computed(() => {
    const peak = Math.max(...props.values, 1);

    return props.values.map((value, index) => ({
        height: Math.max(10, (value / peak) * 100),
        isLatest: index === props.values.length - 1,
    }));
});
</script>

<template>
    <div class="flex h-14 items-end gap-1" aria-hidden="true">
        <span
            v-for="(bar, index) in bars"
            :key="index"
            class="flex-1 rounded-sm transition-[height] duration-500"
            :class="
                bar.isLatest
                    ? 'bg-gradient-to-t from-primary to-violet-400'
                    : 'bg-primary/20'
            "
            :style="{ height: `${bar.height}%` }"
        />
    </div>
</template>
