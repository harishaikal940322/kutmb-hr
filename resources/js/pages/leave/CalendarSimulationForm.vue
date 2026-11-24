<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{ versionId: number }>();
const form = useForm({ acknowledge_complete: false });
function submit() {
    form.post(
        `/leave/calendar-drafts/${props.versionId}/simulate-confirmation`,
        {
            preserveScroll: true,
            errorBag: `calendar-confirm-${props.versionId}`,
        },
    );
}
</script>
<template>
    <form
        class="space-y-2 rounded-md border p-3 text-sm"
        @submit.prevent="submit"
    >
        <p>
            Simulasi tempatan sahaja. Tiada kelulusan HR atau pelepasan operasi
            diberikan; versi terkunci selepas pengesahan.
        </p>
        <label class="flex items-start gap-2"
            ><input
                v-model="form.acknowledge_complete"
                required
                type="checkbox"
                class="mt-1"
            />
            Data sintetik dan semua tarikh dalam tempoh draf telah disemak untuk
            simulasi.</label
        >
        <p v-if="form.errors.acknowledge_complete" class="text-destructive">
            {{ form.errors.acknowledge_complete }}
        </p>
        <button
            type="submit"
            :disabled="form.processing || !form.acknowledge_complete"
            class="min-h-10 rounded-md border px-3 disabled:opacity-50"
        >
            Sahkan untuk simulasi
        </button>
    </form>
</template>
