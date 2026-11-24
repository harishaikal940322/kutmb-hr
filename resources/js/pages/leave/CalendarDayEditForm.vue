<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{
    versionId: number;
    day: { id: number; date: string; name: string };
}>();
const form = useForm({ date: props.day.date, name: props.day.name });
function submit() {
    form.patch(
        `/leave/calendar-drafts/${props.versionId}/days/${props.day.id}`,
        { preserveScroll: true, errorBag: `calendar-edit-${props.day.id}` },
    );
}
</script>
<template>
    <details class="mt-2 rounded-md border p-3">
        <summary class="cursor-pointer text-sm">Sunting tarikh draf</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
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
