<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Sparkles } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { choose, store } from '@/routes/leads/concepts';
import type { LeadConcepts } from '@/types';

const props = defineProps<{ leadId: number; concepts: LeadConcepts }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const isBuilding = computed(() => props.concepts.status === 'queued');

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
            <Sparkles class="size-4" /> Three designs
        </h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Generate a modern, warm and bold option, then pick the one to keep.
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

            <ul
                v-if="concepts.items.length"
                class="mt-3 grid gap-3 sm:grid-cols-3"
            >
                <li
                    v-for="item in concepts.items"
                    :key="item.id"
                    class="overflow-hidden rounded-lg border"
                >
                    <a
                        :href="item.previewUrl"
                        target="_blank"
                        rel="noopener"
                        class="block aspect-[4/3] border-b"
                        :style="{ backgroundColor: item.accent }"
                    >
                        <span class="sr-only">Preview {{ item.label }}</span>
                    </a>
                    <div class="grid gap-2 p-3">
                        <p class="text-sm font-medium">{{ item.label }}</p>
                        <p class="line-clamp-2 text-xs text-muted-foreground">
                            {{ item.headline }}
                        </p>
                        <Button
                            size="sm"
                            :disabled="isBuilding"
                            @click="pick(item.id)"
                        >
                            Use this design
                        </Button>
                    </div>
                </li>
            </ul>

            <Button
                v-if="!isBuilding"
                class="mt-3"
                size="sm"
                variant="outline"
                @click="request"
            >
                {{
                    concepts.items.length
                        ? 'Generate new options'
                        : 'Generate three designs'
                }}
            </Button>
        </template>
    </section>
</template>
