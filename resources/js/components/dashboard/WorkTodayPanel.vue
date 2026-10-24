<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    AlarmClock,
    Copy,
    Flame,
    Send,
    ListTodo,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { leadStatusLabel } from '@/lib/leadStatus';
import { edit as editLead } from '@/routes/leads';
import type { DashboardWorkToday, DashboardWorkTodayItem } from '@/types';

const props = defineProps<{
    workToday: DashboardWorkToday;
    teamSlug: string;
}>();

const iconFor = (item: DashboardWorkTodayItem) => {
    switch (item.kind) {
        case 'hot_lead':
            return Flame;
        case 'send_preview':
            return Send;
        case 'follow_up':
            return AlarmClock;
        case 'task':
            return ListTodo;
    }
};

const toneFor = (item: DashboardWorkTodayItem): string => {
    switch (item.kind) {
        case 'hot_lead':
            return 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300';
        case 'send_preview':
            return 'bg-violet-100 text-violet-700 dark:bg-violet-500/15 dark:text-violet-300';
        case 'follow_up':
            return item.isOverdue
                ? 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300'
                : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300';
        case 'task':
            return 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300';
    }
};

const copyPreview = async (url: string) => {
    try {
        await navigator.clipboard.writeText(url);
        toast.success('Preview link copied');
    } catch {
        toast.error('Could not copy the link');
    }
};

const leadUrl = (leadId: number) =>
    editLead({ current_team: props.teamSlug, lead: leadId }).url;
</script>

<template>
    <section
        v-if="workToday.count > 0"
        class="panel p-5"
        data-test="work-today-panel"
    >
        <div class="flex items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold tracking-tight">Work today</h2>
                <p class="text-sm text-muted-foreground">
                    Hot leads, previews to send, and items due now.
                </p>
            </div>
            <span
                class="rounded-full bg-primary/10 px-2.5 py-0.5 text-xs font-semibold text-primary"
            >
                {{ workToday.count }}
            </span>
        </div>

        <ul class="mt-4 flex flex-col divide-y">
            <li
                v-for="(item, index) in workToday.items"
                :key="`${item.kind}-${item.leadId}-${index}`"
                class="flex items-start gap-3 py-3 first:pt-0 last:pb-0"
            >
                <span
                    class="grid size-9 shrink-0 place-items-center rounded-xl"
                    :class="toneFor(item)"
                >
                    <component :is="iconFor(item)" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <Link
                        :href="leadUrl(item.leadId)"
                        class="block truncate text-sm font-medium hover:text-primary"
                    >
                        {{ item.title }}
                    </Link>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ item.detail }}
                        <template v-if="item.score !== undefined">
                            · Score {{ item.score }}
                        </template>
                        <template v-else-if="item.status">
                            · {{ leadStatusLabel(item.status) }}
                        </template>
                    </p>
                </div>
                <Button
                    v-if="item.previewUrl"
                    type="button"
                    size="sm"
                    variant="outline"
                    class="shrink-0"
                    @click="copyPreview(item.previewUrl!)"
                >
                    <Copy class="size-3.5" />
                    Copy link
                </Button>
            </li>
        </ul>
    </section>
</template>
