<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

type PageLink = {
    url: string | null;
    label: string;
    active: boolean;
};

const props = defineProps<{
    paginator: Paginated<unknown> & { links?: PageLink[] };
}>();

const pageLinks = computed(() =>
    (props.paginator.links ?? []).filter(
        (link) => link.label === '...' || /^\d+$/.test(link.label.trim()),
    ),
);
</script>

<template>
    <div
        v-if="paginator.total > 0"
        class="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground"
    >
        <span>
            <template v-if="paginator.from !== null && paginator.to !== null">
                Showing {{ paginator.from }}–{{ paginator.to }} of
                {{ paginator.total }}
            </template>
            <template v-else>{{ paginator.total }} items</template>
        </span>
        <div v-if="paginator.last_page > 1" class="flex flex-wrap items-center gap-1">
            <Button
                v-if="paginator.prev_page_url"
                as-child
                size="sm"
                variant="outline"
            >
                <Link :href="paginator.prev_page_url" preserve-scroll>Previous</Link>
            </Button>
            <template v-for="(link, index) in pageLinks" :key="`${link.label}-${index}`">
                <span v-if="link.label === '...'" class="px-1">…</span>
                <Button
                    v-else-if="link.active || !link.url"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    disabled
                >
                    {{ link.label }}
                </Button>
                <Button v-else as-child size="sm" variant="outline">
                    <Link :href="link.url" preserve-scroll>{{ link.label }}</Link>
                </Button>
            </template>
            <Button
                v-if="paginator.next_page_url"
                as-child
                size="sm"
                variant="outline"
            >
                <Link :href="paginator.next_page_url" preserve-scroll>Next</Link>
            </Button>
        </div>
    </div>
</template>
