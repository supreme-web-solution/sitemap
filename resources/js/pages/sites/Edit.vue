<script setup lang="ts">
import { Head, Link, router, usePage, usePoll } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Copy,
    Download,
    ExternalLink,
    Eye,
    EyeOff,
    History,
    LoaderCircle,
    RefreshCw,
    Rocket,
    Settings,
    Star,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CheckList from '@/components/CheckList.vue';
import InputError from '@/components/InputError.vue';
import EditorSettingsDialog from '@/components/sites/editor/EditorSettingsDialog.vue';
import type { SettingsTab } from '@/components/sites/editor/EditorSettingsDialog.vue';
import SiteEditorWorkspace from '@/components/sites/editor/SiteEditorWorkspace.vue';
import SiteChatPanel from '@/components/sites/SiteChatPanel.vue';
import SiteHealthPanel from '@/components/sites/SiteHealthPanel.vue';
import SitePreviewAccessPanel from '@/components/sites/SitePreviewAccessPanel.vue';
import SitePostsPanel from '@/components/SitePostsPanel.vue';
import SitePublishPanel from '@/components/SitePublishPanel.vue';
import { Button } from '@/components/ui/button';
import { formatRelativeTime } from '@/lib/format';
import { store as storeBrand } from '@/routes/leads/brand';
import {
    store as storeReviews,
    update as updateReview,
} from '@/routes/leads/reviews';
import { store as regenerateSite } from '@/routes/leads/site';
import { exportMethod as exportSite, index as sitesIndex } from '@/routes/sites';
import { store as storeChat } from '@/routes/sites/chat';
import { restore as restoreVersion } from '@/routes/sites/versions';
import type {
    EditableSite,
    Option,
    QualityReport,
    SiteChat,
    SiteCopyField,
    SiteDesignChoices,
    SiteHealth,
    SitePost,
    SitePublishing,
    SiteReview,
    SiteReviewsPanel,
    SiteVersion,
} from '@/types';

const props = defineProps<{
    site: EditableSite;
    quality: QualityReport;
    pageOptions: Option[];
    posts: SitePost[];
    publishing: SitePublishing;
    versions: SiteVersion[];
    reviews: SiteReviewsPanel;
    reviewsStyles: Option[];
    templates: Option[];
    palettes: Record<string, string[]>;
    sectionOptions: Option[];
    designChoices: SiteDesignChoices;
    copyFields: SiteCopyField[];
    chat: SiteChat;
    health: SiteHealth;
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const workspace = ref<InstanceType<typeof SiteEditorWorkspace> | null>(null);
const liveQuality = ref<QualityReport>(props.quality);
const pullingBrand = ref(false);
const settingsOpen = ref(false);
const settingsTab = ref<SettingsTab>('general');

watch(
    () => props.quality,
    (quality) => (liveQuality.value = quality),
);

const openSettings = (tab: SettingsTab) => {
    settingsTab.value = tab;
    settingsOpen.value = true;
};

const failedChecks = computed(
    () => liveQuality.value.total - liveQuality.value.passed,
);

const settingsBadges = computed<Partial<Record<SettingsTab, string | number>>>(
    () => (failedChecks.value > 0 ? { quality: failedChecks.value } : {}),
);

const reloadCanvas = () => workspace.value?.reloadCanvas();

const regenerate = () => {
    if (
        !confirm(
            'Regenerate this website? Your text edits will be replaced with fresh content.',
        )
    ) {
        return;
    }

    router.post(
        regenerateSite.url({
            current_team: teamSlug.value,
            lead: props.site.leadId,
        }),
        {},
        { preserveScroll: true, only: ['site'] },
    );
};

const restore = (version: SiteVersion) => {
    if (
        !confirm(
            'Restore this version? Your current edits are saved to history first.',
        )
    ) {
        return;
    }

    void workspace.value?.flush();

    router.post(
        restoreVersion.url({
            current_team: teamSlug.value,
            site: props.site.id,
            version: version.id,
        }),
        {},
        { preserveScroll: true },
    );
};

const isRegenerating = computed(() => props.site.generationStatus === 'queued');
const isFetchingReviews = computed(() => props.reviews.status === 'queued');
const isEditingWithAi = computed(() => props.chat.busy);

const pullBrand = () => {
    if (!props.site.leadWebsite) {
        return;
    }

    pullingBrand.value = true;
    void workspace.value?.flush();
    router.post(
        storeBrand.url({
            current_team: teamSlug.value,
            lead: props.site.leadId,
        }),
        {},
        {
            preserveScroll: true,
            only: ['site'],
            onFinish: () => {
                pullingBrand.value = false;
            },
        },
    );
};

const improve = (payload: {
    field?: string;
    section?: string;
    body?: string;
}) => {
    void workspace.value?.flush();
    router.post(
        storeChat.url({ current_team: teamSlug.value, site: props.site.id }),
        payload,
        { preserveScroll: true },
    );
};

const fixWithAi = (key: string) => {
    settingsOpen.value = false;
    workspace.value?.showAiTab();
    void workspace.value?.flush();
    router.post(
        storeChat.url({ current_team: teamSlug.value, site: props.site.id }),
        { fix: key },
        { preserveScroll: true },
    );
};

const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['site', 'reviews', 'quality', 'chat', 'versions'] },
    { autoStart: false },
);

