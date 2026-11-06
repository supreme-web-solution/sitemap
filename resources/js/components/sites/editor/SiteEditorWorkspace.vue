<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { undoSiteImage } from '@/lib/siteEditor/http';
import { Button } from '@/components/ui/button';
import { useSiteEditor } from '@/composables/useSiteEditor';
import type { CommitOptions } from '@/composables/useSiteEditor';
import type { EditOp } from '@/lib/siteEditor/document';
import type {
    EditorSelection,
    FrameMessage,
    FrameSection,
    PreviewEdit,
} from '@/lib/siteEditor/protocol';
import type {
    EditableSite,
    Option,
    QualityReport,
    SiteCopyField,
    SiteDesignChoices,
    SiteDocument,
    SiteMedia,
} from '@/types';
import EditorCanvas from './EditorCanvas.vue';
import type { Device } from './EditorCanvas.vue';
import EditorToolbar from './EditorToolbar.vue';
import InspectorContent from './InspectorContent.vue';
import InspectorDesign from './InspectorDesign.vue';
import InspectorSelection from './InspectorSelection.vue';
import InspectorStructure from './InspectorStructure.vue';

type Tab = 'selection' | 'content' | 'design' | 'structure' | 'ai';

const props = defineProps<{
    site: EditableSite;
    quality: QualityReport;
    teamSlug: string;
    pageOptions: Option[];
    templates: Option[];
    palettes: Record<string, string[]>;
    sectionOptions: Option[];
    sectionVariantChoices: Record<string, Option[]>;
    reviewsStyles: Option[];
    designChoices: SiteDesignChoices;
    copyFields: SiteCopyField[];
    aiAvailable: boolean;
    aiBusy: boolean;
    pullingBrand: boolean;
}>();

const emit = defineEmits<{
    quality: [quality: QualityReport];
    improve: [payload: {
        field?: string;
        section?: string;
        body?: string;
        redesign?: boolean;
        design_target?: string;
        preview_edits?: PreviewEdit[];
    }];
    'pull-brand': [];
}>();

export type SiteEditorAiContext = {
    design_target: string | null;
    preview_edits: PreviewEdit[];
    label: string | null;
};

const canvas = ref<InstanceType<typeof EditorCanvas> | null>(null);
const device = ref<Device>('desktop');
const mode = ref<'edit' | 'preview'>('edit');
const tab = ref<Tab>('selection');
const selection = ref<EditorSelection>({ kind: 'none' });
const frameSections = ref<FrameSection[]>([]);
const inViewSection = ref<string | null>(null);
const media = ref<SiteMedia>({ ...props.site.media });
const canUndoMedia = ref(false);
let reloadPending = false;

const toDocument = (site: EditableSite): SiteDocument => ({
    template: site.template,
    ...site.content,
});

const editor = useSiteEditor({
    teamSlug: () => props.teamSlug,
    siteId: props.site.id,
    document: toDocument(props.site),
    revision: props.site.revision,
    quality: props.quality,
    onApplied: ({ operations, structural, source }) => {
        if (structural) {
            reloadPending = true;

            return;
        }

        if (source === 'frame') {
            return;
        }

        operations.forEach((operation) => {
            if (operation.op === 'set' && typeof operation.value === 'string') {
                canvas.value?.post({ type: 'apply', path: operation.path, value: operation.value });
            }
        });
    },
    onReset: () => canvas.value?.reload(),
    onSettled: () => {
        if (reloadPending) {
            reloadPending = false;
            canvas.value?.reload();
        }
    },
});

watch(editor.quality, (quality) => emit('quality', quality), { deep: false });

const commit = (operations: EditOp[], options?: CommitOptions) => editor.commit(operations, options);

const sectionIndex = (key: string) => frameSections.value.findIndex((section) => section.key === key);

const selectedSectionKey = computed(() => {
    const current = selection.value;

    if (current.kind === 'section') {
        return current.key;
    }

    if (current.kind === 'design') {
        return current.region;
    }

    return null;
});

