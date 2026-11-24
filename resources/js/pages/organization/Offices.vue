<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import OfficeEndForm from './OfficeEndForm.vue';
type Account = { id: number; username: string };
type Unit = { id: number; code: string; name: string };
type Office = {
    id: number;
    office_code: string;
    username: string;
    unit_name: string | null;
    effective_from: string;
    effective_to: string | null;
};
defineProps<{ users: Account[]; units: Unit[]; offices: Office[] }>();
const form = useForm({
    user_id: '',
    office_code: '',
    unit_id: '',
    effective_from: '',
    effective_to: '',
});
function submit() {
    form.transform((data) => ({
        ...data,
        unit_id: data.office_code === 'PU' ? data.unit_id || null : null,
    })).post('/organization/offices', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
const labels: Record<string, string> = {
    PU: 'Pengurus Unit',
    PB: 'Pengurus Besar',
    SECRETARY_ALK: 'Setiausaha ALK',
};
</script>
<template>
    <Head title="Penetapan Pegawai" />
    <main class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <header>
            <h1 class="text-3xl font-semibold">Penetapan Pegawai</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Penetapan pembangunan tempatan; jawatan tidak memberikan
                kebenaran sistem secara automatik.
            </p>
        </header>
        <section class="space-y-4" aria-labelledby="new-office">
            <h2 id="new-office" class="text-xl font-semibold">
                Penetapan baharu
            </h2>
            <form
                class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <label class="grid gap-1 text-sm"
                    >Akaun pegawai<select
                        v-model="form.user_id"
                        required
                        class="min-h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Pilih akaun</option>
                        <option
                            v-for="user in users"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ user.username }}
                        </option></select
                    ><span class="text-destructive">{{
                        form.errors.user_id
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Jawatan<select
                        v-model="form.office_code"
                        required
                        class="min-h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Pilih jawatan</option>
                        <option
                            v-for="(label, code) in labels"
                            :key="code"
                            :value="code"
                        >
                            {{ label }}
                        </option></select
                    ><span class="text-destructive">{{
                        form.errors.office_code
                    }}</span></label
                >
                <label
                    v-if="form.office_code === 'PU'"
                    class="grid gap-1 text-sm"
                    >Unit<select
                        v-model="form.unit_id"
                        required
                        class="min-h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Pilih unit</option>
                        <option
                            v-for="unit in units"
                            :key="unit.id"
                            :value="unit.id"
                        >
                            {{ unit.code }} — {{ unit.name }}
                        </option></select
                    ><span class="text-destructive">{{
                        form.errors.unit_id
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tarikh mula<input
                        v-model="form.effective_from"
                        required
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.effective_from
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tarikh tamat (pilihan)<input
                        v-model="form.effective_to"
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.effective_to
                    }}</span></label
                >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
                >
                    Rekod penetapan
                </button>
            </form>
        </section>
        <section class="space-y-4" aria-labelledby="office-history">
            <h2 id="office-history" class="text-xl font-semibold">
                Sejarah penetapan
            </h2>
            <p v-if="!offices.length" class="text-sm text-muted-foreground">
                Tiada rekod.
            </p>
            <ul class="divide-y rounded-lg border">
                <li v-for="office in offices" :key="office.id" class="p-4">
                    <p class="font-medium">
                        {{ labels[office.office_code] ?? office.office_code }} ·
                        {{ office.username }}
                    </p>
                    <p class="text-sm text-muted-foreground">
                        {{ office.unit_name ?? 'Seluruh organisasi' }} ·
                        {{ office.effective_from }} —
                        {{ office.effective_to ?? 'terbuka' }}
                    </p>
                    <OfficeEndForm
                        v-if="!office.effective_to"
                        :office-id="office.id"
                    />
                </li>
            </ul>
        </section>
    </main>
</template>
