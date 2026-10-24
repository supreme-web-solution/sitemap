<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    EyeOff,
    ImagePlus,
    LoaderCircle,
    RotateCcw,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { CommitOptions } from '@/composables/useSiteEditor';
import { getPath } from '@/lib/siteEditor/document';
import type { EditOp } from '@/lib/siteEditor/document';
import { generateSiteImage, removeSiteImage, uploadSiteImage } from '@/lib/siteEditor/http';
import type { EditorSelection } from '@/lib/siteEditor/protocol';
import type { Option, SiteDocument, SiteMedia } from '@/types';

const props = defineProps<{
    selection: EditorSelection;
    draft: SiteDocument;
    media: SiteMedia;
    teamSlug: string;
    siteId: number;
    aiAvailable: boolean;
    aiBusy: boolean;
    sectionStyleChoices: Option[];
    canMoveUp: boolean;
    canMoveDown: boolean;
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
    improve: [payload: { field?: string; section?: string; body?: string }];
    'section-action': [action: 'up' | 'down' | 'hide', key: string];
    'media-changed': [media: Pick<SiteMedia, 'logo' | 'photos'>];
}>();

const instruction = ref('');
const imagePrompt = ref('');
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.selection,
    () => {
        instruction.value = '';
    },
);

const textSelection = computed(() => (props.selection.kind === 'text' ? props.selection : null));
const imageSelection = computed(() => (props.selection.kind === 'image' ? props.selection : null));
const sectionSelection = computed(() => (props.selection.kind === 'section' ? props.selection : null));

const textValue = computed(() => {
    const selection = textSelection.value;

    if (!selection) {
        return '';
    }

    const stored = getPath(props.draft, selection.path);

    return typeof stored === 'string' && stored !== '' ? stored : selection.value;
});

const isCopyField = computed(() => textSelection.value?.path.startsWith('copy.') ?? false);
const hasCopyOverride = computed(() => {
    const selection = textSelection.value;

    return selection ? typeof getPath(props.draft, selection.path) === 'string' : false;
});

const setText = (value: string | number) => {
    const selection = textSelection.value;

    if (!selection) {
        return;
    }

    emit(
        'commit',
        [{ op: 'set', path: selection.path, value: String(value) }],
        { coalesceKey: `text:${selection.path}`, reload: selection.reload },
    );
};

const resetCopy = () => {
    const selection = textSelection.value;

    if (selection) {
        emit('commit', [{ op: 'set', path: selection.path, value: null }], { reload: true, immediate: true });
    }
};

const currentImage = computed(() => {
    const selection = imageSelection.value;

    if (!selection) {
        return null;
    }

    return selection.slot === 'logo' ? props.media.logo : (props.media.photos[selection.index ?? 0] ?? null);
});

const upload = async (event: Event) => {
    const selection = imageSelection.value;
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!selection || !file) {
        return;
    }

    uploading.value = true;

    try {
        emit(
            'media-changed',
            await uploadSiteImage(props.teamSlug, props.siteId, { slot: selection.slot, index: selection.index }, file),
        );
        toast.success('Image updated');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The image could not be saved.');
    } finally {
        uploading.value = false;
        input.value = '';
    }
};

const generateImage = async () => {
    const selection = imageSelection.value;

    if (!selection || selection.slot !== 'photo' || imagePrompt.value.trim() === '') {
        return;
    }

    uploading.value = true;

    try {
        emit(
            'media-changed',
            await generateSiteImage(props.teamSlug, props.siteId, { slot: selection.slot, index: selection.index }, imagePrompt.value.trim()),
        );
        toast.success('Image generated');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The image could not be generated.');
    } finally {
        uploading.value = false;
    }
};

const removeImage = async () => {
    const selection = imageSelection.value;

    if (!selection) {
        return;
    }

    uploading.value = true;

    try {
        emit(
            'media-changed',
            await removeSiteImage(props.teamSlug, props.siteId, { slot: selection.slot, index: selection.index }),
        );
        toast.success('Image removed');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The image could not be removed.');
    } finally {
        uploading.value = false;
    }
};

const sectionStyle = computed(() => {
    const selection = sectionSelection.value;

    return selection ? (props.draft.section_styles?.[selection.key] ?? '') : '';
});

const setSectionStyle = (value: string) => {
    const selection = sectionSelection.value;

    if (selection) {
        emit(
            'commit',
            [{ op: 'set', path: `section_styles.${selection.key}`, value: value === '' ? null : value }],
            { reload: true, immediate: true },
        );
    }
};