const adoptSectionInView = (): void => {
    const key = inViewSection.value;

    if (!key) {
        return;
    }

    const current = selection.value;
    const currentKey = current.kind === 'section' ? current.key : current.kind === 'design' ? current.region : null;

    if (current.kind !== 'none' && current.kind !== 'section' && current.kind !== 'design') {
        return;
    }

    if (currentKey === key) {
        return;
    }

    const section = frameSections.value.find((item) => item.key === key);

    if (!section) {
        return;
    }

    selection.value = { kind: 'section', key: section.key, label: section.label };
};

watch(tab, (value) => {
    if (value === 'selection') {
        adoptSectionInView();
    }
});

const aiDesignContext = computed((): SiteEditorAiContext => {
    const current = selection.value;

    if (current.kind === 'design') {
        return {
            design_target: current.region,
            label: current.label,
            preview_edits: [
                {
                    region: current.region,
                    selector: current.selector,
                    tag: current.tag,
                    text: current.text,
                    href: current.href,
                },
            ],
        };
    }

    if (current.kind === 'section') {
        return {
            design_target: current.key,
            label: current.label,
            preview_edits: [
                {
                    region: current.key,
                    selector: `#${current.key}, .${current.key}-block`,
                    tag: 'section',
                },
            ],
        };
    }

    return { design_target: null, preview_edits: [], label: null };
});

provide('siteEditorAiContext', aiDesignContext);
provide('siteEditorTab', tab);
const canMoveUp = computed(() => selectedSectionKey.value !== null && sectionIndex(selectedSectionKey.value) > 0);
const canMoveDown = computed(() => {
    const key = selectedSectionKey.value;

    return key !== null && sectionIndex(key) !== -1 && sectionIndex(key) < frameSections.value.length - 1;
});

const sectionAction = (action: 'up' | 'down' | 'hide', key: string) => {
    const sections = editor.draft.value.sections;

    if (action === 'hide') {
        const index = sections.indexOf(key);

        if (index !== -1 && sections.length > 1) {
            commit([{ op: 'remove', path: 'sections', index }], { immediate: true, reload: true });
            selection.value = { kind: 'none' };
        }

        return;
    }

    const rendered = frameSections.value.map((section) => section.key);
    const neighbour = rendered[rendered.indexOf(key) + (action === 'up' ? -1 : 1)];

    if (neighbour === undefined) {
        return;
    }

    commit(
        [{ op: 'move', path: 'sections', from: sections.indexOf(key), to: sections.indexOf(neighbour) }],
        { immediate: true, reload: true },
    );
};

const onFrameMessage = (message: FrameMessage) => {
    switch (message.type) {
        case 'ready':
            frameSections.value = message.sections;
            inViewSection.value = message.sections[0]?.key ?? null;

            if (tab.value === 'selection') {
                adoptSectionInView();
            }

            break;
        case 'inview':
            inViewSection.value = message.key;

            if (tab.value === 'selection') {
                adoptSectionInView();
            }

            break;
        case 'select': {
            const { type: _type, ...rest } = message;
            selection.value = rest as EditorSelection;

            if (rest.kind === 'text' || rest.kind === 'image') {
                tab.value = 'selection';
            } else if (rest.kind !== 'none' && tab.value !== 'design') {
                tab.value = 'selection';
            }

            break;
        }
        case 'edit':
            commit([{ op: 'set', path: message.path, value: message.value }], {
                coalesceKey: `text:${message.path}`,
                source: 'frame',
                reload: message.reload,
            });
            break;
        case 'section':
            if (message.action === 'improve') {
                tab.value = 'ai';
                emit('improve', {
                    design_target: message.key,
                    body: 'Improve the design and layout of this section.',
                    preview_edits: [
                        {
                            region: message.key,
                            selector: `#${message.key}, .${message.key}-block`,
                            tag: 'section',
                        },
                    ],
                });
            } else {
                sectionAction(message.action, message.key);
            }

            break;
        case 'shortcut':
            if (message.name === 'undo') {
                editor.undo();
            } else if (message.name === 'redo') {
                editor.redo();
            } else {
                void editor.flush();
            }

            break;
    }
};

const locate = (path: string) => canvas.value?.post({ type: 'select', path });
const locateSection = (key: string) => canvas.value?.post({ type: 'section', key });

