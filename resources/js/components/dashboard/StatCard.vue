<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    value: string | number;
    caption?: string;
    icon: Component;
}>();
</script>

<template>
    <div class="flex flex-col gap-4 panel p-5">
        <div class="flex items-center justify-between gap-2">
            <span class="text-sm font-medium text-muted-foreground">{{
                title
            }}</span>
            <span
                class="grid size-8 place-items-center rounded-xl bg-accent text-primary"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <div>
            <div class="text-3xl font-semibold tracking-tight tabular-nums">
                {{ value }}
            </div>
            <p v-if="caption" class="mt-1 text-xs text-muted-foreground">
                {{ caption }}
            </p>
        </div>
        <div class="mt-auto flex min-h-14 items-end">
            <div class="w-full">
                <slot />
            </div>
        </div>
    </div>
</template>
