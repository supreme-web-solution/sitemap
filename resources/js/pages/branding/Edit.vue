<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update } from '@/routes/branding';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    canManage: boolean;
    brand: { name: string; logoUrl: string; color: string; email: string };
    preview: { name: string; poweredBy: boolean };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Branding', href: '#' }] },
});

const page = usePage();

const form = useForm({
    name: props.brand.name,
    logo_url: props.brand.logoUrl,
    color: props.brand.color,
    email: props.brand.email,
});

const submit = () => {
    form.transform((data) => ({
        name: data.name || null,
        logo_url: data.logo_url || null,
        color: data.color || null,
        email: data.email || null,
    })).put(update.url(page.props.currentTeam?.slug ?? ''), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head title="Branding" />

    <div class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 p-4 md:p-6">
        <Heading
            title="Branding"
            description="Your clients see this on the client portal, proposals and invoice pages instead of the platform's name."
        />

        <form class="grid gap-4 panel p-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="brand-name">Agency name</Label>
                    <Input id="brand-name" v-model="form.name" :disabled="!canManage" placeholder="Your agency" />
                    <InputError :message="form.errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="brand-email">Support email</Label>
                    <Input id="brand-email" v-model="form.email" type="email" :disabled="!canManage" />
                    <InputError :message="form.errors.email" />
                </div>
                <div class="grid gap-2 sm:col-span-2">
                    <Label for="brand-logo">Logo URL (https)</Label>
                    <Input id="brand-logo" v-model="form.logo_url" type="url" :disabled="!canManage" placeholder="https://example.com/logo.png" />
                    <InputError :message="form.errors.logo_url" />
                </div>
                <div class="grid gap-2">
                    <Label for="brand-color">Accent colour</Label>
                    <div class="flex items-center gap-2">
                        <input id="brand-color" v-model="form.color" type="color" :disabled="!canManage" class="h-9 w-14 rounded border border-input" />
                        <span class="text-sm text-muted-foreground">{{ form.color }}</span>
                    </div>
                    <InputError :message="form.errors.color" />
                </div>
            </div>

            <p class="text-sm text-muted-foreground">
                <template v-if="preview.poweredBy">
                    Public pages show "Powered by {{ page.props.name }}". The
                    white-label feature removes it. Ask your administrator to
                    upgrade your plan.
                </template>
                <template v-else>
                    Public pages are white-labelled: no platform branding is shown.
                </template>
            </p>

            <div v-if="canManage">
                <Button type="submit" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="animate-spin" />
                    Save branding
                </Button>
            </div>
        </form>
    </div>
</template>