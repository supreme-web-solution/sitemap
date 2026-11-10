<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import {
    Bot,
    LoaderCircle,
    MessageSquare,
    Pause,
    Play,
    RefreshCw,
    TriangleAlert,
    Users,
    Video,
} from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { formatRelativeTime } from '@/lib/format';
import {
    knowledge as refreshKnowledge,
    update as saveAssistant,
    voicePreview,
} from '@/routes/sites/assistant';
import type {
    PitchPresenter,
    SimliFaceOption,
    SiteAssistantOptions,
    SiteAssistantSettings,
} from '@/types';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    assistant: SiteAssistantSettings;
    /** Loaded when this tab opens; undefined while loading. */
    options?: SiteAssistantOptions;
}>();

const page = usePage();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

const form = useForm({
    enabled: props.assistant.enabled,
    name: props.assistant.name,
    presenter_id: props.assistant.presenterId ?? '',
    voice_id: props.assistant.voiceId,
    talking_avatar_enabled: props.assistant.talkingAvatarEnabled,
    simli_face_id: props.assistant.simliFaceId ?? '',
    launcher_side: props.assistant.launcherSide ?? 'right',
    greeting: props.assistant.greeting ?? '',
    knowledge: props.assistant.knowledge,
});

const voiceTouched = ref(false);
const voiceFilter = ref<'all' | 'male' | 'female'>('all');
const refreshing = ref(false);
const previewing = ref<string | null>(null);
const audio = ref<HTMLAudioElement | null>(null);

const filteredVoices = computed(() =>
    props.assistant.voices.filter((voice) => {
        if (voiceFilter.value === 'all' || voice.id === 'off') {
            return true;
        }

        return voice.gender === voiceFilter.value;
    }),
);

const simliFaces = computed(() => props.assistant.simliFaces ?? []);

const presenters = computed(() => props.options?.presenters ?? []);
const selectedPresenter = computed<PitchPresenter | null>(
    () =>
        presenters.value.find((presenter) => presenter.id === form.presenter_id) ??
        (props.assistant.presenterId
            ? {
                  id: props.assistant.presenterId,
                  name: props.assistant.presenterName ?? 'Presenter',
                  gender: (props.assistant.presenterGender as
                      | 'male'
                      | 'female'
                      | null) ?? null,
                  thumbnail_url: props.assistant.presenterThumbnailUrl ?? '',
              }
            : null),
);

const isLive = computed(() => props.assistant.enabled);
const liveMode = computed(
    () =>
        props.assistant.talkingAvatarAvailable &&
        form.talking_avatar_enabled === true,
);
const selectedSimliFace = computed(
    () =>
        simliFaces.value.find((face) => face.id === form.simli_face_id) ?? null,
);
const routeArgs = () => ({ current_team: props.teamSlug, site: props.siteId });

const matchVoiceToGender = (gender: string | null | undefined) => {
    if (voiceTouched.value || !gender) {
        return;
    }

    const match = props.assistant.voices.find(
        (voice) => voice.gender === gender && voice.id !== 'off',
    );

    if (match) {
        form.voice_id = match.id;
    }
};

const applyDisplayName = (name: string, previousName: string | null) => {
    if (
        (previousName && form.name.trim() === previousName) ||
        !form.name.trim()
    ) {
        form.name = name;
    }
};

const choosePresenter = (presenter: PitchPresenter) => {
    applyDisplayName(presenter.name, selectedPresenter.value?.name ?? null);
    form.presenter_id = presenter.id;
    matchVoiceToGender(presenter.gender);

    if (!form.simli_face_id && presenter.gender) {
        const face = simliFaces.value.find(
            (option) => option.gender === presenter.gender,
        );

        if (face) {
            form.simli_face_id = face.id;
        }
    }
};

const chooseSimliFace = (face: SimliFaceOption) => {
    applyDisplayName(face.name, selectedSimliFace.value?.name ?? null);
    form.simli_face_id = face.id;
    matchVoiceToGender(face.gender);
};

const setTalkingAvatar = (enabled: boolean) => {
    form.talking_avatar_enabled = enabled;

    if (enabled && !form.simli_face_id) {
        const gender =
            selectedPresenter.value?.gender ??
            props.assistant.presenterGender ??
            'female';
        const face =
            simliFaces.value.find((option) => option.gender === gender) ??
            simliFaces.value[0];

        if (face) {
            chooseSimliFace(face);
        }
    }
};

const save = () =>
    form.put(saveAssistant.url(routeArgs()), { preserveScroll: true });

const rewrite = () => {
    if (
        !confirm(
            'Rewrite the knowledge base from the website and Google listing? Your edits to it will be replaced.',
        )
    ) {
        return;
    }

    router.post(
        refreshKnowledge.url(routeArgs()),
        {},
        {
            preserveScroll: true,
            onStart: () => (refreshing.value = true),
            onFinish: () => (refreshing.value = false),
        },
    );
};