watch(
    () =>
        isRegenerating.value || isFetchingReviews.value || isEditingWithAi.value,
    (busy) => (busy ? startPolling() : stopPolling()),
    { immediate: true },
);

watch(isFetchingReviews, (fetching, wasFetching) => {
    if (!fetching && wasFetching) {
        reloadCanvas();
    }
});

const reviewsRequestError = computed(
    () => (page.props.errors as Record<string, string | undefined>).reviews,
);

const visibleReviewCount = computed(
    () => props.reviews.items.filter((review) => review.isVisible).length,
);

const fetchReviews = () => {
    router.post(
        storeReviews.url({
            current_team: teamSlug.value,
            lead: props.site.leadId,
        }),
        {},
        { preserveScroll: true, only: ['reviews', 'errors'] },
    );
};

const toggleReview = (review: SiteReview) => {
    router.patch(
        updateReview.url({
            current_team: teamSlug.value,
            lead: props.site.leadId,
            review: review.id,
        }),
        { is_visible: !review.isVisible },
        {
            preserveScroll: true,
            only: ['reviews', 'quality'],
            onSuccess: reloadCanvas,
        },
    );
};

const { copy } = useClipboard();

const copyLink = async () => {
    await copy(props.site.previewUrl);
    toast.success('Preview link copied');
};
</script>

