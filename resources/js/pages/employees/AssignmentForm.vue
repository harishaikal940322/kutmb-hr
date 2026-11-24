<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
type Reference = { id: number; code: string; name: string };
const props = defineProps<{
    employeeId: number;
    units: Reference[];
    categories: Reference[];
}>();
const form = useForm({ unit_id: '', category_id: '', effective_from: '' });
function submit() {
    form.post(`/employees/${props.employeeId}/assignments`, {
        preserveScroll: true,
        errorBag: `assignment-${props.employeeId}`,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <form
        class="mt-4 grid gap-3 rounded-md border p-3 sm:grid-cols-2"
        @submit.prevent="submit"
    >
        <p class="text-sm sm:col-span-2">
            Penempatan baharu akan menamatkan penempatan terbuka sebelumnya
            sehari sebelum tarikh mula.
        </p>
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
            >Unit<select
                v-model="form.unit_id"
                required
                class="min-h-10 rounded-md border bg-background px-3"
            >
                <option value="">Pilih unit</option>
                <option v-for="unit in units" :key="unit.id" :value="unit.id">
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
            :disabled="form.processing || !units.length || !categories.length"
            class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
        >
            Rekod penempatan
        </button>
    </form>
</template>
