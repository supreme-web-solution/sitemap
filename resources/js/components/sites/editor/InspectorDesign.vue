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
import type { Option, SiteDesignChoices, SiteDocument } from '@/types';

const props = defineProps<{
    draft: SiteDocument;
    templates: Option[];
    palettes: Record<string, string[]>;
    reviewsStyles: Option[];
    choices: SiteDesignChoices;
    canPullBrand: boolean;
    pullingBrand: boolean;
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
    'pull-brand': [];
}>();

const DEFAULT = 'default';

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
