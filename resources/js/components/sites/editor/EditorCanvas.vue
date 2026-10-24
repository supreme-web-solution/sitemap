<script setup lang="ts">
import { LoaderCircle } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import {
    FRAME_SOURCE,
    HOST_SOURCE,
    isFrameMessage,
} from '@/lib/siteEditor/protocol';
import type { FrameMessage, HostMessage } from '@/lib/siteEditor/protocol';

export type Device = 'desktop' | 'tablet' | 'mobile';

const props = defineProps<{
    src: string;
    device: Device;
    mode: 'edit' | 'preview';
}>();

const emit = defineEmits<{ message: [message: FrameMessage] }>();

const frame = ref<HTMLIFrameElement | null>(null);
const loading = ref(true);
const ready = ref(false);

const widthClass = computed(() => {
    if (props.device === 'mobile') {
        return 'w-[390px]';
    }

    if (props.device === 'tablet') {
        return 'w-[768px]';
    }

    return 'w-full';
});

const post = (message: HostMessage): void => {
    frame.value?.contentWindow?.postMessage(
        { source: HOST_SOURCE, ...message },
        window.location.origin,
    );
};

const reload = (): void => {
    loading.value = true;

    if (ready.value) {
        ready.value = false;
        post({ type: 'reload' });
    } else if (frame.value) {
        frame.value.src = props.src;
    }
};

const onMessage = (event: MessageEvent): void => {
    if (
        event.origin !== window.location.origin ||
        event.source !== frame.value?.contentWindow ||
        !isFrameMessage(event.data)
    ) {
        return;
    }

    const message = event.data;

    if (message.type === 'ready') {
        ready.value = true;
        loading.value = false;
        post({ type: 'mode', mode: props.mode });
    }

    emit('message', message);
};

onMounted(() => window.addEventListener('message', onMessage));
onBeforeUnmount(() => window.removeEventListener('message', onMessage));

watch(
    () => props.mode,
    (mode) => post({ type: 'mode', mode }),
);

defineExpose({ post, reload, source: FRAME_SOURCE });
</script>

<template>
    <div
        class="relative flex min-h-0 flex-1 justify-center overflow-auto bg-muted/40"
        data-test="editor-canvas"
    >
        <iframe
            ref="frame"
            :src="src"
            title="Website editor"
            :class="[
                'bg-white transition-[width] duration-200',
                device === 'desktop'
                    ? 'h-full min-h-[480px]'
                    : 'my-4 h-[calc(100%-2rem)] min-h-[480px] rounded-lg border shadow-sm',
                widthClass,
            ]"
        />
        <div
            v-if="loading"
            class="pointer-events-none absolute inset-0 flex items-start justify-center bg-background/40 pt-6 backdrop-blur-[1px]"
            role="status"
        >
            <span
                class="flex items-center gap-2 rounded-full bg-background px-3 py-1.5 text-xs shadow"
            >
                <LoaderCircle class="size-3.5 animate-spin" /> Loading preview…
            </span>
        </div>
    </div>
</template>
