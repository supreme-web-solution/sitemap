<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import { index as tutorialsIndex } from '@/routes/tutorials';
import type { Team } from '@/types';

type Tutorial = {
    id: string;
    title: string;
    description: string;
    embedUrl: string;
};

const props = defineProps<{
    tutorials: Tutorial[];
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Tutorials',
                href: props.currentTeam
                    ? tutorialsIndex({ current_team: props.currentTeam.slug }).url
                    : '/',
            },
        ],
    }),
});

const activeId = ref(props.tutorials[0]?.id ?? '');

const active = computed(
    () =>
        props.tutorials.find((tutorial) => tutorial.id === activeId.value) ??
        props.tutorials[0],
);

const activeIndex = computed(() =>
    props.tutorials.findIndex((tutorial) => tutorial.id === active.value?.id),
);
</script>

<template>
    <Head title="Tutorials" />

    <div class="mx-auto flex w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Tutorials"
            description="Short videos for setting up leads, outreach, the pipeline, and your brand."
        />

        <div class="grid items-start gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
            <nav class="panel flex flex-col gap-1 p-2" aria-label="Tutorials">
                <button
                    v-for="(tutorial, index) in tutorials"
                    :key="tutorial.id"
                    type="button"
                    class="flex items-start gap-3 rounded-lg px-3 py-3 text-left transition-colors"
                    :class="
                        tutorial.id === active?.id
                            ? 'bg-primary/10 text-foreground'
                            : 'hover:bg-muted'
                    "
                    :aria-current="tutorial.id === active?.id ? 'true' : undefined"
                    @click="activeId = tutorial.id"
                >
                    <span
                        class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
                        :class="
                            tutorial.id === active?.id
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ index + 1 }}
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-medium">{{
                            tutorial.title
                        }}</span>
                        <span
                            class="mt-0.5 block text-xs leading-5 text-muted-foreground"
                        >
                            {{ tutorial.description }}
                        </span>
                    </span>
                </button>
            </nav>

            <section v-if="active" class="panel overflow-hidden">
                <div class="border-b px-5 py-4">
                    <p class="text-xs font-medium tracking-wide text-primary uppercase">
                        Video {{ activeIndex + 1 }} of {{ tutorials.length }}
                    </p>
                    <h2 class="mt-1 text-lg font-semibold">{{ active.title }}</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ active.description }}
                    </p>
                </div>

                <div class="bg-black">
                    <div class="relative aspect-video">
                        <iframe
                            :key="active.id"
                            :src="active.embedUrl"
                            class="absolute inset-0 h-full w-full border-0"
                            :title="active.title"
                            allow="autoplay; encrypted-media; fullscreen; picture-in-picture"
                            allowfullscreen
                        />
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>
