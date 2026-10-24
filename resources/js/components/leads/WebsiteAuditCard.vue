<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Palette, ScanSearch } from '@lucide/vue';
import { computed, ref } from 'vue';
import CheckList from '@/components/CheckList.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { store as storeAudit } from '@/routes/leads/audit';
import { store as storeBrand } from '@/routes/leads/brand';
import type { LeadProfile } from '@/types';

const props = defineProps<{
    lead: LeadProfile;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const compareVersion = ref(0);
const pullingBrand = ref(false);

const isAuditing = computed(() => props.lead.websiteAuditStatus === 'queued');

const generatedPreview = computed(() =>
    props.lead.site
        ? `${props.lead.site.previewUrl}?internal=1&v=${compareVersion.value}`
        : null,
);

const runAudit = () => {
    router.post(
        storeAudit.url({ current_team: teamSlug.value, lead: props.lead.id }),
        {},
        { preserveScroll: true, only: ['lead'] },
    );
};

const pullBrand = () => {
    pullingBrand.value = true;
    router.post(
        storeBrand.url({ current_team: teamSlug.value, lead: props.lead.id }),
        {},
        {
            preserveScroll: true,
            only: ['lead'],
            onFinish: () => {
                pullingBrand.value = false;
                compareVersion.value++;
            },
        },
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
    <section id="website-audit-card" class="panel p-5">
        <h2 class="text-base font-semibold">Website audit</h2>

        <p v-if="!lead.website" class="mt-2 text-sm text-muted-foreground">
            This business has no website listed, which is the strongest
            opportunity. Build them one and share the preview.
        </p>

        <template v-else>
            <p class="mt-1 text-sm break-all text-muted-foreground">
                {{ lead.website }}
            </p>

            <div class="mt-4 flex flex-wrap items-center gap-3">
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
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="pullingBrand"
                    data-test="pull-brand"
                    @click="pullBrand"
                >
                    <LoaderCircle v-if="pullingBrand" class="animate-spin" />
                    <Palette v-else />
                    Pull brand
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

            <div
                v-if="generatedPreview"
                class="mt-5 grid gap-3 md:grid-cols-2"
                data-test="old-vs-new"
            >
                <div class="grid gap-2">
                    <p class="text-sm font-medium">Their site</p>
                    <iframe
                        :src="lead.website ?? undefined"
                        title="Current website"
                        sandbox=""
                        class="h-64 w-full rounded-xl border bg-white"
                    />
                </div>
                <div class="grid gap-2">
                    <p class="text-sm font-medium">Generated preview</p>
                    <iframe
                        :src="generatedPreview"
                        title="Generated website"
                        class="h-64 w-full rounded-xl border bg-white"
                    />
                </div>
            </div>
        </template>
    </section>
</template>
