<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{
    versionId: number;
    dayId: number;
    isCancelled: boolean;
}>();
const form = useForm({ cancelled: !props.isCancelled });
function submit() {
    form.transform(() => ({ cancelled: !props.isCancelled })).patch(
        `/leave/calendar-drafts/${props.versionId}/days/${props.dayId}/cancellation`,
        { preserveScroll: true, errorBag: `calendar-cancel-${props.dayId}` },
    );
}
</script>
<template>
    <form class="mt-2 space-y-1" @submit.prevent="submit">
        <p v-if="form.errors.cancelled" class="text-sm text-destructive">
            {{ form.errors.cancelled }}
        </p>
        <button
            type="submit"
            :disabled="form.processing"
            class="min-h-10 rounded-md border px-3 text-sm disabled:opacity-50"
        >
            {{ isCancelled ? 'Pulihkan tarikh' : 'Batalkan tarikh' }}
        </button>
    </form>
</template>
