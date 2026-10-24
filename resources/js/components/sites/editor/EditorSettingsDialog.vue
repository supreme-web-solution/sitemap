<script setup lang="ts">
import {
    ArrowLeft,
    Globe,
    History,
    Lock,
    Newspaper,
    ShieldCheck,
    SlidersHorizontal,
    Star,
} from '@lucide/vue';
import { computed } from 'vue';
import type { Component } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';

export type SettingsTab =
    | 'general'
    | 'publish'
    | 'access'
    | 'reviews'
    | 'quality'
    | 'posts'
    | 'history';

type Section = {
    value: SettingsTab;
    label: string;
    description: string;
    icon: Component;
};

const open = defineModel<boolean>('open', { required: true });
const tab = defineModel<SettingsTab>('tab', { required: true });

defineProps<{
    /** Small count or flag shown next to a section name, e.g. failed checks. */
    badges?: Partial<Record<SettingsTab, string | number>>;
}>();

const sections: Section[] = [
    {
        value: 'general',
        label: 'General',
        description: 'Share, export, and rebuild this website.',
        icon: SlidersHorizontal,
    },
    {
        value: 'publish',
        label: 'Publish & domains',
        description: 'Staging, custom domains, and going live.',
        icon: Globe,
    },
    {
        value: 'access',
        label: 'Preview access',
        description: 'Control who can open the shared preview link.',
        icon: Lock,
    },
    {
        value: 'reviews',
        label: 'Google reviews',
        description: 'Choose which real reviews appear as testimonials.',
        icon: Star,
    },
    {
        value: 'quality',
        label: 'Quality & health',
        description: 'Checks to pass before you send this to a client.',
        icon: ShieldCheck,
    },
    {
        value: 'posts',
        label: 'Blog posts',
        description: 'Write and publish articles for this website.',
        icon: Newspaper,
    },
    {
        value: 'history',
        label: 'Version history',
        description: 'Every save is kept, so you can always go back.',
        icon: History,
    },
];

const current = computed(
    () => sections.find((section) => section.value === tab.value) ?? sections[0],
);
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent
            :show-close-button="false"
            class="flex h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-5xl"
            data-test="editor-settings"
        >
            <DialogTitle class="sr-only">Website settings</DialogTitle>
            <DialogDescription class="sr-only">
                Settings for this website. Close this window to return to the
                editor.
            </DialogDescription>

            <div
                class="flex shrink-0 items-center justify-between gap-3 border-b px-3 py-2.5"
            >
                <Button
                    variant="ghost"
                    size="sm"
                    data-test="editor-settings-close"
                    @click="open = false"
                >
                    <ArrowLeft /> Back to editor
                </Button>
                <p class="text-sm font-medium text-muted-foreground">
                    Website settings
                </p>
            </div>

            <div class="flex min-h-0 flex-1 flex-col md:flex-row">
                <nav
                    class="flex shrink-0 gap-1 overflow-x-auto border-b p-2 md:w-60 md:flex-col md:overflow-visible md:border-r md:border-b-0 md:p-3"
                    aria-label="Settings sections"
                >
                    <button
                        v-for="section in sections"
                        :key="section.value"
                        type="button"
                        :aria-current="tab === section.value ? 'page' : undefined"
                        :data-test="`settings-nav-${section.value}`"
                        :class="[
                            'flex shrink-0 items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm font-medium transition',
                            tab === section.value
                                ? 'bg-secondary text-secondary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                        ]"
                        @click="tab = section.value"
                    >
                        <component :is="section.icon" class="size-4 shrink-0" />
                        <span class="flex-1 whitespace-nowrap">{{
                            section.label
                        }}</span>
                        <span
                            v-if="badges?.[section.value] !== undefined"
                            class="rounded-full bg-amber-100 px-1.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-500/15 dark:text-amber-300"
                        >
                            {{ badges?.[section.value] }}
                        </span>
                    </button>
                </nav>

                <div class="min-h-0 flex-1 overflow-y-auto p-5 md:p-6">
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ current.label }}
                    </h2>
                    <p class="mt-0.5 text-sm text-muted-foreground">
                        {{ current.description }}
                    </p>

                    <div
                        v-for="section in sections"
                        v-show="tab === section.value"
                        :key="section.value"
                        class="mt-5 grid gap-4"
                    >
                        <slot :name="section.value" />
                    </div>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
