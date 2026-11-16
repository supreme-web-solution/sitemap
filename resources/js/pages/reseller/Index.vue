<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, Search, Trash2, UserPlus } from '@lucide/vue';
import { ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AccountRow = {
    id: number;
    name: string;
    email: string;
    created_at: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    accounts: {
        data: AccountRow[];
        current_page: number;
        last_page: number;
        total: number;
        from: number | null;
        to: number | null;
        links: PaginationLink[];
    };
    filters: { search: string };
    stats: { total: number };
    accountRole: string;
    credentials: { email: string; password: string } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reseller', href: '/reseller' }],
    },
});

const search = ref(props.filters.search ?? '');
const deletingId = ref<number | null>(null);
let debounce: ReturnType<typeof setTimeout>;

watch(search, (value) => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get('/reseller', { search: value || undefined }, { preserveState: true, replace: true });
    }, 350);
});

const form = useForm({
    name: '',
    email: '',
});

const submit = () => {
    form.post('/reseller/accounts', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const deleteAccount = (account: AccountRow) => {
    if (!confirm(`Delete the account for "${account.name}" (${account.email})? They will lose access immediately.`)) {
        return;
    }

    deletingId.value = account.id;
    router.delete(`/reseller/accounts/${account.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
};
</script>

<template>
    <Head title="Reseller" />

    <div class="mx-auto flex w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            :title="`Reseller · ${stats.total.toLocaleString()} accounts`"
            :description="`Create accounts for your customers. Each account gets ${accountRole} access and receives its login details by email.`"
        />

        <form class="panel grid gap-4 p-5 md:grid-cols-[1fr_1fr_auto] md:items-end" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="reseller-name">Name</Label>
                <Input id="reseller-name" v-model="form.name" required />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label for="reseller-email">Email</Label>
                <Input id="reseller-email" v-model="form.email" type="email" required />
                <InputError :message="form.errors.email" />
            </div>
            <Button type="submit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <UserPlus v-else />
                Create account
            </Button>
        </form>

        <section
            v-if="credentials"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900"
        >
            <p class="font-medium">Share these login details with your customer:</p>
            <p class="mt-2">Email: <span class="font-mono">{{ credentials.email }}</span></p>
            <p>Password: <span class="font-mono">{{ credentials.password }}</span></p>
        </section>

        <section class="panel overflow-hidden">
            <div class="flex flex-col gap-3 border-b p-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold">Your customer accounts</p>
                    <p v-if="accounts.total > 0" class="text-xs text-muted-foreground">
                        {{ accounts.from }}–{{ accounts.to }} of {{ accounts.total.toLocaleString() }}
                    </p>
                </div>
                <div class="relative max-w-xs flex-1">
                    <Search class="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input v-model="search" class="pl-8" placeholder="Search name or email" aria-label="Search accounts" />
                </div>
            </div>

            <p v-if="!accounts.data.length" class="p-8 text-center text-sm text-muted-foreground">
                Accounts you create for your customers will appear here.
            </p>

            <table v-else class="w-full text-sm">
                <thead class="text-left text-xs text-muted-foreground">
                    <tr>
                        <th class="p-3 font-medium">Customer</th>
                        <th class="p-3 font-medium">Email</th>
                        <th class="p-3 font-medium">Access</th>
                        <th class="p-3 font-medium">Created</th>
                        <th class="p-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <tr v-for="account in accounts.data" :key="account.id">
                        <td class="p-3 font-medium">{{ account.name }}</td>
                        <td class="p-3">{{ account.email }}</td>
                        <td class="p-3">
                            <span class="rounded-full bg-muted px-2 py-0.5 text-xs">{{ accountRole }}</span>
                        </td>
                        <td class="p-3 text-xs text-muted-foreground">
                            {{ new Date(account.created_at).toLocaleDateString() }}
                        </td>
                        <td class="p-3 text-right">
                            <Button
                                size="sm"
                                variant="ghost"
                                :disabled="deletingId === account.id"
                                @click="deleteAccount(account)"
                            >
                                <Trash2 /> Delete
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