const onKeydown = (event: KeyboardEvent) => {
    const target = event.target as HTMLElement | null;

    if (target && (target.closest('input, textarea, select, [contenteditable]') !== null)) {
        return;
    }

    const modifier = event.ctrlKey || event.metaKey;
    const key = event.key.toLowerCase();

    if (modifier && key === 'z' && !event.shiftKey) {
        event.preventDefault();
        handleUndo();
    } else if (modifier && (key === 'y' || (key === 'z' && event.shiftKey))) {
        event.preventDefault();
        editor.redo();
    } else if (modifier && key === 's') {
        event.preventDefault();
        void editor.flush();
    }
};

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

watch(
    () => props.site.revision,
    (serverRevision) => {
        if (serverRevision <= editor.revision.value) {
            return;
        }

        if (editor.hasUnsaved.value) {
            editor.markConflict('This website was changed by someone or something else while you had unsaved edits.');

            return;
        }

        editor.reset(toDocument(props.site), serverRevision, props.quality);
        media.value = { ...props.site.media };
        canvas.value?.reload();
    },
);

const reloadFromServer = () => {
    router.reload({
        only: ['site', 'quality', 'chat', 'versions'],
        onSuccess: () => {
            editor.reset(toDocument(props.site), props.site.revision, props.quality);
            media.value = { ...props.site.media };
            canvas.value?.reload();
        },
    });
};

const onMediaChanged = (changed: Pick<SiteMedia, 'logo' | 'photos' | 'services' | 'placements'> & { revision?: number }) => {
    media.value = {
        ...media.value,
        logo: changed.logo,
        photos: changed.photos,
        services: changed.services ?? media.value.services,
        placements: changed.placements ?? media.value.placements,
    };
    canUndoMedia.value = true;

    if (typeof changed.revision === 'number') {
        editor.syncRevision(changed.revision);
    }

    canvas.value?.reload();
};

const undoMediaChange = async (): Promise<void> => {
    try {
        const next = await undoSiteImage(props.teamSlug, props.site.id);
        media.value = {
            ...media.value,
            logo: next.logo,
            photos: next.photos,
            services: next.services ?? media.value.services,
            placements: next.placements ?? media.value.placements,
        };

        if (typeof next.revision === 'number') {
            editor.syncRevision(next.revision);
        }

        canUndoMedia.value = false;
        canvas.value?.reload();
        toast.success('Image change undone');
    } catch (error) {
        toast.error(error instanceof Error ? error.message : 'Could not undo the image change.');
    }
};

const handleUndo = (): void => {
    if (editor.canUndo.value) {
        editor.undo();

        return;
    }

    if (canUndoMedia.value) {
        void undoMediaChange();
    }
};

const tabs: { value: Tab; label: string }[] = [
    { value: 'selection', label: 'Selected' },
    { value: 'content', label: 'Content' },
    { value: 'design', label: 'Design' },
    { value: 'structure', label: 'Pages' },
    { value: 'ai', label: 'AI' },
];

defineExpose({
    reloadCanvas: () => canvas.value?.reload(),
    flush: () => editor.flush(),
    showAiTab: () => {
        tab.value = 'ai';
    },
});
</script>

