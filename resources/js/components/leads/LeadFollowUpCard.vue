<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CalendarClock } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dateFromToday } from '@/lib/dates';
import { formatRelativeTime } from '@/lib/format';
import { leadStatuses } from '@/lib/leadStatus';
import { update as updateLead } from '@/routes/leads';
import type { LeadProfile } from '@/types';

const props = defineProps<{
    lead: LeadProfile;
    members: { id: number; name: string }[];
    lostReasons: { value: string; label: string }[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const save = (data: Record<string, string | number | null>) => {
    router.patch(
        updateLead.url({ current_team: teamSlug.value, lead: props.lead.id }),
        data,
        { preserveScroll: true, only: ['lead', 'activities'] },
    );
};

const quickDates = [
    { label: 'Tomorrow', days: 1 },
    { label: 'In 3 days', days: 3 },
    { label: 'Next week', days: 7 },
];
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Stage and follow-up</h2>

        <div class="mt-4 grid gap-4">
            <div class="grid gap-2">
                <Label>Stage</Label>
                <Select
                    :model-value="lead.status"
                    @update:model-value="
                        (status) =>
                            save(
                                String(status) === 'lost'
                                    ? {
                                          status: 'lost',
                                          lost_reason: lead.lostReason ?? 'other',
                                      }
                                    : { status: String(status) },
                            )
                    "
                >
                    <SelectTrigger class="w-full">
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
            </div>

            <div v-if="lead.status === 'lost'" class="grid gap-2">
                <Label>Lost reason</Label>
                <Select
                    :model-value="lead.lostReason ?? ''"
                    @update:model-value="
                        (reason) =>
                            save({
                                status: 'lost',
                                lost_reason: String(reason),
                            })
                    "
                >
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Why was it lost?" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="reason in lostReasons"
                            :key="reason.value"
                            :value="reason.value"
                        >
                            {{ reason.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div class="grid gap-2">
                <Label>Assigned to</Label>
                <Select
                    :model-value="lead.assignedUserId ? String(lead.assignedUserId) : 'none'"
                    @update:model-value="
                        (value) =>
                            save({
                                assigned_user_id:
                                    value === 'none' ? null : String(value),
                            })
                    "
                >
                    <SelectTrigger class="w-full">
                        <SelectValue placeholder="Unassigned" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="none">Unassigned</SelectItem>
                        <SelectItem
                            v-for="member in members"
                            :key="member.id"
                            :value="String(member.id)"
                        >
                            {{ member.name }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div v-if="lead.status === 'won'" class="grid gap-2">
                <Label for="deal-value">Deal value ($)</Label>
                <Input
                    id="deal-value"
                    type="number"
                    min="0"
                    :default-value="lead.dealValue ?? ''"
                    @change="
                        (event: Event) =>
                            save({
                                deal_value:
                                    (event.target as HTMLInputElement).value ||
                                    null,
                            })
                    "
                />
            </div>

            <div class="grid gap-2">
                <Label for="follow-up" class="flex items-center gap-1.5">
                    <CalendarClock class="size-4" /> Next follow-up
                </Label>
                <Input
                    id="follow-up"
                    type="date"
                    :model-value="lead.nextFollowUpAt ?? ''"
                    @change="
                        (event: Event) =>
                            save({
                                next_follow_up_at:
                                    (event.target as HTMLInputElement).value ||
                                    null,
                            })
                    "
                />
                <div class="flex flex-wrap gap-1.5">
                    <Button
                        v-for="quick in quickDates"
                        :key="quick.days"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="h-8 rounded-full px-3 text-xs"
                        @click="
                            save({
                                next_follow_up_at: dateFromToday(quick.days),
                            })
                        "
                    >
                        {{ quick.label }}
                    </Button>
                    <Button
                        v-if="lead.nextFollowUpAt"
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="h-8 rounded-full px-3 text-xs text-muted-foreground"
                        @click="save({ next_follow_up_at: null })"
                    >
                        Clear
                    </Button>
                </div>
                <p
                    v-if="lead.lastContactedAt"
                    class="text-xs text-muted-foreground"
                >
                    Last contacted
                    {{ formatRelativeTime(lead.lastContactedAt) }}.
                </p>
            </div>
        </div>
    </section>
</template>
