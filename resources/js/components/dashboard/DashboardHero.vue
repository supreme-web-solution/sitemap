<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { ArrowUp, Link2, MapPin, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import StarArt from '@/components/StarArt.vue';
import { store as storeLeadImport } from '@/routes/lead-imports';
import { store as storeLeadSearch } from '@/routes/lead-searches';

const page = usePage();
const form = useForm({ prompt: '' });

const firstName = computed(
    () => page.props.auth.user.name.split(' ')[0] ?? 'there',
);
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const examples = [
    'Plumbers in Austin, TX',
    'Dentists in Leeds',
    'Barbers in Lagos',
];

const looksLikeMapsLink = (value: string): boolean =>
    /^https?:\/\//i.test(value) || /^ChIJ[\w-]{10,}$/.test(value);

const submit = () => {
    const prompt = form.prompt.trim();

    form.clearErrors();

    if (prompt === '') {
        form.setError(
            'prompt',
            'Tell me what to find, e.g. "plumbers in Austin".',
        );

        return;
    }

    if (looksLikeMapsLink(prompt)) {
        form.transform(() => ({ maps_url: prompt })).post(
            storeLeadImport({ current_team: teamSlug.value }).url,
        );

        return;
    }

    const match = prompt.match(/^(.+?)\s+(?:in|near|around|at)\s+(.+)$/i);

    if (!match) {
        form.setError(
            'prompt',
            'Use the format "business type in city", like "plumbers in Austin".',
        );

        return;
    }

    form.transform(() => ({
        keyword: match[1].trim(),
        location: match[2].trim(),
        website_filter: 'withoutWebsite',
        minimum_rating: null,
        max_results: 20,
    })).post(storeLeadSearch({ current_team: teamSlug.value }).url);
};

const errorMessage = computed(() => Object.values(form.errors)[0] ?? null);
</script>

<template>
    <section
        class="relative isolate overflow-hidden rounded-3xl border border-white/60 bg-hero px-6 py-8 sm:px-10 sm:py-10 dark:border-white/5"
    >
        <StarArt
            class="pointer-events-none absolute -top-6 right-6 -z-10 hidden w-36 animate-float-slow opacity-95 sm:block"
        />
        <StarArt
            class="pointer-events-none absolute right-40 bottom-4 -z-10 hidden w-14 opacity-80 md:block"
        />
        <StarArt
            class="pointer-events-none absolute right-2 bottom-8 -z-10 hidden w-9 opacity-70 md:block"
        />

        <p
            class="mb-3 inline-flex items-center gap-1.5 rounded-full bg-white/60 px-3 py-1 text-[0.68rem] font-semibold tracking-[0.08em] text-primary uppercase backdrop-blur dark:bg-white/10"
        >
            <Sparkles class="size-3.5" />
            AI prospecting
        </p>
        <h1
            class="max-w-2xl text-3xl font-semibold tracking-tight text-balance sm:text-4xl"
        >
            Welcome back, {{ firstName }}
        </h1>
        <p class="mt-2 max-w-xl text-base text-foreground/70">
            Find local businesses without a website and launch a ready-made site
            for each one in a click.
        </p>

        <form class="mt-7 max-w-2xl" @submit.prevent="submit">
            <label for="hero-prompt" class="sr-only"
                >What should we find?</label
            >
            <div
                class="flex items-center gap-2 rounded-2xl border border-white/80 bg-card p-2 pl-4 shadow-[0_8px_30px_-12px_rgba(88,28,135,0.35)] transition focus-within:ring-4 focus-within:ring-primary/15 dark:border-border"
            >
                <MapPin class="size-5 shrink-0 text-muted-foreground" />
                <input
                    id="hero-prompt"
                    v-model="form.prompt"
                    type="text"
                    autocomplete="off"
                    placeholder="Plumbers in Austin, or paste a Google Maps link"
                    class="h-10 min-w-0 flex-1 bg-transparent text-[0.95rem] outline-none placeholder:text-muted-foreground"
                />
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="grid size-10 shrink-0 place-items-center rounded-xl bg-primary text-primary-foreground shadow-sm transition hover:bg-primary/90 active:translate-y-px disabled:opacity-60"
                    aria-label="Start search"
                >
                    <ArrowUp class="size-5" />
                </button>
            </div>
            <p v-if="errorMessage" class="mt-2 pl-2 text-sm text-destructive">
                {{ errorMessage }}
            </p>
        </form>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button
                v-for="example in examples"
                :key="example"
                type="button"
                class="rounded-full border border-white/70 bg-white/55 px-3.5 py-1.5 text-sm text-foreground/75 backdrop-blur transition hover:bg-white hover:text-foreground dark:border-white/10 dark:bg-white/10 dark:hover:bg-white/15"
                @click="form.prompt = example"
            >
                {{ example }}
            </button>
            <span
                class="hidden items-center gap-1.5 text-xs text-foreground/55 sm:inline-flex"
            >
                <Link2 class="size-3.5" />
                Maps links work too
            </span>
        </div>
    </section>
</template>
