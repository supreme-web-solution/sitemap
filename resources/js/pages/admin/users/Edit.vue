<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { LoaderCircle, UserRoundCog } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
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
import { SELECT_DEFAULT } from '@/lib/selectSentinels';
import { dashboard as adminDashboard } from '@/routes/admin';
import { update as updateTeam } from '@/routes/admin/teams';
import {
    destroy,
    impersonate,
    index,
    restore,
    suspend,
    update,
} from '@/routes/admin/users';
import type { AdminUser, PlanOption } from '@/types';

type UserTeam = {
    id: number;
    name: string;
    slug: string;
    is_personal: boolean;
    role: string;
    plan_id: number | null;
    plan_name: string | null;
};

const props = defineProps<{
    account: AdminUser;
    teams: UserTeam[];
    plans: PlanOption[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Admin', href: adminDashboard() },
            { title: 'Users', href: index() },
            { title: 'Edit', href: '#' },
        ],
    },
});

const form = useForm({
    name: props.account.name,
    email: props.account.email,
    password: '',
    verified: props.account.email_verified_at !== null,
});

const submit = () =>
    form.put(update.url(props.account.id), {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });

const setPlan = (team: UserTeam, value: string) =>
    router.put(
        updateTeam.url(team.slug),
        { plan_id: value === '' ? null : Number(value) },
        { preserveScroll: true },
    );

const remove = () => {
    if (
        confirm(
            `Delete ${props.account.name}? Teams where they are the only member are deleted too.`,
        )
    ) {
        router.delete(destroy.url(props.account.id));
    }
};

const teamPlanValue = (team: UserTeam) =>
    team.plan_id ? String(team.plan_id) : SELECT_DEFAULT;
</script>

<template>
    <Head :title="account.name" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading :title="account.name" :description="account.email" />

        <form class="grid gap-4 panel p-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" v-model="form.name" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="email">Email</Label>
                    <Input id="email" v-model="form.email" type="email" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2">
                    <Label for="password">New password</Label>
                    <PasswordInput
                        id="password"
                        v-model="form.password"
                        autocomplete="new-password"
                        placeholder="Leave blank to keep the current one"
                    />
                    <InputError :message="form.errors.password" />
                </div>
                <label class="flex items-end gap-2 pb-2 text-sm">
                    <input
                        v-model="form.verified"
                        type="checkbox"
                        class="size-4"
                    />
                    Email verified
                </label>
            </div>
            <div>
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Save changes
                </Button>
            </div>
        </form>

        <section class="panel p-5">
            <h2 class="mb-3 text-base font-semibold">Teams and plans</h2>
            <ul class="divide-y text-sm">
                <li
                    v-for="team in teams"
                    :key="team.id"
                    class="flex flex-wrap items-center justify-between gap-2 py-3"
                >
                    <div>
                        <p class="font-medium">{{ team.name }}</p>
                        <p class="text-xs text-muted-foreground capitalize">
                            {{ team.role }}
                            <template v-if="team.is_personal"> · personal</template>
                        </p>
                    </div>
                    <Select
                        :model-value="teamPlanValue(team)"
                        @update:model-value="
                            (value) => {
                                const chosen = String(value ?? '');
                                setPlan(
                                    team,
                                    chosen === SELECT_DEFAULT ? '' : chosen,
                                );
                            }
                        "
                    >
                        <SelectTrigger
                            class="h-9 w-44"
                            :aria-label="`Plan for ${team.name}`"
                        >
                            <SelectValue placeholder="Default plan" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="SELECT_DEFAULT">
                                Default plan
                            </SelectItem>
                            <SelectItem
                                v-for="plan in plans"
                                :key="plan.id"
                                :value="String(plan.id)"
                            >
                                {{ plan.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </li>
            </ul>
        </section>

        <section class="panel flex flex-wrap items-center gap-2 p-5">
            <template v-if="!account.is_admin">
                <Button
                    variant="outline"
                    @click="router.post(impersonate.url(account.id))"
                >
                    <UserRoundCog /> Sign in as this user
                </Button>
                <Button
                    v-if="!account.suspended_at"
                    variant="outline"
                    @click="router.post(suspend.url(account.id), {}, { preserveScroll: true })"
                >
                    Suspend
                </Button>
                <Button
                    v-else
                    variant="outline"
                    @click="router.delete(restore.url(account.id), { preserveScroll: true })"
                >
                    Reactivate
                </Button>
                <Button variant="destructive" @click="remove">Delete user</Button>
            </template>
            <p v-else class="text-sm text-muted-foreground">
                This account is an administrator (ADMIN_EMAILS), so it cannot be
                suspended, deleted or impersonated.
            </p>
        </section>
    </div>
</template>