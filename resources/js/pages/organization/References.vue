<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import ReferenceForm from './ReferenceForm.vue';

type Reference = {
    id: number;
    code: string;
    name: string;
    active: boolean | number;
};
defineProps<{ units: Reference[]; categories: Reference[] }>();
</script>

<template>
    <Head title="Unit dan Kategori" />
    <main class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <header class="space-y-2">
            <h1 class="text-3xl font-semibold">Unit dan Kategori Pekerjaan</h1>
            <p class="text-sm text-muted-foreground">
                Penyelenggaraan rujukan untuk pembangunan tempatan menggunakan
                data sintetik.
            </p>
        </header>
        <section class="space-y-4" aria-labelledby="units-heading">
            <h2 id="units-heading" class="text-xl font-semibold">Unit</h2>
            <ReferenceForm kind="units" />
            <ReferenceForm
                v-for="item in units"
                :key="item.id"
                kind="units"
                :item="item"
            />
        </section>
        <section class="space-y-4" aria-labelledby="categories-heading">
            <h2 id="categories-heading" class="text-xl font-semibold">
                Kategori Pekerjaan
            </h2>
            <ReferenceForm kind="categories" />
            <ReferenceForm
                v-for="item in categories"
                :key="item.id"
                kind="categories"
                :item="item"
            />
        </section>
    </main>
</template>
