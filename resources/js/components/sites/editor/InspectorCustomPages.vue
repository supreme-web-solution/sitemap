<script setup lang="ts">
import { ExternalLink, MapPin, Megaphone, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CommitOptions } from '@/composables/useSiteEditor';
import {
    MAX_CUSTOM_PAGES,
    SLUG_PATTERN,
    customPagePath,
    customPages,
    newLandingPage,
    newLocationPage,
    pagePublicPath,
    slugify,
} from '@/lib/siteEditor/customPages';
import type { EditOp } from '@/lib/siteEditor/document';
import type { SiteCustomPage, SiteDocument } from '@/types';

const props = defineProps<{
    draft: SiteDocument;
    businessName: string;
    businessCity: string | null;
    businessCategory: string | null;
    previewUrl: string;
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
}>();

const expanded = ref<number | null>(0);

const pages = computed(() => customPages(props.draft));

const atLimit = computed(() => pages.value.length >= MAX_CUSTOM_PAGES);

const previewLink = (page: SiteCustomPage): string => {
    try {
        const url = new URL(props.previewUrl);
        const base = url.pathname.replace(/\/$/, '');

        url.pathname = `${base}${pagePublicPath(page)}`;

        return url.toString();
    } catch {
        return pagePublicPath(page);
    }
};

const setField = (
    index: number,
    field: keyof SiteCustomPage,
    value: string,
) => {
    let next = value;

    if (field === 'slug') {
        next = slugify(value);
    }

    emit(
        'commit',
        [{ op: 'set', path: customPagePath(index, field), value: next }],
        field === 'slug' || field === 'title' || field === 'headline'
            ? { immediate: true, reload: true }
            : { coalesceKey: `custom:${index}:${field}` },
    );
};

const addPage = (kind: SiteCustomPage['kind']) => {
    const existing = pages.value;
    const page =
        kind === 'landing'
            ? newLandingPage(
                  props.businessName,
                  props.businessCity,
                  props.businessCategory,
                  existing,
              )
            : newLocationPage(
                  props.businessName,
                  props.businessCity,
                  props.businessCategory,
                  existing,
              );

    emit(
        'commit',
        [
            {
                op: 'insert',
                path: 'custom_pages',
                index: existing.length,
                value: page,
            },
        ],
        { immediate: true, reload: true },
    );
    expanded.value = existing.length;
};

const removePage = (index: number) => {
    const page = pages.value[index];

    if (
        !page ||
        !confirm(`Remove “${page.title}”? The URL ${pagePublicPath(page)} will stop working.`)
    ) {
        return;
    }

    emit(
        'commit',
        [{ op: 'remove', path: 'custom_pages', index }],
        { immediate: true, reload: true },
    );
    expanded.value = null;
};

const kindLabel = (kind: SiteCustomPage['kind']) =>
    kind === 'landing' ? 'Landing page' : 'Location page';

const inputClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';