const stopPreview = () => {
    audio.value?.pause();
    audio.value = null;
    previewing.value = null;
};

const previewVoice = async (voiceId: string) => {
    if (voiceId === 'off') {
        return;
    }

    if (previewing.value === voiceId) {
        stopPreview();

        return;
    }

    stopPreview();
    previewing.value = voiceId;

    try {
        const response = await fetch(voicePreview.url(routeArgs()), {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN':
                    document
                        .querySelector('meta[name="csrf-token"]')
                        ?.getAttribute('content') ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ voice_id: voiceId }),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('preview failed');
        }

        const data = (await response.json()) as { url: string };
        const player = new Audio(data.url);
        audio.value = player;
        player.onended = stopPreview;
        await player.play();
    } catch {
        stopPreview();
    }
};

watch(
    () => props.assistant.knowledge,
    (knowledge) => {
        form.knowledge = knowledge;
    },
);

onBeforeUnmount(stopPreview);

const fieldClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';

const reloadOptions = () => router.reload({ only: ['assistantOptions'] });
</script>

<template>
    <div class="grid gap-4" data-test="assistant-panel">
        <section class="panel p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <Bot class="mt-0.5 size-5 text-primary" />
                    <div>
                        <h3 class="text-base font-semibold">
                            Show the AI sales assistant on this website
                        </h3>
                        <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                            A sales representative on the website that answers
                            questions, speaks with an AI voice, and can collect
                            visitor details.
                        </p>
                    </div>
                </div>
                <Switch
                    :model-value="form.enabled"
                    aria-label="Show the AI assistant on this website"
                    data-test="assistant-enabled"
                    @update:model-value="
                        (value: boolean) => (form.enabled = value === true)
                    "
                />
            </div>

            <p
                class="mt-3 text-xs"
                :class="isLive ? 'text-emerald-600' : 'text-muted-foreground'"
                data-test="assistant-status"
            >
                {{
                    isLive
                        ? 'Live. The script is added to the preview, the published website, and exported ZIP files.'
                        : 'Switched off. No script is added to the website or to exported files.'
                }}
            </p>

            <div class="mt-4">
                <p class="text-sm font-medium">Launcher position</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Where the floating face sits. On the right it lifts above the
                    Contact button so they do not overlap.
                </p>
                <div
                    class="mt-2 inline-flex rounded-lg border bg-card p-0.5 text-xs font-medium"
                    role="group"
                    aria-label="Launcher position"
                    data-test="assistant-launcher-side"
                >
                    <button
                        v-for="option in [
                            { id: 'right', label: 'Bottom right' },
                            { id: 'left', label: 'Bottom left' },
                        ]"
                        :key="option.id"
                        type="button"
                        :class="[
                            'rounded-md px-2.5 py-1.5 transition',
                            form.launcher_side === option.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="form.launcher_side = option.id as 'right' | 'left'"
                    >
                        {{ option.label }}
                    </button>
                </div>
                <InputError :message="errors.launcher_side" class="mt-2" />
            </div>

            <div
                v-if="!assistant.didConfigured && !liveMode"
                class="mt-3 flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    Add DID_API_KEY to choose photoreal presenters (the same
                    stock faces used for the sales video).
                </p>
            </div>

            <div
                v-if="!assistant.canAnswer"
                class="mt-3 flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    Add ANTHROPIC_API_KEY so the assistant can answer. Until then
                    it only collects visitor details.
                </p>
            </div>

            <div
                v-if="!assistant.canSpeak"
                class="mt-3 flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    Add OPENAI_API_KEY for natural AI voices. Without it, the
                    assistant stays text-only.
                </p>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div
                    class="flex items-center gap-2 rounded-lg border bg-card p-3"
                >
                    <MessageSquare class="size-4 text-muted-foreground" />
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Questions answered
                        </dt>
                        <dd class="font-semibold">
                            {{ assistant.messagesCount }}
                        </dd>
                    </div>
                </div>
                <div
                    class="flex items-center gap-2 rounded-lg border bg-card p-3"
                >
                    <Users class="size-4 text-muted-foreground" />
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            Visitors who left details
                        </dt>
                        <dd class="font-semibold">{{ assistant.leadsCount }}</dd>
                    </div>
                </div>
            </dl>
        </section>

        <section
            v-if="assistant.talkingAvatarAvailable"
            class="panel p-5"
            data-test="talking-avatar-panel"
        >
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <Video class="mt-0.5 size-5 text-primary" />
                    <div>
                        <h3 class="text-base font-semibold">
                            How the assistant looks
                        </h3>
                        <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                            <template v-if="liveMode">
                                Live talking face (Simli). The visitor sees this
                                person speak with lip-sync. Uses stream minutes
                                (~$0.01/min).
                            </template>
                            <template v-else>
                                Still photo mode (free). Turn this on for a live
                                talking face with lip-sync (~$0.01/min).
                            </template>
                        </p>
                    </div>
                </div>
                <Switch
                    :model-value="form.talking_avatar_enabled"
                    aria-label="Enable live talking avatar"
                    data-test="talking-avatar-enabled"
                    @update:model-value="
                        (value: boolean) => setTalkingAvatar(value === true)
                    "
                />
            </div>
        </section>

        <section v-if="liveMode" class="panel p-5">
            <h3 class="text-base font-semibold">1. Choose the talking face</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                This is the person visitors see and hear. Voice below is what
                they speak with.
            </p>
            <div
                class="mt-3 grid max-h-80 grid-cols-3 gap-3 overflow-y-auto p-1 sm:grid-cols-4"
                role="listbox"
                aria-label="Talking face"
            >
                <button
                    v-for="face in simliFaces"
                    :key="face.id"
                    type="button"
                    role="option"
                    :aria-selected="form.simli_face_id === face.id"
                    :data-test="`simli-face-${face.id}`"
                    :class="[
                        'overflow-hidden rounded-lg border text-left transition',
                        form.simli_face_id === face.id
                            ? 'border-primary ring-2 ring-primary/30'
                            : 'hover:border-primary/50',
                    ]"
                    @click="chooseSimliFace(face)"
                >
                    <img
                        :src="face.image_url"
                        :alt="face.name"
                        loading="lazy"
                        class="aspect-square w-full object-cover object-top"
                    />
                    <span class="flex items-center justify-between px-2 py-1.5">
                        <span class="truncate text-xs font-medium">{{
                            face.name
                        }}</span>
                        <span
                            class="text-[10px] text-muted-foreground capitalize"
                            >{{ face.gender }}</span
                        >
                    </span>
                </button>
            </div>
            <InputError :message="errors.simli_face_id" class="mt-2" />

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-medium">
                    Display name
                    <Input
                        v-model="form.name"
                        maxlength="60"
                        data-test="assistant-name"
                    />
                    <InputError :message="errors.name" />
                </label>

                <label class="grid gap-1.5 text-sm font-medium">
                    Greeting
                    <textarea
                        v-model="form.greeting"
                        rows="2"
                        maxlength="300"
                        :class="fieldClass"
                        placeholder="Leave empty for a default welcome."
                        data-test="assistant-greeting"
                    />
                    <InputError :message="errors.greeting" />
                </label>
            </div>
        </section>

        <section v-else class="panel p-5">
            <h3 class="text-base font-semibold">1. Choose a presenter</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Photoreal still faces. The visitor sees this person large on the
                left; chat sits on the right and can be collapsed.
            </p>

            <div
                v-if="options === undefined"
                class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4"
            >
                <Skeleton v-for="n in 8" :key="n" class="aspect-[3/4]" />
            </div>
            <div
                v-else-if="options.error"
                class="mt-3 flex flex-wrap items-center gap-3 text-sm text-rose-700 dark:text-rose-300"
            >
                {{ options.error }}
                <Button variant="outline" size="sm" @click="reloadOptions">
                    Try again
                </Button>
            </div>
            <p
                v-else-if="presenters.length === 0"
                class="mt-3 text-sm text-muted-foreground"
            >
                Presenters appear here once D-ID is connected.
            </p>
            <div
                v-else
                class="mt-3 grid max-h-80 grid-cols-3 gap-3 overflow-y-auto p-1 sm:grid-cols-4"
                role="radiogroup"
                aria-label="Presenter"
            >
                <button
                    v-for="presenter in presenters"
                    :key="presenter.id"
                    type="button"
                    role="radio"
                    :aria-checked="form.presenter_id === presenter.id"
                    :data-test="`assistant-presenter-${presenter.id}`"
                    :class="[
                        'overflow-hidden rounded-lg border text-left transition',
                        form.presenter_id === presenter.id
                            ? 'border-primary ring-2 ring-primary/30'
                            : 'hover:border-primary/50',
                    ]"
                    @click="choosePresenter(presenter)"
                >
                    <img
                        :src="presenter.thumbnail_url"
                        :alt="presenter.name"
                        loading="lazy"
                        class="aspect-[3/4] w-full object-cover object-top"
                    />
                    <span class="flex items-center justify-between px-2 py-1.5">
                        <span class="truncate text-xs font-medium">{{
                            presenter.name
                        }}</span>
                        <span
                            v-if="presenter.gender"
                            class="text-[10px] text-muted-foreground capitalize"
                            >{{ presenter.gender }}</span
                        >
                    </span>
                </button>
            </div>
            <InputError :message="errors.presenter_id" class="mt-2" />

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-medium">
                    Display name
                    <Input
                        v-model="form.name"
                        maxlength="60"
                        data-test="assistant-name"
                    />
                    <InputError :message="errors.name" />
                </label>

                <label class="grid gap-1.5 text-sm font-medium">
                    Greeting
                    <textarea
                        v-model="form.greeting"
                        rows="2"
                        maxlength="300"
                        :class="fieldClass"
                        placeholder="Leave empty for a default welcome."
                        data-test="assistant-greeting"
                    />
                    <InputError :message="errors.greeting" />
                </label>
            </div>
        </section>

        <section class="panel p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold">2. Choose a voice</h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="liveMode">
                            This is the voice the talking face speaks with.
                            Preview, then pick one that fits.
                        </template>
                        <template v-else>
                            Natural AI voices. Preview each one, then pick the
                            voice that fits the presenter.
                        </template>
                    </p>
                </div>
                <div
                    class="inline-flex rounded-lg border bg-card p-0.5 text-xs font-medium"
                    role="group"
                    aria-label="Voice filter"
                >
                    <button
                        v-for="option in [
                            { id: 'all', label: 'All' },
                            { id: 'female', label: 'Female' },
                            { id: 'male', label: 'Male' },
                        ]"
                        :key="option.id"
                        type="button"
                        :class="[
                            'rounded-md px-2.5 py-1.5 transition',
                            voiceFilter === option.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:text-foreground',
                        ]"
                        @click="
                            voiceFilter = option.id as 'all' | 'male' | 'female'
                        "
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <ul
                class="mt-3 grid max-h-72 gap-2 overflow-y-auto p-1"
                role="listbox"
                aria-label="Voice"
            >
                <li v-for="voice in filteredVoices" :key="voice.id">
                    <div
                        :class="[
                            'flex items-center gap-3 rounded-xl border px-3 py-2.5 transition',
                            form.voice_id === voice.id
                                ? 'border-primary bg-primary/5 shadow-sm'
                                : 'hover:border-primary/40',
                        ]"
                    >
                        <button
                            type="button"
                            role="option"
                            :aria-selected="form.voice_id === voice.id"
                            class="min-w-0 flex-1 text-left"
                            @click="
                                form.voice_id = voice.id;
                                voiceTouched = true;
                            "
                        >
                            <span class="block truncate text-sm font-medium">{{
                                voice.name
                            }}</span>
                            <span class="block text-xs text-muted-foreground">
                                {{ voice.blurb
                                }}<template v-if="voice.gender">
                                    · {{ voice.gender }}</template
                                >
                            </span>
                        </button>
                        <button
                            v-if="voice.id !== 'off' && assistant.canSpeak"
                            type="button"
                            class="inline-flex size-9 shrink-0 items-center justify-center rounded-full border bg-background text-foreground transition hover:border-primary hover:text-primary"
                            :data-test="`assistant-voice-preview-${voice.id}`"
                            :aria-label="
                                previewing === voice.id
                                    ? `Stop ${voice.name}`
                                    : `Preview ${voice.name}`
                            "
                            @click="previewVoice(voice.id)"
                        >
                            <Pause
                                v-if="previewing === voice.id"
                                class="size-4"
                            />
                            <Play v-else class="size-4" />
                        </button>
                    </div>
                </li>
            </ul>
            <InputError :message="errors.voice_id" class="mt-2" />
        </section>

        <section class="panel p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold">Knowledge base</h3>
                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                        Everything the assistant knows. Edit anything wrong, and
                        add prices, offers, areas or policies visitors ask about.
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="refreshing"
                    data-test="assistant-rewrite"
                    @click="rewrite"
                >
                    <LoaderCircle v-if="refreshing" class="animate-spin" />
                    <RefreshCw v-else />
                    Rewrite from website
                </Button>
            </div>

            <textarea
                v-model="form.knowledge"
                rows="16"
                :maxlength="assistant.maxKnowledgeLength"
                :class="[fieldClass, 'mt-3 font-mono text-xs leading-relaxed']"
                data-test="assistant-knowledge"
            />
            <div class="mt-1 flex justify-between text-xs text-muted-foreground">
                <span v-if="assistant.knowledgeGeneratedAt">
                    Written
                    {{ formatRelativeTime(assistant.knowledgeGeneratedAt) }}
                </span>
                <span v-else>Draft. Save to keep it.</span>
                <span>
                    {{ form.knowledge.length }} /
                    {{ assistant.maxKnowledgeLength }}
                </span>
            </div>
            <InputError :message="errors.knowledge" class="mt-1" />
        </section>

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-xl text-xs text-muted-foreground">
                The assistant still runs from this app, so an exported copy of the
                website keeps working only while your account is active.
            </p>
            <Button
                :disabled="form.processing"
                data-test="assistant-save"
                @click="save"
            >
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Save assistant
            </Button>
        </div>
    </div>
</template>
