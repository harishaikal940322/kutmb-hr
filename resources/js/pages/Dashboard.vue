<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowUpRight, CircleCheck, UserRound } from '@lucide/vue';
import { computed } from 'vue';
import { dashboard, profile } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const page = usePage();
const displayName = computed(() => page.props.auth.user?.name ?? 'there');
</script>

<template>
    <Head title="Dashboard" />

    <section
        class="mx-auto w-full max-w-6xl space-y-7 px-4 py-7 sm:px-6 lg:px-8"
    >
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div class="space-y-2">
                <p
                    class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                >
                    System Core
                </p>
                <h1 class="text-3xl font-semibold tracking-tight sm:text-4xl">
                    Welcome back, {{ displayName }}
                </h1>
                <p
                    class="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base"
                >
                    Your account and access details are available in one place.
                </p>
            </div>

            <Link
                :href="profile()"
                class="inline-flex min-h-11 items-center justify-center gap-2 self-start rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 motion-reduce:transition-none sm:self-auto"
            >
                <UserRound class="size-4" aria-hidden="true" />
                View my profile
                <ArrowUpRight class="size-4" aria-hidden="true" />
            </Link>
        </header>

        <div
            class="grid gap-5 lg:grid-cols-[minmax(0,1.55fr)_minmax(16rem,0.8fr)]"
        >
            <section
                class="relative overflow-hidden rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
            >
                <div
                    class="pointer-events-none absolute -top-16 -right-12 size-56 rounded-full bg-primary/[0.035]"
                    aria-hidden="true"
                />
                <div
                    class="relative flex h-full flex-col justify-between gap-8"
                >
                    <div class="space-y-3">
                        <p class="text-sm font-medium text-muted-foreground">
                            Your workspace
                        </p>
                        <h2
                            class="max-w-xl text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            A clear view of your account
                        </h2>
                        <p
                            class="max-w-xl text-sm leading-6 text-muted-foreground"
                        >
                            Your profile shows the identity details currently
                            associated with your sign-in. Profile information is
                            read-only in this release.
                        </p>
                    </div>

                    <Link
                        :href="profile()"
                        class="group inline-flex min-h-11 w-fit items-center gap-2 rounded-md text-sm font-semibold underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-4 motion-reduce:transition-none"
                    >
                        Open My Profile
                        <ArrowUpRight
                            class="size-4 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </section>

            <aside
                class="flex flex-col justify-between gap-6 rounded-2xl border border-border bg-muted/35 p-6 sm:p-7"
            >
                <div class="space-y-3">
                    <div
                        class="flex size-10 items-center justify-center rounded-xl bg-background text-foreground shadow-sm ring-1 ring-border"
                    >
                        <CircleCheck class="size-5" aria-hidden="true" />
                    </div>
                    <h2 class="text-base font-semibold">Account access</h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        Your current session is active. Protected pages verify
                        your account status on the server.
                    </p>
                </div>
                <div
                    class="flex items-center gap-2 border-t border-border pt-4 text-sm font-medium"
                >
                    <span
                        class="size-2 rounded-full bg-emerald-600 dark:bg-emerald-400"
                        aria-hidden="true"
                    />
                    Active session
                </div>
            </aside>
        </div>

        <section
            aria-labelledby="profile-card-heading"
            class="rounded-2xl border border-border bg-card p-6 shadow-sm sm:flex sm:items-center sm:justify-between sm:gap-8 sm:p-7"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-muted text-foreground"
                >
                    <UserRound class="size-5" aria-hidden="true" />
                </div>
                <div class="space-y-1">
                    <h2 id="profile-card-heading" class="font-semibold">
                        My Profile
                    </h2>
                    <p class="max-w-xl text-sm leading-6 text-muted-foreground">
                        Review your display name, username, email address, and
                        account status.
                    </p>
                </div>
            </div>
            <Link
                :href="profile()"
                class="mt-5 inline-flex min-h-11 items-center justify-center rounded-md border border-border bg-background px-4 py-2 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 motion-reduce:transition-none sm:mt-0 sm:shrink-0"
            >
                View profile
            </Link>
        </section>
    </section>
</template>
