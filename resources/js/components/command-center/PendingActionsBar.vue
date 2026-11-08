<script setup lang="ts">
import { AlertCircle } from '@lucide/vue';
import { Button } from '@/components/ui/button';

export type PendingActionItem = {
    id: number;
    summary: string;
    action?: string;
    createdAt?: string | null;
};

defineProps<{
    items: PendingActionItem[];
    compact?: boolean;
    busy?: boolean;
}>();

defineEmits<{
    approve: [id: number];
    dismiss: [id: number];
}>();
</script>

<template>
    <div
        v-if="items.length"
        class="shrink-0 border-t border-amber-500/30 bg-amber-500/10"
        :class="compact ? 'px-2.5 py-2' : 'px-4 py-3'"
        data-test="command-center-pending-bar"
    >
        <div
            class="mb-2 flex items-center gap-1.5 font-semibold text-amber-950 dark:text-amber-100"
            :class="compact ? 'text-[11px]' : 'text-xs'"
        >
            <AlertCircle :class="compact ? 'size-3.5' : 'size-4'" />
            Needs your approval
        </div>
        <ul class="space-y-2">
            <li
                v-for="item in items"
                :key="item.id"
                class="rounded-lg border border-amber-500/25 bg-card shadow-sm"
                :class="compact ? 'p-2' : 'p-3'"
            >
                <p
                    class="font-medium text-foreground"
                    :class="compact ? 'text-[12px] leading-snug' : 'text-sm'"
                >
                    {{ item.summary }}
                </p>
                <div class="mt-2 flex gap-2">
                    <Button
                        size="sm"
                        class="h-8 flex-1"
                        :disabled="busy"
                        data-test="pending-approve"
                        @click="$emit('approve', item.id)"
                    >
                        Approve
                    </Button>
                    <Button
                        size="sm"
                        variant="ghost"
                        class="h-8"
                        :disabled="busy"
                        data-test="pending-dismiss"
                        @click="$emit('dismiss', item.id)"
                    >
                        Dismiss
                    </Button>
                </div>
            </li>
        </ul>
    </div>
</template>
