<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import BrandMark from '@/components/BrandMark.vue';

const name = usePage().props.name;
</script>

<template>
    <BrandMark class="size-8 shrink-0 drop-shadow-sm" />
    <div class="ml-0.5 grid flex-1 text-left text-[1.05rem]">
        <span class="truncate leading-tight font-semibold tracking-tight">{{
            name
        }}</span>
    </div>
</template>
