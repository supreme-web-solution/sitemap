<script setup lang="ts">
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

const props = defineProps<{
    text: string;
    class?: string;
}>();
</script>

<template>
    <Tooltip v-if="text">
        <TooltipTrigger as-child>
            <span
                :class="cn('block min-w-0 truncate', props.class)"
                tabindex="0"
            >
                {{ text }}
            </span>
        </TooltipTrigger>
        <TooltipContent side="top" class="max-w-sm break-words">
            {{ text }}
        </TooltipContent>
    </Tooltip>
</template>
