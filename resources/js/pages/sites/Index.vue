<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    Copy,
    Download,
    ExternalLink,
    Eye,
    Globe,
    Pencil,
    Sparkles,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import PaginationBar from '@/components/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { leadStatusClasses, leadStatusLabel } from '@/lib/leadStatus';
import { index as leadsIndex } from '@/routes/leads';
import {
    analytics as siteAnalytics,
    edit,
    exportMethod as exportSite,
    index as sitesIndex,
} from '@/routes/sites';
import type { Paginated, SiteSummary, Team } from '@/types';

defineProps<{
    sites: Paginated<SiteSummary>;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Websites',
                href: props.currentTeam
                    ? sitesIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const { copy } = useClipboard();

const copyLink = async (site: SiteSummary) => {
    await copy(site.previewUrl);
    toast.success('Preview link copied');
};
</script>

<template>
    <Head title="Websites" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4">
        <Heading
            variant="small"
            title="Generated websites"
            description="Share a live preview with each prospect, then export the site when they buy."
        />

        <div
            v-if="sites.data.length"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <article
                v-for="site in sites.data"
                :key="site.id"
                data-test="site-card"
                class="flex flex-col overflow-hidden panel"
            >
                <a
                    :href="site.previewUrl"
                    target="_blank"
                    rel="noopener"
                    class="site-hero-thumb relative isolate block overflow-hidden bg-slate-950"
                    :aria-label="`Preview ${site.name}`"
                >
                    <div
                        v-if="site.heroImage"
                        class="absolute inset-0 bg-cover bg-center"
                        :style="{ backgroundImage: `url(${site.heroImage})` }"
                    />
                    <div
                        class="absolute inset-0"
                        :style="{
                            background: `linear-gradient(135deg, ${site.accent}cc, #0f172acc)`,
                        }"
                    />
                    <iframe
                        :src="site.thumbnailUrl"
                        class="site-hero-thumb__frame pointer-events-none absolute top-0 left-0 z-10 border-0 bg-white"
                        loading="lazy"
                        tabindex="-1"
                        aria-hidden="true"
                    />
                    <span
                        class="absolute top-3 left-3 z-20 rounded-full bg-black/45 px-2 py-0.5 text-[10px] font-medium tracking-wide text-white uppercase backdrop-blur-sm"
                    >
                        {{ site.template }}
                    </span>
                </a>
                <div class="flex flex-1 flex-col gap-3 p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-medium">{{ site.name }}</div>
                            <div class="text-sm text-muted-foreground">
                                {{ site.category }}
                                <template v-if="site.city">
                                    · {{ site.city }}</template
                                >
                            </div>
                        </div>
                        <span
                            :class="[
                                'rounded-full px-2 py-0.5 text-xs font-medium whitespace-nowrap',
                                leadStatusClasses[site.leadStatus],
                            ]"
                        >
                            {{ leadStatusLabel(site.leadStatus) }}
                        </span>
                    </div>
                    <div
                        class="flex items-center gap-1 text-sm text-muted-foreground"
                    >
                        <Eye class="size-4" />
                        {{ site.viewsCount }}
                        {{ site.viewsCount === 1 ? 'view' : 'views' }}
                        <template v-if="site.lastViewedAt">
                            · last opened
                            {{
                                new Date(site.lastViewedAt).toLocaleDateString()
                            }}
                        </template>
                    </div>
                    <div class="mt-auto flex flex-wrap gap-2">
                        <Button size="sm" as-child>
                            <Link
                                :href="
                                    edit({
                                        current_team: teamSlug,
                                        site: site.id,
                                    })
                                "
                            >
                                <Pencil /> Edit
                            </Link>
                        </Button>
                        <Button size="sm" variant="outline" as-child>
                            <a
                                :href="site.previewUrl"
                                target="_blank"
                                rel="noopener"
                            >
                                <ExternalLink /> Preview
                            </a>
                        </Button>
                        <Button size="sm" variant="outline" as-child>
                            <Link
                                :href="
                                    siteAnalytics.url({
                                        current_team: teamSlug,
                                        site: site.id,
                                    })
                                "
                            >
                                <Eye /> Analytics
                            </Link>
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            @click="copyLink(site)"
                        >
                            <Copy /> Copy link
                        </Button>
                        <Button size="sm" variant="ghost" as-child>
                            <a
                                :href="
                                    exportSite.url({
                                        current_team: teamSlug,
                                        site: site.id,
                                    })
                                "
                            >
                                <Download /> ZIP
                            </a>
                        </Button>
                    </div>
                </div>
            </article>
        </div>

        <EmptyState
            v-else
            title="No websites yet"
            description="Find a local business, confirm their details, then generate a preview site to share before they buy."
            :icon="Globe"
        >
            <Button as-child>
                <Link :href="leadsIndex({ current_team: teamSlug })">
                    <Sparkles /> Find leads
                </Link>
            </Button>
        </EmptyState>

        <PaginationBar :paginator="sites" />
    </div>
</template>

<style scoped>
.site-hero-thumb {
    container-type: inline-size;
    aspect-ratio: 16 / 10;
}
.site-hero-thumb__frame {
    width: 1280px;
    height: 800px;
    transform: scale(calc(100cqw / 1280));
    transform-origin: top left;
}
</style>
