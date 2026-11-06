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
    WandSparkles,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { CommitOptions } from '@/composables/useSiteEditor';
import { getPath } from '@/lib/siteEditor/document';
import type { EditOp } from '@/lib/siteEditor/document';
import {
    generateSiteImage,
    improveSiteImage,
    removeSiteImage,
    uploadSiteImage,
    type MediaResponse,
} from '@/lib/siteEditor/http';
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
    sectionVariantChoices: Record<string, Option[]>;
    canMoveUp: boolean;
    canMoveDown: boolean;
}>();

const emit = defineEmits<{
    commit: [operations: EditOp[], options?: CommitOptions];
    improve: [payload: { field?: string; section?: string; body?: string; redesign?: boolean }];
    'section-action': [action: 'up' | 'down' | 'hide', key: string];
    'media-changed': [media: MediaResponse];
}>();

const instruction = ref('');
const imagePrompt = ref('');
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const addFileInput = ref<HTMLInputElement | null>(null);
const uploadTargetIndex = ref<number | null>(null);

watch(
    () => props.selection,
    () => {
        instruction.value = '';
    },
);

const textSelection = computed(() => (props.selection.kind === 'text' ? props.selection : null));
const imageSelection = computed(() => {
    if (props.selection.kind === 'image') {
        return props.selection;
    }

    // Overlay heroes are hard to click — when the Hero section is selected, still offer photo tools.
    if (props.selection.kind === 'section' && props.selection.key === 'hero') {
        return { kind: 'image' as const, slot: 'home_hero' as const, index: null, label: 'Home hero photo' };
    }

    if (props.selection.kind === 'design' && props.selection.region === 'hero') {
        return { kind: 'image' as const, slot: 'home_hero' as const, index: null, label: 'Home hero photo' };
    }

    return null;
});
const sectionSelection = computed(() => {
    const current = props.selection;

    if (current.kind === 'section') {
        return current;
    }

    if (current.kind === 'design') {
        return { kind: 'section' as const, key: current.region, label: current.label };
    }

    return null;
});

const designSelection = computed(() => (props.selection.kind === 'design' ? props.selection : null));
const heroPhotoFromSection = computed(
    () =>
        props.selection.kind !== 'image' &&
        ((props.selection.kind === 'section' && props.selection.key === 'hero') ||
            (props.selection.kind === 'design' && props.selection.region === 'hero')),
);

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

    if (selection.slot === 'logo') {
        return props.media.logo;
    }

    if (selection.slot === 'service') {
        const url = props.media.services?.[selection.index ?? 0] ?? null;

        return url && !url.startsWith('data:') ? url : null;
    }

    if (selection.slot !== 'photo') {
        return props.media.placements?.[selection.slot] ?? null;
    }

    return props.media.photos[selection.index ?? 0] ?? null;
});

const isPagePhoto = computed(() => {
    const slot = imageSelection.value?.slot;

    return slot !== undefined && slot !== 'logo' && slot !== 'photo' && slot !== 'service';
});

const canUseAiImage = computed(() => {
    const selection = imageSelection.value;

    return selection !== null && selection.slot !== 'logo' && props.aiAvailable;
});

const canGenerateWithoutPrompt = computed(() => {
    const selection = imageSelection.value;

    if (!selection) {
        return false;
    }

    if (selection.slot === 'service' || isPagePhoto.value) {
        return true;
    }

    return selection.slot === 'photo' && (selection.index ?? 0) > 0;
});

const canAddPhoto = computed(() => {
    const selection = imageSelection.value;

    return (
        selection?.slot === 'photo' &&
        props.media.photos.length < (props.media.maxPhotos ?? 12)
    );
});

const askingForNewPhoto = ref(false);

watch(imageSelection, () => {
    askingForNewPhoto.value = false;
    imagePrompt.value = '';
});

const nextPhotoIndex = computed(() => props.media.photos.length);

const upload = async (event: Event) => {
    const selection = imageSelection.value;
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];

    if (!selection || !file) {
        uploadTargetIndex.value = null;
        input.value = '';

        return;
    }

    const index =
        selection.slot === 'photo' ? (uploadTargetIndex.value ?? selection.index) : selection.index;
    uploadTargetIndex.value = null;
    uploading.value = true;

    try {
        emit(
            'media-changed',
            await uploadSiteImage(props.teamSlug, props.siteId, { slot: selection.slot, index }, file),
        );
        toast.success(index !== null && index >= props.media.photos.length ? 'Photo added' : 'Image updated');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The image could not be saved.');
    } finally {
        uploading.value = false;
        input.value = '';
    }
};

