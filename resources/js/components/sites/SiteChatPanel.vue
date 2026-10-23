<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { store } from '@/routes/sites/chat';
import type { SiteChat } from '@/types';

const props = defineProps<{ siteId: number; chat: SiteChat }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({ body: '' });

const submit = () => {
    form.post(
        store.url({ current_team: teamSlug.value, site: props.siteId }),
        {
            preserveScroll: true,
            onSuccess: () => form.reset('body'),
        },
    );
};
</script>

<template>
    <section class="panel p-5" data-test="site-chat-panel">
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <Sparkles class="size-4" /> Edit with AI
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Describe a change in plain English. The current website is saved
            first, so you can restore it.
        </p>

        <p
            v-if="!chat.available"
            class="mt-3 text-sm text-amber-700 dark:text-amber-300"
        >
            AI editing is not included in your plan.
        </p>

        <template v-else>
            <ol
                v-if="chat.messages.length"
                class="mt-3 grid max-h-72 gap-2 overflow-y-auto text-sm"
            >
                <li
                    v-for="message in chat.messages"
                    :key="message.id"
                    class="rounded-lg px-3 py-2"
                    :class="
                        message.role === 'user'
                            ? 'ml-auto max-w-[85%] bg-primary/10'
                            : 'bg-muted'
                    "
                >
                    <p class="whitespace-pre-wrap break-words">
                        {{ message.body }}
                    </p>
                    <p
                        v-if="message.status !== 'done'"
                        class="mt-1 text-[11px] text-muted-foreground"
                    >
                        {{ message.status }}
                    </p>
                </li>
            </ol>

            <form class="mt-3 grid gap-2" @submit.prevent="submit">
                <textarea
                    v-model="form.body"
                    rows="3"
                    maxlength="1000"
                    class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm"
                    placeholder='e.g. Change the headline to "Trusted Roofing in Austin"'
                    :disabled="chat.busy || form.processing"
                    aria-label="AI edit instruction"
                />
                <InputError :message="form.errors.body" />
                <Button
                    type="submit"
                    size="sm"
                    :disabled="chat.busy || form.processing"
                >
                    <LoaderCircle
                        v-if="chat.busy || form.processing"
                        class="animate-spin"
                    />
                    {{ chat.busy ? 'Editing…' : 'Apply edit' }}
                </Button>
            </form>
        </template>
    </section>
</template>
