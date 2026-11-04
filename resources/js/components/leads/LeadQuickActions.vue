<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ClipboardCheck,
    ExternalLink,
    LoaderCircle,
    Pencil,
    RotateCcw,
    Sparkles,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { edit as reviewLead } from '@/routes/leads';
import { edit as editSite } from '@/routes/sites';
import type { Lead } from '@/types';

defineProps<{
    lead: Lead;
    teamSlug: string;
    generatingLeadId: number | null;
}>();

const emit = defineEmits<{
    pickDesign: [lead: Lead];
}>();
</script>

<template>
    <div class="flex flex-wrap items-center justify-end gap-1">
        <Tooltip>
            <TooltipTrigger as-child>
                <Button size="icon" variant="ghost" class="size-8" as-child>
                    <Link
                        :href="
                            reviewLead({
                                current_team: teamSlug,
                                lead: lead.id,
                            })
                        "
                    >
                        <ClipboardCheck
                            class="size-4"
                            :class="
                                lead.detailsConfirmed
                                    ? 'text-emerald-600'
                                    : undefined
                            "
                        />
                        <span class="sr-only">Review details</span>
                    </Link>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {{
                    lead.detailsConfirmed
                        ? 'Details confirmed'
                        : 'Review details, logo and photos'
                }}
            </TooltipContent>
        </Tooltip>

        <Button
            v-if="lead.siteGenerationStatus === 'queued'"
            size="icon"
            class="size-8"
            disabled
        >
            <LoaderCircle class="size-4 animate-spin" />
            <span class="sr-only">Building website</span>
        </Button>

        <template v-else-if="lead.site">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        size="icon"
                        variant="outline"
                        class="size-8"
                        as-child
                    >
                        <a
                            :href="lead.site.previewUrl"
                            target="_blank"
                            rel="noopener"
                        >
                            <ExternalLink class="size-4" />
                            <span class="sr-only">Preview site</span>
                        </a>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Preview site</TooltipContent>
            </Tooltip>
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button size="icon" class="size-8" as-child>
                        <Link
                            :href="
                                editSite({
                                    current_team: teamSlug,
                                    site: lead.site.id,
                                })
                            "
                        >
                            <Pencil class="size-4" />
                            <span class="sr-only">Edit site</span>
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent>Edit site</TooltipContent>
            </Tooltip>
        </template>

        <Tooltip v-else>
            <TooltipTrigger as-child>
                <Button
                    size="icon"
                    class="size-8"
                    data-test="generate-site-button"
                    :disabled="generatingLeadId !== null"
                    @click="emit('pickDesign', lead)"
                >
                    <LoaderCircle
                        v-if="generatingLeadId === lead.id"
                        class="size-4 animate-spin"
                    />
                    <RotateCcw
                        v-else-if="lead.siteGenerationStatus === 'failed'"
                        class="size-4"
                    />
                    <Sparkles v-else class="size-4" />
                    <span class="sr-only">
                        {{
                            lead.siteGenerationStatus === 'failed'
                                ? 'Try again'
                                : 'Create website'
                        }}
                    </span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>
                {{
                    lead.siteGenerationStatus === 'failed'
                        ? 'Try again'
                        : 'Create website'
                }}
            </TooltipContent>
        </Tooltip>
    </div>
</template>
