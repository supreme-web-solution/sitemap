<script setup lang="ts">
import PaginationBar from '@/components/PaginationBar.vue';
import type { Paginated } from '@/types';

defineProps<{ paginator: Paginated<unknown> }>();
</script>

<template>
    <PaginationBar :paginator="paginator" />
</template>
