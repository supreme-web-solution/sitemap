<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { Copy, Download, ExternalLink, Eye, Pencil } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { leadStatusClasses, leadStatusLabel } from '@/lib/leadStatus';
import { index as leadsIndex } from '@/routes/leads';
import {
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
                <div
                    class="h-24 p-4 text-white"
                    :style="{
                        background: `linear-gradient(135deg, ${site.accent}, #0f172a)`,
                    }"
                >
                    <div class="text-xs tracking-wide uppercase opacity-80">
                        {{ site.template }}
                    </div>
                    <div class="mt-1 line-clamp-2 font-semibold">
                        {{ site.headline }}
                    </div>
                </div>
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

        <div
            v-else
            class="rounded-xl border border-dashed p-12 text-center text-muted-foreground"
        >
            No websites yet.
            <Link
                :href="leadsIndex({ current_team: teamSlug })"
                class="font-medium text-foreground underline"
                >Find a lead</Link
            >
            and click “Create website”.
        </div>

        <div v-if="sites.last_page > 1" class="flex justify-end gap-2 text-sm">
            <Button
                v-if="sites.prev_page_url"
                size="sm"
                variant="outline"
                as-child
            >
                <Link :href="sites.prev_page_url">Previous</Link>
            </Button>
            <Button
                v-if="sites.next_page_url"
                size="sm"
                variant="outline"
                as-child
            >
                <Link :href="sites.next_page_url">Next</Link>
            </Button>
        </div>
    </div>
</template>
