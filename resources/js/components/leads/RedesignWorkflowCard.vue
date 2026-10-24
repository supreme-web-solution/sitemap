<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    Check,
    Circle,
    Copy,
    ExternalLink,
    LoaderCircle,
    ScanSearch,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { edit as editSite } from '@/routes/sites';
import type { LeadProfile } from '@/types';

const props = defineProps<{
    lead: LeadProfile;
    teamSlug: string;
    isBuilding: boolean;
    generating: boolean;
}>();

const emit = defineEmits<{
    generate: [];
    'scroll-to': [target: 'audit' | 'outreach'];
}>();

const hasWebsite = computed(() => Boolean(props.lead.website));

const auditDone = computed(
    () =>
        Boolean(props.lead.websiteAudit) &&
        !props.lead.websiteAudit?.error &&
        props.lead.websiteAuditStatus !== 'failed',
);

const siteReady = computed(() => Boolean(props.lead.site) && !props.isBuilding);

const previewSent = computed(
    () =>
        (props.lead.site?.viewsCount ?? 0) > 0 ||
        ['contacted', 'interested', 'won'].includes(props.lead.status),
);

const steps = computed(() => {
    const list = [];

    if (hasWebsite.value) {
        list.push({
            key: 'audit',
            label: 'Audit their website',
            hint: auditDone.value
                ? 'Issues identified — use them in your pitch.'
                : 'See mobile, SEO and UX gaps.',
            done: auditDone.value,
            active: !auditDone.value,
        });
    }

    list.push({
        key: 'build',
        label: props.lead.site ? 'Rebuild your concept site' : 'Generate your concept site',
        hint: siteReady.value
            ? 'Preview is ready to share.'
            : 'Queue AI generation from this profile.',
        done: siteReady.value,
        active: hasWebsite.value ? auditDone.value && !siteReady.value : !siteReady.value,
    });

    if (hasWebsite.value && siteReady.value) {
        list.push({
            key: 'compare',
            label: 'Compare old vs new',
            hint: 'Side-by-side in the audit section below.',
            done: true,
            active: false,
        });
    }

    list.push({
        key: 'share',
        label: 'Send the preview',
        hint: previewSent.value
            ? 'Prospect has opened or you already reached out.'
            : 'Copy the link or send outreach email.',
        done: previewSent.value,
        active: siteReady.value && !previewSent.value,
    });

    return list;
});

const copyPreview = async () => {
    const url = props.lead.site?.previewUrl;

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Preview link copied');
    } catch {
        toast.error('Could not copy the link');
    }
};
</script>

<template>
    <section class="panel p-5" data-test="redesign-workflow">
        <h2 class="text-base font-semibold">Redesign workflow</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            {{
                hasWebsite
                    ? 'Win redesign deals: audit their site, generate yours, share the preview.'
                    : 'No website on file — generate a concept site and share the preview.'
            }}
        </p>

        <ol class="mt-4 grid gap-3">
            <li
                v-for="(step, index) in steps"
                :key="step.key"
                class="flex gap-3 rounded-xl border px-3 py-2.5"
                :class="
                    step.active
                        ? 'border-primary/40 bg-primary/5'
                        : 'border-border/80'
                "
            >
                <span
                    class="mt-0.5 grid size-6 shrink-0 place-items-center rounded-full text-xs font-semibold"
                    :class="
                        step.done
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                            : step.active
                              ? 'bg-primary text-primary-foreground'
                              : 'bg-muted text-muted-foreground'
                    "
                >
                    <Check v-if="step.done" class="size-3.5" />
                    <Circle v-else class="size-3.5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium">
                        {{ index + 1 }}. {{ step.label }}
                    </p>
                    <p class="text-xs text-muted-foreground">{{ step.hint }}</p>
                    <div
                        v-if="step.active || (step.key === 'share' && siteReady)"
                        class="mt-2 flex flex-wrap gap-2"
                    >
                        <Button
                            v-if="step.key === 'audit'"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="emit('scroll-to', 'audit')"
                        >
                            <ScanSearch class="size-3.5" />
                            Go to audit
                        </Button>
                        <Button
                            v-if="step.key === 'build'"
                            type="button"
                            size="sm"
                            :disabled="generating || isBuilding"
                            data-test="workflow-generate"
                            @click="emit('generate')"
                        >
                            <LoaderCircle
                                v-if="generating || isBuilding"
                                class="animate-spin"
                            />
                            <Sparkles v-else class="size-3.5" />
                            {{
                                lead.site
                                    ? 'Save & rebuild website'
                                    : 'Save & build website'
                            }}
                        </Button>
                        <Button
                            v-if="
                                step.key === 'compare' &&
                                siteReady &&
                                hasWebsite
                            "
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="emit('scroll-to', 'audit')"
                        >
                            View comparison
                        </Button>
                        <template v-if="step.key === 'share' && siteReady">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="copyPreview"
                            >
                                <Copy class="size-3.5" />
                                Copy preview link
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="emit('scroll-to', 'outreach')"
                            >
                                Open outreach
                            </Button>
                            <Button
                                v-if="lead.site"
                                size="sm"
                                variant="ghost"
                                as-child
                            >
                                <a
                                    :href="lead.site.previewUrl"
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <ExternalLink class="size-3.5" />
                                    Preview
                                </a>
                            </Button>
                            <Button
                                v-if="lead.site"
                                size="sm"
                                variant="ghost"
                                as-child
                            >
                                <Link
                                    :href="
                                        editSite({
                                            current_team: teamSlug,
                                            site: lead.site.id,
                                        })
                                    "
                                >
                                    Edit site
                                </Link>
                            </Button>
                        </template>
                    </div>
                </div>
            </li>
        </ol>
    </section>
</template>
