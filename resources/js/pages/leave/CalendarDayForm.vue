<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { calendarGuidance } from './calendarGuidance';
type Day = {
    id: number;
    date: string;
    name: string;
    kind: string;
    cancelled_at: string | null;
    cancelled_by: number | null;
};
const props = defineProps<{
    versionId: number;
    days: Day[];
    effectiveFrom: string;
    effectiveTo: string | null;
}>();
const form = useForm({
    date: '',
    name: '',
    kind: 'holiday',
    original_day_id: '',
});
const guidance = computed(() =>
    calendarGuidance(
        form.date,
        form.kind,
        props.days,
        props.effectiveFrom,
        props.effectiveTo,
    ),
);
function submit() {
    form.transform((data) => ({
        ...data,
        original_day_id:
            data.kind === 'replacement' ? data.original_day_id || null : null,
    })).post(`/leave/calendar-drafts/${props.versionId}/days`, {
        preserveScroll: true,
        errorBag: `calendar-day-${props.versionId}`,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <form
        class="grid gap-3 rounded-md border p-4 sm:grid-cols-2"
        @submit.prevent="submit"
    >
        <label class="grid gap-1 text-sm"
            >Tarikh<input
                v-model="form.date"
                type="date"
                required
                class="min-h-10 rounded-md border bg-background px-3"
            /><span class="text-destructive">{{
                form.errors.date
            }}</span></label
        >
        <label class="grid gap-1 text-sm"
            >Nama<input
                v-model="form.name"
                required
                maxlength="255"
                class="min-h-10 rounded-md border bg-background px-3"
            /><span class="text-destructive">{{
                form.errors.name
            }}</span></label
        >
        <label class="grid gap-1 text-sm"
            >Jenis<select
                v-model="form.kind"
                class="min-h-10 rounded-md border bg-background px-3"
            >
                <option value="holiday">Cuti umum</option>
                <option value="replacement">Cuti gantian</option></select
            ><span class="text-destructive">{{ form.errors.kind }}</span></label
        >
        <label v-if="form.kind === 'replacement'" class="grid gap-1 text-sm"
            >Cuti umum asal<select
                v-model="form.original_day_id"
                required
                class="min-h-10 rounded-md border bg-background px-3"
            >
                <option value="">Pilih cuti umum</option>
                <option
                    v-for="day in days.filter(
                        (item) =>
                            item.kind === 'holiday' &&
                            !item.cancelled_at &&
                            !item.cancelled_by,
                    )"
                    :key="day.id"
                    :value="day.id"
                >
                    {{ day.date }} — {{ day.name }}
                </option></select
            ><span class="text-destructive">{{
                form.errors.original_day_id
            }}</span></label
        >
        <ul
            v-if="guidance.length"
            class="space-y-1 text-sm sm:col-span-2"
            aria-live="polite"
        >
            <li v-for="message in guidance" :key="message">{{ message }}</li>
        </ul>
        <button
            type="submit"
            :disabled="form.processing"
            class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
        >
            Tambah ke draf
        </button>
    </form>
</template>
