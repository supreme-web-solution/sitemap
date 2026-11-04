<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';
import { create as createProposal } from '@/routes/proposals';
import { edit as editLead } from '@/routes/leads';

const props = defineProps<{
    teamSlug: string;
    leadId: number;
    suggestions: { title: string; detail: string }[];
}>();

const hrefFor = (title: string): string | null => {
    switch (title) {
        case 'Build a preview website':
            return `${editLead({ current_team: props.teamSlug, lead: props.leadId }).url}#website-audit-card`;
        case 'Send the preview':
            return `${editLead({ current_team: props.teamSlug, lead: props.leadId }).url}#lead-outreach-card`;
        case 'Send a proposal':
            return createProposal(props.teamSlug, {
                query: { lead: props.leadId },
            }).url;
        case 'Issue an invoice or deposit':
            return editLead({
                current_team: props.teamSlug,
                lead: props.leadId,
            }).url;
        default:
            return null;
    }
};

const items = computed(() =>
    props.suggestions.map((item) => ({
        ...item,
        href: hrefFor(item.title),
    })),
);
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Next steps</h2>
        <p class="mt-1 text-xs text-muted-foreground">
            Based on this lead’s stage and what is already on file.
        </p>
        <ul class="mt-3 grid gap-3">
            <li v-for="item in items" :key="item.title">
                <component
                    :is="item.href ? Link : 'div'"
                    :href="item.href ?? undefined"
                    class="block rounded-lg border border-transparent px-2 py-1.5 transition"
                    :class="
                        item.href
                            ? 'hover:border-primary/30 hover:bg-accent/40'
                            : ''
                    "
                >
                    <p class="flex items-center gap-1 text-sm font-medium">
                        {{ item.title }}
                        <ArrowRight
                            v-if="item.href"
                            class="size-3.5 text-muted-foreground"
                            aria-hidden="true"
                        />
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ item.detail }}
                    </p>
                </component>
            </li>
        </ul>
    </section>
</template>
