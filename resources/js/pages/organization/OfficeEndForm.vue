<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{ officeId: number }>();
const form = useForm({ effective_to: '' });
function submit() {
    form.patch(`/organization/offices/${props.officeId}/end`, {
        preserveScroll: true,
        errorBag: `office-${props.officeId}`,
    });
}
</script>
<template>
    <form class="mt-3 flex flex-wrap items-end gap-3" @submit.prevent="submit">
        <label class="grid gap-1 text-sm"
            >Tarikh tamat<input
                v-model="form.effective_to"
                type="date"
                required
                class="min-h-10 rounded-md border bg-background px-3"
            /><span class="text-destructive">{{
                form.errors.effective_to
            }}</span></label
        >
        <button
            type="submit"
            :disabled="form.processing"
            class="min-h-10 rounded-md border px-4 disabled:opacity-50"
        >
            Tamatkan penetapan
        </button>
    </form>
</template>
