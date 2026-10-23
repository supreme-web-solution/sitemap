<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { CheckCircle2, Globe, MapPin, Star } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import StarArt from '@/components/StarArt.vue';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();

const steps = [
    'Search Google Maps for businesses with no website',
    'AI builds a tailored site from their real details',
    'Share the preview link and close the deal',
];
</script>

<template>
    <div
        class="grid min-h-dvh bg-background lg:grid-cols-[minmax(0,1fr)_minmax(0,1.05fr)]"
    >
        <div class="flex min-h-dvh flex-col px-6 py-8 sm:px-12 lg:px-16">
            <Link
                :href="home()"
                class="flex w-fit items-center rounded-lg outline-none focus-visible:ring-4 focus-visible:ring-primary/15"
            >
                <AppLogo />
            </Link>

            <div class="flex flex-1 items-center justify-center py-10">
                <div class="w-full max-w-[26rem]">
                    <div class="mb-8 flex flex-col gap-2">
                        <h1
                            v-if="title"
                            class="text-3xl font-semibold tracking-tight text-balance sm:text-[2rem]"
                        >
                            {{ title }}
                        </h1>
                        <p
                            v-if="description"
                            class="text-[0.95rem] text-muted-foreground"
                        >
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>

            <p class="text-xs text-muted-foreground">
                &copy; {{ new Date().getFullYear() }} {{ name }}. All rights
                reserved.
            </p>
        </div>

        <div class="hidden p-4 lg:block">
            <div
                class="relative isolate flex h-full min-h-[40rem] flex-col justify-between overflow-hidden rounded-[2rem] bg-gradient-to-br from-violet-600 via-purple-500 to-pink-400 p-12 text-white shadow-2xl shadow-violet-900/20"
            >
                <div
                    class="absolute -top-24 -right-24 -z-10 size-96 rounded-full bg-white/20 blur-3xl"
                />
                <div
                    class="absolute -bottom-32 -left-20 -z-10 size-96 rounded-full bg-fuchsia-300/30 blur-3xl"
                />
                <StarArt
                    class="pointer-events-none absolute top-14 right-14 -z-10 w-44 animate-float-slow"
                />
                <StarArt
                    class="pointer-events-none absolute bottom-24 left-10 -z-10 w-16 opacity-80"
                />
                <StarArt
                    class="pointer-events-none absolute top-1/2 right-6 -z-10 w-10 opacity-70"
                />

                <div class="max-w-md">
                    <span
                        class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1 text-xs font-medium backdrop-blur"
                    >
                        <span class="size-1.5 rounded-full bg-emerald-300" />
                        Built for web designers and agencies
                    </span>
                    <h2
                        class="mt-5 text-4xl leading-[1.1] font-semibold tracking-tight text-balance"
                    >
                        Find local businesses. Launch their website in one
                        click.
                    </h2>
                    <ul
                        class="mt-6 flex flex-col gap-3 text-[0.95rem] text-white/85"
                    >
                        <li
                            v-for="step in steps"
                            :key="step"
                            class="flex items-start gap-2.5"
                        >
                            <CheckCircle2
                                class="mt-0.5 size-5 shrink-0 text-white"
                            />
                            {{ step }}
                        </li>
                    </ul>
                </div>

                <div class="relative mt-10 h-56">
                    <div
                        class="absolute bottom-0 left-0 w-72 rounded-2xl border border-white/30 bg-white/15 p-4 shadow-xl backdrop-blur-md"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="grid size-10 place-items-center rounded-xl bg-white/90 text-violet-600"
                            >
                                <MapPin class="size-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold">
                                    Bright Smile Dental
                                </p>
                                <p
                                    class="flex items-center gap-1 text-xs text-white/75"
                                >
                                    <Star
                                        class="size-3 fill-amber-300 text-amber-300"
                                    />
                                    4.8 &middot; Austin, TX
                                </p>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-white/75">
                            New lead found &middot; no website
                        </p>
                    </div>

                    <div
                        class="absolute top-0 right-0 w-64 rounded-2xl bg-white p-4 text-foreground shadow-2xl"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-medium text-violet-600"
                        >
                            <Globe class="size-4" />
                            Website ready
                        </div>
                        <div class="mt-3 space-y-2">
                            <div
                                class="h-2.5 w-3/4 rounded-full bg-violet-200"
                            />
                            <div class="h-2 w-full rounded-full bg-muted" />
                            <div class="h-2 w-5/6 rounded-full bg-muted" />
                        </div>
                        <div class="mt-4 flex items-center justify-between">
                            <span
                                class="rounded-full bg-violet-600 px-3 py-1 text-xs font-medium text-white"
                            >
                                Share preview
                            </span>
                            <span class="text-xs text-muted-foreground"
                                >12 views</span
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
