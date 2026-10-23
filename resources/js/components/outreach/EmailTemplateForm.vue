<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle, Save, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    destroy as destroyTemplate,
    store as storeTemplate,
    update as updateTemplate,
} from '@/routes/email-templates';
import type { EmailTemplate } from '@/types';

const props = defineProps<{
    template?: EmailTemplate;
}>();

const emit = defineEmits<{ saved: [] }>();

const page = usePage();
const teamSlug = computed(() => page.props.currentTeam?.slug ?? '');

const form = useForm({
    name: props.template?.name ?? '',
    subject: props.template?.subject ?? '',
    body: props.template?.body ?? '',
});

const submit = () => {
    if (props.template) {
        form.put(
            updateTemplate.url({
                current_team: teamSlug.value,
                emailTemplate: props.template.id,
            }),
            { preserveScroll: true },
        );

        return;
    }

    form.post(storeTemplate.url({ current_team: teamSlug.value }), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
    });
};

const remove = () => {
    if (
        !props.template ||
        !window.confirm(`Delete "${props.template.name}"?`)
    ) {
        return;
    }

    form.delete(
        destroyTemplate.url({
            current_team: teamSlug.value,
            emailTemplate: props.template.id,
        }),
        { preserveScroll: true },
    );
};

const textareaClass =
    'w-full rounded-lg border border-input bg-card px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15';
</script>

<template>
    <form class="grid gap-4 panel p-5" @submit.prevent="submit">
        <div class="grid gap-4 md:grid-cols-2">
            <div class="grid gap-2">
                <Label :for="`name-${template?.id ?? 'new'}`"
                    >Template name</Label
                >
                <Input
                    :id="`name-${template?.id ?? 'new'}`"
                    v-model="form.name"
                    required
                />
                <InputError :message="form.errors.name" />
            </div>
            <div class="grid gap-2">
                <Label :for="`subject-${template?.id ?? 'new'}`">Subject</Label>
                <Input
                    :id="`subject-${template?.id ?? 'new'}`"
                    v-model="form.subject"
                    required
                />
                <InputError :message="form.errors.subject" />
            </div>
        </div>

        <div class="grid gap-2">
            <Label :for="`body-${template?.id ?? 'new'}`">Message</Label>
            <textarea
                :id="`body-${template?.id ?? 'new'}`"
                v-model="form.body"
                rows="8"
                required
                :class="textareaClass"
            />
            <InputError :message="form.errors.body" />
        </div>

        <div class="flex items-center justify-between">
            <Button type="submit" size="sm" :disabled="form.processing">
                <LoaderCircle v-if="form.processing" class="animate-spin" />
                <Save v-else />
                {{ template ? 'Save changes' : 'Create template' }}
            </Button>
            <Button
                v-if="template"
                type="button"
                size="sm"
                variant="ghost"
                class="text-destructive hover:text-destructive"
                @click="remove"
            >
                <Trash2 /> Delete
            </Button>
        </div>
    </form>
</template>
