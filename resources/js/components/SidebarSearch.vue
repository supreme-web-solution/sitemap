<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Search } from '@lucide/vue';
import { useEventListener } from '@vueuse/core';
import { computed, ref } from 'vue';
import { index as leadsIndex } from '@/routes/leads';

const page = usePage();
const query = ref('');
const input = ref<HTMLInputElement | null>(null);

const shortcut = computed(() =>
    typeof navigator !== 'undefined' &&
    /Mac|iPhone|iPad/.test(navigator.platform)
        ? '⌘K'
        : 'Ctrl K',
);

useEventListener(window, 'keydown', (event: KeyboardEvent) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        input.value?.focus();
    }
});

const submit = () => {
    const team = page.props.currentTeam;

    if (!team || !query.value.trim()) {
        return;
    }

    router.get(leadsIndex.url({ current_team: team.slug }), {
        search: query.value.trim(),
    });
};
</script>

<template>
    <form
        class="group/search relative px-3 group-data-[collapsible=icon]:hidden"
        role="search"
        @submit.prevent="submit"
    >
        <Search
            class="pointer-events-none absolute top-1/2 left-6 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <input
            ref="input"
            v-model="query"
            type="search"
            placeholder="Search leads…"
            aria-label="Search leads"
            class="h-10 w-full rounded-full border border-input bg-card pr-14 pl-9 text-sm shadow-xs transition-[box-shadow,border-color] outline-none placeholder:text-muted-foreground/80 hover:border-primary/30 focus-visible:border-primary/60 focus-visible:ring-4 focus-visible:ring-primary/15 [&::-webkit-search-cancel-button]:hidden"
        />
        <kbd
            class="pointer-events-none absolute top-1/2 right-6 -translate-y-1/2 rounded-md border bg-muted/70 px-1.5 py-0.5 font-sans text-[0.65rem] font-medium text-muted-foreground group-focus-within/search:hidden"
        >
            {{ shortcut }}
        </kbd>
    </form>
</template>
