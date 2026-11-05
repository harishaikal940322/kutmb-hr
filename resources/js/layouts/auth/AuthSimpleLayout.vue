<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const appName = usePage().props.name;
</script>

<template>
    <main
        class="flex min-h-svh flex-col items-center justify-center bg-muted/35 px-4 py-10 sm:px-6"
    >
        <div class="w-full max-w-md">
            <div class="mb-6 flex justify-center">
                <Link
                    :href="home()"
                    class="inline-flex min-h-11 items-center gap-3 rounded-lg px-3 py-2 font-medium focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                >
                    <span
                        class="flex size-10 items-center justify-center rounded-lg bg-primary text-primary-foreground"
                    >
                        <AppLogoIcon class="size-6 fill-current" />
                    </span>
                    <span class="text-sm font-semibold tracking-tight">
                        {{ appName }}
                    </span>
                </Link>
            </div>

            <section
                class="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
                aria-labelledby="auth-title"
            >
                <header class="mb-7 space-y-2">
                    <h1
                        id="auth-title"
                        class="text-2xl font-semibold tracking-tight"
                    >
                        {{ title }}
                    </h1>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ description }}
                    </p>
                </header>
                <slot />
            </section>
        </div>
    </main>
</template>
