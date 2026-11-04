<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Search } from '@lucide/vue';
import { computed } from 'vue';
import PaginationBar from '@/components/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as researchIndex, store as storeResearch } from '@/routes/research';
import type { Paginated, Team } from '@/types';

type NicheBriefPayload = {
    summary?: string;
    searchQueries?: string[];
    idealCustomer?: string;
    objections?: string[];
    openingLine?: string;
};

type BriefRow = {
    id: number;
    industry: string;
    location: string;
    status: string;
    brief: NicheBriefPayload | null;
    error: string | null;
    createdAt: string;
};

const props = defineProps<{
    briefs: Paginated<BriefRow>;
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Niche research',
                href: props.currentTeam
                    ? researchIndex({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const form = useForm({ industry: '', location: '' });

const submit = () =>
    form.post(storeResearch.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });

const statusLabel = (status: string) => {
    if (status === 'done') {
        return 'Ready';
    }

    if (status === 'queued') {
        return 'Preparing…';
    }

    return status;
};

const statusVariant = (status: string) => {
    if (status === 'done') {
        return 'default' as const;
    }

    if (status === 'queued') {
        return 'secondary' as const;
    }

    return 'destructive' as const;
};

const hasBriefs = computed(() => props.briefs.data.length > 0);
</script>

<template>
    <Head title="Niche research" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Niche research"
            description="Pick an industry and area. You get search phrases for Leads, a target profile, objections, and a first outreach line."
        />

        <form class="panel grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="industry">Industry or niche</Label>
                <Input
                    id="industry"
                    v-model="form.industry"
                    required
                    maxlength="80"
                    placeholder="e.g. Plumber, dental clinic, HVAC"
                    autocomplete="off"
                />
                <InputError :message="form.errors.industry" />
            </div>
            <div class="grid gap-2">
                <Label for="location">City or region</Label>
                <Input
                    id="location"
                    v-model="form.location"
                    required
                    maxlength="120"
                    placeholder="e.g. Austin TX, Manchester, Greater London"
                    autocomplete="off"
                />
                <InputError :message="form.errors.location" />
            </div>
            <Button type="submit" size="sm" class="w-fit sm:col-span-2" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Generate brief
            </Button>
        </form>

        <EmptyState
            v-if="!hasBriefs"
            title="No briefs yet"
            description="Enter an industry and location above. Your brief appears here with search queries, a target profile, objections, and a suggested opening line."
            :icon="Search"
        />

        <section
            v-for="item in briefs.data"
            :key="item.id"
            class="panel space-y-4 p-5"
        >
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold capitalize">
                        {{ item.industry }} in {{ item.location }}
                    </h2>
                    <p class="text-xs text-muted-foreground">
                        Generated {{ new Date(item.createdAt).toLocaleString() }}
                    </p>
                </div>
                <Badge :variant="statusVariant(item.status)">
                    {{ statusLabel(item.status) }}
                </Badge>
            </div>

            <p v-if="item.error" class="text-sm text-destructive">{{ item.error }}</p>

            <template v-if="item.status === 'done' && item.brief">
                <p v-if="item.brief.summary" class="text-sm leading-relaxed">
                    {{ item.brief.summary }}
                </p>

                <div v-if="item.brief.searchQueries?.length" class="space-y-2">
                    <h3 class="text-sm font-medium">Try these in lead search</h3>
                    <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li v-for="query in item.brief.searchQueries" :key="query">
                            {{ query }}
                        </li>
                    </ul>
                </div>

                <div v-if="item.brief.idealCustomer" class="space-y-1">
                    <h3 class="text-sm font-medium">Ideal customer snapshot</h3>
                    <p class="text-sm text-muted-foreground">{{ item.brief.idealCustomer }}</p>
                </div>

                <div v-if="item.brief.objections?.length" class="space-y-2">
                    <h3 class="text-sm font-medium">Objections you may hear</h3>
                    <ul class="list-disc space-y-1 pl-5 text-sm text-muted-foreground">
                        <li v-for="objection in item.brief.objections" :key="objection">
                            {{ objection }}
                        </li>
                    </ul>
                </div>

                <div v-if="item.brief.openingLine" class="rounded-lg border bg-muted/40 px-3 py-2">
                    <h3 class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                        Suggested opening line
                    </h3>
                    <p class="mt-1 text-sm">{{ item.brief.openingLine }}</p>
                </div>
            </template>

            <p
                v-else-if="item.status === 'queued'"
                class="flex items-center gap-2 text-sm text-muted-foreground"
            >
                <LoaderCircle class="size-4 animate-spin" aria-hidden="true" />
                Finishing your brief… refresh if this stays more than a few seconds.
            </p>
        </section>

        <PaginationBar v-if="hasBriefs" :paginator="briefs" />
    </div>
</template>
