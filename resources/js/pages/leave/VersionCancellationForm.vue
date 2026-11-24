<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{
    kind: 'calendar' | 'policy';
    versionId: number;
    isCancelled: boolean;
}>();
const form = useForm({ cancelled: !props.isCancelled });
function submit() {
    form.transform(() => ({ cancelled: !props.isCancelled })).patch(
        `/leave/${props.kind}-drafts/${props.versionId}/cancellation`,
        {
            preserveScroll: true,
            errorBag: `version-cancel-${props.kind}-${props.versionId}`,
        },
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
            {{ isCancelled ? 'Pulihkan versi draf' : 'Batalkan versi draf' }}
        </button>
    </form>
</template>
