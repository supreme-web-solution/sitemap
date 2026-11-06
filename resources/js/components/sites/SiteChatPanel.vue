<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Paperclip, Sparkles, X } from '@lucide/vue';
import {
    computed,
    inject,
    nextTick,
    onMounted,
    ref,
    watch,
    type ComputedRef,
    type Ref,
} from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import type { SiteEditorAiContext } from '@/components/sites/editor/SiteEditorWorkspace.vue';
import type { PreviewEdit } from '@/lib/siteEditor/protocol';
import { formatMessageClockTime, formatRelativeTime } from '@/lib/format';
import { destroy as cancelChatEdit, store } from '@/routes/sites/chat';
import type { SiteChat, SiteChatMessage } from '@/types';

const MAX_ATTACHMENTS = 5;

const props = defineProps<{ siteId: number; chat: SiteChat; embedded?: boolean }>();

const emit = defineEmits<{ success: [] }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const aiContext = inject<ComputedRef<SiteEditorAiContext>>(
    'siteEditorAiContext',
    computed(() => ({ design_target: null, preview_edits: [], label: null })),
);

const form = useForm<{
    body: string;
    attachments: File[];
    design_target?: string;
    preview_edits?: PreviewEdit[];
}>({
    body: '',
    attachments: [],
});

const pendingUserText = ref<string | null>(null);
const pendingAttachmentPreviews = ref<string[]>([]);
const messageScroll = ref<HTMLElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const composer = ref<HTMLTextAreaElement | null>(null);

const attachmentPreviewUrls = ref<string[]>([]);

watch(
    () => form.attachments,
    (files) => {
        for (const url of attachmentPreviewUrls.value) {
            URL.revokeObjectURL(url);
        }

        attachmentPreviewUrls.value = files.map((file) => URL.createObjectURL(file));
    },
    { deep: true },
);

const messageAttachmentUrls = (message: SiteChatMessage): string[] => {
    if (message.attachmentUrls?.length) {
        return message.attachmentUrls;
    }

    if (message.attachmentUrl) {
        return [message.attachmentUrl];
    }

    return [];
};

const displayMessages = computed((): SiteChatMessage[] => {
    const messages = [...props.chat.messages];

    if (pendingUserText.value !== null || pendingAttachmentPreviews.value.length > 0) {
        messages.push({
            id: -1,
            role: 'user',
            body: pendingUserText.value ?? '',
            status: 'done',
            at: new Date().toISOString(),
            attachmentUrls: pendingAttachmentPreviews.value,
        });
    }

    return messages;
});

const isPendingAssistant = (message: SiteChatMessage): boolean =>
    message.role === 'assistant' && message.status === 'pending';

const showBuildLog = computed(() => props.chat.busy || form.processing);

const buildLogLines = computed((): string[] => {
    const lines = props.chat.progressLines ?? [];

    if (lines.length > 0) {
        return lines;
    }

    if (showBuildLog.value) {
        return ['Reading your request'];
    }

    return [];
});

const visibleMessages = computed(() =>
    displayMessages.value.filter(
        (message) => !(showBuildLog.value && isPendingAssistant(message)),
    ),
);

const editorTab = inject<Ref<string>>('siteEditorTab', ref('selection'));

const scrollToLatest = () => {
    nextTick(() => {
        requestAnimationFrame(() => {
            const el = messageScroll.value;

            if (el) {
                el.scrollTop = el.scrollHeight;
            }
        });
    });
};

