<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Eye, MessageSquare, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { analytics, edit as editSite, index as sitesIndex } from '@/routes/sites';
import type { Team } from '@/types';

type SectionView = {
    key: string;
    label: string;
    views: number;
    share: number;
};

type InterestEvent = {
    id: number;
    type: string;
    label: string;
    summary: string;
    name: string | null;
    contact: string | null;
    message: string | null;
    createdAt: string | null;
};

type CommentRow = {
    id: number;
    author: string;
    contact: string | null;
    section: string | null;
    body: string;
    createdAt: string | null;
};

const props = defineProps<{
    site: {
        id: number;
        name: string;
        previewUrl: string;
        viewsCount: number;
        lastViewedAt: string | null;
        interestCount: number;
        commentCount: number;
        claimCount: number;
    };
    sectionViews: SectionView[];
    interestEvents: InterestEvent[];
    comments: CommentRow[];
}>();

defineOptions({
    layout: (layoutProps: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Websites',
                href: layoutProps.currentTeam
                    ? sitesIndex({ current_team: layoutProps.currentTeam.slug })
                    : '/',
            },
            {
                title: props.site.name,
                href: layoutProps.currentTeam
                    ? editSite({
                          current_team: layoutProps.currentTeam.slug,
                          site: props.site.id,
                      })
                    : '/',
            },
            {
                title: 'Analytics',
                href: layoutProps.currentTeam
                    ? analytics.url({
                          current_team: layoutProps.currentTeam.slug,
                          site: props.site.id,
                      })
                    : '#',
            },
        ],
    }),
});

const formatWhen = (value: string | null) =>
    value ? new Date(value).toLocaleString() : '—';

const hasEngagement = computed(
    () =>
        props.site.viewsCount > 0 ||
        props.interestEvents.length > 0 ||
        props.comments.length > 0,
);
</script>

<template>
    <Head :title="`${site.name} · Preview analytics`" />

    <div class="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <Heading
                :title="site.name"
                description="How prospects engage with this preview — views, sections, claims, and comments."
            />
            <div class="flex gap-2">
                <Button variant="outline" size="sm" as-child>
                    <Link
                        :href="
                            editSite({
                                current_team: $page.props.currentTeam?.slug ?? '',
                                site: site.id,
                            })
                        "
                    >
                        <ArrowLeft class="size-4" />
                        Editor
                    </Link>
                </Button>
                <Button variant="outline" size="sm" as-child>
                    <a :href="site.previewUrl" target="_blank" rel="noopener">
                        Open preview
                    </a>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Preview views</p>
                <p class="mt-1 flex items-center gap-2 text-2xl font-semibold">
                    <Eye class="size-5 text-muted-foreground" />
                    {{ site.viewsCount }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Last opened {{ formatWhen(site.lastViewedAt) }}
                </p>
            </div>
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Interest replies</p>
                <p class="mt-1 flex items-center gap-2 text-2xl font-semibold">
                    <MessageSquare class="size-5 text-muted-foreground" />
                    {{ site.interestCount }}
                </p>
            </div>
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Claim requests</p>
                <p class="mt-1 flex items-center gap-2 text-2xl font-semibold">
                    <Sparkles class="size-5 text-muted-foreground" />
                    {{ site.claimCount }}
                </p>
            </div>
            <div class="panel p-4">
                <p class="text-xs text-muted-foreground">Section comments</p>
                <p class="mt-1 text-2xl font-semibold">
                    {{ site.commentCount }}
                </p>
            </div>
        </div>

        <section class="panel p-5">
            <h2 class="text-base font-semibold">Section attention</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Which parts of the page visitors scrolled into view.
            </p>
            <ul v-if="sectionViews.some((row) => row.views > 0)" class="mt-4 space-y-3">
                <li v-for="row in sectionViews" :key="row.key" class="grid gap-1">
                    <div class="flex justify-between text-sm">
                        <span>{{ row.label }}</span>
                        <span class="font-medium">{{ row.views }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-primary/80"
                            :style="{ width: `${row.share}%` }"
                        />
                    </div>
                </li>
            </ul>
            <p v-else class="mt-4 text-sm text-muted-foreground">
                No section views yet. Share the preview to start collecting attention data.
            </p>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="panel p-5">
                <h2 class="text-base font-semibold">Claims & interest</h2>
                <ul v-if="interestEvents.length" class="mt-4 space-y-3">
                    <li
                        v-for="event in interestEvents"
                        :key="event.id"
                        class="rounded-lg border border-border/70 p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                                {{ event.label }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatWhen(event.createdAt) }}
                            </span>
                        </div>
                        <p class="mt-1 text-sm font-medium">
                            {{ event.name || 'Visitor' }}
                            <span v-if="event.contact" class="font-normal text-muted-foreground">
                                · {{ event.contact }}
                            </span>
                        </p>
                        <p v-if="event.message" class="mt-1 text-sm text-muted-foreground">
                            {{ event.message }}
                        </p>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    No discuss or claim submissions yet.
                </p>
            </section>

            <section class="panel p-5">
                <h2 class="text-base font-semibold">Section comments</h2>
                <ul v-if="comments.length" class="mt-4 space-y-3">
                    <li
                        v-for="comment in comments"
                        :key="comment.id"
                        class="rounded-lg border border-border/70 p-3"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium">{{ comment.author }}</span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatWhen(comment.createdAt) }}
                            </span>
                        </div>
                        <p v-if="comment.section" class="text-xs text-muted-foreground">
                            Section: {{ comment.section }}
                        </p>
                        <p class="mt-1 text-sm">{{ comment.body }}</p>
                    </li>
                </ul>
                <p v-else class="mt-4 text-sm text-muted-foreground">
                    No section comments yet.
                </p>
            </section>
        </div>

        <p v-if="!hasEngagement" class="text-center text-sm text-muted-foreground">
            Analytics will fill in as prospects open and interact with the preview.
        </p>
    </div>
</template>
