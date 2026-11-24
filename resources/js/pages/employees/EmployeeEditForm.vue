<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
type Employee = {
    id: number;
    employee_no: string;
    display_name: string;
    service_start: string;
    confirmation_date: string | null;
    service_end: string | null;
};
const props = defineProps<{ employee: Employee }>();
const form = useForm({
    employee_no: props.employee.employee_no,
    display_name: props.employee.display_name,
    service_start: props.employee.service_start,
    confirmation_date: props.employee.confirmation_date ?? '',
    service_end: props.employee.service_end ?? '',
});
function submit() {
    form.patch(`/employees/${props.employee.id}`, {
        preserveScroll: true,
        errorBag: `employee-${props.employee.id}`,
    });
}
</script>
<template>
    <details class="mt-4 rounded-md border p-3">
        <summary class="cursor-pointer text-sm font-medium">
            Sunting maklumat pekerja
        </summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
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
                    type="date"
                    required
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.service_start
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Tarikh pengesahan<input
                    v-model="form.confirmation_date"
                    type="date"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.confirmation_date
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Tarikh tamat perkhidmatan<input
                    v-model="form.service_end"
                    type="date"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.service_end
                }}</span></label
            >
            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
            >
                Simpan perubahan
            </button>
        </form>
    </details>
</template>
