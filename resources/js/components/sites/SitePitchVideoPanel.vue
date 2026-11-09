<script setup lang="ts">
import { router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import {
    Check,
    Copy,
    ExternalLink,
    LoaderCircle,
    Play,
    Sparkles,
    Square,
    Trash2,
    TriangleAlert,
    Video,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { formatRelativeTime } from '@/lib/format';
import {
    destroy as removeVideo,
    script as writeScript,
    store as createVideo,
} from '@/routes/sites/pitch-video';
import type {
    PitchPresenter,
    PitchVideoOptions,
    PitchVideoPanel,
    PitchVoice,
} from '@/types';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    pitch: PitchVideoPanel;
    /** Loaded when this tab opens; undefined while loading. */
    options?: PitchVideoOptions;
}>();

const MAX_SCRIPT = 1000;
const MIN_SCRIPT = 30;

const page = usePage();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);
const { copy, copied } = useClipboard();

const video = computed(() => props.pitch.video);
const isGenerating = computed(() => video.value?.status === 'generating');

const form = useForm({
    presenter_id: video.value?.presenterId ?? '',
    voice_id: video.value?.voiceId ?? '',
    script: video.value?.script ?? '',
});

const voiceTouched = ref(video.value?.voiceId != null);
const onlyMatchingVoices = ref(true);
const writing = ref(false);

const { start: startPoll, stop: stopPoll } = usePoll(
    4000,
    { only: ['pitchVideo'] },
    { autoStart: false },
);

watch(
    isGenerating,
    (active) => (active ? startPoll() : stopPoll()),
    { immediate: true },
);

watch(
    () => video.value?.script,
    (script) => {
        if (script != null) {
            form.script = script;
        }
    },
);

const presenters = computed(() => props.options?.presenters ?? []);
const voices = computed(() => props.options?.voices ?? []);

const selectedPresenter = computed<PitchPresenter | null>(
    () =>
        presenters.value.find(
            (presenter) => presenter.id === form.presenter_id,
        ) ?? null,
);

const selectedVoice = computed<PitchVoice | null>(
    () => voices.value.find((voice) => voice.id === form.voice_id) ?? null,
);

const presenterGender = computed(
    () => selectedPresenter.value?.gender ?? video.value?.presenterGender ?? null,
);

const visibleVoices = computed(() =>
    onlyMatchingVoices.value && presenterGender.value !== null
        ? voices.value.filter((voice) => voice.gender === presenterGender.value)
        : voices.value,
);

const voiceMismatch = computed(
    () =>
        presenterGender.value !== null &&
        selectedVoice.value?.gender != null &&
        selectedVoice.value.gender !== presenterGender.value,
);

const matchingVoiceId = (gender: PitchPresenter['gender']): string | null => {
    if (gender === null || props.options === undefined) {
        return voices.value[0]?.id ?? null;
    }

    return (
        props.options.defaultVoices[gender] ??
        voices.value.find((voice) => voice.gender === gender)?.id ??
        null
    );
};

const choosePresenter = (presenter: PitchPresenter) => {
    form.presenter_id = presenter.id;

    if (!voiceTouched.value) {
        form.voice_id = matchingVoiceId(presenter.gender) ?? form.voice_id;
    }
};

const chooseVoice = (voice: PitchVoice) => {
    voiceTouched.value = true;
    form.voice_id = voice.id;
};

watch(
    () => props.options,
    (options) => {
        if (options === undefined || options.presenters.length === 0) {
            return;
        }

        if (form.presenter_id === '') {
            choosePresenter(options.presenters[0]);
        } else if (form.voice_id === '') {
            const current = options.presenters.find(
                (presenter) => presenter.id === form.presenter_id,
            );
            form.voice_id = matchingVoiceId(current?.gender ?? null) ?? '';
        }
    },
    { immediate: true },
);

const playingVoiceId = ref<string | null>(null);
let audio: HTMLAudioElement | null = null;

const stopAudio = () => {
    audio?.pause();
    audio = null;
    playingVoiceId.value = null;
};

const togglePreview = (voice: PitchVoice) => {
    if (voice.preview_url === null) {
        return;
    }

    if (playingVoiceId.value === voice.id) {
        stopAudio();

        return;
    }

    stopAudio();
    audio = new Audio(voice.preview_url);
    audio.addEventListener('ended', stopAudio);
    playingVoiceId.value = voice.id;
    audio.play().catch(() => {
        toast.error('This voice sample could not be played.');
        stopAudio();
    });
};

