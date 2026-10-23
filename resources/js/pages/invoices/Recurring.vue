<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Pause, Pencil, Play, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import RecurringInvoiceForm from '@/components/invoices/RecurringInvoiceForm.vue';
import { Button } from '@/components/ui/button';
import { index as invoicesIndex } from '@/routes/invoices';
import {
    destroy,
    index as recurringIndex,
    toggle,
} from '@/routes/recurring-invoices';
import type { RecurringSchedule, Team } from '@/types';

defineProps<{
    schedules: RecurringSchedule[];
    leads: { id: number; name: string; email: string | null }[];
    intervals: { value: string; label: string }[];
    today: string;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Invoices',
                href: props.currentTeam
                    ? invoicesIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
            {
                title: 'Recurring billing',
                href: props.currentTeam
                    ? recurringIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const creating = ref(false);
const editingId = ref<number | null>(null);

const routeArgs = (schedule: RecurringSchedule) => ({
    current_team: teamSlug.value,
    recurringInvoice: schedule.id,
});

const pauseOrResume = (schedule: RecurringSchedule) =>
    router.post(toggle.url(routeArgs(schedule)), {}, { preserveScroll: true });

const remove = (schedule: RecurringSchedule) => {
    if (
        confirm(
            `Delete the schedule for ${schedule.leadName}? Invoices already issued are kept.`,
        )
    ) {
        router.delete(destroy.url(routeArgs(schedule)), {
            preserveScroll: true,
        });
    }
};

const statusClasses: Record<RecurringSchedule['status'], string> = {
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
    paused: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    ended: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
};
</script>

<template>
    <Head title="Recurring billing" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <Heading
                title="Recurring billing"
                description="Bill clients for hosting, maintenance or SEO on a schedule. Each cycle issues a new invoice with its own pay link and emails it to the client."
            />
            <Button size="sm" @click="creating = !creating">
                <Plus /> New schedule
            </Button>
        </div>

        <RecurringInvoiceForm
            v-if="creating"
            :team-slug="teamSlug"
            :leads="leads"
            :intervals="intervals"
            :today="today"
            @saved="creating = false"
        />

        <div
            v-for="schedule in schedules"
            :key="schedule.id"
            data-test="schedule"
        >
            <RecurringInvoiceForm
                v-if="editingId === schedule.id"
                :team-slug="teamSlug"
                :leads="leads"
                :intervals="intervals"
                :today="today"
                :schedule="schedule"
                @saved="editingId = null"
            />
            <article
                v-else
                class="flex flex-wrap items-center justify-between gap-4 panel p-5"
            >
                <div class="min-w-0">
                    <p class="flex items-center gap-2 font-medium">
                        {{ schedule.leadName }}
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                            :class="statusClasses[schedule.status]"
                            >{{ schedule.status }}</span
                        >
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ schedule.total }} ·
                        {{ schedule.intervalLabel.toLowerCase() }}
                        <template
                            v-if="
                                schedule.nextRunOn &&
                                schedule.status === 'active'
                            "
                        >
                            · next invoice {{ schedule.nextRunOn }}</template
                        >
                        <template v-if="schedule.endsOn">
                            · ends {{ schedule.endsOn }}</template
                        >
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ schedule.cyclesRun }} cycle{{
                            schedule.cyclesRun === 1 ? '' : 's'
                        }}
                        billed ·
                        <Link
                            :href="invoicesIndex(teamSlug)"
                            class="underline underline-offset-4"
                            >view invoices</Link
                        >
                    </p>
                </div>
                <div class="flex gap-1">
                    <Button
                        v-if="schedule.status !== 'ended'"
                        type="button"
                        size="icon-sm"
                        variant="ghost"
                        :aria-label="
                            schedule.status === 'active'
                                ? 'Pause schedule'
                                : 'Resume schedule'
                        "
                        @click="pauseOrResume(schedule)"
                    >
                        <Pause v-if="schedule.status === 'active'" />
                        <Play v-else />
                    </Button>
                    <Button
                        type="button"
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Edit schedule"
                        @click="editingId = schedule.id"
                    >
                        <Pencil />
                    </Button>
                    <Button
                        type="button"
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Delete schedule"
                        @click="remove(schedule)"
                    >
                        <Trash2 />
                    </Button>
                </div>
            </article>
        </div>

        <p
            v-if="!schedules.length && !creating"
            class="panel p-8 text-center text-sm text-muted-foreground"
        >
            No recurring billing yet. Add a schedule for a client on a hosting
            or maintenance plan.
        </p>
    </div>
</template>