const clearAttachments = () => {
    for (const url of attachmentPreviewUrls.value) {
        URL.revokeObjectURL(url);
    }

    attachmentPreviewUrls.value = [];
    form.attachments = [];

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const removeAttachment = (index: number) => {
    form.attachments = form.attachments.filter((_, i) => i !== index);

    if (form.attachments.length === 0 && fileInput.value) {
        fileInput.value.value = '';
    }
};

const addAttachmentFile = (file: File | null) => {
    if (file === null || !file.type.startsWith('image/')) {
        return;
    }

    if (form.attachments.length >= MAX_ATTACHMENTS) {
        return;
    }

    const duplicate = form.attachments.some(
        (existing) =>
            existing.name === file.name &&
            existing.size === file.size &&
            existing.lastModified === file.lastModified,
    );

    if (!duplicate) {
        form.attachments = [...form.attachments, file];
    }
};

const onFileSelected = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const files = input.files ? Array.from(input.files) : [];

    for (const file of files) {
        if (form.attachments.length >= MAX_ATTACHMENTS) {
            break;
        }

        addAttachmentFile(file);
    }

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const onPaste = (event: ClipboardEvent) => {
    const items = event.clipboardData?.items;

    if (!items) {
        return;
    }

    for (const item of items) {
        if (item.type.startsWith('image/')) {
            const file = item.getAsFile();

            if (file && form.attachments.length < MAX_ATTACHMENTS) {
                event.preventDefault();
                addAttachmentFile(file);

                return;
            }
        }
    }
};

watch(displayMessages, scrollToLatest, { deep: true, immediate: true });
watch(buildLogLines, scrollToLatest, { deep: true });
watch(
    () => props.chat.messages,
    () => scrollToLatest(),
    { deep: true },
);

watch(
    () => editorTab.value,
    (activeTab) => {
        if (activeTab === 'ai') {
            scrollToLatest();
            window.setTimeout(scrollToLatest, 50);
            window.setTimeout(scrollToLatest, 200);
        }
    },
    { immediate: true },
);

onMounted(() => {
    scrollToLatest();
    window.setTimeout(scrollToLatest, 50);
    window.setTimeout(scrollToLatest, 200);
});

const canSubmit = computed(
    () =>
        form.body.trim() !== '' ||
        form.attachments.length > 0 ||
        aiContext.value.preview_edits.length > 0 ||
        aiContext.value.design_target !== null,
);

const attachmentError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return (
        errors.attachments ??
        errors['attachments.0'] ??
        errors.attachment ??
        undefined
    );
});

const submit = () => {
    const text = form.body.trim();

    if (!canSubmit.value) {
        return;
    }

    pendingUserText.value = text;

    if (form.attachments.length > 0) {
        pendingAttachmentPreviews.value = form.attachments.map((file) =>
            URL.createObjectURL(file),
        );
    }

    const edits = aiContext.value.preview_edits;
    const payload: {
        body: string;
        attachments: File[];
        design_target?: string;
        preview_edits?: PreviewEdit[];
    } = { body: text, attachments: [...form.attachments] };

    if (aiContext.value.design_target) {
        payload.design_target = aiContext.value.design_target;
    }

    if (edits.length > 0) {
        payload.preview_edits = edits;
    }

    form.body = '';
    clearAttachments();

    scrollToLatest();

    form
        .transform(() => payload)
        .post(store.url({ current_team: teamSlug.value, site: props.siteId }), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                for (const url of pendingAttachmentPreviews.value) {
                    URL.revokeObjectURL(url);
                }

                pendingUserText.value = null;
                pendingAttachmentPreviews.value = [];
                form.reset('body', 'attachments');
                form.transform((data) => data);
                scrollToLatest();
            },
            onFinish: () => {
                form.transform((data) => data);
            },
        });
};

const cancelling = ref(false);
const retryingMessageId = ref<number | null>(null);

const retryEdit = (message: SiteChatMessage) => {
    if (!message.canRetry || props.chat.busy || retryingMessageId.value !== null) {
        return;
    }

    retryingMessageId.value = message.id;

    router.post(
        `/${teamSlug.value}/sites/${props.siteId}/chat/retry`,
        { message_id: message.id },
        {
            preserveScroll: true,
            onFinish: () => {
                retryingMessageId.value = null;
            },
        },
    );
};

const cancelEdit = () => {
    if (!props.chat.busy || cancelling.value) {
        return;
    }

    cancelling.value = true;

    router.delete(cancelChatEdit.url({ current_team: teamSlug.value, site: props.siteId }), {
        preserveScroll: true,
        onFinish: () => {
            cancelling.value = false;
        },
    });
};
</script>

