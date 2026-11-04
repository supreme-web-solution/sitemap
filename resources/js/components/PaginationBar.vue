<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';

defineProps<{ paginator: Paginated<unknown> }>();
</script>

<template>
    <div
        v-if="paginator.total > 0"
        class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
    >
        <span>
            <template v-if="paginator.from !== null && paginator.to !== null">
                Showing {{ paginator.from }}–{{ paginator.to }} of
                {{ paginator.total }}
            </template>
            <template v-else>{{ paginator.total }} items</template>
        </span>
        <div v-if="paginator.last_page > 1" class="flex gap-2">
            <Button
                v-if="paginator.prev_page_url"
                as-child
                size="sm"
                variant="outline"
            >
                <Link :href="paginator.prev_page_url" preserve-scroll
                    >Previous</Link
                >
            </Button>
            <Button
                v-if="paginator.next_page_url"
                as-child
                size="sm"
                variant="outline"
            >
                <Link :href="paginator.next_page_url" preserve-scroll
                    >Next</Link
                >
            </Button>
        </div>
    </div>
</template>
