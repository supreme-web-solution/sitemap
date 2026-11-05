<script setup lang="ts">
import { computed } from 'vue';
import { renderChatMarkdown } from '@/lib/chatMarkdown';

const props = defineProps<{
    source: string;
}>();

const html = computed(() => renderChatMarkdown(props.source));
</script>

<template>
    <div class="chat-md" v-html="html" />
</template>

<style scoped>
.chat-md :deep(p) {
    margin: 0;
}

.chat-md :deep(p + p) {
    margin-top: 0.85rem;
}

.chat-md :deep(strong) {
    font-weight: 650;
}

.chat-md :deep(em) {
    font-style: italic;
}

.chat-md :deep(ul),
.chat-md :deep(ol) {
    margin: 0.7rem 0 0;
    padding-left: 1.15rem;
}

.chat-md :deep(li + li) {
    margin-top: 0.35rem;
}

.chat-md :deep(ul) {
    list-style: disc;
}

.chat-md :deep(ol) {
    list-style: decimal;
}

.chat-md :deep(code) {
    border-radius: 0.35rem;
    background: color-mix(in oklab, currentColor 8%, transparent);
    padding: 0.1rem 0.35rem;
    font-size: 0.86em;
}

.chat-md :deep(pre) {
    margin-top: 0.75rem;
    overflow-x: auto;
    border-radius: 0.75rem;
    background: color-mix(in oklab, currentColor 8%, transparent);
    padding: 0.75rem 0.9rem;
}

.chat-md :deep(pre code) {
    background: transparent;
    padding: 0;
}

.chat-md :deep(a) {
    text-decoration: underline;
    text-underline-offset: 2px;
}
</style>