<template>
    <Head :title="`Edit ${site.name}`" />

    <div class="flex h-full min-h-0 flex-1 flex-col">
        <SiteEditorWorkspace
            ref="workspace"
            :site="site"
            :quality="quality"
            :team-slug="teamSlug"
            :page-options="pageOptions"
            :templates="templates"
            :palettes="palettes"
            :section-options="sectionOptions"
            :reviews-styles="reviewsStyles"
            :design-choices="designChoices"
            :copy-fields="copyFields"
            :ai-available="chat.available"
            :ai-busy="chat.busy"
            :pulling-brand="pullingBrand"
            @quality="liveQuality = $event"
            @improve="improve"
            @pull-brand="pullBrand"
        >
            <template #start>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    as-child
                    aria-label="Back to websites"
                    title="Back to websites"
                >
                    <Link
                        :href="sitesIndex({ current_team: teamSlug })"
                        data-test="editor-back"
                    >
                        <ArrowLeft />
                    </Link>
                </Button>
                <div class="min-w-0">
                    <h1
                        class="max-w-[14rem] truncate text-sm leading-tight font-semibold"
                    >
                        {{ site.name }}
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        {{ site.viewsCount }}
                        {{ site.viewsCount === 1 ? 'view' : 'views' }}
                        <template v-if="isRegenerating">
                            · Regenerating…
                        </template>
                    </p>
                </div>
            </template>

            <template #end>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    aria-label="Copy preview link"
                    title="Copy preview link"
                    @click="copyLink"
                >
                    <Copy />
                </Button>
                <Button
                    variant="ghost"
                    size="icon-sm"
                    as-child
                    aria-label="Open preview"
                    title="Open preview in a new tab"
                >
                    <a :href="site.previewUrl" target="_blank" rel="noopener">
                        <ExternalLink />
                    </a>
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="relative"
                    data-test="editor-settings-open"
                    @click="openSettings('general')"
                >
                    <Settings /> Settings
                    <span
                        v-if="failedChecks > 0"
                        class="absolute -top-1 -right-1 size-2.5 rounded-full bg-amber-500 ring-2 ring-card"
                        :title="`${failedChecks} quality checks need attention`"
                    />
                </Button>
                <Button
                    size="sm"
                    data-test="editor-publish-open"
                    @click="openSettings('publish')"
                >
                    <Rocket />
                    {{ publishing.isPublished ? 'Published' : 'Publish' }}
                </Button>
            </template>

            <template #ai>
                <SiteChatPanel :site-id="site.id" :chat="chat" embedded />
            </template>
        </SiteEditorWorkspace>

        <EditorSettingsDialog
            v-model:open="settingsOpen"
            v-model:tab="settingsTab"
            :badges="settingsBadges"
        >
            <template #general>
                <section class="panel p-5" data-test="general-settings">
                    <h3 class="text-base font-semibold">Preview link</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ site.viewsCount }}
                        {{ site.viewsCount === 1 ? 'view' : 'views' }} so far.
                        <template v-if="site.updatedAt">
                            Last edited
                            {{ formatRelativeTime(site.updatedAt) }}.
                        </template>
                    </p>
                    <a
                        :href="site.previewUrl"
                        target="_blank"
                        rel="noopener"
                        class="mt-2 block truncate text-sm underline"
                        >{{ site.previewUrl }}</a
                    >
                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button variant="outline" size="sm" @click="copyLink">
                            <Copy /> Copy link
                        </Button>
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="site.previewUrl"
                                target="_blank"
                                rel="noopener"
                            >
                                <ExternalLink /> Open preview
                            </a>
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="openSettings('access')"
                        >
                            Expiry &amp; password
                        </Button>
                    </div>
                </section>

                <section class="panel p-5">
                    <h3 class="text-base font-semibold">Export</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Download the website as static HTML, one folder per
                        page, with sitemap and robots.
                    </p>
                    <Button variant="outline" size="sm" class="mt-3" as-child>
                        <a
                            :href="
                                exportSite.url({
                                    current_team: teamSlug,
                                    site: site.id,
                                })
                            "
                        >
                            <Download /> Export ZIP
                        </a>
                    </Button>
                </section>

                <section class="panel p-5">
                    <h3 class="text-base font-semibold">Regenerate</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Rebuild the website from the business details. Your
                        text edits will be replaced with fresh content, and
                        the current version is kept in history.
                    </p>
                    <Button
                        variant="outline"
                        size="sm"
                        class="mt-3"
                        :disabled="isRegenerating"
                        @click="regenerate"
                    >
                        <LoaderCircle
                            v-if="isRegenerating"
                            class="animate-spin"
                        />
                        <RefreshCw v-else />
                        {{ isRegenerating ? 'Regenerating…' : 'Regenerate' }}
                    </Button>
                </section>
            </template>

            <template #publish>
                <SitePublishPanel
                    :site-id="site.id"
                    :team-slug="teamSlug"
                    :publishing="publishing"
                />
            </template>

            <template #access>
                <SitePreviewAccessPanel :site="site" :team-slug="teamSlug" />
            </template>

            <template #reviews>
                <section class="panel p-5" data-test="reviews-panel">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <p class="max-w-xl text-sm text-muted-foreground">
                            Real reviews from Google appear on the website as
                            testimonials, credited to Google. Hide any you do
                            not want shown.
                            <template v-if="reviews.totalOnGoogle > 0">
                                This business has
                                {{ reviews.totalOnGoogle }} on Google.
                            </template>
                        </p>
                        <Button
                            v-if="reviews.canFetch"
                            type="button"
                            size="sm"
                            variant="outline"
                            :disabled="isFetchingReviews"
                            data-test="fetch-reviews"
                            @click="fetchReviews"
                        >
                            <LoaderCircle
                                v-if="isFetchingReviews"
                                class="animate-spin"
                            />
                            <RefreshCw v-else />
                            {{
                                isFetchingReviews
                                    ? 'Fetching…'
                                    : reviews.fetchedAt
                                      ? 'Refresh reviews'
                                      : 'Fetch reviews'
                            }}
                        </Button>
                    </div>

                    <p
                        v-if="reviews.status === 'failed' && reviews.error"
                        class="mt-3 rounded-xl bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-300"
                    >
                        {{ reviews.error }}
                    </p>
                    <InputError class="mt-2" :message="reviewsRequestError" />

                    <p
                        v-if="reviews.items.length"
                        class="mt-3 text-xs text-muted-foreground"
                    >
                        {{ visibleReviewCount }} of
                        {{ reviews.items.length }} shown on the site<template
                            v-if="visibleReviewCount < reviews.minimum"
                        >
                            · needs at least
                            {{ reviews.minimum }} to appear</template
                        >.
                    </p>

                    <ul
                        v-if="reviews.items.length"
                        class="mt-3 grid gap-3 md:grid-cols-2"
                    >
                        <li
                            v-for="review in reviews.items"
                            :key="review.id"
                            :class="[
                                'flex items-start gap-3 rounded-xl border p-3 transition',
                                review.isVisible ? '' : 'opacity-50',
                            ]"
                        >
                            <div class="min-w-0 flex-1">
                                <p
                                    class="flex items-center gap-1 text-sm font-medium"
                                >
                                    <span class="truncate">{{
                                        review.author
                                    }}</span>
                                    <span class="flex shrink-0 text-amber-400">
                                        <Star
                                            v-for="n in review.rating"
                                            :key="n"
                                            class="size-3.5 fill-current"
                                        />
                                    </span>
                                </p>
                                <p
                                    class="mt-1 line-clamp-3 text-sm text-muted-foreground"
                                >
                                    {{ review.body }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                :aria-label="
                                    review.isVisible
                                        ? `Hide review by ${review.author}`
                                        : `Show review by ${review.author}`
                                "
                                @click="toggleReview(review)"
                            >
                                <Eye v-if="review.isVisible" />
                                <EyeOff v-else />
                            </Button>
                        </li>
                    </ul>
                    <p
                        v-else-if="reviews.status !== 'queued'"
                        class="mt-4 text-sm text-muted-foreground"
                    >
                        No reviews collected yet.
                        <template v-if="reviews.canFetch"
                            >Fetch them to add a testimonials section to the
                            site.</template
                        >
                    </p>
                </section>
            </template>

            <template #quality>
                <section class="panel p-5" data-test="quality-report">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-base font-semibold">Quality check</h3>
                        <span
                            :class="[
                                'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                liveQuality.passed === liveQuality.total
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                            ]"
                        >
                            {{ liveQuality.passed }}/{{ liveQuality.total }}
                            passed
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Check the site before sending it to a client.
                    </p>
                    <CheckList
                        :items="liveQuality.checks"
                        class="mt-4"
                        :on-fix="chat.available ? fixWithAi : undefined"
                        :busy="chat.busy"
                    />
                </section>

                <SiteHealthPanel
                    :site-id="site.id"
                    :team-slug="teamSlug"
                    :health="health"
                    :transferred-at="site.transferredAt"
                    :transferred-to-email="site.transferredToEmail"
                    :can-fix="chat.available"
                    :fixing="chat.busy"
                    @fix="fixWithAi"
                />
            </template>

            <template #posts>
                <SitePostsPanel
                    :site-id="site.id"
                    :team-slug="teamSlug"
                    :posts="posts"
                    @saved="reloadCanvas"
                />
            </template>

            <template #history>
                <section class="panel p-5" data-test="version-history">
                    <p class="text-sm text-muted-foreground">
                        Restoring a version saves the current one first, so
                        nothing is lost.
                    </p>
                    <ul
                        v-if="versions.length"
                        class="mt-4 flex flex-col divide-y"
                    >
                        <li
                            v-for="version in versions"
                            :key="version.id"
                            class="flex items-center justify-between gap-3 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ version.label ?? version.headline }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatRelativeTime(version.createdAt) }}
                                    <template v-if="version.author">
                                        · {{ version.author }}</template
                                    >
                                    · {{ version.template }}
                                </p>
                            </div>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="restore(version)"
                            >
                                <History /> Restore
                            </Button>
                        </li>
                    </ul>
                    <p v-else class="mt-4 text-sm text-muted-foreground">
                        No earlier versions yet. They appear after your first
                        edit.
                    </p>
                </section>
            </template>
        </EditorSettingsDialog>
    </div>
</template>
