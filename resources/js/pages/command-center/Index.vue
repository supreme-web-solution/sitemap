<script setup lang="ts">
import { Head, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    AlertCircle,
    ChevronDown,
    ChevronUp,
    Battery,
    Copy,
    Globe,
    MessageSquare,
    RotateCcw,
    Send,
    Smartphone,
    Wifi,
} from '@lucide/vue';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import ChatMarkdown from '@/components/command-center/ChatMarkdown.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { chatDayKey, formatChatDayLabel, formatMessageClockTime } from '@/lib/format';
import { requestHeaders } from '@/lib/siteEditor/http';
import { index as commandCenterIndex, mode as updateCommandCenterMode } from '@/routes/command-center';
import { destroy as resetConversation } from '@/routes/command-center/conversation';
import { history as messageHistory, store as sendMessage } from '@/routes/command-center/messages';
import {
    approve as approvePendingAction,
    dismiss as dismissPendingAction,
} from '@/routes/command-center/pending';
import type { Team } from '@/types';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    body: string;
    at: string | null;
    source?: 'web' | 'whatsapp';
};

type WhatsAppSetup = {
    configured: boolean;
    platformNumber: string | null;
    platformNumberDisplay: string | null;
    linkCode: string | null;
    waMeUrl: string | null;
    linked: boolean;
    linkedAt: string | null;
    webhookUrl: string | null;
};

