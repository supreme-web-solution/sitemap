<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as idealCustomerEdit, update as updateIdeal } from '@/routes/ideal-customer';
import type { Option, ScoreComponent, Team } from '@/types';

const props = defineProps<{
    ideal: {
        categories: string[];
        cities: string[];
        minimumRating: number | null;
        websiteFilter: string | null;
    };
    websiteFilters: Option[];
    scoreWeights: Record<string, number>;
    weightLabels: Record<string, string>;
    example: { score: number; breakdown: ScoreComponent[] };
}>();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Ideal customer',
                href: props.currentTeam
                    ? idealCustomerEdit({ current_team: props.currentTeam.slug })
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({
    categories: (props.ideal.categories ?? []).join(', '),
    cities: (props.ideal.cities ?? []).join(', '),
    minimumRating: props.ideal.minimumRating ? String(props.ideal.minimumRating) : '',
    websiteFilter: props.ideal.websiteFilter ?? '',
    score_weights: { ...props.scoreWeights },
});

const weightKeys = computed(() => Object.keys(form.score_weights));

const weightSum = computed(() =>
    weightKeys.value.reduce(
        (total, key) => total + Number(form.score_weights[key] ?? 0),
        0,
    ),
);

const liveExample = computed(() => {
    const breakdown = props.example.breakdown.map((item) => {
        const max = Number(form.score_weights[item.key] ?? item.max);
        const ratio = item.max > 0 ? item.points / item.max : 0;
        const points = Math.round(ratio * max);

        return { ...item, max, points };
    });

    return {
        score: Math.min(
            100,
            breakdown.reduce((total, item) => total + item.points, 0),
        ),
        breakdown,
    };
});

const split = (value: string) =>
    value
        .split(',')
        .map((part) => part.trim())
        .filter(Boolean);

const submit = () => {
    form.transform((data) => ({
        categories: split(data.categories),
        cities: split(data.cities),
        minimumRating: data.minimumRating ? Number(data.minimumRating) : null,
        websiteFilter: data.websiteFilter || null,
        score_weights: Object.fromEntries(
            Object.entries(data.score_weights).map(([key, value]) => [
                key,
                Number(value),
            ]),
        ),
    })).put(updateIdeal.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Ideal customer" />

    <div class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Ideal customer"
            description="Who you want, and how strongly each signal ranks the lead list. Comma-separate several categories or cities."
        />

        <form class="panel grid gap-4 p-5" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="categories">Categories</Label>
                <Input
                    id="categories"
                    v-model="form.categories"
                    placeholder="plumbers, HVAC, electricians"
                />
                <InputError :message="form.errors.categories" />
            </div>
            <div class="grid gap-2">
                <Label for="cities">Cities</Label>
                <Input id="cities" v-model="form.cities" placeholder="Austin, Dallas" />
            </div>
            <div class="grid gap-2">
                <Label for="rating">Minimum rating</Label>
                <Input
                    id="rating"
                    v-model="form.minimumRating"
                    placeholder="4"
                />
            </div>
            <div class="grid gap-2">
                <Label for="website-filter">Website</Label>
                <select
                    id="website-filter"
                    v-model="form.websiteFilter"
                    class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs"
                >
                    <option value="">Any</option>
                    <option
                        v-for="filter in websiteFilters"
                        :key="filter.value"
                        :value="filter.value"
                    >
                        {{ filter.label }}
                    </option>
                </select>
            </div>

            <div class="grid gap-3 border-t pt-4">
                <div class="flex items-baseline justify-between gap-3">
                    <Label>Opportunity score weights</Label>
                    <span
                        class="text-xs tabular-nums"
                        :class="
                            weightSum === 100
                                ? 'text-muted-foreground'
                                : 'text-destructive'
                        "
                    >
                        {{ weightSum }} / 100
                    </span>
                </div>
                <p class="text-sm text-muted-foreground">
                    The five weights must add up to 100. Hot, Warm and Cold on
                    the lead list use this mix.
                </p>
                <div
                    v-for="key in weightKeys"
                    :key="key"
                    class="grid gap-1"
                >
                    <div class="flex items-center justify-between gap-2 text-sm">
                        <label :for="`weight-${key}`">{{
                            weightLabels[key] ?? key
                        }}</label>
                        <span class="tabular-nums text-muted-foreground"
                            >{{ form.score_weights[key] }}%</span
                        >
                    </div>
                    <input
                        :id="`weight-${key}`"
                        v-model.number="form.score_weights[key]"
                        type="range"
                        min="0"
                        max="100"
                        class="w-full accent-primary"
                    />
                </div>
                <InputError :message="form.errors.score_weights" />
            </div>

            <div class="grid gap-2 rounded-xl bg-muted/50 p-4">
                <p class="text-sm font-medium">
                    Example: a well-known plumber with no website scores
                    {{ liveExample.score }}
                </p>
                <ul class="grid gap-1 text-sm text-muted-foreground">
                    <li
                        v-for="item in liveExample.breakdown"
                        :key="item.key"
                    >
                        {{ item.label }} · {{ item.points }}/{{ item.max }}
                        · {{ item.reason }}
                    </li>
                </ul>
            </div>

            <Button
                type="submit"
                size="sm"
                class="w-fit"
                :disabled="form.processing || weightSum !== 100"
            >
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Save
            </Button>
        </form>
    </div>
</template>