<template>
    <div class="flex min-h-0 flex-1 flex-col" data-test="site-editor">
        <EditorToolbar
            v-model:device="device"
            v-model:mode="mode"
            :status="editor.status.value"
            :can-undo="editor.canUndo.value || canUndoMedia"
            :can-redo="editor.canRedo.value"
            :saved-at="editor.savedAt.value"
            @undo="handleUndo"
            @redo="editor.redo"
            @save="editor.flush"
        >
            <template #start><slot name="start" /></template>
            <template #end><slot name="end" /></template>
        </EditorToolbar>

        <div
            v-if="editor.message.value"
            class="flex shrink-0 flex-wrap items-center gap-3 border-b px-3 py-2 text-sm"
            :class="
                editor.status.value === 'conflict' || editor.status.value === 'error'
                    ? 'bg-rose-50 text-rose-800 dark:bg-rose-500/10 dark:text-rose-200'
                    : 'bg-amber-50 text-amber-800 dark:bg-amber-500/10 dark:text-amber-200'
            "
            role="alert"
            data-test="editor-message"
        >
            <CircleAlert class="size-4 shrink-0" />
            <span class="flex-1">{{ editor.message.value }}</span>
            <Button
                v-if="editor.status.value === 'conflict'"
                size="sm"
                variant="outline"
                data-test="editor-reload-latest"
                @click="reloadFromServer"
            >
                Reload latest
            </Button>
            <Button
                v-if="editor.status.value === 'offline'"
                size="sm"
                variant="outline"
                data-test="editor-retry-save"
                @click="editor.flush()"
            >
                Retry save
            </Button>
        </div>

        <div
            class="flex min-h-0 flex-1 flex-col overflow-y-auto lg:grid lg:grid-cols-[380px_1fr] lg:overflow-hidden"
        >
            <aside
                class="flex h-[55vh] min-h-0 shrink-0 flex-col border-b bg-card lg:h-full lg:border-r lg:border-b-0"
                aria-label="Editor panel"
            >
                <div class="flex shrink-0 border-b p-1.5" role="tablist">
                    <button
                        v-for="item in tabs"
                        :key="item.value"
                        type="button"
                        role="tab"
                        :aria-selected="tab === item.value"
                        :data-test="`editor-tab-${item.value}`"
                        :class="[
                            'relative flex-1 rounded-lg px-1.5 py-1.5 text-[13px] font-medium transition',
                            tab === item.value
                                ? 'bg-secondary text-secondary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="tab = item.value"
                    >
                        {{ item.label }}
                        <span
                            v-if="item.value === 'ai' && aiBusy"
                            class="absolute top-1 right-1 size-1.5 animate-pulse rounded-full bg-primary"
                            aria-label="AI is working"
                        />
                    </button>
                </div>
                <div
                    class="min-h-0 flex-1 p-4"
                    :class="
                        tab === 'ai'
                            ? 'flex flex-col overflow-hidden'
                            : 'overflow-y-auto'
                    "
                >
                    <InspectorSelection
                        v-show="tab === 'selection'"
                        :selection="selection"
                        :draft="editor.draft.value"
                        :media="media"
                        :team-slug="teamSlug"
                        :site-id="site.id"
                        :ai-available="aiAvailable"
                        :ai-busy="aiBusy"
                        :section-style-choices="designChoices.sectionStyle"
                        :section-variant-choices="sectionVariantChoices"
                        :can-move-up="canMoveUp"
                        :can-move-down="canMoveDown"
                        @commit="commit"
                        @improve="$emit('improve', $event)"
                        @section-action="sectionAction"
                        @media-changed="onMediaChanged"
                    />
                    <InspectorContent
                        v-show="tab === 'content'"
                        :draft="editor.draft.value"
                        :copy-fields="copyFields"
                        @commit="commit"
                        @locate="locate"
                    />
                    <InspectorDesign
                        v-show="tab === 'design'"
                        :draft="editor.draft.value"
                        :templates="templates"
                        :palettes="palettes"
                        :reviews-styles="reviewsStyles"
                        :choices="designChoices"
                        :can-pull-brand="site.leadWebsite !== null"
                        :pulling-brand="pullingBrand"
                        @commit="commit"
                        @pull-brand="$emit('pull-brand')"
                    />
                    <InspectorStructure
                        v-show="tab === 'structure'"
                        :draft="editor.draft.value"
                        :page-options="pageOptions"
                        :section-options="sectionOptions"
                        :section-variant-choices="sectionVariantChoices"
                        :ai-available="aiAvailable"
                        :ai-busy="aiBusy"
                        :business-name="site.name"
                        :business-city="site.leadCity"
                        :business-category="site.leadCategory"
                        :preview-url="site.previewUrl"
                        @commit="commit"
                        @improve="$emit('improve', { section: $event })"
                        @redesign="$emit('improve', { section: $event, redesign: true })"
                        @locate="locateSection"
                    />
                    <div
                        v-show="tab === 'ai'"
                        class="flex min-h-0 flex-1 flex-col"
                    >
                        <slot name="ai" />
                    </div>
                </div>
            </aside>

            <EditorCanvas
                ref="canvas"
                :src="site.editorUrl"
                :device="device"
                :mode="mode"
                @message="onFrameMessage"
            />
        </div>
    </div>
</template>
