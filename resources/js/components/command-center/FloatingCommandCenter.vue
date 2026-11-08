<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { useLocalStorage } from '@vueuse/core';
import {
    ExternalLink,
    RotateCcw,
    Send,
    Sparkles,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import ChatMarkdown from '@/components/command-center/ChatMarkdown.vue';
import { formatMessageClockTime } from '@/lib/format';
import { requestHeaders } from '@/lib/siteEditor/http';
import { index as commandCenterIndex } from '@/routes/command-center';
import { destroy as resetConversation } from '@/routes/command-center/conversation';
import { history as messageHistory, store as sendMessage } from '@/routes/command-center/messages';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    body: string;
    at: string | null;
    source?: 'web' | 'whatsapp';
};

type WidgetPos = { x: number; y: number };

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const shared = computed(() => page.props.commandCenter);

const open = useLocalStorage('cc-widget-open', false);
/** v2 key — older saves parked the launcher at top-left before default placement ran. */
const savedPos = useLocalStorage<WidgetPos | null>('cc-widget-pos-v2', null);

const messages = ref<ChatMessage[]>([]);
const draft = ref('');
const chatProcessing = ref(false);
const aiAvailable = ref(shared.value?.aiAvailable ?? false);
const sending = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);
const scrollEl = ref<HTMLElement | null>(null);
const pollTimer = ref<ReturnType<typeof setInterval> | null>(null);
const ready = ref(false);

const employee = computed(
    () =>
        shared.value?.employee ?? {
            name: 'Assistant',
            title: 'Command Center',
            avatarUrl: null as string | null,
        },
);

