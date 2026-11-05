<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { KeyRound, Menu as MenuIcon, ShieldCheck } from '@lucide/vue';

type Permission = {
    code: string;
    label: string;
    description: string;
    group: string;
    systemOnly: boolean;
};

defineProps<{ permissions: Permission[] }>();
</script>

<template>
    <Head title="Permission catalog" />

    <main
        class="mx-auto w-full max-w-6xl space-y-7 px-4 py-7 sm:px-6 lg:px-8"
        aria-labelledby="permissions-heading"
    >
        <header class="space-y-2">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                System administration
            </p>
            <h1
                id="permissions-heading"
                class="text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                Permission catalog
            </h1>
            <p
                class="max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base"
            >
                These stable permission keys are defined alongside protected
                system actions. Assign them to roles from the role screen;
                adding an unused key here would not grant a working capability.
            </p>
            <nav
                class="flex flex-wrap gap-2 pt-2"
                aria-label="Access management"
            >
                <Link
                    href="/administration/access/roles"
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    ><ShieldCheck
                        class="size-4"
                        aria-hidden="true"
                    />Roles</Link
                >
                <span
                    class="inline-flex min-h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"
                    ><KeyRound
                        class="size-4"
                        aria-hidden="true"
                    />Permissions</span
                >
                <Link
                    href="/administration/access/menus"
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    ><MenuIcon class="size-4" aria-hidden="true" />Menu
                    access</Link
                >
            </nav>
        </header>

        <section
            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            aria-label="Available permissions"
        >
            <div class="border-b border-border px-5 py-4 sm:px-6">
                <h2 class="font-semibold">Available permissions</h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ permissions.length }}
                    {{
                        permissions.length === 1 ? 'permission' : 'permissions'
                    }}
                    registered
                </p>
            </div>
            <div v-if="permissions.length" class="divide-y divide-border">
                <article
                    v-for="permission in permissions"
                    :key="permission.code"
                    class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:px-6"
                >
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-semibold">
                                {{ permission.label }}
                            </h3>
                            <span
                                v-if="permission.systemOnly"
                                class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary"
                                >System only</span
                            >
                        </div>
                        <p class="font-mono text-xs text-muted-foreground">
                            {{ permission.code }}
                        </p>
                        <p
                            class="max-w-3xl text-sm leading-5 text-muted-foreground"
                        >
                            {{ permission.description }}
                        </p>
                    </div>
                    <span
                        class="w-fit rounded-md bg-muted px-2.5 py-1.5 text-xs font-medium text-muted-foreground"
                        >{{ permission.group }}</span
                    >
                </article>
            </div>
            <div v-else class="px-6 py-12 text-center">
                <h3 class="font-semibold">No permissions registered</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Permission definitions appear here when a protected system
                    action is added.
                </p>
            </div>
        </section>
    </main>
</template>
