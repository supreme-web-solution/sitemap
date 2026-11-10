<script setup lang="ts">
import { router, useForm, usePage } from '@inertiajs/vue3';
import { Bot, LoaderCircle, MessageSquare, RefreshCw, TriangleAlert, Users } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { formatRelativeTime } from '@/lib/format';
import { knowledge as refreshKnowledge, update as saveAssistant } from '@/routes/sites/assistant';
import type { AssistantAvatar, SiteAssistantSettings } from '@/types';

const props = defineProps<{
    siteId: number;
    teamSlug: string;
    assistant: SiteAssistantSettings;
}>();

const page = usePage();
const errors = computed(
    () => page.props.errors as Record<string, string | undefined>,
);

const form = useForm({
    enabled: props.assistant.enabled,
    name: props.assistant.name,
    avatar: props.assistant.avatar,
    voice: props.assistant.voice,
    greeting: props.assistant.greeting ?? '',
    knowledge: props.assistant.knowledge,
});

const voiceTouched = ref(false);
const refreshing = ref(false);

const selectedAvatar = computed<AssistantAvatar | undefined>(() =>
    props.assistant.avatars.find((avatar) => avatar.key === form.avatar),
);

const isLive = computed(() => props.assistant.enabled);

const routeArgs = () => ({ current_team: props.teamSlug, site: props.siteId });

const chooseAvatar = (avatar: AssistantAvatar) => {
    const previous = selectedAvatar.value;

    // Keep a name the user typed; only follow the avatar while it is still the avatar's own name.
    if (previous && form.name.trim() === previous.name) {
        form.name = avatar.name;
    }

    form.avatar = avatar.key;

    if (!voiceTouched.value && form.voice !== 'off') {
        form.voice = avatar.gender;
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

watch(
    () => props.assistant.knowledge,
    (knowledge) => {
        form.knowledge = knowledge;
    },
);

const voices = [
    { value: 'female', label: 'Female voice' },
    { value: 'male', label: 'Male voice' },
    { value: 'off', label: 'No voice (text only)' },
] as const;

const fieldClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';
</script>

<template>
    <div class="grid gap-4" data-test="assistant-panel">
        <section class="panel p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <Bot class="mt-0.5 size-5 text-primary" />
                    <div>
                        <h3 class="text-base font-semibold">
                            Show the AI assistant on this website
                        </h3>
                        <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                            A friendly avatar sits in the corner of the website.
                            Visitors ask questions and it answers like a sales
                            representative, speaks the answer aloud, and can
                            collect their details for the business.
                        </p>
                    </div>
                </div>
                <Switch
                    :model-value="form.enabled"
                    aria-label="Show the AI assistant on this website"
                    data-test="assistant-enabled"
                    @update:model-value="(value: boolean) => (form.enabled = value === true)"
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

            <div
                v-if="!assistant.canAnswer"
                class="mt-3 flex gap-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
            >
                <TriangleAlert class="mt-0.5 size-4 shrink-0" />
                <p>
                    Add ANTHROPIC_API_KEY in your environment so the assistant
                    can answer. Until then it only collects visitor details.
                </p>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                <div class="flex items-center gap-2 rounded-lg border bg-card p-3">
                    <MessageSquare class="size-4 text-muted-foreground" />
                    <div>
                        <dt class="text-xs text-muted-foreground">Questions answered</dt>
                        <dd class="font-semibold">{{ assistant.messagesCount }}</dd>
                    </div>
                </div>
                <div class="flex items-center gap-2 rounded-lg border bg-card p-3">
                    <Users class="size-4 text-muted-foreground" />
                    <div>
                        <dt class="text-xs text-muted-foreground">Visitors who left details</dt>
                        <dd class="font-semibold">{{ assistant.leadsCount }}</dd>
                    </div>
                </div>
            </dl>
        </section>

        <section class="panel p-5">
            <h3 class="text-base font-semibold">Avatar</h3>
            <p class="mt-1 text-sm text-muted-foreground">
                The voice follows the avatar unless you change it.
            </p>
            <div
                class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-6"
                role="radiogroup"
                aria-label="Avatar"
            >
                <button
                    v-for="avatar in assistant.avatars"
                    :key="avatar.key"
                    type="button"
                    role="radio"
                    :aria-checked="form.avatar === avatar.key"
                    :data-test="`assistant-avatar-${avatar.key}`"
                    :class="[
                        'overflow-hidden rounded-lg border text-left transition',
                        form.avatar === avatar.key
                            ? 'border-primary ring-2 ring-primary/30'
                            : 'hover:border-primary/50',
                    ]"
                    @click="chooseAvatar(avatar)"
                >
                    <img
                        :src="avatar.imageUrl"
                        :alt="avatar.name"
                        loading="lazy"
                        class="aspect-square w-full object-cover"
                    />
                    <span class="block px-2 py-1.5 text-xs font-medium">
                        {{ avatar.name }}
                    </span>
                </button>
            </div>
            <InputError :message="errors.avatar" class="mt-2" />

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <label class="grid gap-1.5 text-sm font-medium">
                    Name
                    <Input
                        v-model="form.name"
                        maxlength="60"
                        data-test="assistant-name"
                    />
                    <InputError :message="errors.name" />
                </label>

                <fieldset class="grid gap-1.5">
                    <legend class="text-sm font-medium">Voice</legend>
                    <div class="grid gap-1.5">
                        <label
                            v-for="voice in voices"
                            :key="voice.value"
                            class="flex items-center gap-2 text-sm"
                        >
                            <input
                                v-model="form.voice"
                                type="radio"
                                name="assistant-voice"
                                :value="voice.value"
                                @change="voiceTouched = true"
                            />
                            {{ voice.label }}
                        </label>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Spoken with the visitor's own device, so it costs
                        nothing and starts instantly. Visitors can mute it.
                    </p>
                    <InputError :message="errors.voice" />
                </fieldset>
            </div>

            <label class="mt-4 grid gap-1.5 text-sm font-medium">
                Greeting
                <textarea
                    v-model="form.greeting"
                    rows="2"
                    maxlength="300"
                    :class="fieldClass"
                    placeholder="Leave empty for: Hi, I'm Maya, the virtual assistant for this business. How can I help you today?"
                    data-test="assistant-greeting"
                />
                <InputError :message="errors.greeting" />
            </label>
        </section>

        <section class="panel p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold">Knowledge base</h3>
                    <p class="mt-1 max-w-xl text-sm text-muted-foreground">
                        Everything the assistant knows. It was written from this
                        website and the Google listing. Edit anything that is
                        wrong, and add what visitors ask about: prices, offers,
                        service areas, policies. It will not answer beyond this.
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
                The assistant runs from this app, so an exported copy of the
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
