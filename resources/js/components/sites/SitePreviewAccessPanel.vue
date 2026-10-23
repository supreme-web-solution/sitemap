<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { update as updateAccess } from '@/routes/sites/preview-access';
import type { EditableSite } from '@/types';

const props = defineProps<{
    site: EditableSite;
    teamSlug: string;
}>();

const form = useForm({
    preview_expires_at: props.site.previewExpiresAt ?? '',
    preview_password: '',
    clear_password: false,
});

const submit = () =>
    form.put(
        updateAccess.url({ current_team: props.teamSlug, site: props.site.id }),
        { preserveScroll: true },
    );
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Preview access</h2>
        <p class="mt-1 text-sm text-muted-foreground">
            Optional expiry and password for the public preview link.
        </p>
        <form class="mt-4 grid gap-3" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="preview-expires">Expires on</Label>
                <Input id="preview-expires" v-model="form.preview_expires_at" type="date" />
            </div>
            <div class="grid gap-2">
                <Label for="preview-password">
                    {{ site.hasPreviewPassword ? 'Replace password' : 'Password' }}
                </Label>
                <Input
                    id="preview-password"
                    v-model="form.preview_password"
                    type="password"
                    autocomplete="new-password"
                />
                <InputError :message="form.errors.preview_password" />
            </div>
            <label v-if="site.hasPreviewPassword" class="flex items-center gap-2 text-sm">
                <input v-model="form.clear_password" type="checkbox" class="size-4" />
                Remove password
            </label>
            <Button type="submit" size="sm" class="w-fit" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                Save access
            </Button>
        </form>
    </section>
</template>
