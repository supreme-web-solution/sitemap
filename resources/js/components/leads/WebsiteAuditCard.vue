<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, ScanSearch } from '@lucide/vue';
import { computed } from 'vue';
import CheckList from '@/components/CheckList.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { store as storeAudit } from '@/routes/leads/audit';
import type { LeadProfile } from '@/types';

const props = defineProps<{
    lead: LeadProfile;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const isAuditing = computed(() => props.lead.websiteAuditStatus === 'queued');

const runAudit = () => {
    router.post(
        storeAudit.url({ current_team: teamSlug.value, lead: props.lead.id }),
        {},
        { preserveScroll: true, only: ['lead'] },
    );
};

const failedChecks = computed(
    () =>
        props.lead.websiteAudit?.checks.filter(
            (check) => check.status !== 'pass',
        ) ?? [],
);
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Website audit</h2>

        <p v-if="!lead.website" class="mt-2 text-sm text-muted-foreground">
            This business has no website listed, which is the strongest
            opportunity. Build them one and share the preview.
        </p>

        <template v-else>
            <p class="mt-1 text-sm break-all text-muted-foreground">
                {{ lead.website }}
            </p>

            <div class="mt-4 flex items-center gap-3">
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="isAuditing"
                    data-test="run-audit"
                    @click="runAudit"
                >
                    <LoaderCircle v-if="isAuditing" class="animate-spin" />
                    <ScanSearch v-else />
                    {{ lead.websiteAudit ? 'Run again' : 'Audit website' }}
                </Button>
                <span
                    v-if="lead.websiteAuditedAt"
                    class="text-xs text-muted-foreground"
                >
                    Checked {{ formatRelativeTime(lead.websiteAuditedAt) }}
                </span>
            </div>

            <p
                v-if="lead.websiteAuditStatus === 'failed'"
                class="mt-3 text-sm text-destructive"
            >
                The audit could not run. Please try again.
            </p>

            <div v-if="lead.websiteAudit" class="mt-5">
                <p
                    v-if="lead.websiteAudit.error"
                    class="rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"
                >
                    {{ lead.websiteAudit.error }}
                </p>
                <template v-else>
                    <div class="flex items-baseline gap-2">
                        <span class="text-3xl font-semibold tabular-nums">{{
                            lead.websiteAudit.score
                        }}</span>
                        <span class="text-sm text-muted-foreground"
                            >/ 100, {{ failedChecks.length }} things to
                            improve</span
                        >
                    </div>
                    <CheckList class="mt-4" :items="lead.websiteAudit.checks" />
                </template>
            </div>
        </template>
    </section>
</template>
