<script setup lang="ts">
defineProps<{
    suggestions: { title: string; detail: string }[];
}>();
</script>

<template>
    <section class="panel p-5">
        <h2 class="text-base font-semibold">Next steps</h2>
        <ul class="mt-3 grid gap-3">
            <li v-for="item in suggestions" :key="item.title">
                <p class="text-sm font-medium">{{ item.title }}</p>
                <p class="text-sm text-muted-foreground">{{ item.detail }}</p>
            </li>
        </ul>
    </section>
</template>
