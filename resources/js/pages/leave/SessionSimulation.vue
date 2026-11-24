<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{
    date: string;
    sessions: string[];
    units: number | null;
    unavailable: boolean;
}>();
const date = ref(props.date);
const sessions = ref([...props.sessions]);
function simulate() {
    router.get(
        '/leave/session-simulation',
        { date: date.value, sessions: sessions.value },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head title="Simulasi Sesi Cuti" />
    <main class="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <header>
            <h1 class="text-3xl font-semibold">Simulasi Sesi Cuti</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Ujian data sintetik untuk pembangunan tempatan. Simulasi tidak
                membuat permohonan, menempah baki atau meluluskan cuti.
            </p>
            <Link
                href="/leave/calendar-drafts"
                class="mt-3 inline-flex min-h-10 items-center rounded-md border px-3 text-sm"
                >Kembali ke draf kalendar</Link
            >
        </header>
        <form
            class="space-y-4 rounded-lg border p-4"
            @submit.prevent="simulate"
        >
            <label class="grid gap-1 text-sm"
                >Tarikh<input
                    v-model="date"
                    required
                    type="date"
                    class="min-h-10 rounded-md border bg-background px-3"
            /></label>
            <fieldset class="space-y-2 text-sm">
                <legend>Sesi (pilih sekurang-kurangnya satu)</legend>
                <div class="flex gap-5">
                    <label class="flex gap-2"
                        ><input v-model="sessions" type="checkbox" value="AM" />
                        Pagi</label
                    ><label class="flex gap-2"
                        ><input v-model="sessions" type="checkbox" value="PM" />
                        Petang</label
                    >
                </div>
            </fieldset>
            <button
                type="submit"
                :disabled="!date || !sessions.length"
                class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
            >
                Kira simulasi
            </button>
        </form>
        <p
            v-if="unavailable"
            role="status"
            class="rounded-lg border p-4 text-sm"
        >
            Jadual atau kalendar simulasi yang lengkap dan tidak bertindih tidak
            tersedia untuk tarikh ini.
        </p>
        <p
            v-else-if="units !== null"
            role="status"
            class="rounded-lg border p-4"
        >
            Hasil simulasi: {{ units }} unit separuh hari ({{ units / 2 }}
            hari).
        </p>
    </main>
</template>