const employeeInitials = computed(() =>
    employee.value.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

const panelWidth = 360;
const panelHeight = 520;
/** Avatar is 64px; grip tab adds a little width on the right. */
const launcherSize = 72;

const pos = ref<WidgetPos>({ x: 0, y: 0 });
const viewport = ref({ w: 1280, h: 800 });

const bottomRightPos = (): WidgetPos => ({
    x: Math.max(16, viewport.value.w - launcherSize - 24),
    y: Math.max(16, viewport.value.h - launcherSize - 24),
});

const clampPos = (next: WidgetPos): WidgetPos => {
    const maxX = Math.max(8, viewport.value.w - launcherSize - 8);
    const maxY = Math.max(8, viewport.value.h - launcherSize - 8);

    return {
        x: Math.min(maxX, Math.max(8, next.x)),
        y: Math.min(maxY, Math.max(8, next.y)),
    };
};

const syncViewport = () => {
    viewport.value = { w: window.innerWidth, h: window.innerHeight };
};

const isUsableSavedPos = (value: WidgetPos | null): value is WidgetPos => {
    if (!value || typeof value.x !== 'number' || typeof value.y !== 'number') {
        return false;
    }

    // Discard the old top-left parking spot from the first buggy default.
    if (value.x <= 40 && value.y <= 40) {
        return false;
    }

    return true;
};

onMounted(() => {
    syncViewport();
    pos.value = isUsableSavedPos(savedPos.value)
        ? clampPos(savedPos.value)
        : bottomRightPos();
    ready.value = true;

    window.addEventListener('resize', onResize);

    if (open.value) {
        void refreshThread();
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', onResize);
    detachDragListeners();
    stopPoll();
});

const onResize = () => {
    syncViewport();
    pos.value = clampPos(pos.value);
    savedPos.value = pos.value;
};

const dragging = ref(false);
const dragMoved = ref(false);
const dragOrigin = ref({ x: 0, y: 0 });
const dragOffset = ref({ x: 0, y: 0 });
const activePointerId = ref<number | null>(null);

const onWindowPointerMove = (event: PointerEvent) => {
    if (!dragging.value || event.pointerId !== activePointerId.value) {
        return;
    }

    const dx = event.clientX - dragOrigin.value.x;
    const dy = event.clientY - dragOrigin.value.y;

    if (!dragMoved.value && Math.hypot(dx, dy) < 5) {
        return;
    }

    event.preventDefault();
    dragMoved.value = true;
    pos.value = clampPos({
        x: event.clientX - dragOffset.value.x,
        y: event.clientY - dragOffset.value.y,
    });
};

const onWindowPointerUp = (event: PointerEvent) => {
    if (!dragging.value || event.pointerId !== activePointerId.value) {
        return;
    }

    detachDragListeners();
    dragging.value = false;
    activePointerId.value = null;

    if (!dragMoved.value) {
        toggle();
        return;
    }

    savedPos.value = { ...pos.value };
};

const detachDragListeners = () => {
    window.removeEventListener('pointermove', onWindowPointerMove);
    window.removeEventListener('pointerup', onWindowPointerUp);
    window.removeEventListener('pointercancel', onWindowPointerUp);
};

const onLauncherPointerDown = (event: PointerEvent) => {
    if (event.button !== 0) {
        return;
    }

    event.preventDefault();
    event.stopPropagation();

    dragging.value = true;
    dragMoved.value = false;
    activePointerId.value = event.pointerId;
    dragOrigin.value = { x: event.clientX, y: event.clientY };
    dragOffset.value = {
        x: event.clientX - pos.value.x,
        y: event.clientY - pos.value.y,
    };

    window.addEventListener('pointermove', onWindowPointerMove, { passive: false });
    window.addEventListener('pointerup', onWindowPointerUp);
    window.addEventListener('pointercancel', onWindowPointerUp);
};

const panelStyle = computed(() => {
    const left = Math.min(
        Math.max(12, pos.value.x + launcherSize - panelWidth),
        viewport.value.w - panelWidth - 12,
    );
    const top = Math.min(
        Math.max(12, pos.value.y - panelHeight - 12),
        viewport.value.h - panelHeight - 12,
    );

    return {
        left: `${left}px`,
        top: `${Math.max(12, top)}px`,
    };
});

const launcherStyle = computed(() => ({
    left: `${pos.value.x}px`,
    top: `${pos.value.y}px`,
}));

const scrollToBottom = async () => {
    await nextTick();
    if (scrollEl.value) {
        scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
    }
};

const refreshThread = async () => {
    if (!teamSlug.value) {
        return;
    }

    loading.value = true;
    error.value = null;

    try {
        const response = await fetch(
            messageHistory({ current_team: teamSlug.value }).url,
            { headers: requestHeaders(), credentials: 'same-origin' },
        );

        if (!response.ok) {
            error.value = 'Could not load the conversation.';
            return;
        }

        const data = (await response.json()) as {
            messages: ChatMessage[];
            chatProcessing: boolean;
            aiAvailable: boolean;
        };

        messages.value = data.messages ?? [];
        chatProcessing.value = Boolean(data.chatProcessing);
        aiAvailable.value = Boolean(data.aiAvailable);
        await scrollToBottom();

        if (chatProcessing.value) {
            startPoll();
        } else {
            stopPoll();
        }
    } finally {
        loading.value = false;
    }
};

const startPoll = () => {
    if (pollTimer.value !== null) {
        return;
    }

    pollTimer.value = setInterval(() => {
        void refreshThread();
    }, 1600);
};

const stopPoll = () => {
    if (pollTimer.value !== null) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
};

watch(open, (isOpen) => {
    if (isOpen) {
        void refreshThread();
    } else {
        stopPoll();
    }
});

const toggle = () => {
    open.value = !open.value;
};

const send = async () => {
    const body = draft.value.trim();

    if (!teamSlug.value || !body || chatProcessing.value || sending.value) {
        return;
    }

    sending.value = true;
    error.value = null;
    messages.value = [
        ...messages.value,
        {
            id: `local-${Date.now()}`,
            role: 'user',
            body,
            at: new Date().toISOString(),
            source: 'web',
        },
    ];
    draft.value = '';
    chatProcessing.value = true;
    await scrollToBottom();
    startPoll();

    try {
        const response = await fetch(sendMessage({ current_team: teamSlug.value }).url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                ...requestHeaders(),
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ message: body }),
        });

        if (!response.ok) {
            const payload = (await response.json().catch(() => null)) as {
                message?: string;
                errors?: Record<string, string[]>;
            } | null;
            error.value =
                payload?.errors?.message?.[0] ??
                payload?.message ??
                'Message could not be sent.';
            const lastUser = [...messages.value]
                .reverse()
                .find((message) => message.role === 'user' && message.body === body);
            if (lastUser) {
                messages.value = messages.value.filter((message) => message.id !== lastUser.id);
            }
            chatProcessing.value = false;
            stopPoll();
            return;
        }

        await refreshThread();
    } finally {
        sending.value = false;
    }
};

