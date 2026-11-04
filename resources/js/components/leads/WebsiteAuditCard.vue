<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Copy, ExternalLink, LoaderCircle, ScanSearch } from '@lucide/vue';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import CheckList from '@/components/CheckList.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { store as storeAudit } from '@/routes/leads/audit';
import type { LeadProfile } from '@/types';

const props = withDefaults(
    defineProps<{
        lead: LeadProfile;
        previewRefreshKey?: number;
    }>(),
    { previewRefreshKey: 0 },
);

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const isAuditing = computed(() => props.lead.websiteAuditStatus === 'queued');

const auditSucceeded = computed(
    () =>
        props.lead.websiteAudit !== null &&
        props.lead.websiteAudit.error === null,
);

const auditReportUrl = computed(() =>
    auditSucceeded.value ? props.lead.auditPublicUrl : null,
);

const generatedPreview = computed(() =>
    props.lead.site
        ? `${props.lead.site.previewUrl}?internal=1&v=${props.previewRefreshKey}`
        : null,
);

const runAudit = () => {
    router.post(
        storeAudit.url({ current_team: teamSlug.value, lead: props.lead.id }),
        {},
        { preserveScroll: true, only: ['lead'] },
    );
};

const copyAuditReport = async () => {
    const url = auditReportUrl.value;

    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Audit report link copied');
    } catch {
        toast.error('Could not copy the link');
    }
};

const failedChecks = computed(
    () =>
        props.lead.websiteAudit?.checks.filter(
            (check) => check.status !== 'pass',
        ) ?? [],
);
</script>

<template>
    <section id="website-audit-card" class="panel p-5" data-test="audit-panel">
        <h2 class="text-base font-semibold">Website audit</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Score their live site for outreach and share a report link with the
            owner.
        </p>

        <p v-if="!lead.website" class="mt-4 text-sm text-muted-foreground">
            This business has no website listed, which is the strongest
            opportunity. Build them one and share the preview.
        </p>

        <template v-else>
            <a
                :href="lead.website"
                target="_blank"
                rel="noopener noreferrer"
                class="mt-4 flex w-full items-center gap-2 rounded-lg border bg-muted/20 px-3 py-2 text-sm transition-colors hover:bg-muted/40"
            >
                <ExternalLink class="size-4 shrink-0 text-muted-foreground" />
                <span class="min-w-0 truncate font-medium">{{
                    lead.website
                }}</span>
            </a>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <Button
                    size="sm"
                    :disabled="isAuditing"
                    data-test="run-audit"
                    @click="runAudit"
                >
                    <LoaderCircle v-if="isAuditing" class="animate-spin" />
                    <ScanSearch v-else />
                    {{ lead.websiteAudit ? 'Run again' : 'Audit website' }}
                </Button>
                <Button
                    v-if="auditReportUrl"
                    type="button"
                    size="sm"
                    variant="secondary"
                    data-test="copy-audit-report"
                    @click="copyAuditReport"
                >
                    <Copy class="size-3.5" />
                    Copy report link
                </Button>
            </div>

            <p
                v-if="lead.websiteAuditedAt"
                class="mt-2 text-xs text-muted-foreground"
            >
                Last checked {{ formatRelativeTime(lead.websiteAuditedAt) }}
            </p>

            <p
                v-if="lead.websiteAuditStatus === 'failed'"
                class="mt-3 text-sm text-destructive"
            >
                The audit could not run. Please try again.
            </p>

            <div v-if="lead.websiteAudit" class="mt-4">
                <p
                    v-if="lead.websiteAudit.error"
                    class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"
                >
                    {{ lead.websiteAudit.error }}
                </p>
                <p
                    v-else-if="!auditReportUrl"
                    class="text-sm text-muted-foreground"
                >
                    A shareable report link appears after a successful audit.
                </p>
                <template v-else>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-semibold tabular-nums">{{
                            lead.websiteAudit.score
                        }}</span>
                        <span class="text-sm text-muted-foreground"
                            >/ 100 · {{ failedChecks.length }} to improve</span
                        >
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">
                        Mention these gaps in outreach, then build your preview
                        in the workflow above.
                    </p>
                    <CheckList class="mt-4" :items="lead.websiteAudit.checks" />
                </template>
            </div>

            <p
                v-else-if="!isAuditing"
                class="mt-4 text-sm text-muted-foreground"
            >
                No audit yet. Run one to see a score and checklist.
            </p>

            <div
                v-if="generatedPreview"
                class="mt-6 border-t pt-5"
                data-test="old-vs-new"
            >
                <h3 class="text-sm font-semibold">Compare sites</h3>
                <p class="mt-1 text-xs text-muted-foreground">
                    Their live site next to your generated preview.
                </p>
                <div class="mt-4 grid gap-4">
                    <div class="grid gap-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            Their site
                        </p>
                        <iframe
                            :src="lead.website ?? undefined"
                            title="Current website"
                            sandbox=""
                            class="h-52 w-full rounded-lg border bg-white"
                        />
                    </div>
                    <div class="grid gap-2">
                        <p class="text-xs font-medium text-muted-foreground">
                            Generated preview
                        </p>
                        <iframe
                            :src="generatedPreview"
                            title="Generated website"
                            class="h-52 w-full rounded-lg border bg-white"
                        />
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>
