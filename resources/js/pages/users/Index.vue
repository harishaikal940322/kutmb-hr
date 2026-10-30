<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, UsersRound } from '@lucide/vue';
import { ref } from 'vue';

type DirectoryUser = {
    displayName: string;
    username: string;
    status: 'ACTIVE' | 'INACTIVE' | string;
};

const props = defineProps<{
    users: DirectoryUser[];
    pagination: {
        currentPage: number;
        lastPage: number;
        perPage: number;
        total: number;
    };
    filters: { search: string };
}>();

const search = ref(props.filters.search);
const loading = ref(false);

function submitSearch() {
    router.get(
        '/users',
        { search: search.value || undefined },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}

function visitPage(page: number) {
    router.get(
        '/users',
        { search: props.filters.search || undefined, page },
        {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                loading.value = true;
            },
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="User Directory" />

    <main
        class="mx-auto w-full max-w-6xl space-y-7 px-4 py-7 sm:px-6 lg:px-8"
        aria-labelledby="users-heading"
    >
        <header class="space-y-2">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                System Core
            </p>
            <h1
                id="users-heading"
                class="text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                User Directory
            </h1>
            <p
                class="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base"
            >
                Read-only account directory for this development preview.
            </p>
        </header>

        <form
            class="flex w-full max-w-2xl flex-col gap-3 sm:flex-row"
            role="search"
            @submit.prevent="submitSearch"
        >
            <label for="user-search" class="sr-only"
                >Search display name or username</label
            >
            <input
                id="user-search"
                v-model="search"
                type="search"
                name="search"
                maxlength="100"
                autocomplete="off"
                placeholder="Search display name or username"
                class="h-10 min-w-0 flex-1 rounded-md border border-input bg-background px-3 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/50 motion-reduce:transition-none"
            />
            <button
                type="submit"
                :disabled="loading"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground shadow-xs transition-colors outline-none hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-60 motion-reduce:transition-none"
            >
                <Search class="size-4" aria-hidden="true" />
                Search
            </button>
        </form>

        <section
            class="overflow-hidden rounded-xl border border-border bg-card shadow-sm"
            aria-label="User results"
            :aria-busy="loading"
        >
            <div
                class="flex items-center justify-between border-b border-border px-5 py-4 sm:px-6"
            >
                <div>
                    <h2 class="font-semibold">Accounts</h2>
                    <p
                        class="mt-1 text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {{ pagination.total }}
                        {{ pagination.total === 1 ? 'account' : 'accounts' }}
                        <span v-if="filters.search">
                            matching “{{ filters.search }}”</span
                        >
                    </p>
                </div>
                <p
                    v-if="loading"
                    role="status"
                    class="text-sm text-muted-foreground"
                >
                    Loading…
                </p>
            </div>

            <div v-if="users.length" class="divide-y divide-border">
                <article
                    v-for="user in users"
                    :key="user.username"
                    class="grid gap-3 px-5 py-4 sm:grid-cols-[minmax(0,1fr)_minmax(12rem,0.7fr)_auto] sm:items-center sm:px-6"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span
                            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"
                            ><UsersRound class="size-5" aria-hidden="true"
                        /></span>
                        <div class="min-w-0">
                            <h3 class="truncate font-medium">
                                {{ user.displayName }}
                            </h3>
                        </div>
                    </div>
                    <p class="font-mono text-sm text-muted-foreground">
                        @{{ user.username }}
                    </p>
                    <span
                        class="inline-flex w-fit items-center rounded-full px-2.5 py-1 text-xs font-semibold tracking-wide"
                        :class="
                            user.status === 'ACTIVE'
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                                : 'bg-muted text-muted-foreground'
                        "
                    >
                        {{ user.status }}
                    </span>
                </article>
            </div>

            <div v-else-if="filters.search" class="px-6 py-14 text-center">
                <h3 class="font-semibold">No users found</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Try another display name or username.
                </p>
            </div>
            <div v-else class="px-6 py-14 text-center">
                <h3 class="font-semibold">No users yet</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    There are no directory accounts to show.
                </p>
            </div>

            <nav
                v-if="pagination.lastPage > 1"
                class="flex items-center justify-between border-t border-border px-5 py-4 sm:px-6"
                aria-label="User directory pages"
            >
                <button
                    type="button"
                    :disabled="pagination.currentPage <= 1 || loading"
                    class="rounded-md border border-input px-3 py-2 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                    @click="visitPage(pagination.currentPage - 1)"
                >
                    Previous
                </button>
                <p class="text-sm text-muted-foreground" aria-live="polite">
                    Page {{ pagination.currentPage }} of
                    {{ pagination.lastPage }}
                </p>
                <button
                    type="button"
                    :disabled="
                        pagination.currentPage >= pagination.lastPage || loading
                    "
                    class="rounded-md border border-input px-3 py-2 text-sm font-medium outline-none hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50"
                    @click="visitPage(pagination.currentPage + 1)"
                >
                    Next
                </button>
            </nav>
        </section>
    </main>
</template>
