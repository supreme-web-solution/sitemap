<script setup lang="ts">
import { LoaderCircle, Palette } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CommitOptions } from '@/composables/useSiteEditor';
import type { EditOp } from '@/lib/siteEditor/document';
import type { FrameSection } from '@/lib/siteEditor/protocol';
import type { Option, SiteDesignChoices, SiteDocument } from '@/types';

const props = defineProps<{
    draft: SiteDocument;
    templates: Option[];
    palettes: Record<string, string[]>;
    reviewsStyles: Option[];
    choices: SiteDesignChoices;
    sectionVariantChoices: Record<string, Option[]>;
    frameSections: FrameSection[];
    focusedSection: string | null;
    canPullBrand: boolean;
    pullingBrand: boolean;
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
    'pull-brand': [];
    locate: [section: string];
}>();

const DEFAULT = 'default';

const catalogLabels: Record<string, string> = {
    hero: 'Hero',
    services: 'Services',
    about: 'About',
    process: 'How it works',
    why: 'Why choose us',
    gallery: 'Gallery',
    areas: 'Areas we serve',
    faq: 'FAQ',
    hours: 'Hours & map',
    contact: 'Contact',
    header: 'Navigation',
    footer: 'Footer',
    about_page: 'About story',
    mission: 'Our mission',
    services_page: 'Services list',
};

const palette = computed(() => props.palettes[props.draft.template] ?? []);

const setTemplate = (value: unknown) =>
    emit('commit', [{ op: 'set', path: 'template', value: String(value) }], { reload: true, immediate: true });

const setAccent = (value: string) =>
    emit('commit', [{ op: 'set', path: 'accent', value }], { coalesceKey: 'accent', reload: true });

const setDesign = (token: 'font' | 'radius' | 'spacing', value: unknown) =>
    emit(
        'commit',
        [{ op: 'set', path: `design.${token}`, value: value === DEFAULT ? null : String(value) }],
        { reload: true, immediate: true },
    );

const setReviewsStyle = (value: unknown) =>
    emit('commit', [{ op: 'set', path: 'reviews_style', value: String(value) }], { reload: true, immediate: true });

const setVariant = (section: string, value: unknown) =>
    emit(
        'commit',
        [{ op: 'set', path: `variants.${section}`, value: String(value) }],
        { reload: true, immediate: true },
    );

const layoutTitle = (key: string, label?: string) =>
    `${label || catalogLabels[key] || key.replaceAll('_', ' ')} layout`;

const layoutSections = computed(() => {
    const choices = props.sectionVariantChoices;
    const seen = new Set<string>();
    const fromFrame = props.frameSections.flatMap((section) => {
        if (seen.has(section.key) || (choices[section.key] ?? []).length === 0) {
            return [];
        }

        seen.add(section.key);

        return [{ key: section.key, label: layoutTitle(section.key, section.label) }];
    });

    if (fromFrame.length > 0) {
        return fromFrame;
    }

    return ['hero', 'footer'].flatMap((key) => {
        if ((choices[key] ?? []).length === 0) {
            return [];
        }

        return [{ key, label: layoutTitle(key) }];
    });
});

const focusedLayout = computed(() => {
    const key = props.focusedSection;

    if (!key) {
        return layoutSections.value[0] ?? null;
    }

    return layoutSections.value.find((section) => section.key === key) ?? layoutSections.value[0] ?? null;
});

const otherLayoutSections = computed(() =>
    layoutSections.value.filter((section) => section.key !== focusedLayout.value?.key),
);

const variantValue = (section: string) =>
    props.draft.variants?.[section] ?? props.sectionVariantChoices[section]?.[0]?.value;

const tokens: { token: 'font' | 'radius' | 'spacing'; label: string; options: Option[] }[] = [
    { token: 'font', label: 'Fonts', options: props.choices.font },
    { token: 'radius', label: 'Corners', options: props.choices.radius },
    { token: 'spacing', label: 'Spacing', options: props.choices.spacing },
];
</script>

<template>
    <div class="grid gap-5" data-test="inspector-design">
        <div class="grid grid-cols-[1fr_auto] gap-3">
            <div class="grid gap-2">
                <Label>Template</Label>
                <Select :model-value="draft.template" @update:model-value="setTemplate">
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
                    :value="draft.accent"
                    type="color"
                    class="h-9 w-16 cursor-pointer rounded-md border bg-transparent p-1"
                    @input="setAccent(($event.target as HTMLInputElement).value)"
                />
            </div>
        </div>

        <Button
            v-if="canPullBrand"
            type="button"
            size="sm"
            variant="outline"
            class="w-fit"
            :disabled="pullingBrand"
            data-test="pull-brand"
            @click="$emit('pull-brand')"
        >
            <LoaderCircle v-if="pullingBrand" class="animate-spin" />
            <Palette v-else />
            Pull brand from their site
        </Button>

        <div v-if="palette.length" class="flex flex-wrap gap-2">
            <button
                v-for="colour in palette"
                :key="colour"
                type="button"
                :title="colour"
                :aria-label="`Use colour ${colour}`"
                :class="[
                    'size-7 rounded-full border-2 transition',
                    draft.accent.toLowerCase() === colour ? 'border-foreground' : 'border-transparent',
                ]"
                :style="{ backgroundColor: colour }"
                @click="setAccent(colour)"
            />
        </div>

        <div v-for="item in tokens" :key="item.token" class="grid gap-2">
            <Label>{{ item.label }}</Label>
            <Select
                :model-value="draft.design?.[item.token] ?? DEFAULT"
                @update:model-value="setDesign(item.token, $event)"
            >
                <SelectTrigger class="w-full">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem :value="DEFAULT">Template default</SelectItem>
                    <SelectItem
                        v-for="option in item.options"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <div v-if="layoutSections.length" class="grid gap-3">
            <p class="text-xs text-muted-foreground">
                Scroll the preview or click a section, then swap its layout here.
            </p>

            <div
                v-if="focusedLayout"
                class="grid gap-2 rounded-lg border border-primary/25 bg-primary/5 p-3"
                data-test="focused-section-layout"
            >
                <button
                    type="button"
                    class="text-left text-sm font-medium hover:underline"
                    @click="$emit('locate', focusedLayout.key)"
                >
                    {{ focusedLayout.label }}
                </button>
                <p class="text-xs text-muted-foreground">
                    This section is in view. Pick another style — it only changes this block.
                </p>
                <Select
                    :model-value="variantValue(focusedLayout.key)"
                    @update:model-value="setVariant(focusedLayout.key, $event)"
                >
                    <SelectTrigger class="w-full" data-test="focused-section-layout-select">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in sectionVariantChoices[focusedLayout.key] ?? []"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div
                v-for="section in otherLayoutSections"
                :key="section.key"
                class="grid gap-2"
            >
                <button
                    type="button"
                    class="text-left text-sm font-medium hover:underline"
                    @click="$emit('locate', section.key)"
                >
                    {{ section.label }}
                </button>
                <Select
                    :model-value="variantValue(section.key)"
                    @update:model-value="setVariant(section.key, $event)"
                >
                    <SelectTrigger class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="option in sectionVariantChoices[section.key] ?? []"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>
        </div>

        <div v-if="draft.sections.includes('reviews')" class="grid gap-2">
            <Label>Reviews style</Label>
            <Select :model-value="draft.reviews_style ?? 'carousel'" @update:model-value="setReviewsStyle">
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
        </div>

        <p class="text-xs text-muted-foreground">
            Section backgrounds: click the empty part of a section in the page.
        </p>
    </div>
</template>
