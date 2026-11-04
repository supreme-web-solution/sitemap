<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { CircleHelp, ExternalLink, LoaderCircle, Palette } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { store as storeBrand } from '@/routes/leads/brand';
import type { LeadProfile } from '@/types';
import type { BrandPullResult } from '@/types/ui';

const props = defineProps<{
    lead: LeadProfile;
}>();

const emit = defineEmits<{
    previewRefresh: [];
}>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const pullingBrand = ref(false);
const brandResult = ref<BrandPullResult | null>(null);

const pullBrand = () => {
    pullingBrand.value = true;
    brandResult.value = null;
    router.post(
        storeBrand.url({ current_team: teamSlug.value, lead: props.lead.id }),
        {},
        {
            preserveScroll: true,
            only: ['lead'],
            onFlash: (flash) => {
                const result = flash?.brand_pull as BrandPullResult | undefined;
                if (result) {
                    brandResult.value = result;
                }
            },
            onError: (errors) => {
                const message =
                    (typeof errors.website === 'string' && errors.website) ||
                    'Could not pull brand from that website.';
                toast.error(message);
            },
            onSuccess: () => {
                emit('previewRefresh');
            },
            onFinish: () => {
                pullingBrand.value = false;
            },
        },
    );
};

const pullBrandHint =
    'Reads their live site for a theme colour and logo. Saves the logo on the lead profile and updates your generated preview accent when you have one.';
</script>

<template>
    <section class="panel p-5" data-test="brand-panel">
        <h2 class="text-base font-semibold">Brand from their site</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Logo and accent colour for Business details and your generated
            preview. This is separate from the audit score.
        </p>

        <a
            v-if="lead.website"
            :href="lead.website"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-4 flex w-full items-center gap-2 rounded-lg border bg-muted/20 px-3 py-2 text-sm transition-colors hover:bg-muted/40"
        >
            <ExternalLink class="size-4 shrink-0 text-muted-foreground" />
            <span class="min-w-0 truncate font-medium">{{
                lead.website
            }}</span>
        </a>

        <div class="mt-4 flex items-center gap-1">
            <Button
                size="sm"
                variant="outline"
                :disabled="pullingBrand || !lead.website"
                data-test="pull-brand"
                @click="pullBrand"
            >
                <LoaderCircle v-if="pullingBrand" class="animate-spin" />
                <Palette v-else />
                Pull brand
            </Button>
            <Tooltip>
                <TooltipTrigger as-child>
                    <button
                        type="button"
                        class="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                        aria-label="What pull brand does"
                    >
                        <CircleHelp class="size-4" />
                    </button>
                </TooltipTrigger>
                <TooltipContent class="max-w-xs text-sm">
                    {{ pullBrandHint }}
                </TooltipContent>
            </Tooltip>
        </div>

        <div
            v-if="lead.logoUrl && !brandResult"
            class="mt-4 grid gap-3 rounded-lg border border-dashed bg-muted/20 p-3"
        >
            <img
                :src="lead.logoUrl"
                alt="Saved logo"
                class="h-12 w-fit max-w-full rounded border bg-white object-contain p-1"
            />
            <p class="text-sm text-muted-foreground">
                Logo already saved on this lead from a previous pull.
            </p>
        </div>

        <div
            v-if="brandResult && (brandResult.logoSaved || brandResult.accent)"
            class="mt-4 grid gap-4 rounded-lg border bg-muted/30 p-3"
            data-test="pull-brand-result"
        >
            <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                Latest pull
            </p>
            <div
                v-if="brandResult.accent"
                class="flex items-center gap-3"
            >
                <span
                    class="size-10 shrink-0 rounded-lg border shadow-sm"
                    :style="{ backgroundColor: brandResult.accent }"
                    :title="brandResult.accent"
                />
                <div class="min-w-0 grid gap-0.5 text-sm">
                    <span class="font-mono text-xs">{{
                        brandResult.accent
                    }}</span>
                    <span
                        v-if="brandResult.siteAccentUpdated"
                        class="text-xs text-muted-foreground"
                    >
                        Applied to generated preview
                    </span>
                    <span
                        v-else-if="!lead.site"
                        class="text-xs text-muted-foreground"
                    >
                        Generate a preview to use this colour
                    </span>
                </div>
            </div>
            <div v-if="brandResult.logoUrl" class="grid gap-2">
                <img
                    :src="brandResult.logoUrl"
                    alt="Pulled logo"
                    class="h-12 w-fit max-w-full rounded-md border bg-white object-contain p-1"
                />
                <p class="text-sm text-muted-foreground">
                    Logo saved — shown in Business details and on their site when
                    you build it.
                </p>
            </div>
        </div>

        <p
            v-else-if="!pullingBrand && !lead.logoUrl"
            class="mt-4 text-sm text-muted-foreground"
        >
            Pull brand when you are ready to match their look on the preview.
        </p>
    </section>
</template>
