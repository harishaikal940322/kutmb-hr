<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { AtSign, Mail, ShieldCheck, UserRound } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'My Profile',
                href: '/profile',
            },
        ],
    },
});

defineProps<{
    profile: {
        displayName: string;
        username: string;
        email: string | null;
        status: string;
    };
}>();
</script>

<template>
    <Head title="My Profile" />

    <section
        aria-labelledby="profile-heading"
        class="mx-auto w-full max-w-5xl space-y-7 px-4 py-7 sm:px-6 lg:px-8"
    >
        <header class="space-y-2">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                Personal account
            </p>
            <h1
                id="profile-heading"
                class="text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                My Profile
            </h1>
            <p
                class="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base"
            >
                Review the identity details linked to your account.
            </p>
        </header>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,0.82fr)_minmax(0,1.5fr)]">
            <section
                class="flex flex-col justify-between gap-8 rounded-2xl bg-primary p-6 text-primary-foreground shadow-sm sm:p-8"
            >
                <div class="space-y-5">
                    <div
                        class="flex size-14 items-center justify-center rounded-2xl bg-primary-foreground/10 ring-1 ring-primary-foreground/15"
                    >
                        <UserRound class="size-7" aria-hidden="true" />
                    </div>
                    <div class="space-y-2">
                        <p class="text-sm text-primary-foreground/70">
                            Signed-in account
                        </p>
                        <h2
                            class="text-2xl font-semibold tracking-tight break-words"
                        >
                            {{ profile.displayName }}
                        </h2>
                        <p class="text-sm break-all text-primary-foreground/75">
                            @{{ profile.username }}
                        </p>
                    </div>
                </div>
                <div
                    class="flex items-center gap-2 border-t border-primary-foreground/15 pt-5 text-sm"
                >
                    <ShieldCheck class="size-4" aria-hidden="true" />
                    <span>Account status</span>
                    <span
                        class="ml-auto rounded-full bg-primary-foreground/10 px-2.5 py-1 text-xs font-semibold"
                    >
                        {{ profile.status }}
                    </span>
                </div>
            </section>

            <section
                aria-labelledby="identity-heading"
                class="rounded-2xl border border-border bg-card shadow-sm"
            >
                <div class="border-b border-border px-6 py-5 sm:px-7">
                    <h2 id="identity-heading" class="font-semibold">
                        Identity details
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Information currently stored for your account.
                    </p>
                </div>

                <dl class="divide-y divide-border px-6 sm:px-7">
                    <div
                        class="grid gap-2 py-5 sm:grid-cols-[minmax(8rem,0.45fr)_minmax(0,1fr)] sm:gap-6"
                    >
                        <dt
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <UserRound class="size-4" aria-hidden="true" />
                            Display name
                        </dt>
                        <dd class="text-sm font-medium break-words">
                            {{ profile.displayName }}
                        </dd>
                    </div>

                    <div
                        class="grid gap-2 py-5 sm:grid-cols-[minmax(8rem,0.45fr)_minmax(0,1fr)] sm:gap-6"
                    >
                        <dt
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <AtSign class="size-4" aria-hidden="true" />
                            Username
                        </dt>
                        <dd class="font-mono text-sm font-medium break-all">
                            {{ profile.username }}
                        </dd>
                    </div>

                    <div
                        v-if="profile.email"
                        class="grid gap-2 py-5 sm:grid-cols-[minmax(8rem,0.45fr)_minmax(0,1fr)] sm:gap-6"
                    >
                        <dt
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Mail class="size-4" aria-hidden="true" />
                            Email
                        </dt>
                        <dd class="text-sm font-medium break-all">
                            {{ profile.email }}
                        </dd>
                    </div>
                </dl>

                <p
                    class="mx-6 mb-6 rounded-lg bg-muted/50 px-4 py-3 text-xs leading-5 text-muted-foreground sm:mx-7"
                >
                    Profile details are read-only. Account changes are not
                    available here.
                </p>
            </section>
        </div>
    </section>
</template>
