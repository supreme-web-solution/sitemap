<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { emptyToSelect, SELECT_ALL, selectToEmpty } from '@/lib/selectSentinels';
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

const websiteFilterSelect = computed({
    get: () => emptyToSelect(form.websiteFilter),
    set: (value: string) => {
        form.websiteFilter = selectToEmpty(value);
    },
});

const weightSum = computed(() =>
    weightKeys.value.reduce(
        (total, key) => total + Number(form.score_weights[key] ?? 0),
        0,
    ),
);

const weightsBalanced = computed(() => weightSum.value === 100);

const saveButtonLabel = computed(() => {
    if (form.processing) {
        return 'Saving…';
    }

    if (!weightsBalanced.value) {
        const gap = 100 - weightSum.value;

        if (gap > 0) {
            return `Add ${gap} more points on the sliders to save (${weightSum.value}/100)`;
        }

        return `Remove ${Math.abs(gap)} points on the sliders to save (${weightSum.value}/100)`;
    }

    return 'Save ideal customer';
});

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
    if (!weightsBalanced.value) {
        return;
    }

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

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Ideal customer"
            description="Filters narrow the Leads list (Ideal customer toggle). Sliders set Hot / Warm / Cold — they must total 100 points."
        />

        <form class="panel grid gap-4 p-5" @submit.prevent="submit">
            <div>
                <p class="text-sm font-medium text-foreground">Who should match?</p>
                <p class="text-sm text-muted-foreground">
                    Comma-separate values. Leave blank to ignore a filter.
                </p>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="categories">Categories</Label>
                    <Input
                        id="categories"
                        v-model="form.categories"
                        placeholder="e.g. plumbers, HVAC, electricians"
                        autocomplete="off"
                    />
                    <InputError :message="form.errors.categories" />
                </div>
                <div class="grid gap-2">
                    <Label for="cities">Cities</Label>
                    <Input
                        id="cities"
                        v-model="form.cities"
                        placeholder="e.g. Austin, Dallas, Houston"
                        autocomplete="off"
                    />
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="rating">Minimum Google rating</Label>
                    <Input
                        id="rating"
                        v-model="form.minimumRating"
                        placeholder="e.g. 4 or 4.5 (optional)"
                        inputmode="decimal"
                    />
                    <InputError :message="form.errors.minimumRating" />
                </div>
                <div class="grid gap-2">
                    <Label>Website on listing</Label>
                    <Select v-model="websiteFilterSelect">
                        <SelectTrigger class="w-full" aria-label="Website on listing">
                            <SelectValue placeholder="Any" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="SELECT_ALL">Any</SelectItem>
                            <SelectItem
                                v-for="filter in websiteFilters"
                                :key="filter.value"
                                :value="filter.value"
                            >
                                {{ filter.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div class="grid gap-3 border-t pt-4">
                <div class="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <Label>Opportunity score weights</Label>
                        <p class="text-sm text-muted-foreground">
                            Hot, Warm, and Cold on the lead list use this mix.
                        </p>
                    </div>
                    <span
                        class="text-sm font-medium tabular-nums"
                        :class="
                            weightsBalanced
                                ? 'text-muted-foreground'
                                : 'text-destructive'
                        "
                        aria-live="polite"
                    >
                        {{ weightSum }} / 100
                    </span>
                </div>
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
                            >{{ form.score_weights[key] }} pts</span
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

            <details class="rounded-xl border border-border px-4 py-3 text-sm">
                <summary class="cursor-pointer font-medium text-foreground">
                    How each lead is scored (what we check)
                </summary>
                <ul class="mt-3 list-disc space-y-2 pl-5 text-muted-foreground">
                    <li>
                        <span class="font-medium text-foreground">Website need</span>
                        — full points when the listing has no website URL (your best prospects for selling a new site).
                    </li>
                    <li>
                        <span class="font-medium text-foreground">Website quality</span>
                        — if they have a site, uses your
                        <span class="font-medium text-foreground">website audit</span>
                        score (lower audit = more points). No site counts as maximum need here too.
                    </li>
                    <li>
                        <span class="font-medium text-foreground">Business activity</span>
                        — Google review count and star rating from lead search (more reviews + 4.5★+ helps).
                    </li>
                    <li>
                        <span class="font-medium text-foreground">Contact details</span>
                        — phone, email, and address on the lead record (partial credit if some are missing).
                    </li>
                    <li>
                        <span class="font-medium text-foreground">Profile completeness</span>
                        — category and city filled in on the lead.
                    </li>
                </ul>
                <p class="mt-3 text-muted-foreground">
                    For each signal the app computes a 0–100% fit, then multiplies by your slider weight
                    (e.g. 30 pts × 100% = 30). Totals cap at 100.
                    <span class="font-medium text-foreground">Hot</span> is 70+, <span class="font-medium text-foreground">Warm</span> is 45–69, below that is
                    <span class="font-medium text-foreground">Cold</span>.
                    Scores update when leads are saved or when you change weights here (background rescore).
                </p>
                <p class="mt-2 text-muted-foreground">
                    The filters at the top (categories, cities, etc.) do
                    <span class="font-medium text-foreground">not</span> change the math — they only narrow the list when you turn on
                    <span class="font-medium text-foreground">Ideal customer</span> on Leads.
                </p>
            </details>

            <div class="grid gap-2 rounded-xl bg-muted/50 p-4">
                <p class="text-sm font-medium">
                    Live preview: fixed sample plumber (no website, 4.8★, 80 reviews) scores
                    {{ liveExample.score }} / 100
                </p>
                <p class="text-xs text-muted-foreground">
                    This is not one of your leads — it shows how your current slider weights would rank a typical strong prospect.
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
                class="h-auto min-h-9 w-full whitespace-normal py-2.5 text-center"
                :variant="weightsBalanced ? 'default' : 'secondary'"
                :disabled="form.processing || !weightsBalanced"
                :aria-label="saveButtonLabel"
            >
                <LoaderCircle v-if="form.processing" class="size-4 shrink-0 animate-spin" />
                {{ saveButtonLabel }}
            </Button>
        </form>
    </div>
</template>
