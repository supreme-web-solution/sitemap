<script setup lang="ts">
import { Head, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Download,
    ExternalLink,
    EyeOff,
    History,
    LoaderCircle,
    Monitor,
    Plus,
    RefreshCw,
    Smartphone,
    Star,
    Eye,
    Trash2,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CheckList from '@/components/CheckList.vue';
import InputError from '@/components/InputError.vue';
import SiteChatPanel from '@/components/sites/SiteChatPanel.vue';
import SiteHealthPanel from '@/components/sites/SiteHealthPanel.vue';
import SitePreviewAccessPanel from '@/components/sites/SitePreviewAccessPanel.vue';
import SitePostsPanel from '@/components/SitePostsPanel.vue';
import SitePublishPanel from '@/components/SitePublishPanel.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as regenerateSite } from '@/routes/leads/site';
import {
    edit,
    exportMethod as exportSite,
    index as sitesIndex,
    update,
} from '@/routes/sites';
import { formatRelativeTime, slugify } from '@/lib/format';
import {
    store as storeReviews,
    update as updateReview,
} from '@/routes/leads/reviews';
import { restore as restoreVersion } from '@/routes/sites/versions';
import type {
    EditableSite,
    Option,
    QualityReport,
    SiteChat,
    SiteHealth,
    SitePost,
    SitePublishing,
    SiteReview,
    SiteReviewsPanel,
    SiteVersion,
    Team,
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
    chat: SiteChat;
    health: SiteHealth;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null; site: EditableSite }) => ({
        breadcrumbs: props.currentTeam
            ? [
                  {
                      title: 'Websites',
                      href: sitesIndex({
                          current_team: props.currentTeam.slug,
                      }),
                  },
                  {
                      title: props.site.name,
                      href: edit({
                          current_team: props.currentTeam.slug,
                          site: props.site.id,
                      }),
                  },
              ]
            : [],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const formDefaults = () => ({
    template: props.site.template,
    ...props.site.content,
    reviews_style: props.site.content.reviews_style ?? 'carousel',
    pages:
        props.site.content.pages ??
        props.pageOptions.map((option) => option.value),
    services: props.site.content.services.map((service) => ({
        ...service,
        slug: service.slug ?? slugify(service.title),
        body: service.body ?? '',
    })),
});

const form = useForm(formDefaults());

const previewVersion = ref(0);
const previewMode = ref<'desktop' | 'mobile'>('desktop');

const previewSrc = computed(
    () => `${props.site.previewUrl}?internal=1&v=${previewVersion.value}`,
);

const save = () => {
    form.put(
        update.url({ current_team: teamSlug.value, site: props.site.id }),
        {
            preserveScroll: true,
            onSuccess: () => previewVersion.value++,
        },
    );
};

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

    router.post(
        restoreVersion.url({
            current_team: teamSlug.value,
            site: props.site.id,
            version: version.id,
        }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                form.defaults(formDefaults());
                form.reset();
                previewVersion.value++;
            },
        },
    );
};

const isRegenerating = computed(() => props.site.generationStatus === 'queued');
const isFetchingReviews = computed(() => props.reviews.status === 'queued');

const isEditingWithAi = computed(() => props.chat.busy);

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

watch(isEditingWithAi, (editing, wasEditing) => {
    if (!editing && wasEditing) {
        form.defaults(formDefaults());
        form.reset();
        previewVersion.value++;
    }
});

watch(isRegenerating, (regenerating, wasRegenerating) => {
    if (!regenerating && wasRegenerating) {
        form.defaults(formDefaults());
        form.reset();
        previewVersion.value++;
    }
});

watch(isFetchingReviews, (fetching, wasFetching) => {
    if (!fetching && wasFetching) {
        previewVersion.value++;
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
            onSuccess: () => previewVersion.value++,
        },
    );
};

const palette = computed(() => props.palettes[form.template] ?? []);

const sectionLabel = (value: string) =>
    props.sectionOptions.find((section) => section.value === value)?.label ??
    value;

const hiddenSections = computed(() =>
    props.sectionOptions.filter(
        (section) => !form.sections.includes(section.value),
    ),
);

const moveSection = (index: number, offset: -1 | 1) => {
    const target = index + offset;

    if (target < 0 || target >= form.sections.length) {
        return;
    }

    [form.sections[index], form.sections[target]] = [
        form.sections[target],
        form.sections[index],
    ];
};

