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
import type { Option, Team } from '@/types';

const props = defineProps<{
    ideal: {
        categories: string[];
        cities: string[];
        minimumRating: number | null;
        websiteFilter: string | null;
    };
    websiteFilters: Option[];
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
            description="Saved filters used to rank and filter the lead list. Comma-separate several categories or cities."
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
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Save
            </Button>
        </form>
    </div>
</template>
