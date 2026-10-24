<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, X } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { index as emailTemplatesIndex } from '@/routes/email-templates';
import { index as leadsIndex } from '@/routes/leads';
import { index as proposalsIndex } from '@/routes/proposals';
import { index as sequencesIndex } from '@/routes/sequences';

defineProps<{
    teamSlug: string;
    dismissible?: boolean;
}>();

defineEmits<{ close: [] }>();

const steps = (slug: string) =>
    [
        {
            label: '1. Preview ready',
            hint: 'Open a lead, share the site link',
            href: leadsIndex(
                { current_team: slug },
                { query: { status: 'site_generated' } },
            ).url,
        },
        {
            label: '2. Send outreach',
            hint: 'Email with merge fields',
            href: emailTemplatesIndex({ current_team: slug }).url,
        },
        {
            label: '3. Follow up',
            hint: 'Day 1, 3, 6, 10 emails',
            href: sequencesIndex({ current_team: slug }).url,
        },
        {
            label: '4. Close & bill',
            hint: 'Proposal then invoice',
            href: proposalsIndex({ current_team: slug }).url,
        },
    ] as const;
</script>

<template>
    <aside
        class="panel relative flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between"
        :class="dismissible ? 'pr-10' : ''"
        data-test="sell-flow-guide"
        aria-label="How to sell a website"
    >
        <div class="min-w-0 sm:max-w-sm">
            <p class="text-sm font-medium">Your sales path</p>
            <p class="mt-0.5 text-xs text-muted-foreground">
                Work on the lead profile (redesign steps + outreach). Drag
                cards on this board as stages change. Email templates,
                sequences, and packages are one-time setup in Sell.
            </p>
        </div>
        <ol class="flex flex-wrap items-stretch gap-2 text-xs sm:justify-end">
            <li
                v-for="(step, index) in steps(teamSlug)"
                :key="step.label"
                class="flex items-center gap-2"
            >
                <Link
                    :href="step.href"
                    class="rounded-lg border bg-card px-2.5 py-2 transition hover:border-primary/40 hover:bg-accent/50"
                    :title="step.hint"
                >
                    <span class="font-medium">{{ step.label }}</span>
                    <span
                        class="mt-0.5 block text-[11px] text-muted-foreground"
                        >{{ step.hint }}</span
                    >
                </Link>
                <ArrowRight
                    v-if="index < steps(teamSlug).length - 1"
                    class="hidden size-3.5 shrink-0 text-muted-foreground/50 sm:block"
                    aria-hidden="true"
                />
            </li>
        </ol>
        <Button
            v-if="dismissible"
            variant="ghost"
            size="icon-sm"
            class="absolute top-2 right-2 size-7"
            aria-label="Hide sales path"
            title="Hide (bring it back with “Sales path”)"
            data-test="sell-guide-close"
            @click="$emit('close')"
        >
            <X />
        </Button>
    </aside>
</template>
