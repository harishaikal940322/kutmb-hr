<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{ versionId: number }>();
const form = useForm({ acknowledge_schedule: false });
function submit() {
    form.post(`/leave/policy-drafts/${props.versionId}/simulate-schedule`, {
        preserveScroll: true,
        errorBag: `schedule-simulation-${props.versionId}`,
    });
}
</script>
<template>
    <form
        class="space-y-2 rounded-md border p-3 text-sm"
        @submit.prevent="submit"
    >
        <p>
            Simulasi jadual tempatan sahaja; ini tidak mengesahkan kadar
            kelayakan, dasar HR atau pelepasan operasi. Versi draf terkunci
            selepas simulasi.
        </p>
        <label class="flex items-start gap-2"
            ><input
                v-model="form.acknowledge_schedule"
                required
                type="checkbox"
                class="mt-1"
            />
            Hari dan masa sintetik dalam tempoh ini telah disemak untuk
            simulasi.</label
        >
        <p v-if="form.errors.acknowledge_schedule" class="text-destructive">
            {{ form.errors.acknowledge_schedule }}
        </p>
        <button
            type="submit"
            :disabled="form.processing || !form.acknowledge_schedule"
            class="min-h-10 rounded-md border px-3 disabled:opacity-50"
        >
            Sahkan jadual untuk simulasi
        </button>
    </form>
</template>
