<script setup lang="ts">
import { useId } from 'vue';

/**
 * A chrome four-point star, built from four gradient facets so it reads as 3D.
 */
const uid = useId();
</script>

<template>
    <svg
        viewBox="0 0 100 100"
        fill="none"
        aria-hidden="true"
        class="drop-shadow-[0_18px_28px_rgb(70_30_110/0.35)]"
    >
        <defs>
            <linearGradient
                :id="`${uid}-tr`"
                x1="50"
                y1="0"
                x2="100"
                y2="50"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#ffffff" />
                <stop offset="0.55" stop-color="#d9d5e6" />
                <stop offset="1" stop-color="#9d97b4" />
            </linearGradient>
            <linearGradient
                :id="`${uid}-tl`"
                x1="0"
                y1="50"
                x2="50"
                y2="0"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#8f89a6" />
                <stop offset="0.5" stop-color="#e9e6f2" />
                <stop offset="1" stop-color="#ffffff" />
            </linearGradient>
            <linearGradient
                :id="`${uid}-br`"
                x1="100"
                y1="50"
                x2="50"
                y2="100"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#b4aecb" />
                <stop offset="0.5" stop-color="#6f6985" />
                <stop offset="1" stop-color="#cfcade" />
            </linearGradient>
            <linearGradient
                :id="`${uid}-bl`"
                x1="0"
                y1="50"
                x2="50"
                y2="100"
                gradientUnits="userSpaceOnUse"
            >
                <stop offset="0" stop-color="#f4f2fa" />
                <stop offset="0.5" stop-color="#bdb8d0" />
                <stop offset="1" stop-color="#7d7794" />
            </linearGradient>
        </defs>
        <path
            d="M50 50 L50 0 C54 30 70 46 100 50 Z"
            :fill="`url(#${uid}-tr)`"
        />
        <path d="M50 50 L50 0 C46 30 30 46 0 50 Z" :fill="`url(#${uid}-tl)`" />
        <path
            d="M50 50 L100 50 C70 54 54 70 50 100 Z"
            :fill="`url(#${uid}-br)`"
        />
        <path
            d="M50 50 L0 50 C30 54 46 70 50 100 Z"
            :fill="`url(#${uid}-bl)`"
        />
        <path
            d="M50 0 C54 30 70 46 100 50 C70 54 54 70 50 100 C46 70 30 54 0 50 C30 46 46 30 50 0Z"
            stroke="white"
            stroke-opacity="0.55"
            stroke-width="0.6"
        />
    </svg>
</template>