onBeforeUnmount(stopAudio);

const routeArgs = () => ({ current_team: props.teamSlug, site: props.siteId });

const aiWrite = () => {
    router.post(
        writeScript.url(routeArgs()),
        {},
        {
            preserveScroll: true,
            onStart: () => (writing.value = true),
            onFinish: () => (writing.value = false),
        },
    );
};

const generate = () =>
    form.post(createVideo.url(routeArgs()), { preserveScroll: true });

const remove = () => {
    if (!confirm('Remove this video? Links already sent will stop working.')) {
        return;
    }

    router.delete(removeVideo.url(routeArgs()), { preserveScroll: true });
};

const copyLink = async (url: string) => {
    await copy(url);
    toast.success('Link copied');
};

const reloadOptions = () =>
    router.reload({ only: ['pitchVideoOptions'] });

const words = computed(
    () => form.script.trim().split(/\s+/).filter(Boolean).length,
);
const seconds = computed(() => Math.round(words.value / 2.5));

const canGenerate = computed(
    () =>
        props.pitch.configured &&
        !isGenerating.value &&
        !form.processing &&
        form.presenter_id !== '' &&
        form.voice_id !== '' &&
        form.script.trim().length >= MIN_SCRIPT &&
        form.script.length <= MAX_SCRIPT,
);

const textareaClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';
</script>

