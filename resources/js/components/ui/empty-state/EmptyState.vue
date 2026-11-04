<script setup lang="ts">
import type { Component, HTMLAttributes } from 'vue';
import { cn } from '@/lib/utils';

const props = withDefaults(
    defineProps<{
        title: string;
        description?: string;
        icon?: Component;
        variant?: 'panel' | 'plain';
        class?: HTMLAttributes['class'];
    }>(),
    {
        variant: 'panel',
    },
);
</script>

<template>
    <div
        data-slot="empty-state"
        :class="
            cn(
                'flex flex-col items-center justify-center text-center',
                variant === 'panel'
                    ? 'panel gap-4 border-dashed px-6 py-14'
                    : 'gap-3 py-10',
                props.class,
            )
        "
    >
        <div
            v-if="icon || $slots.icon"
            class="grid size-12 place-items-center rounded-full bg-primary/10 text-primary"
        >
            <slot name="icon">
                <component :is="icon" class="size-6" aria-hidden="true" />
            </slot>
        </div>

        <div class="flex max-w-md flex-col gap-1.5">
            <p class="text-base font-semibold">{{ title }}</p>
            <p
                v-if="description || $slots.description"
                class="text-sm text-muted-foreground"
            >
                <slot name="description">{{ description }}</slot>
            </p>
        </div>

        <div
            v-if="$slots.default"
            class="flex flex-wrap items-center justify-center gap-2 pt-1"
        >
            <slot />
        </div>
    </div>
</template>
