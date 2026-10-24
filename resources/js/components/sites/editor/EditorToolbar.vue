<script setup lang="ts">
import {
    CircleAlert,
    CircleCheck,
    Eye,
    LoaderCircle,
    Monitor,
    MousePointerClick,
    Redo2,
    Smartphone,
    Tablet,
    Undo2,
    WifiOff,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { SaveStatus } from '@/composables/useSiteEditor';
import type { Device } from './EditorCanvas.vue';

const props = defineProps<{
    status: SaveStatus;
    canUndo: boolean;
    canRedo: boolean;
    device: Device;
    mode: 'edit' | 'preview';
    savedAt: Date | null;
}>();

defineEmits<{
    undo: [];
    redo: [];
    'update:device': [device: Device];
    'update:mode': [mode: 'edit' | 'preview'];
    save: [];
}>();

const statusLabel = computed(() => {
    switch (props.status) {
        case 'saving':
            return 'Saving…';
        case 'dirty':
            return 'Unsaved changes';
        case 'offline':
            return 'Offline — will save when you reconnect';
        case 'conflict':
            return 'Out of date';
        case 'error':
            return 'Not saved';
        default:
            return props.savedAt
                ? `Saved ${props.savedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
                : 'All changes saved';
    }
});

const devices: { value: Device; label: string }[] = [
    { value: 'desktop', label: 'Desktop' },
    { value: 'tablet', label: 'Tablet' },
    { value: 'mobile', label: 'Mobile' },
];
</script>

<template>
    <header
        class="flex shrink-0 flex-wrap items-center gap-x-3 gap-y-2 border-b bg-card px-3 py-2"
        role="toolbar"
        aria-label="Editor tools"
        data-test="editor-toolbar"
    >
        <div class="flex min-w-0 items-center gap-2">
            <slot name="start" />
        </div>

        <div class="hidden h-6 w-px bg-border md:block" aria-hidden="true" />

        <div class="flex items-center gap-1">
            <Button
                size="icon-sm"
                variant="ghost"
                :disabled="!canUndo"
                aria-label="Undo"
                title="Undo (Ctrl+Z)"
                data-test="editor-undo"
                @click="$emit('undo')"
            >
                <Undo2 />
            </Button>
            <Button
                size="icon-sm"
                variant="ghost"
                :disabled="!canRedo"
                aria-label="Redo"
                title="Redo (Ctrl+Shift+Z)"
                data-test="editor-redo"
                @click="$emit('redo')"
            >
                <Redo2 />
            </Button>
        </div>

        <span
            class="flex items-center gap-1.5 text-xs"
            :class="{
                'text-muted-foreground': status === 'saved' || status === 'saving' || status === 'dirty',
                'text-amber-600 dark:text-amber-400': status === 'offline' || status === 'conflict',
                'text-destructive': status === 'error',
            }"
            role="status"
            data-test="editor-status"
        >
            <LoaderCircle v-if="status === 'saving'" class="size-3.5 animate-spin" />
            <WifiOff v-else-if="status === 'offline'" class="size-3.5" />
            <CircleAlert v-else-if="status === 'conflict' || status === 'error'" class="size-3.5" />
            <CircleCheck v-else-if="status === 'saved'" class="size-3.5" />
            {{ statusLabel }}
        </span>
        <Button
            v-if="status === 'dirty' || status === 'error' || status === 'offline'"
            size="sm"
            variant="outline"
            class="h-7"
            data-test="editor-save-now"
            @click="$emit('save')"
        >
            {{ status === 'offline' ? 'Retry save' : 'Save now' }}
        </Button>

        <div class="mx-auto flex items-center gap-2">
            <div class="flex rounded-lg border p-0.5" role="group" aria-label="Mode">
                <Button
                    size="sm"
                    class="h-7"
                    :variant="mode === 'edit' ? 'secondary' : 'ghost'"
                    :aria-pressed="mode === 'edit'"
                    @click="$emit('update:mode', 'edit')"
                >
                    <MousePointerClick /> Edit
                </Button>
                <Button
                    size="sm"
                    class="h-7"
                    :variant="mode === 'preview' ? 'secondary' : 'ghost'"
                    :aria-pressed="mode === 'preview'"
                    @click="$emit('update:mode', 'preview')"
                >
                    <Eye /> Preview
                </Button>
            </div>
            <div class="flex rounded-lg border p-0.5" role="group" aria-label="Device">
                <Button
                    v-for="item in devices"
                    :key="item.value"
                    size="icon-sm"
                    class="size-7"
                    :variant="device === item.value ? 'secondary' : 'ghost'"
                    :aria-label="`${item.label} preview`"
                    :aria-pressed="device === item.value"
                    :title="item.label"
                    @click="$emit('update:device', item.value)"
                >
                    <Monitor v-if="item.value === 'desktop'" />
                    <Tablet v-else-if="item.value === 'tablet'" />
                    <Smartphone v-else />
                </Button>
            </div>
        </div>

        <div class="flex items-center gap-1.5">
            <slot name="end" />
        </div>
    </header>
</template>
