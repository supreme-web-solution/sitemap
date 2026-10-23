<script setup lang="ts">
import { useId } from 'vue';

const uid = useId();
</script>

<template>
    <svg viewBox="0 0 40 40" fill="none" aria-hidden="true">
        <defs>
            <linearGradient
                :id="`${uid}-bg`"
                x1="4"
                y1="2"
                x2="36"
                y2="38"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#9b6bff" />
                <stop offset="1" stop-color="#5b21d6" />
            </linearGradient>
        </defs>
        <rect width="40" height="40" rx="11" :fill="`url(#${uid}-bg)`" />
        <rect
            x="0.5"
            y="0.5"
            width="39"
            height="39"
            rx="10.5"
            stroke="white"
            stroke-opacity="0.25"
        />
        <path
            d="M20 7 C21.2 14.5 25.5 18.8 33 20 C25.5 21.2 21.2 25.5 20 33 C18.8 25.5 14.5 21.2 7 20 C14.5 18.8 18.8 14.5 20 7Z"
            fill="white"
        />
        <path
            d="M30.5 6.5 C30.9 8.6 32 9.7 34 10 C32 10.3 30.9 11.4 30.5 13.5 C30.1 11.4 29 10.3 27 10 C29 9.7 30.1 8.6 30.5 6.5Z"
            fill="white"
            fill-opacity="0.85"
        />
    </svg>
</template>
