<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
type Category = { id: number; code: string; name: string };
const props = defineProps<{ versionId: number; categories: Category[] }>();
const form = useForm({
    category_id: '',
    band_description: '',
    annual_half_day_units: '',
});
function submit() {
    form.transform((data) => ({
        ...data,
        annual_half_day_units:
            data.annual_half_day_units === ''
                ? null
                : data.annual_half_day_units,
    })).post(`/leave/policy-drafts/${props.versionId}/entitlements`, {
        preserveScroll: true,
        errorBag: `entitlement-${props.versionId}`,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <details class="mt-3 rounded-md border p-3">
        <summary class="cursor-pointer text-sm">
            Tambah cadangan kelayakan
        </summary>
        <p class="mt-2 text-sm text-muted-foreground">
            Label tempoh adalah catatan sahaja; sempadan perkhidmatan dan
            pemetaan sebenar belum disahkan. Draf tidak boleh digunakan untuk
            mengira kelayakan.
        </p>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
            <label class="grid gap-1 text-sm"
                >Kategori aktif<select
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
            <label class="grid gap-1 text-sm"
                >Label tempoh (catatan)<input
                    v-model="form.band_description"
                    required
                    maxlength="255"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.band_description
                }}</span></label
            >
            <label class="grid gap-1 text-sm"
                >Kelayakan tahunan dalam unit separuh hari (pilihan)<input
                    v-model="form.annual_half_day_units"
                    type="number"
                    min="0"
                    step="1"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.annual_half_day_units
                }}</span></label
            >
            <button
                type="submit"
                :disabled="form.processing || !categories.length"
                class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
            >
                Tambah cadangan
            </button>
        </form>
    </details>
</template>