const textareaClass =
    'w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <div class="grid gap-4" data-test="inspector-selection">
        <p
            v-if="selection.kind === 'none'"
            class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
        >
            Click any text on the page to edit it, click a photo or the logo to
            replace it, or click the background of a section to style it.
        </p>

        <template v-if="textSelection">
            <div class="grid gap-2">
                <Label for="selected-text">{{ textSelection.label }}</Label>
                <textarea
                    v-if="textSelection.multiline"
                    id="selected-text"
                    :value="textValue"
                    :maxlength="textSelection.max"
                    rows="6"
                    :class="textareaClass"
                    @input="setText(($event.target as HTMLTextAreaElement).value)"
                />
                <Input
                    v-else
                    id="selected-text"
                    :model-value="textValue"
                    :maxlength="textSelection.max"
                    @update:model-value="setText"
                />
                <p class="text-right text-xs text-muted-foreground">
                    {{ textValue.length }} / {{ textSelection.max }}
                </p>
            </div>
            <Button
                v-if="isCopyField && hasCopyOverride"
                type="button"
                size="sm"
                variant="ghost"
                class="w-fit"
                @click="resetCopy"
            >
                <RotateCcw /> Back to the default wording
            </Button>

            <div v-if="aiAvailable" class="grid gap-2 rounded-lg border bg-muted/30 p-3">
                <Label for="improve-text" class="flex items-center gap-1.5">
                    <Sparkles class="size-3.5" /> Improve with AI
                </Label>
                <Input
                    id="improve-text"
                    v-model="instruction"
                    placeholder="Optional: e.g. shorter and friendlier"
                    maxlength="300"
                />
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="aiBusy"
                    data-test="improve-field"
                    @click="$emit('improve', { field: textSelection.path, body: instruction })"
                >
                    <LoaderCircle v-if="aiBusy" class="animate-spin" />
                    <Sparkles v-else />
                    {{ aiBusy ? 'Working…' : 'Rewrite this text' }}
                </Button>
                <p class="text-xs text-muted-foreground">
                    Only this text changes, and the version before is saved in
                    the history.
                </p>
            </div>
        </template>

        <template v-if="imageSelection">
            <div class="grid gap-2">
                <Label>{{ imageSelection.label }}</Label>
                <div
                    class="flex aspect-video items-center justify-center overflow-hidden rounded-lg border bg-muted/40"
                >
                    <img
                        v-if="currentImage"
                        :src="currentImage"
                        :alt="imageSelection.label"
                        class="size-full object-cover"
                    />
                    <span v-else class="text-sm text-muted-foreground">No image yet</span>
                </div>
                <input
                    ref="fileInput"
                    type="file"
                    class="sr-only"
                    accept="image/png,image/jpeg,image/webp"
                    data-test="image-input"
                    @change="upload"
                />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        size="sm"
                        :disabled="uploading"
                        @click="fileInput?.click()"
                    >
                        <LoaderCircle v-if="uploading" class="animate-spin" />
                        <ImagePlus v-else />
                        {{ currentImage ? 'Replace image' : 'Upload image' }}
                    </Button>
                    <Button
                        v-if="currentImage"
                        type="button"
                        size="sm"
                        variant="ghost"
                        :disabled="uploading"
                        @click="removeImage"
                    >
                        <Trash2 /> Remove
                    </Button>
                </div>
                <div
                    v-if="imageSelection.slot === 'photo' && aiAvailable"
                    class="grid gap-2 rounded-lg border border-dashed p-3"
                >
                    <Label for="image-prompt">Generate with AI</Label>
                    <Input
                        id="image-prompt"
                        v-model="imagePrompt"
                        placeholder="Describe the photo you want"
                        maxlength="500"
                    />
                    <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        :disabled="uploading || imagePrompt.trim() === ''"
                        @click="generateImage"
                    >
                        <LoaderCircle v-if="uploading" class="animate-spin" />
                        <Sparkles v-else />
                        Generate image
                    </Button>
                </div>
                <div
                    class="rounded-lg border border-sky-500/25 bg-sky-500/5 px-3 py-2 text-xs leading-relaxed text-muted-foreground"
                    data-test="media-immediate-save-notice"
                >
                    JPG, PNG or WebP, up to
                    {{ imageSelection.slot === 'logo' ? 2 : 5 }} MB. Images save
                    immediately when you upload, generate, or remove. Use
                    <strong class="font-medium text-foreground">Undo</strong>
                    (Ctrl+Z) to revert the last image change only.
                </div>
            </div>
        </template>

        <template v-if="sectionSelection">
            <div class="grid gap-3">
                <p class="text-sm font-medium">{{ sectionSelection.label }} section</p>
                <div class="grid gap-2">
                    <Label>Background</Label>
                    <div class="flex flex-wrap gap-1.5" role="group" aria-label="Section background">
                        <Button
                            type="button"
                            size="sm"
                            :variant="sectionStyle === '' ? 'secondary' : 'outline'"
                            @click="setSectionStyle('')"
                        >
                            Template default
                        </Button>
                        <Button
                            v-for="choice in sectionStyleChoices"
                            :key="choice.value"
                            type="button"
                            size="sm"
                            :variant="sectionStyle === choice.value ? 'secondary' : 'outline'"
                            @click="setSectionStyle(choice.value)"
                        >
                            {{ choice.label }}
                        </Button>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="!canMoveUp"
                        @click="$emit('section-action', 'up', sectionSelection.key)"
                    >
                        <ArrowUp /> Move up
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        :disabled="!canMoveDown"
                        @click="$emit('section-action', 'down', sectionSelection.key)"
                    >
                        <ArrowDown /> Move down
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        @click="$emit('section-action', 'hide', sectionSelection.key)"
                    >
                        <EyeOff /> Hide
                    </Button>
                </div>
                <Button
                    v-if="aiAvailable"
                    type="button"
                    size="sm"
                    variant="outline"
                    class="w-fit"
                    :disabled="aiBusy"
                    @click="$emit('improve', { section: sectionSelection.key })"
                >
                    <Sparkles /> Improve this section with AI
                </Button>
            </div>
        </template>
    </div>
</template>