const clearChat = async () => {
    if (!teamSlug.value) {
        return;
    }

    await fetch(resetConversation({ current_team: teamSlug.value }).url, {
        method: 'DELETE',
        credentials: 'same-origin',
        headers: requestHeaders(),
    });

    await refreshThread();
};

const fullPageUrl = computed(() =>
    teamSlug.value ? commandCenterIndex({ current_team: teamSlug.value }).url : '#',
);
</script>

<template>
    <Teleport to="body">
        <div
            v-if="teamSlug && shared && ready"
            class="pointer-events-none fixed inset-0 z-[80]"
            data-test="command-center-widget"
        >
            <div
                v-if="open"
                class="pointer-events-auto fixed flex w-[min(100vw-1.5rem,22.5rem)] flex-col overflow-hidden rounded-2xl border border-border/80 bg-card shadow-2xl shadow-black/20"
                :style="{ ...panelStyle, height: `${panelHeight}px` }"
            >
                <header
                    class="flex shrink-0 items-center gap-3 border-b border-border/70 bg-card px-3.5 py-3"
                >
                    <div class="relative shrink-0">
                        <img
                            v-if="employee.avatarUrl"
                            :src="employee.avatarUrl"
                            :alt="employee.name"
                            class="size-10 rounded-full object-cover ring-2 ring-primary/20"
                        />
                        <div
                            v-else
                            class="flex size-10 items-center justify-center rounded-full bg-primary text-xs font-semibold text-primary-foreground"
                        >
                            {{ employeeInitials }}
                        </div>
                        <span
                            class="absolute -right-0.5 -bottom-0.5 size-3 rounded-full border-2 border-card bg-emerald-500"
                            title="Online"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold">
                            {{ employee.name }}
                        </p>
                        <p class="truncate text-[11px] text-muted-foreground">
                            Command Center · same thread as WhatsApp
                        </p>
                    </div>
                    <Link
                        :href="fullPageUrl"
                        class="grid size-8 place-items-center rounded-lg text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        title="Open full Command Center"
                        aria-label="Open full Command Center"
                    >
                        <ExternalLink class="size-4" />
                    </Link>
                    <button
                        type="button"
                        class="grid size-8 place-items-center rounded-lg text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        aria-label="Close chat"
                        @click="open = false"
                    >
                        <X class="size-4" />
                    </button>
                </header>

                <div
                    class="flex shrink-0 items-center justify-between border-b border-border/50 px-3.5 py-2"
                >
                    <span class="text-xs font-medium text-muted-foreground">Chat</span>
                    <button
                        type="button"
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[11px] font-medium text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="clearChat"
                    >
                        <RotateCcw class="size-3" />
                        Clear chat
                    </button>
                </div>

                <div
                    ref="scrollEl"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto bg-muted/30 px-3 py-3"
                >
                    <p
                        v-if="loading && messages.length === 0"
                        class="py-8 text-center text-xs text-muted-foreground"
                    >
                        Loading conversation…
                    </p>

                    <template v-for="message in messages" :key="message.id">
                        <div
                            v-if="message.role === 'user'"
                            class="flex justify-end"
                        >
                            <div
                                class="max-w-[85%] rounded-2xl rounded-br-md bg-primary px-3 py-2 text-[13px] leading-5 text-primary-foreground shadow-sm"
                            >
                                <ChatMarkdown :source="message.body" />
                                <p
                                    v-if="message.at"
                                    class="mt-1 text-right text-[10px] text-primary-foreground/70"
                                >
                                    {{ formatMessageClockTime(message.at) }}
                                </p>
                            </div>
                        </div>
                        <div v-else class="flex items-start gap-2">
                            <img
                                v-if="employee.avatarUrl"
                                :src="employee.avatarUrl"
                                :alt="employee.name"
                                class="mt-0.5 size-7 shrink-0 rounded-full object-cover"
                            />
                            <div
                                class="max-w-[85%] rounded-2xl rounded-tl-md border border-border/60 bg-card px-3 py-2 text-[13px] leading-5 text-foreground shadow-sm"
                            >
                                <ChatMarkdown :source="message.body" />
                                <p
                                    v-if="message.at"
                                    class="mt-1 text-right text-[10px] text-muted-foreground"
                                >
                                    {{ formatMessageClockTime(message.at) }}
                                </p>
                            </div>
                        </div>
                    </template>

                    <div
                        v-if="chatProcessing || sending"
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-primary [animation-delay:0ms]"
                        />
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-primary [animation-delay:150ms]"
                        />
                        <span
                            class="size-1.5 animate-bounce rounded-full bg-primary [animation-delay:300ms]"
                        />
                        <span>{{ employee.name }} is typing…</span>
                    </div>
                </div>

                <form
                    class="shrink-0 border-t border-border/70 bg-card p-2.5"
                    @submit.prevent="send"
                >
                    <div
                        class="flex items-end gap-2 rounded-xl border border-border bg-muted/40 p-1.5"
                    >
                        <textarea
                            v-model="draft"
                            rows="1"
                            :placeholder="`Message ${employee.name}…`"
                            :disabled="!aiAvailable || chatProcessing"
                            class="max-h-24 min-h-10 flex-1 resize-none border-0 bg-transparent px-2 py-2 text-sm outline-none placeholder:text-muted-foreground"
                            @keydown.enter.exact.prevent="send"
                        />
                        <button
                            type="submit"
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-primary text-primary-foreground transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                            :disabled="
                                !aiAvailable ||
                                chatProcessing ||
                                sending ||
                                !draft.trim()
                            "
                            aria-label="Send"
                        >
                            <Send class="size-4" />
                        </button>
                    </div>
                    <p
                        v-if="error"
                        class="mt-1.5 px-1 text-[11px] text-destructive"
                    >
                        {{ error }}
                    </p>
                    <p
                        v-else-if="!aiAvailable"
                        class="mt-1.5 px-1 text-[11px] text-amber-600 dark:text-amber-400"
                    >
                        Set OPENAI_API_KEY to chat with {{ employee.name }}.
                    </p>
                </form>
            </div>

            <div
                class="pointer-events-auto fixed touch-none select-none"
                :style="launcherStyle"
                data-test="command-center-launcher"
                :aria-label="open ? 'Close Command Center chat' : 'Open Command Center chat'"
                role="button"
                tabindex="0"
                :class="dragging && dragMoved ? 'cursor-grabbing' : 'cursor-grab'"
                @pointerdown="onLauncherPointerDown"
                @keydown.enter.prevent="toggle"
                @keydown.space.prevent="toggle"
            >
                <div class="relative size-16">
                    <span
                        class="pointer-events-none absolute -inset-2 rounded-full bg-primary/15 blur-[2px]"
                        aria-hidden="true"
                    />
                    <span
                        class="pointer-events-none absolute -inset-1 rounded-full ring-2 ring-primary/25"
                        aria-hidden="true"
                    />

                    <div
                        class="relative size-16 overflow-hidden rounded-full border border-white/80 bg-primary shadow-[0_10px_28px_rgba(15,23,42,0.28)]"
                    >
                        <img
                            v-if="employee.avatarUrl && !open"
                            :src="employee.avatarUrl"
                            :alt="employee.name"
                            class="pointer-events-none size-full object-cover"
                            draggable="false"
                        />
                        <span
                            v-else-if="!open"
                            class="grid size-full place-items-center text-sm font-bold text-primary-foreground"
                        >
                            {{ employeeInitials }}
                        </span>
                        <span
                            v-else
                            class="grid size-full place-items-center bg-primary text-primary-foreground"
                        >
                            <X class="size-6" />
                        </span>
                    </div>

                    <span
                        class="pointer-events-none absolute -top-0.5 -left-0.5 grid size-6 place-items-center rounded-full bg-primary text-primary-foreground shadow-md ring-2 ring-background"
                        aria-hidden="true"
                    >
                        <Sparkles class="size-3" />
                    </span>

                    <span
                        class="pointer-events-none absolute top-1/2 -right-2.5 flex h-9 w-4 -translate-y-1/2 items-center justify-center rounded-r-md bg-slate-700 shadow-md"
                        aria-hidden="true"
                    >
                        <span class="grid grid-cols-2 gap-[2px]">
                            <span
                                v-for="n in 6"
                                :key="n"
                                class="size-[3px] rounded-full bg-white/90"
                            />
                        </span>
                    </span>

                    <span
                        v-if="!open"
                        class="pointer-events-none absolute right-0.5 bottom-0.5 size-3.5 rounded-full border-2 border-background bg-emerald-500"
                        title="Online"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>
