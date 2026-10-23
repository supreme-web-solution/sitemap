<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { destroy, store, update } from '@/routes/packages';

type PackageRow = {
    id: number;
    name: string;
    description: string | null;
    price: number;
    priceLabel: string;
    interval: string | null;
    intervalLabel: string | null;
    isActive: boolean;
};

defineProps<{
    packages: PackageRow[];
    intervals: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Packages', href: '#' }] },
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const open = ref(false);
const editing = ref<PackageRow | null>(null);

const form = useForm({
    name: '',
    description: '',
    price: '' as number | '',
    interval: '',
    is_active: true,
});

const startCreating = () => {
    editing.value = null;
    form.reset();
    form.clearErrors();
    open.value = true;
};

const startEditing = (row: PackageRow) => {
    editing.value = row;
    form.clearErrors();
    form.name = row.name;
    form.description = row.description ?? '';
    form.price = row.price;
    form.interval = row.interval ?? '';
    form.is_active = row.isActive;
    open.value = true;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        interval: data.interval || null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => (open.value = false),
    };

    if (editing.value) {
        form.put(
            update.url({ current_team: teamSlug.value, package: editing.value.id }),
            options,
        );

        return;
    }

    form.post(store.url(teamSlug.value), options);
};

const remove = (row: PackageRow) => {
    if (confirm(`Delete ${row.name}?`)) {
        router.delete(
            destroy.url({ current_team: teamSlug.value, package: row.id }),
            { preserveScroll: true },
        );
    }
};

const selectClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 dark:bg-input/30';
</script>

<template>
    <Head title="Packages" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Packages"
                description="The services you sell. Add them to proposals in one click. Prices are in US dollars."
            />
            <Button @click="startCreating"><Plus /> New package</Button>
        </div>

        <form v-if="open" class="grid gap-4 panel p-5" data-test="package-form" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="pkg-name">Name</Label>
                    <Input id="pkg-name" v-model="form.name" placeholder="Website design and build" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="pkg-price">Price (USD)</Label>
                    <Input id="pkg-price" v-model.number="form.price" type="number" min="0.01" step="0.01" />
                    <InputError :message="form.errors.price" />
                </div>
                <div class="grid gap-2">
                    <Label for="pkg-interval">Billing</Label>
                    <select id="pkg-interval" v-model="form.interval" :class="selectClass">
                        <option value="">One time</option>
                        <option v-for="interval in intervals" :key="interval.value" :value="interval.value">
                            {{ interval.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.interval" />
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm">
                    <input v-model="form.is_active" type="checkbox" class="size-4" />
                    Available in proposals
                </label>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="pkg-desc">Description</Label>
                    <Input id="pkg-desc" v-model="form.description" />
                </div>
            </div>
            <div class="flex gap-2">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    {{ editing ? 'Save package' : 'Add package' }}
                </Button>
                <Button type="button" variant="ghost" @click="open = false">Cancel</Button>
            </div>
        </form>

        <p v-if="!packages.length" class="text-sm text-muted-foreground">
            No packages yet. Add the services you sell to build proposals faster.
        </p>

        <ul class="grid gap-3">
            <li v-for="row in packages" :key="row.id" class="flex items-start justify-between gap-3 panel p-4">
                <div>
                    <p class="font-medium">
                        {{ row.name }}
                        <span v-if="!row.isActive" class="ml-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-600">Hidden</span>
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ row.priceLabel }} · {{ row.intervalLabel ?? 'One time' }}
                    </p>
                    <p v-if="row.description" class="mt-1 text-sm">{{ row.description }}</p>
                </div>
                <div class="flex gap-1">
                    <Button size="icon-sm" variant="ghost" aria-label="Edit package" @click="startEditing(row)"><Pencil /></Button>
                    <Button size="icon-sm" variant="ghost" aria-label="Delete package" @click="remove(row)"><Trash2 /></Button>
                </div>
            </li>
        </ul>
    </div>
</template>