<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
type Reference = { id: number; code: string; name: string };
type Employee = {
    id: number;
    employee_no: string;
    display_name: string;
    service_start: string;
    confirmation_date: string | null;
    service_end: string | null;
};
defineProps<{
    employees: Employee[];
    units: Reference[];
    categories: Reference[];
    canCreate: boolean;
}>();
const form = useForm({
    employee_no: '',
    display_name: '',
    service_start: '',
    confirmation_date: '',
    service_end: '',
    effective_from: '',
    unit_id: '',
    category_id: '',
});
function submit() {
    form.post('/employees', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <Head title="Pekerja" />
    <main class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <header>
            <h1 class="text-3xl font-semibold">Pekerja</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Direktori pembangunan tempatan untuk data sintetik.
            </p>
        </header>
        <section
            v-if="canCreate"
            class="space-y-4"
            aria-labelledby="new-employee"
        >
            <h2 id="new-employee" class="text-xl font-semibold">
                Tambah pekerja
            </h2>
            <p v-if="!units.length || !categories.length" class="text-sm">
                Unit dan kategori aktif diperlukan sebelum pekerja boleh
                ditambah.
            </p>
            <form
                class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <label class="grid gap-1 text-sm"
                    >Nombor pekerja<input
                        v-model="form.employee_no"
                        required
                        maxlength="64"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.employee_no
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Nama penuh<input
                        v-model="form.display_name"
                        required
                        maxlength="255"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.display_name
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tarikh mula perkhidmatan<input
                        v-model="form.service_start"
                        required
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.service_start
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tarikh pengesahan (pilihan)<input
                        v-model="form.confirmation_date"
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.confirmation_date
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tarikh tamat perkhidmatan (pilihan)<input
                        v-model="form.service_end"
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.service_end
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tarikh mula penempatan<input
                        v-model="form.effective_from"
                        required
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.effective_from
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
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
                    >Kategori<select
                        v-model="form.category_id"
                        required
                        class="min-h-10 rounded-md border bg-background px-3"
                    >
                        <option value="">Pilih kategori</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.code }} — {{ category.name }}
                        </option></select
                    ><span class="text-destructive">{{
                        form.errors.category_id
                    }}</span></label
                >
                <button
                    type="submit"
                    :disabled="
                        form.processing || !units.length || !categories.length
                    "
                    class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
                >
                    Tambah pekerja
                </button>
            </form>
        </section>
        <section class="space-y-4" aria-labelledby="employees-list">
            <h2 id="employees-list" class="text-xl font-semibold">
                Senarai pekerja
            </h2>
            <p v-if="!employees.length" class="text-sm text-muted-foreground">
                Tiada rekod.
            </p>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="employee in employees"
                    :key="employee.id"
                    class="p-4"
                >
                    <p class="font-medium">{{ employee.display_name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ employee.employee_no }} · Mula
                        {{ employee.service_start }}
                    </p>
                </li>
            </ul>
        </section>
    </main>
</template>