const addService = () =>
    form.services.push({
        title: 'New service',
        description: '',
        slug: '',
        body: '',
    });

const fillServiceSlug = (service: { title: string; slug?: string }) => {
    if (!service.slug) {
        service.slug = slugify(service.title);
    }
};

const togglePage = (page: string, enabled: boolean) => {
    form.pages = enabled
        ? [...form.pages, page]
        : form.pages.filter((value) => value !== page);
};

const addFaq = () => form.faqs.push({ question: '', answer: '' });

const { copy } = useClipboard();

const copyLink = async () => {
    await copy(props.site.previewUrl);
    toast.success('Preview link copied');
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head :title="`Edit ${site.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <div
            class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h1 class="text-xl font-semibold tracking-tight">
                    {{ site.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ site.viewsCount }} preview
                    {{ site.viewsCount === 1 ? 'view' : 'views' }} ·
                    <a
                        :href="site.previewUrl"
                        target="_blank"
                        rel="noopener"
                        class="underline"
                        >{{ site.previewUrl }}</a
                    >
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="copyLink">
                    <Copy /> Copy link
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="site.previewUrl" target="_blank" rel="noopener">
                        <ExternalLink /> Open preview
                    </a>
                </Button>
                <Button variant="outline" size="sm" as-child>
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
                <Button
                    variant="ghost"
                    size="sm"
                    :disabled="isRegenerating"
                    @click="regenerate"
                >
                    <LoaderCircle v-if="isRegenerating" class="animate-spin" />
                    <RefreshCw v-else />
                    {{ isRegenerating ? 'Regenerating…' : 'Regenerate' }}
                </Button>
            </div>
        </div>

        <div class="grid flex-1 gap-4 lg:grid-cols-[420px_1fr]">
            <form
                class="flex flex-col gap-5 panel p-4 lg:max-h-[calc(100vh-11rem)] lg:overflow-y-auto"
                @submit.prevent="save"
            >
                <div class="grid grid-cols-[1fr_auto] gap-3">
                    <div class="grid gap-2">
                        <Label>Template</Label>
                        <Select v-model="form.template">
                            <SelectTrigger class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="template in templates"
                                    :key="template.value"
                                    :value="template.value"
                                >
                                    {{ template.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="accent">Brand colour</Label>
                        <input
                            id="accent"
                            v-model="form.accent"
                            type="color"
                            class="h-9 w-16 cursor-pointer rounded-md border bg-transparent p-1"
                        />
                    </div>
                </div>
                <div v-if="palette.length" class="flex flex-wrap gap-2">
                    <button
                        v-for="colour in palette"
                        :key="colour"
                        type="button"
                        :title="colour"
                        :aria-label="`Use colour ${colour}`"
                        :class="[
                            'size-7 rounded-full border-2 transition',
                            form.accent.toLowerCase() === colour
                                ? 'border-foreground'
                                : 'border-transparent',
                        ]"
                        :style="{ backgroundColor: colour }"
                        @click="form.accent = colour"
                    />
                </div>
                <InputError :message="form.errors.accent" />

                <div class="grid gap-2" data-test="pages-setting">
                    <Label>Pages in the menu</Label>
                    <p class="text-xs text-muted-foreground">
                        Home and Contact are always on. A page is left out
                        automatically until it has something to show.
                    </p>
                    <label
                        v-for="option in pageOptions"
                        :key="option.value"
                        class="flex items-center gap-2 text-sm"
                    >
                        <Checkbox
                            :model-value="form.pages.includes(option.value)"
                            @update:model-value="
                                togglePage(option.value, $event === true)
                            "
                        />
                        {{ option.label }}
                    </label>
                    <InputError :message="form.errors.pages" />
                </div>

                <div class="grid gap-2">
                    <Label>Home page sections</Label>
                    <ol class="grid gap-1.5">
                        <li
                            v-for="(section, index) in form.sections"
                            :key="section"
                            class="flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm"
                        >
                            <span class="flex-1">{{
                                sectionLabel(section)
                            }}</span>
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                aria-label="Move up"
                                :disabled="index === 0"
                                @click="moveSection(index, -1)"
                            >
                                <ArrowUp />
                            </Button>
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                aria-label="Move down"
                                :disabled="index === form.sections.length - 1"
                                @click="moveSection(index, 1)"
                            >
                                <ArrowDown />
                            </Button>
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                aria-label="Hide section"
                                :disabled="form.sections.length <= 1"
                                @click="form.sections.splice(index, 1)"
                            >
                                <EyeOff />
                            </Button>
                        </li>
                    </ol>
                    <div
                        v-if="hiddenSections.length"
                        class="flex flex-wrap gap-1.5"
                    >
                        <Button
                            v-for="section in hiddenSections"
                            :key="section.value"
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="form.sections.push(section.value)"
                        >
                            <Plus /> {{ section.label }}
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Sections without content (e.g. a gallery with no photos)
                        are left out automatically.
                    </p>
                    <InputError :message="form.errors.sections" />
                </div>

                <div
                    v-if="form.sections.includes('reviews')"
                    class="grid gap-2"
                >
                    <Label>Reviews style</Label>
                    <Select v-model="form.reviews_style">
                        <SelectTrigger class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="style in reviewsStyles"
                                :key="style.value"
                                :value="style.value"
                            >
                                {{ style.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Shown when at least {{ reviews.minimum }} reviews are
                        visible. The ticker needs 5.
                    </p>
                    <InputError :message="form.errors.reviews_style" />
                </div>

                <div class="grid gap-2">
                    <Label for="headline">Headline</Label>
                    <Input id="headline" v-model="form.headline" />
                    <InputError :message="form.errors.headline" />
                </div>

                <div class="grid gap-2">
                    <Label for="tagline">Tagline</Label>
                    <textarea
                        id="tagline"
                        v-model="form.tagline"
                        rows="2"
                        :class="textareaClass"
                    />
                    <InputError :message="form.errors.tagline" />
                </div>

                <div class="grid gap-2">
                    <Label for="cta_label">Button text</Label>
                    <Input id="cta_label" v-model="form.cta_label" />
                    <InputError :message="form.errors.cta_label" />
                </div>

                <div class="grid gap-2">
                    <Label for="about">About</Label>
                    <textarea
                        id="about"
                        v-model="form.about"
                        rows="5"
                        :class="textareaClass"
                    />
                    <InputError :message="form.errors.about" />
                </div>

                <div class="grid gap-3">
                    <div class="flex items-center justify-between">
                        <Label>Services</Label>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            :disabled="form.services.length >= 8"
                            @click="addService"
                        >
                            <Plus /> Add
                        </Button>
                    </div>
                    <div
                        v-for="(service, index) in form.services"
                        :key="index"
                        class="grid gap-2 rounded-lg border p-3"
                    >
                        <div class="flex gap-2">
                            <Input
                                v-model="service.title"
                                placeholder="Service name"
                                @blur="fillServiceSlug(service)"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                :disabled="form.services.length <= 1"
                                @click="form.services.splice(index, 1)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <textarea
                            v-model="service.description"
                            rows="2"
                            placeholder="Short description"
                            :class="textareaClass"
                        />
                        <div class="flex items-center gap-1 text-xs">
                            <span class="shrink-0 text-muted-foreground"
                                >/services/</span
                            >
                            <Input
                                v-model="service.slug"
                                class="h-8 text-xs"
                                placeholder="page-address"
                                :aria-label="`Page address for ${service.title}`"
                            />
                        </div>
                        <textarea
                            v-model="service.body"
                            rows="4"
                            placeholder="Details for this service's own page (what is included, who it is for, how it works). Unique text helps it rank on Google."
                            :class="textareaClass"
                        />
                        <InputError
                            :message="
                                form.errors[`services.${index}.title`] ??
                                form.errors[`services.${index}.description`] ??
                                form.errors[`services.${index}.slug`] ??
                                form.errors[`services.${index}.body`]
                            "
                        />
                    </div>
                </div>

                <div class="grid gap-3">
                    <div class="flex items-center justify-between">
                        <Label>FAQs</Label>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            :disabled="form.faqs.length >= 10"
                            @click="addFaq"
                        >
                            <Plus /> Add
                        </Button>
                    </div>
                    <div
                        v-for="(faq, index) in form.faqs"
                        :key="index"
                        class="grid gap-2 rounded-lg border p-3"
                    >
                        <div class="flex gap-2">
                            <Input
                                v-model="faq.question"
                                placeholder="Question"
                            />
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                @click="form.faqs.splice(index, 1)"
                            >
                                <Trash2 />
                            </Button>
                        </div>
                        <textarea
                            v-model="faq.answer"
                            rows="2"
                            placeholder="Answer"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="
                                form.errors[`faqs.${index}.question`] ??
                                form.errors[`faqs.${index}.answer`]
                            "
                        />
                    </div>
                </div>

                <div
                    class="sticky bottom-0 -mx-4 -mb-4 border-t bg-background p-4"
                >
                    <Button
                        type="submit"
                        class="w-full"
                        :disabled="form.processing || !form.isDirty"
                    >
                        <LoaderCircle
                            v-if="form.processing"
                            class="animate-spin"
                        />
                        Save changes
                    </Button>
                </div>
            </form>

            <div class="flex flex-col gap-2">
                <div class="flex justify-end gap-1">
                    <Button
                        size="icon-sm"
                        :variant="
                            previewMode === 'desktop' ? 'secondary' : 'ghost'
                        "
                        aria-label="Desktop preview"
                        @click="previewMode = 'desktop'"
                    >
                        <Monitor />
                    </Button>
                    <Button
                        size="icon-sm"
                        :variant="
                            previewMode === 'mobile' ? 'secondary' : 'ghost'
                        "
                        aria-label="Mobile preview"
                        @click="previewMode = 'mobile'"
                    >
                        <Smartphone />
                    </Button>
                </div>
                <div
                    class="flex flex-1 justify-center overflow-hidden rounded-xl border bg-muted/40"
                >
                    <iframe
                        :key="previewSrc"
                        :src="previewSrc"
                        title="Website preview"
                        :class="[
                            'h-full min-h-[70vh] bg-white transition-all',
                            previewMode === 'mobile' ? 'w-[390px]' : 'w-full',
                        ]"
                    />
                </div>
            </div>
        </div>

        <section class="panel p-5" data-test="reviews-panel">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold">Google reviews</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Real reviews from Google appear on the website as
                        testimonials, credited to Google. Hide any you do not
                        want shown.
                        <template v-if="reviews.totalOnGoogle > 0">
                            This business has {{ reviews.totalOnGoogle }} on
                            Google.
                        </template>
                    </p>
                </div>
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
                {{ visibleReviewCount }} of {{ reviews.items.length }} shown on
                the site<template v-if="visibleReviewCount < reviews.minimum">
                    · needs at least {{ reviews.minimum }} to appear</template
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
                        <p class="flex items-center gap-1 text-sm font-medium">
                            <span class="truncate">{{ review.author }}</span>
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

        <SiteChatPanel :site-id="site.id" :chat="chat" />

        <SitePreviewAccessPanel :site="site" :team-slug="teamSlug" />

        <SiteHealthPanel
            :site-id="site.id"
            :team-slug="teamSlug"
            :health="health"
            :transferred-at="site.transferredAt"
            :transferred-to-email="site.transferredToEmail"
        />

        <SitePublishPanel
            :site-id="site.id"
            :team-slug="teamSlug"
            :publishing="publishing"
        />

        <SitePostsPanel
            :site-id="site.id"
            :team-slug="teamSlug"
            :posts="posts"
            @saved="previewVersion++"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="panel p-5" data-test="quality-report">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="text-base font-semibold">Quality check</h2>
                    <span
                        :class="[
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            quality.passed === quality.total
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300'
                                : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300',
                        ]"
                    >
                        {{ quality.passed }}/{{ quality.total }} passed
                    </span>
                </div>
                <p class="mt-1 text-sm text-muted-foreground">
                    Check the site before sending it to a client.
                </p>
                <CheckList :items="quality.checks" class="mt-4" />
            </section>

            <section class="panel p-5" data-test="version-history">
                <h2 class="text-base font-semibold">Version history</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Every save is kept. Restoring a version saves the current
                    one first, so nothing is lost.
                </p>
                <ul v-if="versions.length" class="mt-4 flex flex-col divide-y">
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
                    No earlier versions yet. They appear after your first edit.
                </p>
            </section>
        </div>
    </div>
</template>