<template>
    <section
        :class="[
            embedded ? 'flex min-h-0 flex-1 flex-col' : 'panel p-5',
        ]"
        data-test="site-chat-panel"
    >
        <h2
            class="flex shrink-0 items-center gap-2 text-base font-semibold"
        >
            <Sparkles class="size-4" /> Edit with AI
        </h2>

        <p
            v-if="!chat.available"
            class="mt-3 shrink-0 text-sm text-amber-700 dark:text-amber-300"
        >
            AI editing is not included in your plan.
        </p>

        <template v-else>
            <div
                ref="messageScroll"
                :class="[
                    'mt-3 min-h-0 flex-1 overflow-y-auto overscroll-contain',
                    embedded ? '' : 'max-h-72',
                ]"
            >
                <ol
                    v-if="visibleMessages.length || showBuildLog"
                    class="grid gap-2 pr-1 text-sm"
                >
                    <li
                        v-for="message in visibleMessages"
                        :key="message.id"
                        class="rounded-lg px-3 py-2"
                        :class="
                            message.role === 'user'
                                ? 'ml-auto max-w-[85%] bg-primary/10'
                                : 'bg-muted'
                        "
                    >
                        <p
                            v-if="message.at"
                            class="mb-1 text-[10px] text-muted-foreground"
                            :title="formatMessageClockTime(message.at)"
                        >
                            {{ formatRelativeTime(message.at) }}
                        </p>
                        <div
                            v-if="messageAttachmentUrls(message).length"
                            class="mb-2 flex flex-wrap gap-2"
                        >
                            <img
                                v-for="(url, attachmentIndex) in messageAttachmentUrls(message)"
                                :key="`${message.id}-${attachmentIndex}`"
                                :src="url"
                                alt="Screenshot attached to this message"
                                class="size-20 rounded-md border border-border/60 object-cover object-top"
                            />
                        </div>
                        <template v-if="isPendingAssistant(message)">
                            <p class="whitespace-pre-wrap break-words text-muted-foreground">
                                {{ message.body }}
                            </p>
                            <p class="mt-2 flex items-center gap-1.5 text-[11px] text-muted-foreground">
                                <LoaderCircle class="size-3 animate-spin" />
                                Still working…
                            </p>
                        </template>
                        <template v-else>
                            <p
                                v-if="message.body"
                                class="whitespace-pre-wrap break-words"
                            >
                                {{ message.body }}
                            </p>
                            <div
                                v-if="message.role === 'assistant' && message.status === 'failed'"
                                class="mt-3 space-y-2 border-t border-border/50 pt-2"
                                data-test="chat-edit-failed"
                            >
                                <p class="text-[11px] text-destructive">
                                    This edit could not be completed.
                                </p>
                                <p
                                    v-if="
                                        message.retryAttemptsUsed &&
                                        message.retryLimit &&
                                        message.retryAttemptsUsed < message.retryLimit &&
                                        message.canRetry
                                    "
                                    class="text-[11px] text-muted-foreground"
                                >
                                    Attempt {{ message.retryAttemptsUsed }} of
                                    {{ message.retryLimit }}.
                                </p>
                                <Button
                                    v-if="message.showRetryButton"
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    class="h-8 w-full"
                                    :disabled="
                                        !message.canRetry ||
                                        retryingMessageId === message.id ||
                                        retryingMessageId !== null
                                    "
                                    data-test="chat-retry-edit"
                                    @click="retryEdit(message)"
                                >
                                    <LoaderCircle
                                        v-if="retryingMessageId === message.id"
                                        class="size-3.5 animate-spin"
                                    />
                                    {{
                                        retryingMessageId === message.id
                                            ? 'Retrying…'
                                            : 'Try again'
                                    }}
                                </Button>
                                <p
                                    v-else-if="message.retryBlockedMessage"
                                    class="text-[11px] leading-relaxed text-muted-foreground"
                                    data-test="chat-retry-blocked"
                                >
                                    {{ message.retryBlockedMessage }}
                                </p>
                            </div>
                        </template>
                    </li>

                    <li
                        v-if="showBuildLog"
                        class="rounded-lg bg-muted px-3 py-2"
                        data-test="ai-build-log"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="flex items-center gap-1.5 text-xs font-medium">
                                <LoaderCircle class="size-3.5 animate-spin" />
                                Building your edit…
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="h-7 shrink-0 px-2 text-xs"
                                :disabled="cancelling"
                                data-test="chat-cancel-edit"
                                @click="cancelEdit"
                            >
                                {{ cancelling ? 'Cancelling…' : 'Cancel' }}
                            </Button>
                        </div>
                        <ul class="mt-2 space-y-1 font-mono text-[11px] leading-relaxed">
                            <li
                                v-for="(step, index) in buildLogLines"
                                :key="`${index}-${step}`"
                                :class="
                                    index < buildLogLines.length - 1
                                        ? 'text-muted-foreground'
                                        : 'text-foreground'
                                "
                            >
                                <span v-if="index < buildLogLines.length - 1">✓</span>
                                <span v-else>→</span>
                                {{ step }}<span v-if="index === buildLogLines.length - 1">…</span>
                            </li>
                        </ul>
                    </li>
                </ol>
            </div>

            <form class="mt-3 grid shrink-0 gap-2 border-t border-border/60 pt-3" @submit.prevent="submit">
                <div
                    class="rounded-md border border-input bg-transparent focus-within:ring-2 focus-within:ring-ring/40"
                >
                    <div
                        v-if="form.attachments.length"
                        class="flex flex-wrap gap-2 p-2"
                        data-test="chat-attachment-preview"
                    >
                        <div
                            v-for="(url, index) in attachmentPreviewUrls"
                            :key="`${url}-${index}`"
                            class="relative inline-block"
                        >
                            <img
                                :src="url"
                                alt="Screenshot to send with your edit"
                                class="size-20 rounded-md border border-border/60 object-cover object-top"
                            />
                            <button
                                type="button"
                                class="absolute -top-1 -right-1 rounded-full border border-border bg-background p-0.5 text-muted-foreground shadow-sm hover:text-foreground"
                                :aria-label="`Remove screenshot ${index + 1}`"
                                data-test="chat-attachment-remove"
                                @click="removeAttachment(index)"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                        <p
                            v-if="form.attachments.length >= MAX_ATTACHMENTS"
                            class="w-full text-[11px] text-muted-foreground"
                        >
                            Up to {{ MAX_ATTACHMENTS }} screenshots per message.
                        </p>
                    </div>
                    <textarea
                        ref="composer"
                        v-model="form.body"
                        rows="3"
                        maxlength="1000"
                        class="w-full resize-none border-0 bg-transparent px-3 py-2 text-sm focus-visible:outline-none"
                        placeholder="Describe a change, or paste screenshots (up to 5)…"
                        :disabled="chat.busy || form.processing"
                        aria-label="AI edit instruction"
                        @keydown.enter.exact.prevent="submit"
                        @paste="onPaste"
                    />
                </div>
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="hidden"
                    multiple
                    data-test="chat-attachment-input"
                    @change="onFileSelected"
                />
                <InputError :message="form.errors.body || attachmentError" />
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="chat.busy || form.processing || form.attachments.length >= MAX_ATTACHMENTS"
                        aria-label="Attach screenshot"
                        data-test="chat-attachment-open"
                        @click="fileInput?.click()"
                    >
                        <Paperclip class="size-4" />
                    </Button>
                    <Button
                        type="submit"
                        size="sm"
                        class="flex-1"
                        :disabled="chat.busy || form.processing || !canSubmit"
                    >
                        <LoaderCircle
                            v-if="chat.busy || form.processing"
                            class="animate-spin"
                        />
                        {{ chat.busy ? 'Editing…' : form.processing ? 'Sending…' : 'Apply edit' }}
                    </Button>
                </div>
            </form>
        </template>
    </section>
</template>
