<script setup lang="ts">
import type { LucideIcon } from '@lucide/vue';
import { onMounted, ref } from 'vue';
import { initSidebarIconStrokes } from '@/composables/useSidebarIconDraw';

withDefaults(
    defineProps<{
        icon: LucideIcon;
        size?: 'default' | 'compact';
    }>(),
    { size: 'default' },
);

const root = ref<HTMLElement | null>(null);

onMounted(() => {
    if (root.value) {
        initSidebarIconStrokes(root.value);
    }
});
</script>

<template>
    <span
        ref="root"
        data-sidebar-nav-icon
        class="inline-flex shrink-0 text-sidebar-foreground/60 [&>svg]:stroke-[1.75]"
        :class="size === 'compact' ? '[&>svg]:size-3.5' : '[&>svg]:size-[1.05rem]'"
    >
        <component :is="icon" />
    </span>
</template>
