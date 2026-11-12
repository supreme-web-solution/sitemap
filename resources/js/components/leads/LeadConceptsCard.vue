<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Expand, ExternalLink, LoaderCircle, Sparkles } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { choose, store } from '@/routes/leads/concepts';
import type { LeadConcepts, SiteConceptOption } from '@/types';

const props = defineProps<{ leadId: number; concepts: LeadConcepts; siteBuilding?: boolean }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const isBuilding = computed(() => props.concepts.status === 'queued');

const previewOpen = ref(false);
const previewItem = ref<SiteConceptOption | null>(null);

const vibeLabel: Record<string, string> = {
    modern: 'Modern',
    warm: 'Warm',
    bold: 'Bold',
};

const openPreview = (item: SiteConceptOption) => {
    previewItem.value = item;
    previewOpen.value = true;
};

const request = () => {
    router.post(
        store.url({ current_team: teamSlug.value, lead: props.leadId }),
        {},
        { preserveScroll: true },
    );
};

const pick = (id: number) => {
    if (!confirm('Use this design as the website?')) {
        return;
    }

    router.post(
        choose.url({
            current_team: teamSlug.value,
            lead: props.leadId,
            concept: id,
        }),
        {},
        { preserveScroll: true },
    );
};
</script>

<template>
    <section class="panel p-5" data-test="lead-concepts-card">
        <h2 class="flex items-center gap-2 text-base font-semibold">
            <Sparkles class="size-4 text-primary" /> Three designs
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Compare three theme looks for this business. Expand any option, then
            choose one as the live site.
        </p>

        <p
            v-if="!concepts.available"
            class="mt-3 text-sm text-amber-700 dark:text-amber-300"
        >
            Design concepts are not included in your plan.
        </p>

        <template v-else>
            <p
                v-if="isBuilding"
                class="mt-3 flex items-center gap-2 text-sm font-medium"
            >
                <LoaderCircle class="size-4 animate-spin" />
                Building three options…
            </p>

            <ul v-if="concepts.items.length" class="mt-4 grid gap-4">
                <li
                    v-for="item in concepts.items"
                    :key="item.id"
                    class="overflow-hidden rounded-xl border bg-card shadow-sm transition-shadow hover:shadow-md"
                >
                    <div
                        class="flex items-center justify-between gap-2 border-b px-3 py-2"
                        :style="{
                            background: `linear-gradient(135deg, color-mix(in srgb, ${item.accent} 22%, white), white)`,
                        }"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold">
                                {{ item.label }}
                            </p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ item.headline }}
                            </p>
                            <p
                                v-if="item.layoutSummary"
                                class="mt-0.5 truncate text-[11px] capitalize text-muted-foreground/80"
                                :title="item.layoutSummary"
                            >
                                {{ item.layoutSummary }}
                            </p>
                        </div>
                        <Badge variant="secondary" class="shrink-0 capitalize">
                            {{ vibeLabel[item.vibe] ?? item.vibe }}
                        </Badge>
                    </div>

                    <div class="relative bg-muted/40">
                        <div
                            class="relative h-44 w-full overflow-hidden sm:h-52"
                        >
                            <iframe
                                :src="item.previewUrl"
                                :title="`Preview ${item.label}`"
                                class="pointer-events-none absolute left-0 top-0 h-[200%] w-[200%] origin-top-left scale-50 border-0 bg-white"
                                loading="lazy"
                                tabindex="-1"
                            />
                            <div
                                class="absolute inset-0 bg-gradient-to-t from-background/80 via-transparent to-transparent"
                            />
                            <Button
                                type="button"
                                size="sm"
                                variant="secondary"
                                class="absolute bottom-3 right-3 shadow-md"
                                :disabled="isBuilding"
                                @click="openPreview(item)"
                            >
                                <Expand />
                                Expand preview
                            </Button>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 p-3">
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="flex-1 sm:flex-none"
                            :disabled="isBuilding"
                            @click="openPreview(item)"
                        >
                            <Expand />
                            Preview
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            class="flex-1 sm:flex-none"
                            as-child
                        >
                            <a
                                :href="item.previewUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <ExternalLink />
                                New tab
                            </a>
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            class="w-full sm:ml-auto sm:w-auto"
                            :disabled="isBuilding"
                            @click="pick(item.id)"
                        >
                            Use this design
                        </Button>
                    </div>
                </li>
            </ul>

            <p
                v-if="siteBuilding"
                class="mt-3 text-sm text-muted-foreground"
            >
                Available after the website finishes building.
            </p>
            <Button
                v-if="!isBuilding"
                class="mt-4"
                size="sm"
                variant="outline"
                :disabled="siteBuilding"
                @click="request"
            >
                {{
                    concepts.items.length
                        ? 'Generate new options'
                        : 'Generate three designs'
                }}
            </Button>
        </template>

        <Dialog v-model:open="previewOpen">
            <DialogScrollContent
                v-if="previewItem"
                class="flex max-h-[92vh] w-[min(96vw,56rem)] max-w-none flex-col gap-0 overflow-hidden p-0"
                data-test="concept-preview-dialog"
            >
                <DialogHeader class="space-y-1 border-b px-4 py-3 text-left">
                    <DialogTitle>{{ previewItem.label }}</DialogTitle>
                    <DialogDescription class="line-clamp-2">
                        {{ previewItem.headline }}
                    </DialogDescription>
                </DialogHeader>
                <iframe
                    :src="previewItem.previewUrl"
                    :title="`Full preview ${previewItem.label}`"
                    class="min-h-[min(72vh,720px)] w-full flex-1 border-0 bg-white"
                />
                <DialogFooter
                    class="flex-wrap gap-2 border-t px-4 py-3 sm:justify-between"
                >
                    <Button variant="outline" as-child>
                        <a
                            :href="previewItem.previewUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink />
                            Open in new tab
                        </a>
                    </Button>
                    <Button
                        :disabled="isBuilding"
                        @click="pick(previewItem.id)"
                    >
                        Use this design
                    </Button>
                </DialogFooter>
            </DialogScrollContent>
        </Dialog>
    </section>
</template>
