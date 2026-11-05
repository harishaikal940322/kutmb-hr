<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { KeyRound, Menu as MenuIcon, ShieldCheck } from '@lucide/vue';

type Permission = {
    code: string;
    label: string;
    description: string;
    group: string;
    systemOnly: boolean;
};
type MenuEntry = {
    key: string;
    label: string;
    href: string;
    permission: string;
    editable: boolean;
};

const props = defineProps<{ menus: MenuEntry[]; permissions: Permission[] }>();
const form = useForm({
    mappings: Object.fromEntries(
        props.menus.map((menu) => [menu.key, menu.permission]),
    ) as Record<string, string>,
});
</script>

<template>
    <Head title="Menu access" />

    <main
        class="mx-auto w-full max-w-6xl space-y-7 px-4 py-7 sm:px-6 lg:px-8"
        aria-labelledby="menus-heading"
    >
        <header class="space-y-2">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                System administration
            </p>
            <h1
                id="menus-heading"
                class="text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                Menu access
            </h1>
            <p
                class="max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base"
            >
                Choose which permission makes each registered navigation item
                visible. Menu visibility is a convenience; protected pages still
                enforce their own server-side authorization.
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
                <Link
                    href="/administration/access/permissions"
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    ><KeyRound
                        class="size-4"
                        aria-hidden="true"
                    />Permissions</Link
                >
                <span
                    class="inline-flex min-h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"
                    ><MenuIcon class="size-4" aria-hidden="true" />Menu
                    access</span
                >
            </nav>
        </header>

        <form
            class="space-y-4"
            @submit.prevent="
                form.put('/administration/access/menus', {
                    preserveScroll: true,
                })
            "
        >
            <section
                class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
                aria-label="Registered menu items"
                :aria-busy="form.processing"
            >
                <div class="border-b border-border px-5 py-4 sm:px-6">
                    <h2 class="font-semibold">Registered navigation</h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Menu names, links, and order remain code-managed.
                    </p>
                </div>
                <div v-if="menus.length" class="divide-y divide-border">
                    <article
                        v-for="menu in menus"
                        :key="menu.key"
                        class="grid gap-4 px-5 py-4 sm:px-6 lg:grid-cols-[minmax(0,1fr)_minmax(18rem,0.8fr)] lg:items-center"
                    >
                        <div class="min-w-0 space-y-1">
                            <h3 class="font-semibold">{{ menu.label }}</h3>
                            <p
                                class="font-mono text-xs break-all text-muted-foreground"
                            >
                                {{ menu.key }}
                            </p>
                            <p class="text-xs break-all text-muted-foreground">
                                {{ menu.href }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <label
                                :for="`menu-permission-${menu.key}`"
                                class="text-sm font-medium"
                                >Required permission</label
                            >
                            <select
                                :id="`menu-permission-${menu.key}`"
                                v-model="form.mappings[menu.key]"
                                :name="`mappings[${menu.key}]`"
                                :disabled="!menu.editable"
                                required
                                class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                <option
                                    v-for="permission in permissions"
                                    :key="permission.code"
                                    :value="permission.code"
                                >
                                    {{ permission.label }} —
                                    {{ permission.code }}
                                </option>
                            </select>
                            <p
                                v-if="!menu.editable"
                                class="text-xs text-muted-foreground"
                            >
                                This administration entry uses a protected
                                permission.
                            </p>
                        </div>
                    </article>
                </div>
                <div v-else class="px-6 py-12 text-center">
                    <h3 class="font-semibold">
                        No navigation items registered
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Navigation entries will appear here when added to the
                        application.
                    </p>
                </div>
            </section>
            <p
                v-if="form.errors.mappings"
                role="alert"
                class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
            >
                {{ form.errors.mappings }}
            </p>
            <div class="flex justify-end">
                <button
                    type="submit"
                    :disabled="form.processing || !menus.length"
                    class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-60 sm:w-auto"
                >
                    {{ form.processing ? 'Saving…' : 'Save menu access' }}
                </button>
            </div>
        </form>
    </main>
</template>