<template>
    <div class="grid gap-4" data-test="pitch-video-panel">
        <div
            v-if="!pitch.configured"
            class="flex gap-3 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>
                Video creation is not connected yet. Add your D-ID API key as
                <span class="font-mono">DID_API_KEY</span> in the server
                environment, then reload this page.
            </p>
        </div>

        <section v-if="video?.isWatchable" class="panel p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="flex items-center gap-2 text-base font-semibold">
                        <Video class="size-4" /> Your sales video
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <template v-if="video.viewsCount > 0">
                            Watched {{ video.viewsCount }}
                            {{ video.viewsCount === 1 ? 'time' : 'times' }},
                            first
                            {{ formatRelativeTime(video.viewedAt ?? '') }}.
                        </template>
                        <template v-else>Not watched yet.</template>
                        <template v-if="video.generatedAt">
                            Made {{ formatRelativeTime(video.generatedAt) }}.
                        </template>
                    </p>
                </div>
                <Button variant="ghost" size="sm" @click="remove">
                    <Trash2 /> Remove
                </Button>
            </div>

            <video
                :src="video.videoUrl ?? undefined"
                :poster="video.presenterThumbnailUrl ?? undefined"
                controls
                playsinline
                preload="metadata"
                class="mt-3 aspect-video w-full max-w-xl rounded-lg bg-black"
            />

            <div v-if="video.watchUrl" class="mt-3 flex flex-wrap gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    @click="copyLink(video.watchUrl)"
                >
                    <Check v-if="copied" /><Copy v-else /> Copy watch link
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="video.watchUrl" target="_blank" rel="noopener">
                        <ExternalLink /> Open watch page
                    </a>
                </Button>
            </div>
            <p class="mt-3 text-xs text-muted-foreground">
                This link is added to proposal emails and to outreach emails
                automatically. In your own templates use
                <span class="font-mono">{{ '{' + '{video_url}' + '}' }}</span>.
            </p>
        </section>

        <div
            v-if="isGenerating"
            class="flex items-center gap-3 rounded-lg border bg-card p-4 text-sm"
            data-test="pitch-video-generating"
        >
            <LoaderCircle class="size-4 animate-spin text-primary" />
            <p>
                Creating your video. This usually takes a minute or two, and you
                can close this window while it works.
            </p>
        </div>

        <div
            v-else-if="video?.status === 'failed' && video.error"
            class="flex gap-3 rounded-lg border border-rose-300 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200"
            data-test="pitch-video-failed"
        >
            <TriangleAlert class="mt-0.5 size-4 shrink-0" />
            <p>{{ video.error }}</p>
        </div>

        <section class="panel p-5">
            <h3 class="text-base font-semibold">1. Choose a presenter</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                The voice is matched to the presenter automatically. You can
                change it below.
            </p>

            <div
                v-if="options === undefined"
                class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4"
            >
                <Skeleton v-for="n in 8" :key="n" class="aspect-square" />
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
                class="mt-3 grid max-h-72 grid-cols-3 gap-3 overflow-y-auto p-1 sm:grid-cols-4"
                role="radiogroup"
                aria-label="Presenter"
            >
                <button
                    v-for="presenter in presenters"
                    :key="presenter.id"
                    type="button"
                    role="radio"
                    :aria-checked="form.presenter_id === presenter.id"
                    :data-test="`presenter-${presenter.id}`"
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
                        class="aspect-square w-full object-cover"
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
        </section>

        <section class="panel p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold">2. Choose a voice</h3>
                <label
                    v-if="presenterGender"
                    class="flex items-center gap-2 text-xs text-muted-foreground"
                >
                    <input v-model="onlyMatchingVoices" type="checkbox" />
                    Only {{ presenterGender }} voices
                </label>
            </div>

            <div v-if="options === undefined" class="mt-3 grid gap-2">
                <Skeleton v-for="n in 4" :key="n" class="h-10" />
            </div>
            <ul
                v-else-if="visibleVoices.length"
                class="mt-3 grid max-h-64 gap-1.5 overflow-y-auto p-1"
                role="radiogroup"
                aria-label="Voice"
            >
                <li
                    v-for="voice in visibleVoices"
                    :key="voice.id"
                    :class="[
                        'flex items-center gap-2 rounded-lg border px-3 py-2 transition',
                        form.voice_id === voice.id
                            ? 'border-primary bg-primary/5'
                            : 'hover:border-primary/50',
                    ]"
                >
                    <button
                        type="button"
                        role="radio"
                        :aria-checked="form.voice_id === voice.id"
                        :data-test="`voice-${voice.id}`"
                        class="flex flex-1 items-center justify-between gap-2 text-left text-sm"
                        @click="chooseVoice(voice)"
                    >
                        <span class="font-medium">{{ voice.name }}</span>
                        <span class="text-xs text-muted-foreground capitalize">
                            {{ voice.gender ?? 'voice' }} · {{ voice.locale }}
                        </span>
                    </button>
                    <Button
                        v-if="voice.preview_url"
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        :aria-label="`Preview ${voice.name}`"
                        @click="togglePreview(voice)"
                    >
                        <Square v-if="playingVoiceId === voice.id" />
                        <Play v-else />
                    </Button>
                </li>
            </ul>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                Voices appear here once D-ID is connected.
            </p>

            <p
                v-if="voiceMismatch"
                class="mt-2 text-xs text-amber-700 dark:text-amber-300"
            >
                This voice does not match the presenter's look. That is fine if
                you meant it.
            </p>
            <InputError :message="errors.voice_id" class="mt-2" />
        </section>

        <section class="panel p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-semibold">3. Script</h3>
                <Button
                    v-if="pitch.canWriteScript"
                    variant="outline"
                    size="sm"
                    :disabled="writing || isGenerating"
                    data-test="write-script"
                    @click="aiWrite"
                >
                    <LoaderCircle v-if="writing" class="animate-spin" />
                    <Sparkles v-else />
                    {{ form.script ? 'Rewrite with AI' : 'Write with AI' }}
                </Button>
            </div>
            <p class="mt-1 text-sm text-muted-foreground">
                <template v-if="pitch.hasExistingWebsite">
                    This business already has a website, so the script pitches
                    this one as an upgrade.
                </template>
                <template v-else>
                    This business has no website yet, so the script pitches
                    getting them online.
                </template>
                Edit it freely before you create the video.
            </p>

            <textarea
                v-model="form.script"
                rows="9"
                :maxlength="MAX_SCRIPT"
                :class="[textareaClass, 'mt-3']"
                placeholder="What the presenter will say…"
                data-test="pitch-script"
            />
            <div
                class="mt-1 flex justify-between text-xs text-muted-foreground"
            >
                <span>About {{ seconds }} seconds</span>
                <span>{{ form.script.length }} / {{ MAX_SCRIPT }}</span>
            </div>
            <InputError :message="errors.script" class="mt-2" />
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <Button
                :disabled="!canGenerate"
                data-test="create-video"
                @click="generate"
            >
                <LoaderCircle
                    v-if="isGenerating || form.processing"
                    class="animate-spin"
                />
                <Video v-else />
                {{ video?.isWatchable ? 'Create a new video' : 'Create video' }}
            </Button>
            <p class="text-xs text-muted-foreground">
                Each video uses D-ID credits from your account.
            </p>
        </div>
    </div>
</template>
