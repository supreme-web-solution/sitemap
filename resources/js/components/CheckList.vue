<script setup lang="ts">
import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    XCircle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';

type Status = 'pass' | 'warn' | 'fail' | 'verified' | 'inferred' | 'missing';

const props = defineProps<{
    items: { key: string; label: string; status: Status; detail: string }[];
    onFix?: (key: string) => void;
    fixableKeys?: string[];
    busy?: boolean;
}>();

const tones: Record<Status, { icon: Component; classes: string }> = {
    pass: { icon: CheckCircle2, classes: 'text-emerald-600' },
    verified: { icon: CheckCircle2, classes: 'text-emerald-600' },
    warn: { icon: AlertTriangle, classes: 'text-amber-500' },
    inferred: { icon: AlertTriangle, classes: 'text-amber-500' },
    fail: { icon: XCircle, classes: 'text-rose-500' },
    missing: { icon: CircleDashed, classes: 'text-muted-foreground' },
};

const canFix = (item: { key: string; status: Status }) => {
    if (!props.onFix) {
        return false;
    }

    if (item.status !== 'warn' && item.status !== 'fail') {
        return false;
    }

    return props.fixableKeys === undefined
        ? true
        : props.fixableKeys.includes(item.key);
};
</script>

<template>
    <ul class="flex flex-col gap-3">
        <li
            v-for="item in items"
            :key="item.key"
            class="flex items-start gap-3"
        >
            <component
                :is="tones[item.status].icon"
                :class="[
                    'mt-0.5 size-4.5 shrink-0',
                    tones[item.status].classes,
                ]"
            />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium">
                    {{ item.label }}
                    <span
                        v-if="
                            item.status === 'inferred' ||
                            item.status === 'missing'
                        "
                        class="ml-1 text-xs font-normal text-muted-foreground capitalize"
                        >{{ item.status }}</span
                    >
                </p>
                <p class="text-sm text-muted-foreground">{{ item.detail }}</p>
                <Button
                    v-if="canFix(item)"
                    type="button"
                    size="sm"
                    variant="outline"
                    class="mt-2"
                    :disabled="busy"
                    @click="onFix?.(item.key)"
                >
                    Fix with AI
                </Button>
            </div>
        </li>
    </ul>
</template>