const props = defineProps<{
    employee: { name: string; title: string; avatarUrl: string | null };
    messages: ChatMessage[];
    hasOlderMessages: boolean;
    chatProcessing: boolean;
    whatsapp: WhatsAppSetup;
    mode: string;
    modeOptions: { value: string; label: string }[];
    aiAvailable: boolean;
    pendingActions: {
        id: number;
        summary: string;
        action: string;
        createdAt: string | null;
    }[];
    quickPrompts: string[];
    recentRuns: {
        triggerLabel: string;
        summary: string;
        leadName: string | null;
        createdAt: string | null;
    }[];
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Command center',
                href: layoutProps.currentTeam
                    ? commandCenterIndex({ current_team: layoutProps.currentTeam.slug }).url
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const employeeInitials = computed(() =>
    props.employee.name
        .split(/\s+/)
        .map((part) => part[0])
        .join('')
        .slice(0, 2)
        .toUpperCase(),
);

const thinkingLabel = computed(() => `${props.employee.name} is thinking…`);

const optimisticMessages = ref<ChatMessage[]>([]);
const olderMessages = ref<ChatMessage[]>([]);
const hasOlder = ref(props.hasOlderMessages);
const loadingOlder = ref(false);
const pinToBottom = ref(true);

watch(
    () => props.hasOlderMessages,
    (value) => {
        if (olderMessages.value.length === 0) {
            hasOlder.value = value;
        }
    },
);

watch(
    () => props.messages,
    (messages) => {
        if (messages.length === 1 && messages[0]?.id === 'welcome') {
            olderMessages.value = [];
            hasOlder.value = false;
        }
    },
);

const displayMessages = computed((): ChatMessage[] => {
    const server = props.messages;
    const seen = new Set(server.map((message) => message.id));
    const older = olderMessages.value.filter((message) => !seen.has(message.id));
    const pending = optimisticMessages.value.filter(
        (local) => !server.some((message) => message.role === 'user' && message.body === local.body),
    );

    return [...older, ...server, ...pending];
});

type TimelineRow =
    | { kind: 'day'; id: string; label: string }
    | { kind: 'message'; message: ChatMessage };

const timeline = computed((): TimelineRow[] => {
    const rows: TimelineRow[] = [];
    let previousDay = '';

    for (const message of displayMessages.value) {
        if (message.at) {
            const day = chatDayKey(message.at);

            if (day !== previousDay) {
                rows.push({
                    kind: 'day',
                    id: `day-${day}`,
                    label: formatChatDayLabel(message.at),
                });
                previousDay = day;
            }
        }

        rows.push({ kind: 'message', message });
    }

    return rows;
});

const scrollEl = ref<HTMLElement | null>(null);

const scrollToBottom = () => {
    nextTick(() => {
        if (scrollEl.value) {
            scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
        }
    });
};

const oldestLoadedId = computed((): number | null => {
    const ids = displayMessages.value
        .map((message) => Number(message.id))
        .filter((id) => Number.isInteger(id) && id > 0);

    return ids.length > 0 ? Math.min(...ids) : null;
});

const loadOlder = async () => {
    const before = oldestLoadedId.value;
    const el = scrollEl.value;

    if (!teamSlug.value || before === null || !hasOlder.value || loadingOlder.value || el === null) {
        return;
    }

    loadingOlder.value = true;
    const previousHeight = el.scrollHeight;
    const previousTop = el.scrollTop;

    try {
        const response = await fetch(
            messageHistory({ current_team: teamSlug.value }, { query: { before } }).url,
            { headers: requestHeaders(), credentials: 'same-origin' },
        );

        if (!response.ok) {
            return;
        }

        const page = (await response.json()) as {
            messages: ChatMessage[];
            hasOlder: boolean;
        };

        const known = new Set(displayMessages.value.map((message) => message.id));
        const incoming = page.messages.filter((message) => !known.has(message.id));

        olderMessages.value = [...incoming, ...olderMessages.value];
        hasOlder.value = incoming.length > 0 && page.hasOlder;

        await nextTick();
        el.scrollTop = el.scrollHeight - previousHeight + previousTop;
    } finally {
        loadingOlder.value = false;
    }
};

const onThreadScroll = () => {
    const el = scrollEl.value;

    if (el === null) {
        return;
    }

    const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight;
    pinToBottom.value = distanceFromBottom < 120;

    if (el.scrollTop < 72) {
        void loadOlder();
    }
};

onMounted(scrollToBottom);
watch(timeline, () => {
    if (pinToBottom.value) {
        scrollToBottom();
    }
});
watch(
    () => props.chatProcessing,
    (processing) => {
        if (!processing) {
            optimisticMessages.value = [];
        }
    },
);

const { start: startPoll, stop: stopPoll } = usePoll(
    1500,
    { only: ['messages', 'chatProcessing', 'pendingActions', 'whatsapp'] },
    { autoStart: false },
);

watch(
    () => props.chatProcessing,
    (busy) => (busy ? startPoll() : stopPoll()),
    { immediate: true },
);

const chatForm = useForm({ message: '' });

const isSending = computed(() => chatForm.processing);

const send = () => {
    if (!teamSlug.value || !chatForm.message.trim() || props.chatProcessing) {
        return;
    }

    const body = chatForm.message.trim();
    optimisticMessages.value.push({
        id: `local-${Date.now()}`,
        role: 'user',
        body,
        at: new Date().toISOString(),
        source: 'web',
    });
    scrollToBottom();

    chatForm.post(sendMessage({ current_team: teamSlug.value }).url, {
        preserveScroll: true,
        onSuccess: () => {
            chatForm.reset('message');
        },
        onError: () => {
            optimisticMessages.value = optimisticMessages.value.filter((m) => m.body !== body);
        },
    });
};

const applyPrompt = (text: string) => {
    chatForm.message = text;
};

const modeForm = useForm({ mode: props.mode });

watch(
    () => props.mode,
    (value) => {
        modeForm.mode = value;
    },
);

const onModeChange = (value: string) => {
    if (!teamSlug.value) {
        return;
    }

    modeForm.mode = value;
    modeForm.put(updateCommandCenterMode({ current_team: teamSlug.value }).url, {
        preserveScroll: true,
    });
};

const startFresh = () => {
    if (!teamSlug.value) {
        return;
    }

    optimisticMessages.value = [];
    olderMessages.value = [];
    hasOlder.value = false;
    router.delete(resetConversation({ current_team: teamSlug.value }).url, {
        preserveScroll: true,
    });
};

const approveAction = (id: number) => {
    if (!teamSlug.value) {
        return;
    }

    router.post(
        approvePendingAction({ current_team: teamSlug.value, pendingAction: id }).url,
        {},
        { preserveScroll: true },
    );
};

const dismissAction = (id: number) => {
    if (!teamSlug.value) {
        return;
    }

    router.delete(
        dismissPendingAction({ current_team: teamSlug.value, pendingAction: id }).url,
        { preserveScroll: true },
    );
};

const whatsappSetupOpen = ref(false);

const openWhatsAppSetup = () => {
    whatsappSetupOpen.value = true;
};

const toggleWhatsAppSetup = () => {
    whatsappSetupOpen.value = !whatsappSetupOpen.value;
};

const qrUrl = computed(() => {
    if (!props.whatsapp.waMeUrl) {
        return null;
    }

    return `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(props.whatsapp.waMeUrl)}`;
});

const copyText = async (text: string) => {
    try {
        await navigator.clipboard.writeText(text);
    } catch {
        /* ignore */
    }
};
</script>

<template>
    <Head title="Command center" />

    <div
        class="mx-auto flex h-[calc(100vh-4rem)] max-w-7xl flex-col gap-4 px-4 py-4 lg:flex-row lg:px-6"
    >
        <section
            class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-3xl border border-border/70 bg-card shadow-sm"
        >
            <header
                class="flex flex-wrap items-center justify-between gap-4 border-b border-border/70 px-6 py-5"
            >
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <img
                            v-if="employee.avatarUrl"
                            :src="employee.avatarUrl"
                            :alt="employee.name"
                            class="size-14 rounded-full object-cover shadow-sm ring-1 ring-black/10"
                        />
                        <div
                            v-else
                            class="flex size-14 items-center justify-center rounded-full bg-stone-800 text-sm font-semibold text-stone-50"
                        >
                            {{ employeeInitials }}
                        </div>
                        <span
                            class="absolute bottom-0.5 right-0.5 size-3 rounded-full border-2 border-card bg-emerald-500"
                        />
                    </div>
                    <div class="space-y-1">
                        <p class="text-lg font-semibold tracking-tight text-foreground">
                            {{ employee.name }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ employee.title }} · web and WhatsApp ·
                            <span class="capitalize">{{ mode }}</span>
                        </p>
                    </div>
                </div>
                <Button variant="ghost" size="sm" type="button" class="text-muted-foreground" @click="startFresh">
                    <RotateCcw class="mr-1.5 size-4" />
                    Clear chat
                </Button>
            </header>

            <div class="flex flex-wrap gap-2.5 border-b border-border/60 px-6 py-4">
                <button
                    v-for="prompt in quickPrompts"
                    :key="prompt"
                    type="button"
                    class="rounded-full border border-border bg-background px-3.5 py-1.5 text-xs text-foreground/80 transition hover:bg-muted"
                    @click="applyPrompt(prompt)"
                >
                    {{ prompt }}
                </button>
            </div>

            <div
                ref="scrollEl"
                class="flex-1 space-y-5 overflow-y-auto bg-[#f7f5f2] px-6 py-8 dark:bg-background"
                @scroll="onThreadScroll"
            >
                <p
                    v-if="loadingOlder"
                    class="text-center text-xs text-muted-foreground"
                >
                    Loading earlier messages…
                </p>

                <template v-for="row in timeline" :key="row.kind === 'day' ? row.id : row.message.id">
                    <div
                        v-if="row.kind === 'day'"
                        class="flex items-center justify-center py-1"
                    >
                        <span
                            class="rounded-lg bg-white/90 px-3 py-1 text-[11px] font-medium text-stone-500 shadow-sm dark:bg-card dark:text-muted-foreground"
                        >
                            {{ row.label }}
                        </span>
                    </div>
                    <div
                        v-else-if="row.message.role === 'user'"
                        class="flex justify-end"
                    >
                        <div
                            class="max-w-[min(78%,34rem)] rounded-[1.35rem] rounded-br-md bg-stone-900 px-4 py-3 text-[15px] leading-7 text-stone-50 shadow-sm dark:bg-stone-100 dark:text-stone-900"
                        >
                            <ChatMarkdown :source="row.message.body" />
                            <p
                                class="mt-1.5 flex items-center justify-end gap-1.5 text-[11px] text-stone-300 dark:text-stone-500"
                            >
                                <Globe v-if="(row.message.source ?? 'web') === 'web'" class="size-3" />
                                <MessageSquare v-else class="size-3 text-emerald-400" />
                                <span>{{ (row.message.source ?? 'web') === 'web' ? 'Web' : 'WhatsApp' }}</span>
                                <span v-if="row.message.at">· {{ formatMessageClockTime(row.message.at) }}</span>
                            </p>
                        </div>
                    </div>
                    <div v-else class="flex items-start gap-4">
                        <img
                            v-if="employee.avatarUrl"
                            :src="employee.avatarUrl"
                            :alt="employee.name"
                            class="mt-1 size-10 shrink-0 rounded-full object-cover ring-1 ring-black/10"
                        />
                        <div
                            v-else
                            class="mt-1 flex size-10 shrink-0 items-center justify-center rounded-full bg-stone-800 text-[11px] font-semibold text-stone-50"
                        >
                            {{ employeeInitials }}
                        </div>
                        <div
                            class="max-w-[min(82%,40rem)] rounded-[1.35rem] rounded-tl-md border border-black/5 bg-white px-4 py-3 text-[15px] leading-7 text-stone-800 shadow-sm dark:border-border dark:bg-card dark:text-foreground"
                        >
                            <p
                                v-if="row.message.source === 'whatsapp' && row.message.id !== 'welcome'"
                                class="mb-2 inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                <MessageSquare class="size-3" />
                                Replied on WhatsApp
                            </p>
                            <ChatMarkdown :source="row.message.body" />
                            <p
                                v-if="row.message.at"
                                class="mt-1.5 text-right text-[11px] text-stone-400"
                            >
                                {{ formatMessageClockTime(row.message.at) }}
                            </p>
                        </div>
                    </div>
                </template>

                <div v-if="chatProcessing || isSending" class="flex items-start gap-4">
                    <img
                        v-if="employee.avatarUrl"
                        :src="employee.avatarUrl"
                        :alt="employee.name"
                        class="mt-1 size-10 shrink-0 rounded-full object-cover ring-1 ring-black/10"
                    />
                    <div
                        class="inline-flex items-center gap-3 rounded-[1.35rem] border border-black/5 bg-white px-5 py-4 shadow-sm dark:border-border dark:bg-card"
                    >
                        <div class="flex items-center gap-1.5">
                            <span class="size-1.5 animate-bounce rounded-full bg-stone-500 [animation-delay:0ms]" />
                            <span class="size-1.5 animate-bounce rounded-full bg-stone-500 [animation-delay:150ms]" />
                            <span class="size-1.5 animate-bounce rounded-full bg-stone-500 [animation-delay:300ms]" />
                        </div>
                        <span class="text-sm text-muted-foreground">{{ thinkingLabel }}</span>
                    </div>
                </div>
            </div>

            <form class="border-t border-border/70 bg-card px-6 py-5" @submit.prevent="send">
                <div
                    class="flex items-end gap-3 rounded-2xl border border-border bg-[#f7f5f2] p-2.5 dark:bg-background"
                >
                    <textarea
                        v-model="chatForm.message"
                        rows="1"
                        placeholder="What do you want to accomplish?"
                        :disabled="!aiAvailable || chatProcessing"
                        class="max-h-36 min-h-14 flex-1 resize-none border-0 bg-transparent px-3 py-3 text-[15px] leading-6 outline-none ring-0 placeholder:text-muted-foreground focus-visible:ring-0"
                        @keydown.enter.exact.prevent="send"
                    />
                    <button
                        type="submit"
                        class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-stone-900 text-stone-50 transition hover:bg-stone-800 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-stone-100 dark:text-stone-900"
                        :disabled="
                            !aiAvailable ||
                            chatProcessing ||
                            isSending ||
                            !chatForm.message.trim()
                        "
                    >
                        <Send class="size-4" />
                    </button>
                </div>
                <InputError class="mt-2" :message="chatForm.errors.message" />
                <p v-if="!aiAvailable" class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                    Set OPENAI_API_KEY for {{ employee.name }}. Run
                    <code class="rounded bg-muted px-1">composer run queue:dev</code>
                    so the webhooks worker processes replies.
                </p>
            </form>
        </section>

        <aside class="flex w-full shrink-0 flex-col gap-4 lg:w-80">
            <div class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
                <p class="text-xs font-medium text-muted-foreground">{{ employee.name }} settings</p>
                <Label class="sr-only">Assistant mode</Label>
                <Select :model-value="modeForm.mode" @update:model-value="onModeChange">
                    <SelectTrigger class="mt-2 h-10">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="opt in modeOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <p class="mt-2 text-xs leading-relaxed text-muted-foreground">
                    Autopilot runs allowlisted actions. Deletes always need Approve in Attention.
                </p>
            </div>

            <div
                v-if="pendingActions.length"
                class="rounded-xl border border-amber-500/35 bg-amber-500/5 p-4 shadow-sm"
            >
                <div
                    class="flex items-center gap-2 text-sm font-semibold text-amber-950 dark:text-amber-100"
                >
                    <AlertCircle class="size-4" />
                    Attention
                </div>
                <ul class="mt-3 space-y-3">
                    <li
                        v-for="item in pendingActions"
                        :key="item.id"
                        class="rounded-lg border border-border/70 bg-card p-3 text-xs shadow-sm"
                    >
                        <p class="font-medium text-foreground">{{ item.summary }}</p>
                        <div class="mt-2 flex gap-2">
                            <Button size="sm" class="h-8 flex-1" @click="approveAction(item.id)">
                                Approve
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                class="h-8"
                                @click="dismissAction(item.id)"
                            >
                                Dismiss
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-[#25D366]/35 bg-card shadow-sm dark:border-[#25D366]/25"
            >
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-3 text-left"
                    @click="toggleWhatsAppSetup"
                >
                    <div class="flex items-center gap-2">
                        <MessageSquare class="size-4 text-[#25D366]" />
                        <span class="text-sm font-semibold">WhatsApp</span>
                        <span
                            v-if="!whatsapp.linked"
                            class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                        >
                            Setup
                        </span>
                        <span
                            v-else
                            class="rounded-full bg-[#dcf8c6] px-2 py-0.5 text-[10px] font-semibold uppercase text-[#128C7E] dark:bg-emerald-900/40 dark:text-emerald-200"
                        >
                            Linked
                        </span>
                    </div>
                    <component
                        :is="whatsappSetupOpen ? ChevronUp : ChevronDown"
                        class="size-4 text-muted-foreground"
                    />
                </button>

                <div class="border-t border-border/60 px-4 pb-4 pt-2">
                    <p class="text-xs text-muted-foreground">
                        Same {{ employee.name }} brain from your phone.
                    </p>

                    <template v-if="!whatsapp.configured">
                        <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">
                            Set ZERNIO_WHATSAPP_NUMBER, ZERNIO_API_KEY, and ZERNIO_WEBHOOK_SECRET on the server.
                        </p>
                    </template>

                    <template v-else-if="whatsapp.linked && !whatsappSetupOpen">
                        <p class="mt-3 text-sm text-foreground">
                            Linked — message {{ whatsapp.platformNumberDisplay }} to chat with
                            {{ employee.name }}.
                        </p>
                        <button
                            type="button"
                            class="mt-3 w-full rounded-lg bg-[#25D366] px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#20bd5a]"
                            @click="openWhatsAppSetup"
                        >
                            View connection
                        </button>
                    </template>

                    <template v-else-if="!whatsappSetupOpen">
                        <button
                            type="button"
                            class="mt-3 w-full rounded-lg bg-[#25D366] px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#20bd5a]"
                            @click="openWhatsAppSetup"
                        >
                            Connect WhatsApp
                        </button>
                    </template>

                    <div
                        v-show="whatsappSetupOpen && whatsapp.configured"
                        class="mt-3 max-h-[min(32rem,55vh)] overflow-y-auto overscroll-contain pr-1"
                    >
                        <div
                            v-if="whatsapp.webhookUrl"
                            class="mb-3 rounded-lg border border-border/60 bg-muted/30 px-3 py-2"
                        >
                            <p
                                class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground"
                            >
                                Zernio webhook URL
                            </p>
                            <button
                                type="button"
                                class="mt-1 flex w-full items-start gap-1 break-all text-left font-mono text-[10px] text-foreground underline-offset-2 hover:underline"
                                @click="copyText(whatsapp.webhookUrl ?? '')"
                            >
                                {{ whatsapp.webhookUrl }}
                                <Copy class="mt-0.5 size-3 shrink-0" />
                            </button>
                        </div>

                        <div
                            class="mx-auto w-full max-w-[17.5rem] rounded-[2rem] border-[3px] border-neutral-900 bg-neutral-900 p-1.5 shadow-xl dark:border-neutral-700"
                        >
                            <div
                                class="overflow-hidden rounded-[1.6rem] bg-[#efeae2] dark:bg-[#0b141a]"
                            >
                                <div
                                    class="flex items-center gap-2 bg-[#075E54] px-3 py-2.5 text-white"
                                >
                                    <div
                                        class="flex size-8 shrink-0 items-center justify-center rounded-full bg-[#25D366] text-[10px] font-bold"
                                    >
                                        {{ employeeInitials }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-sm font-semibold leading-tight">
                                            {{ employee.name }}
                                        </p>
                                        <p class="text-[10px] text-white/80">online</p>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1.5 opacity-90">
                                        <Wifi class="size-3.5" />
                                        <Battery class="size-3.5" />
                                    </div>
                                </div>

                                <div class="space-y-3 px-2.5 py-3 pb-4">
                                    <div
                                        class="rounded-lg rounded-tl-none bg-white px-3 py-2 text-[11px] leading-relaxed text-neutral-800 shadow-sm dark:bg-[#202c33] dark:text-neutral-100"
                                    >
                                        Most people connect from the WhatsApp app on their phone — scan
                                        the QR or copy the number and code below.
                                    </div>

                                    <div
                                        class="rounded-xl border border-[#25D366]/20 bg-white p-3 text-center shadow-sm dark:bg-[#111b21]"
                                    >
                                        <img
                                            v-if="qrUrl"
                                            :src="qrUrl"
                                            alt="WhatsApp QR code"
                                            class="mx-auto size-[140px] rounded-lg"
                                        />
                                        <p
                                            class="mt-2 inline-flex items-center justify-center gap-1.5 text-[11px] font-medium text-[#128C7E]"
                                        >
                                            <Smartphone class="size-3.5" />
                                            Scan with your camera
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-xl border border-white/80 bg-white/90 p-3 shadow-sm dark:border-[#2a3942] dark:bg-[#202c33]"
                                    >
                                        <ol
                                            class="list-decimal space-y-2.5 pl-4 text-[11px] leading-snug text-neutral-600 dark:text-neutral-300"
                                        >
                                            <li>
                                                Open
                                                <strong class="text-neutral-900 dark:text-white"
                                                    >WhatsApp</strong
                                                >
                                                on your phone.
                                            </li>
                                            <li class="flex flex-wrap items-center gap-1">
                                                <span>Chat with</span>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-neutral-900 dark:bg-[#2a3942] dark:text-white"
                                                    @click="
                                                        copyText(
                                                            whatsapp.platformNumberDisplay ?? '',
                                                        )
                                                    "
                                                >
                                                    {{ whatsapp.platformNumberDisplay }}
                                                    <Copy class="size-3 opacity-60" />
                                                </button>
                                            </li>
                                            <li class="flex flex-wrap items-center gap-1">
                                                <span>Send</span>
                                                <button
                                                    type="button"
                                                    class="inline-flex items-center gap-1 rounded-md bg-neutral-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-neutral-900 dark:bg-[#2a3942] dark:text-white"
                                                    @click="copyText(whatsapp.linkCode ?? '')"
                                                >
                                                    {{ whatsapp.linkCode }}
                                                    <Copy class="size-3 opacity-60" />
                                                </button>
                                            </li>
                                        </ol>
                                    </div>

                                    <p
                                        class="text-center text-[10px] text-neutral-500 dark:text-neutral-400"
                                    >
                                        WhatsApp on this computer?
                                        <a
                                            v-if="whatsapp.waMeUrl"
                                            :href="whatsapp.waMeUrl"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="font-medium text-[#128C7E] underline-offset-2 hover:underline"
                                        >
                                            Open here
                                        </a>
                                    </p>
                                </div>

                                <div
                                    class="mx-auto mb-2 h-1 w-16 rounded-full bg-neutral-900/20 dark:bg-white/20"
                                />
                            </div>
                        </div>

                        <a
                            v-if="whatsapp.waMeUrl && !whatsapp.linked"
                            :href="whatsapp.waMeUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-3 flex w-full items-center justify-center rounded-lg bg-[#25D366] px-3 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#20bd5a]"
                        >
                            Open in WhatsApp
                        </a>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-border/80 bg-card p-4 shadow-sm">
                <p class="text-sm font-semibold">Activity</p>
                <p class="text-xs text-muted-foreground">Background automation runs</p>
                <ul
                    v-if="recentRuns.length"
                    class="mt-3 max-h-44 space-y-2 overflow-y-auto text-xs"
                >
                    <li
                        v-for="(run, i) in recentRuns"
                        :key="i"
                        class="rounded-md border border-border/50 px-2 py-1.5"
                    >
                        <span class="font-medium">{{ run.triggerLabel }}</span>
                        <span v-if="run.leadName"> · {{ run.leadName }}</span>
                        <p class="text-muted-foreground">{{ run.summary }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-xs text-muted-foreground">No recent runs.</p>
            </div>
        </aside>
    </div>
</template>
