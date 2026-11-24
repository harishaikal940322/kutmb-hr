<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
type Policy = {
    id: number;
    label: string;
    effective_from: string;
    effective_to: string | null;
    working_weekdays: number[] | null;
    work_start: string | null;
    session_boundary: string | null;
    work_end: string | null;
};
const props = defineProps<{ version: Policy }>();
const weekdays = [
    'Isnin',
    'Selasa',
    'Rabu',
    'Khamis',
    'Jumaat',
    'Sabtu',
    'Ahad',
];
const form = useForm({
    label: props.version.label,
    effective_from: props.version.effective_from,
    effective_to: props.version.effective_to ?? '',
    working_weekdays: props.version.working_weekdays ?? ([] as number[]),
    work_start: props.version.work_start?.slice(0, 5) ?? '',
    session_boundary: props.version.session_boundary?.slice(0, 5) ?? '',
    work_end: props.version.work_end?.slice(0, 5) ?? '',
});
function submit() {
    form.transform((data) => ({
        ...data,
        working_weekdays: data.working_weekdays.length
            ? data.working_weekdays
            : null,
    })).patch(`/leave/policy-drafts/${props.version.id}`, {
        preserveScroll: true,
        errorBag: `policy-edit-${props.version.id}`,
    });
}
</script>
<template>
    <details class="mt-3 rounded-md border p-3">
        <summary class="cursor-pointer text-sm">Sunting draf</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
            <label class="grid gap-1 text-sm"
                >Nama versi<input
                    v-model="form.label"
                    required
                    maxlength="255"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.label
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Mula berkuat kuasa<input
                    v-model="form.effective_from"
                    type="date"
                    required
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.effective_from
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Tamat berkuat kuasa<input
                    v-model="form.effective_to"
                    type="date"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.effective_to
                }}</span></label
            >
            <fieldset class="space-y-2 text-sm sm:col-span-2">
                <legend>Hari bekerja (kosong = belum ditetapkan)</legend>
                <div class="flex flex-wrap gap-4">
                    <label
                        v-for="(day, index) in weekdays"
                        :key="day"
                        class="flex items-center gap-1"
                        ><input
                            v-model="form.working_weekdays"
                            type="checkbox"
                            :value="index + 1"
                        />
                        {{ day }}</label
                    >
                </div>
                <span class="text-destructive">{{
                    form.errors.working_weekdays
                }}</span>
            </fieldset>
            <label class="grid gap-1 text-sm"
                >Mula bekerja<input
                    v-model="form.work_start"
                    type="time"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.work_start
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Sempadan sesi<input
                    v-model="form.session_boundary"
                    type="time"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.session_boundary
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Tamat bekerja<input
                    v-model="form.work_end"
                    type="time"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.work_end
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
