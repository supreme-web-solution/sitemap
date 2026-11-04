<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { FileText, LoaderCircle, Package, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import PaginationBar from '@/components/PaginationBar.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import BillingIntervalSelect from '@/components/forms/BillingIntervalSelect.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogDescription,
    DialogHeader,
    DialogScrollContent,
    DialogTitle,
} from '@/components/ui/dialog';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create as createProposal } from '@/routes/proposals';
import { destroy, store, update } from '@/routes/packages';
import type { Paginated } from '@/types';

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

const props = defineProps<{
    packages: Paginated<PackageRow>;
    intervals: { value: string; label: string }[];
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Packages', href: '#' }] },
});

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const modalOpen = ref(false);
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
    modalOpen.value = true;
};

const startEditing = (row: PackageRow) => {
    editing.value = row;
    form.clearErrors();
    form.name = row.name;
    form.description = row.description ?? '';
    form.price = row.price;
    form.interval = row.interval ?? '';
    form.is_active = row.isActive;
    modalOpen.value = true;
};

const closeModal = () => {
    modalOpen.value = false;
};

watch(modalOpen, (isOpen) => {
    if (!isOpen) {
        editing.value = null;
        form.clearErrors();
    }
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        description: data.description || null,
        interval: data.interval || null,
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            modalOpen.value = false;
        },
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

const hasActivePackages = computed(() =>
    props.packages.data.some((row) => row.isActive),
);
</script>

<template>
    <Head title="Packages" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <div class="flex items-start justify-between gap-4">
            <Heading
                title="Packages"
                description="Services you sell once or on a schedule. Active packages appear when you build a proposal."
            />
            <Button @click="startCreating"><Plus /> New package</Button>
        </div>

        <section
            v-if="packages.data.length"
            class="panel flex gap-3 p-4 text-sm"
            data-test="packages-how-to-use"
        >
            <FileText class="size-5 shrink-0 text-primary" />
            <div class="grid gap-2">
                <p class="font-medium">Use packages in a proposal</p>
                <ol class="list-decimal space-y-1 pl-4 text-muted-foreground">
                    <li>
                        Go to
                        <strong class="font-medium text-foreground">Sell → Proposals</strong>
                        and click <strong class="font-medium text-foreground">New proposal</strong>.
                    </li>
                    <li>Choose the client (lead).</li>
                    <li>
                        Under <strong class="font-medium text-foreground">Items</strong>, open
                        <strong class="font-medium text-foreground">Add from packages…</strong>
                        — your active packages fill in price and billing.
                    </li>
                </ol>
                <p v-if="!hasActivePackages" class="text-amber-700 dark:text-amber-300">
                    No packages are marked “Available in proposals” yet. Edit a package and turn
                    that on.
                </p>
                <Button variant="outline" size="sm" class="mt-1 w-fit" as-child>
                    <Link :href="createProposal(teamSlug)">New proposal</Link>
                </Button>
            </div>
        </section>

        <EmptyState
            v-if="!packages.data.length"
            title="No packages yet"
            description="Add website builds, hosting, or maintenance. Then drop them into proposals in one click."
            :icon="Package"
        >
            <Button @click="startCreating"><Plus /> New package</Button>
        </EmptyState>

        <div v-if="packages.data.length" class="flex flex-col gap-3">
            <ul class="grid gap-3">
                <li
                    v-for="row in packages.data"
                    :key="row.id"
                    class="flex items-start justify-between gap-3 panel p-4"
                >
                    <div>
                        <p class="font-medium">
                            {{ row.name }}
                            <span
                                v-if="!row.isActive"
                                class="ml-1 rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-normal text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"
                            >
                                Hidden from proposals
                            </span>
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ row.priceLabel }} · {{ row.intervalLabel ?? 'One time' }}
                        </p>
                        <p v-if="row.description" class="mt-1 text-sm">{{ row.description }}</p>
                    </div>
                    <div class="flex gap-1">
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Edit package"
                            @click="startEditing(row)"
                        >
                            <Pencil />
                        </Button>
                        <Button
                            size="icon-sm"
                            variant="ghost"
                            aria-label="Delete package"
                            @click="remove(row)"
                        >
                            <Trash2 />
                        </Button>
                    </div>
                </li>
            </ul>
            <PaginationBar :paginator="packages" />
        </div>

        <Dialog v-model:open="modalOpen">
            <DialogScrollContent class="max-w-lg" data-test="package-form">
                <DialogHeader>
                    <DialogTitle>{{
                        editing ? 'Edit package' : 'New package'
                    }}</DialogTitle>
                    <DialogDescription>
                        Prices are in US dollars. Active packages show up on the proposal editor.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="pkg-name">Name</Label>
                            <Input
                                id="pkg-name"
                                v-model="form.name"
                                placeholder="Website design and build"
                            />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="pkg-price">Price (USD)</Label>
                            <Input
                                id="pkg-price"
                                v-model.number="form.price"
                                type="number"
                                min="0.01"
                                step="0.01"
                            />
                            <InputError :message="form.errors.price" />
                        </div>
                        <div class="grid gap-2">
                            <Label>Billing</Label>
                            <BillingIntervalSelect
                                v-model="form.interval"
                                :intervals="intervals"
                            />
                            <InputError :message="form.errors.interval" />
                        </div>
                        <label
                            class="flex items-center gap-2 text-sm sm:col-span-2"
                        >
                            <input
                                v-model="form.is_active"
                                type="checkbox"
                                class="size-4"
                            />
                            Available in proposals
                        </label>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="pkg-desc">Description (optional)</Label>
                            <Input id="pkg-desc" v-model="form.description" />
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <Button type="submit" :disabled="form.processing">
                            <LoaderCircle
                                v-if="form.processing"
                                class="animate-spin"
                            />
                            {{ editing ? 'Save package' : 'Add package' }}
                        </Button>
                        <Button type="button" variant="ghost" @click="closeModal">
                            Cancel
                        </Button>
                    </div>
                </form>
            </DialogScrollContent>
        </Dialog>
    </div>
</template>
