<script setup lang="ts">
import { ArrowDown, ArrowUp, EyeOff, Plus, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { CommitOptions } from '@/composables/useSiteEditor';
import type { EditOp } from '@/lib/siteEditor/document';
import InspectorCustomPages from '@/components/sites/editor/InspectorCustomPages.vue';
import type { Option, SiteDocument } from '@/types';

const props = defineProps<{
    draft: SiteDocument;
    pageOptions: Option[];
    sectionOptions: Option[];
    sectionVariantChoices: Record<string, Option[]>;
    aiAvailable: boolean;
    aiBusy: boolean;
    businessName: string;
    businessCity: string | null;
    businessCategory: string | null;
    previewUrl: string;
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
    improve: [section: string];
    redesign: [section: string];
    locate: [section: string];
}>();

const pages = computed(() => props.draft.pages ?? props.pageOptions.map((option) => option.value));

const label = (value: string) =>
    props.sectionOptions.find((section) => section.value === value)?.label ?? value;

const hidden = computed(() =>
    props.sectionOptions.filter((section) => !props.draft.sections.includes(section.value)),
);

const togglePage = (page: string, enabled: boolean) => {
    const nextPages = enabled
        ? [...pages.value, page]
        : pages.value.filter((value) => value !== page);
    const ops: EditOp[] = [{ op: 'set', path: 'pages', value: nextPages }];

    if (page === 'gallery' && enabled && !props.draft.sections.includes('gallery')) {
        ops.push({
            op: 'insert',
            path: 'sections',
            index: props.draft.sections.length,
            value: 'gallery',
        });
    }

    emit('commit', ops, { immediate: true, reload: true });
};

const moveSection = (from: number, offset: -1 | 1) => {
    const to = from + offset;

    if (to >= 0 && to < props.draft.sections.length) {
        emit('commit', [{ op: 'move', path: 'sections', from, to }], { immediate: true, reload: true });
    }
};

const hideSection = (index: number) =>
    emit('commit', [{ op: 'remove', path: 'sections', index }], { immediate: true, reload: true });

const showSection = (value: string) =>
    emit(
        'commit',
        [{ op: 'insert', path: 'sections', index: props.draft.sections.length, value }],
        { immediate: true, reload: true },
    );
</script>

<template>
    <div class="grid gap-5" data-test="inspector-structure">
        <div class="grid gap-2" data-test="pages-setting">
            <Label>Pages in the menu</Label>
            <p class="text-xs text-muted-foreground">
                Home and Contact are always on. Checking Gallery also adds the
                gallery block on the home page (same photos as the Gallery page).
                Blog adds a Blog menu link — write posts with Add a post on that
                page, or under Settings → Blog posts.
            </p>
            <label
                v-for="option in pageOptions"
                :key="option.value"
                class="flex items-center gap-2 text-sm"
            >
                <Checkbox
                    :model-value="pages.includes(option.value)"
                    @update:model-value="togglePage(option.value, $event === true)"
                />
                {{ option.label }}
            </label>
        </div>

        <div class="grid gap-2">
            <Label>Home page sections</Label>
            <ol class="grid gap-1.5">
                <li
                    v-for="(section, index) in draft.sections"
                    :key="section"
                    class="flex items-center gap-1 rounded-md border px-3 py-1.5 text-sm"
                >
                    <button
                        type="button"
                        class="flex-1 text-left hover:underline"
                        @click="$emit('locate', section)"
                    >
                        {{ label(section) }}
                    </button>
                    <Button
                        v-if="aiAvailable"
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="h-7 px-2 text-xs"
                        :disabled="aiBusy"
                        @click="$emit('improve', section)"
                    >
                        <Sparkles class="size-3.5" /> Improve
                    </Button>
                    <Button
                        v-if="aiAvailable && sectionVariantChoices[section]"
                        type="button"
                        size="sm"
                        variant="ghost"
                        class="h-7 px-2 text-xs"
                        :disabled="aiBusy"
                        @click="$emit('redesign', section)"
                    >
                        Redesign
                    </Button>
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
                        :disabled="index === draft.sections.length - 1"
                        @click="moveSection(index, 1)"
                    >
                        <ArrowDown />
                    </Button>
                    <Button
                        type="button"
                        size="icon-sm"
                        variant="ghost"
                        aria-label="Hide section"
                        :disabled="draft.sections.length <= 1"
                        @click="hideSection(index)"
                    >
                        <EyeOff />
                    </Button>
                </li>
            </ol>
            <div v-if="hidden.length" class="flex flex-wrap gap-1.5">
                <Button
                    v-for="section in hidden"
                    :key="section.value"
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="showSection(section.value)"
                >
                    <Plus /> {{ section.label }}
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Home sections without content (e.g. a gallery with no photos) are
                left out of the homepage automatically. That does not hide menu pages.
            </p>
        </div>

        <InspectorCustomPages
            :draft="draft"
            :business-name="businessName"
            :business-city="businessCity"
            :business-category="businessCategory"
            :preview-url="previewUrl"
            @commit="(ops, options) => $emit('commit', ops, options)"
        />
    </div>
</template>
