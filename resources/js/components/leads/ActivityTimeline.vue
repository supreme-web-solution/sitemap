<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    CalendarClock,
    FileText,
    Globe,
    Mail,
    MessageCircleReply,
    MoveRight,
    PhoneCall,
    Receipt,
    ScanSearch,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { store as storeNote } from '@/routes/leads/notes';
import type { LeadActivity, LeadActivityType } from '@/types';

const props = defineProps<{
    leadId: number;
    activities: LeadActivity[];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const icons: Record<LeadActivityType, { icon: Component; tone: string }> = {
    created: {
        icon: Sparkles,
        tone: 'bg-pink-100 text-pink-600 dark:bg-pink-500/15 dark:text-pink-300',
    },
    status_changed: {
        icon: MoveRight,
        tone: 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
    },
    note: {
        icon: FileText,
        tone: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
    },
    site_generated: {
        icon: Globe,
        tone: 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
    },
    website_audited: {
        icon: ScanSearch,
        tone: 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
    },
    email_sent: {
        icon: Mail,
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    },
    marked_contacted: {
        icon: PhoneCall,
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    },
    follow_up_scheduled: {
        icon: CalendarClock,
        tone: 'bg-slate-100 text-slate-600 dark:bg-slate-500/15 dark:text-slate-300',
    },
    invoice_sent: {
        icon: Receipt,
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    },
    invoice_reminder: {
        icon: Receipt,
        tone: 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
    },
    invoice_paid: {
        icon: BadgeDollarSign,
        tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    preview_interest: {
        icon: MessageCircleReply,
        tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    proposal_sent: {
        icon: FileText,
        tone: 'bg-violet-100 text-violet-600 dark:bg-violet-500/15 dark:text-violet-300',
    },
    proposal_accepted: {
        icon: BadgeDollarSign,
        tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    proposal_declined: {
        icon: FileText,
        tone: 'bg-rose-100 text-rose-600 dark:bg-rose-500/15 dark:text-rose-300',
    },
    site_approved: {
        icon: Globe,
        tone: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-300',
    },
    client_feedback: {
        icon: MessageCircleReply,
        tone: 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-300',
    },
    message_sent: {
        icon: Mail,
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    },
    message_received: {
        icon: MessageCircleReply,
        tone: 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
    },
    site_edited: {
        icon: Sparkles,
        tone: 'bg-blue-100 text-blue-600 dark:bg-blue-500/15 dark:text-blue-300',
    },
};

const form = useForm({ note: '' });

const addNote = () => {
    form.post(
        storeNote.url({ current_team: teamSlug.value, lead: props.leadId }),
        {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        },
    );
};

const textareaClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Timeline</h2>

        <form class="mt-4 grid gap-2" @submit.prevent="addNote">
            <label for="note" class="sr-only">Add a note</label>
            <textarea
                id="note"
                v-model="form.note"
                rows="2"
                placeholder="Add a note, e.g. called, prefers mornings"
                :class="textareaClass"
            />
            <InputError :message="form.errors.note" />
            <Button
                type="submit"
                size="sm"
                variant="outline"
                class="w-fit"
                :disabled="form.processing || form.note.trim() === ''"
            >
                Add note
            </Button>
        </form>

        <ol v-if="activities.length" class="mt-5 flex flex-col gap-4">
            <li
                v-for="activity in activities"
                :key="activity.id"
                class="flex gap-3"
            >
                <span
                    :class="[
                        'grid size-8 shrink-0 place-items-center rounded-xl',
                        icons[activity.type].tone,
                    ]"
                >
                    <component :is="icons[activity.type].icon" class="size-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm break-words">{{ activity.summary }}</p>
                    <p
                        v-if="
                            activity.type === 'preview_interest' &&
                            activity.data
                        "
                        class="mt-1 rounded-lg bg-muted/60 px-2.5 py-1.5 text-xs text-muted-foreground"
                    >
                        <span
                            v-if="activity.data.contact"
                            class="block font-medium text-foreground"
                            >{{ activity.data.contact }}</span
                        >
                        <span v-if="activity.data.message" class="block">{{
                            activity.data.message
                        }}</span>
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        {{ activity.label }} ·
                        <template v-if="activity.author"
                            >{{ activity.author }} ·
                        </template>
                        {{ formatRelativeTime(activity.createdAt) }}
                    </p>
                </div>
            </li>
        </ol>
        <p v-else class="mt-5 text-sm text-muted-foreground">
            Nothing yet. Stage changes, emails, audits and replies from the
            preview appear here.
        </p>
    </section>
</template>