const startAddPhoto = () => {
    if (!canAddPhoto.value) {
        return;
    }

    uploadTargetIndex.value = nextPhotoIndex.value;
    addFileInput.value?.click();
};

const generateImage = async () => {
    const selection = imageSelection.value;

    if (!selection || (selection.slot === 'logo')) {
        return;
    }

    const prompt = imagePrompt.value.trim();

    if (prompt === '' && !canGenerateWithoutPrompt.value) {
        return;
    }

    uploading.value = true;

    try {
        emit(
            'media-changed',
            await generateSiteImage(props.teamSlug, props.siteId, { slot: selection.slot, index: selection.index }, prompt),
        );
        toast.success('Image generated');
        askingForNewPhoto.value = false;
        imagePrompt.value = '';
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The image could not be generated.');
    } finally {
        uploading.value = false;
    }
};

const seenGenerateNonce = ref<number | null>(null);

watch(
    () => (props.selection.kind === 'image' ? props.selection.generateNonce : undefined),
    (nonce) => {
        if (nonce == null || nonce === seenGenerateNonce.value || uploading.value) {
            return;
        }

        seenGenerateNonce.value = nonce;
        void generateImage();
    },
);

const improveImage = async () => {
    const selection = imageSelection.value;

    if (!selection || selection.slot === 'logo' || !currentImage.value) {
        return;
    }

    uploading.value = true;

    try {
        emit(
            'media-changed',
            await improveSiteImage(
                props.teamSlug,
                props.siteId,
                { slot: selection.slot, index: selection.index },
                imagePrompt.value.trim(),
            ),
        );
        toast.success('Image improved');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'The image could not be improved.');
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

const canReorderSection = computed(() => {
    const key = sectionSelection.value?.key;

    return key ? props.draft.sections.includes(key) : false;
});

const sectionLayouts = computed(() => {
    const key = sectionSelection.value?.key;

    return key ? (props.sectionVariantChoices[key] ?? []) : [];
});

const sectionLayout = computed(() => {
    const key = sectionSelection.value?.key;

    if (!key) {
        return '';
    }

    return props.draft.variants?.[key] ?? sectionLayouts.value[0]?.value ?? '';
});

const setSectionLayout = (value: unknown) => {
    const selection = sectionSelection.value;

    if (selection) {
        emit(
            'commit',
            [{ op: 'set', path: `variants.${selection.key}`, value: String(value) }],
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
            Scroll the preview. The section on screen shows here, with its layout.
            Click text or a photo to edit that instead.
        </p>

        <p
            v-if="designSelection"
            class="rounded-lg border bg-muted/40 px-3 py-2 text-xs text-muted-foreground"
        >
            AI target:
            <span class="font-medium text-foreground">{{ designSelection.label }}</span>
            <span class="mt-1 block font-mono">{{ designSelection.selector }}</span>
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
            <div class="grid gap-2" :class="heroPhotoFromSection ? 'mb-4 border-b border-border pb-4' : ''">
                <Label>{{ imageSelection.label }}</Label>
                <p v-if="heroPhotoFromSection" class="text-xs text-muted-foreground">
                    Hover the photo to replace it, sharpen it, or create a new one.
                </p>
                <div
                    class="group relative flex aspect-video items-center justify-center overflow-hidden rounded-lg border bg-muted/40"
                >
                    <img
                        v-if="currentImage"
                        :src="currentImage"
                        :alt="imageSelection.label"
                        class="size-full object-cover"
                    />
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-4 text-center"
                    >
                        <span class="text-sm text-muted-foreground">No photo yet</span>
                        <Button
                            type="button"
                            size="sm"
                            :disabled="uploading"
                            @click="
                                uploadTargetIndex = null;
                                fileInput?.click();
                            "
                        >
                            Upload
                        </Button>
                        <button
                            v-if="canUseAiImage"
                            type="button"
                            class="text-xs font-medium text-primary hover:underline"
                            :disabled="uploading"
                            @click="
                                imageSelection?.slot === 'photo' && canGenerateWithoutPrompt
                                    ? generateImage()
                                    : (askingForNewPhoto = true)
                            "
                        >
                            {{ imageSelection?.slot === 'photo' ? 'Generate with AI' : 'or generate one' }}
                        </button>
                    </div>
                    <div
                        v-if="uploading"
                        class="absolute inset-0 grid place-items-center bg-black/35 text-white"
                    >
                        <LoaderCircle class="size-5 animate-spin" />
                    </div>
                    <button
                        v-if="currentImage"
                        type="button"
                        class="absolute top-2 right-2 grid size-8 place-items-center rounded-full bg-black/55 text-white opacity-0 transition hover:bg-black/75 group-hover:opacity-100 group-focus-within:opacity-100 [@media(hover:none)]:opacity-100"
                        aria-label="Remove photo"
                        :disabled="uploading"
                        @click="removeImage"
                    >
                        <Trash2 class="size-3.5" />
                    </button>
                    <div
                        v-if="currentImage"
                        class="absolute inset-x-2 bottom-2 flex flex-wrap gap-1 opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100 [@media(hover:none)]:opacity-100"
                    >
                        <button
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-medium text-slate-900 shadow-sm hover:bg-white"
                            :disabled="uploading"
                            @click="
                                uploadTargetIndex = null;
                                fileInput?.click();
                            "
                        >
                            <ImagePlus class="size-3.5" />
                            Replace
                        </button>
                        <button
                            v-if="canUseAiImage"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-medium text-slate-900 shadow-sm hover:bg-white"
                            :disabled="uploading"
                            @click="improveImage"
                        >
                            <WandSparkles class="size-3.5" />
                            Sharpen
                        </button>
                        <button
                            v-if="canUseAiImage"
                            type="button"
                            class="inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-medium text-slate-900 shadow-sm hover:bg-white"
                            :disabled="uploading"
                            @click="askingForNewPhoto = !askingForNewPhoto"
                        >
                            <Sparkles class="size-3.5" />
                            New with AI
                        </button>
                    </div>
                </div>
                <input
                    ref="fileInput"
                    type="file"
                    class="sr-only"
                    accept="image/png,image/jpeg,image/webp"
                    data-test="image-input"
                    @change="upload"
                />
                <input
                    ref="addFileInput"
                    type="file"
                    class="sr-only"
                    accept="image/png,image/jpeg,image/webp"
                    data-test="add-image-input"
                    @change="upload"
                />
                <form
                    v-if="canUseAiImage && askingForNewPhoto"
                    class="flex gap-2"
                    @submit.prevent="generateImage"
                >
                    <Input
                        id="image-prompt"
                        v-model="imagePrompt"
                        :placeholder="
                            canGenerateWithoutPrompt
                                ? 'Optional description'
                                : 'Describe the photo'
                        "
                        maxlength="500"
                        autofocus
                    />
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="
                            uploading ||
                            (imagePrompt.trim() === '' && !canGenerateWithoutPrompt)
                        "
                    >
                        <Sparkles class="size-3.5" />
                        Create with AI
                    </Button>
                </form>
                <button
                    v-if="canAddPhoto"
                    type="button"
                    class="w-fit text-xs font-medium text-primary hover:underline"
                    :disabled="uploading"
                    data-test="add-gallery-photo"
                    @click="startAddPhoto"
                >
                    Add another gallery photo
                </button>
                <p v-if="imageSelection.slot === 'photo'" class="text-xs text-muted-foreground">
                    {{ media.photos.length }} of {{ media.maxPhotos }}. Home shows the first 6.
                    Each generated gallery photo is a different scene.
                </p>
                <p v-else-if="isPagePhoto" class="text-xs text-muted-foreground">
                    Only this page uses this photo.
                </p>
                <p class="text-xs text-muted-foreground">
                    JPG, PNG or WebP, up to {{ imageSelection.slot === 'logo' ? 2 : 5 }} MB.
                    Undo reverts the last photo.
                </p>
            </div>
        </template>

        <template v-if="sectionSelection">
            <div class="grid gap-3">
                <div>
                    <p class="text-sm font-medium">{{ sectionSelection.label }}</p>
                    <p class="text-xs text-muted-foreground">Section on screen</p>
                </div>
                <div v-if="sectionLayouts.length" class="grid gap-2">
                    <Label>Layout</Label>
                    <Select :model-value="sectionLayout" @update:model-value="setSectionLayout">
                        <SelectTrigger class="w-full" data-test="section-layout">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in sectionLayouts"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-xs text-muted-foreground">
                        Changes this section only. The rest of the template stays.
                    </p>
                </div>
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
                <div v-if="canReorderSection" class="flex flex-wrap gap-2">
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
                <div v-if="aiAvailable" class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        class="w-fit"
                        :disabled="aiBusy"
                        @click="$emit('improve', { section: sectionSelection.key })"
                    >
                        <Sparkles /> Improve copy
                    </Button>
                    <Button
                        v-if="sectionLayouts.length"
                        type="button"
                        size="sm"
                        variant="outline"
                        class="w-fit"
                        :disabled="aiBusy"
                        data-test="redesign-section"
                        @click="$emit('improve', { section: sectionSelection.key, redesign: true })"
                    >
                        <Sparkles /> Redesign layout
                    </Button>
                </div>
            </div>
        </template>
    </div>
</template>
