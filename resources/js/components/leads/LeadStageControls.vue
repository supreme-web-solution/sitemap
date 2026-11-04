<script setup lang="ts">
import { CalendarClock } from '@lucide/vue';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { leadStatusClasses, leadStatuses } from '@/lib/leadStatus';
import type { Lead } from '@/types';

defineProps<{
    lead: Lead;
    followUpLabel: string | null;
    followUpTone: 'upcoming' | 'due' | null;
}>();

const emit = defineEmits<{
    'update:status': [status: string];
    'update:dealValue': [value: string | null];
}>();
</script>

<template>
    <div class="flex min-w-0 flex-col gap-2">
        <Select
            :model-value="lead.status"
            @update:model-value="(status) => emit('update:status', String(status))"
        >
            <SelectTrigger
                size="sm"
                :class="[
                    'h-8 w-full max-w-[9.5rem] min-w-0 border-0',
                    leadStatusClasses[lead.status],
                ]"
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem
                    v-for="status in leadStatuses"
                    :key="status.value"
                    :value="status.value"
                >
                    {{ status.label }}
                </SelectItem>
            </SelectContent>
        </Select>
        <span
            v-if="followUpLabel"
            :class="[
                'inline-flex w-fit max-w-full items-center gap-1 truncate rounded-full px-2 py-0.5 text-xs font-medium',
                followUpTone === 'upcoming'
                    ? 'bg-muted text-muted-foreground'
                    : 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
            ]"
        >
            <CalendarClock class="size-3 shrink-0" />
            <span class="truncate">{{ followUpLabel }}</span>
        </span>
        <Input
            v-if="lead.status === 'won'"
            type="number"
            min="0"
            placeholder="Deal ($)"
            class="h-8 w-full max-w-[9.5rem]"
            :default-value="lead.dealValue ?? ''"
            @change="
                (event: Event) =>
                    emit(
                        'update:dealValue',
                        (event.target as HTMLInputElement).value || null,
                    )
            "
        />
    </div>
</template>
