<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as researchIndex, store as storeResearch } from '@/routes/research';
import type { Team } from '@/types';

defineProps<{
    briefs: {
        id: number;
        industry: string;
        location: string;
        status: string;
        brief: Record<string, unknown> | null;
        error: string | null;
        createdAt: string;
    }[];
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
</script>

<template>
    <Head title="Niche research" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Niche research"
            description="A practical brief for an industry and city: search queries, likely objections and what to say first."
        />

        <form class="panel grid gap-4 p-5 sm:grid-cols-2" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="industry">Industry</Label>
                <Input id="industry" v-model="form.industry" required maxlength="80" />
                <InputError :message="form.errors.industry" />
            </div>
            <div class="grid gap-2">
                <Label for="location">Location</Label>
                <Input id="location" v-model="form.location" required maxlength="120" />
                <InputError :message="form.errors.location" />
            </div>
            <Button type="submit" size="sm" class="sm:col-span-2 w-fit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Generate brief
            </Button>
        </form>

        <section
            v-for="item in briefs"
            :key="item.id"
            class="panel p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-base font-semibold">
                    {{ item.industry }} in {{ item.location }}
                </h2>
                <span class="text-xs text-muted-foreground">{{ item.status }}</span>
            </div>
            <p v-if="item.error" class="mt-2 text-sm text-destructive">{{ item.error }}</p>
            <p v-if="item.brief?.summary" class="mt-3 text-sm">
                {{ item.brief.summary }}
            </p>
            <ul
                v-if="Array.isArray(item.brief?.searchQueries)"
                class="mt-3 list-disc pl-5 text-sm text-muted-foreground"
            >
                <li v-for="query in item.brief.searchQueries as string[]" :key="query">
                    {{ query }}
                </li>
            </ul>
        </section>
    </div>
</template>