const textareaClass =
    'min-h-[88px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <div class="grid gap-3 border-t pt-5" data-test="custom-pages-panel">
        <div class="grid gap-1">
            <Label>Campaign &amp; location pages</Label>
            <p class="text-xs text-muted-foreground">
                Extra URLs for ads and local SEO. Each page gets its own address
                (for example <code class="text-[0.7rem]">/landing/spring-offer</code>).
            </p>
        </div>

        <p
            v-if="pages.length === 0"
            class="rounded-lg border border-dashed p-3 text-sm text-muted-foreground"
        >
            No custom pages yet. Add a landing page for a promotion or a
            location page for a service area.
        </p>

        <div v-else class="grid gap-2">
            <article
                v-for="(page, index) in pages"
                :key="`${page.kind}-${page.slug}-${index}`"
                class="rounded-lg border bg-muted/20"
                :data-test="`custom-page-${index}`"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm font-medium"
                    @click="expanded = expanded === index ? null : index"
                >
                    <Megaphone
                        v-if="page.kind === 'landing'"
                        class="size-4 shrink-0 text-muted-foreground"
                    />
                    <MapPin v-else class="size-4 shrink-0 text-muted-foreground" />
                    <span class="min-w-0 flex-1 truncate">{{ page.title }}</span>
                    <span class="text-xs font-normal text-muted-foreground">
                        {{ pagePublicPath(page) }}
                    </span>
                </button>

                <div v-show="expanded === index" class="grid gap-3 border-t px-3 py-3">
                    <p class="text-xs text-muted-foreground">
                        {{ kindLabel(page.kind) }}
                        ·
                        <a
                            :href="previewLink(page)"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-0.5 text-primary hover:underline"
                        >
                            Preview
                            <ExternalLink class="size-3" />
                        </a>
                    </p>

                    <div class="grid gap-2">
                        <Label :for="`cp-title-${index}`">Menu title</Label>
                        <Input
                            :id="`cp-title-${index}`"
                            :model-value="page.title"
                            maxlength="120"
                            @update:model-value="setField(index, 'title', String($event ?? ''))"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`cp-slug-${index}`">URL slug</Label>
                        <Input
                            :id="`cp-slug-${index}`"
                            :model-value="page.slug"
                            maxlength="60"
                            @update:model-value="setField(index, 'slug', String($event ?? ''))"
                        />
                        <p
                            v-if="page.slug && !SLUG_PATTERN.test(page.slug)"
                            class="text-xs text-destructive"
                        >
                            Use lowercase letters, numbers, and hyphens only.
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`cp-headline-${index}`">Headline</Label>
                        <Input
                            :id="`cp-headline-${index}`"
                            :model-value="page.headline"
                            maxlength="120"
                            @update:model-value="
                                setField(index, 'headline', String($event ?? ''))
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`cp-intro-${index}`">Intro (optional)</Label>
                        <Input
                            :id="`cp-intro-${index}`"
                            :model-value="page.intro ?? ''"
                            maxlength="250"
                            @update:model-value="setField(index, 'intro', String($event ?? ''))"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label :for="`cp-body-${index}`">Body</Label>
                        <textarea
                            :id="`cp-body-${index}`"
                            :class="textareaClass"
                            :value="page.body"
                            maxlength="8000"
                            rows="4"
                            @input="
                                setField(
                                    index,
                                    'body',
                                    ($event.target as HTMLTextAreaElement).value,
                                )
                            "
                        />
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label :for="`cp-cta-${index}`">Button label</Label>
                            <Input
                                :id="`cp-cta-${index}`"
                                :model-value="page.cta_label ?? ''"
                                maxlength="40"
                                @update:model-value="
                                    setField(index, 'cta_label', String($event ?? ''))
                                "
                            />
                        </div>
                        <div v-if="page.kind === 'location'" class="grid gap-2">
                            <Label :for="`cp-city-${index}`">City / area</Label>
                            <Input
                                :id="`cp-city-${index}`"
                                :model-value="page.city ?? ''"
                                maxlength="80"
                                @update:model-value="
                                    setField(index, 'city', String($event ?? ''))
                                "
                            />
                        </div>
                    </div>

                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="w-fit text-destructive hover:text-destructive"
                        @click="removePage(index)"
                    >
                        <Trash2 class="size-4" />
                        Remove page
                    </Button>
                </div>
            </article>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="atLimit"
                data-test="add-landing-page"
                @click="addPage('landing')"
            >
                <Plus class="size-4" />
                Landing page
            </Button>
            <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="atLimit"
                data-test="add-location-page"
                @click="addPage('location')"
            >
                <Plus class="size-4" />
                Location page
            </Button>
        </div>
        <p v-if="atLimit" class="text-xs text-muted-foreground">
            Maximum of {{ MAX_CUSTOM_PAGES }} custom pages per site.
        </p>
    </div>
</template>
