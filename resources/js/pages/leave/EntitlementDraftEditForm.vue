<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
type Category = { id: number; code: string; name: string };
type Rule = {
    id: number;
    category_id: number;
    band_description: string;
    annual_half_day_units: number | null;
};
const props = defineProps<{
    versionId: number;
    rule: Rule;
    categories: Category[];
}>();
const form = useForm({
    category_id: props.rule.category_id,
    band_description: props.rule.band_description,
    annual_half_day_units:
        props.rule.annual_half_day_units === null
            ? ''
            : String(props.rule.annual_half_day_units),
});
function submit() {
    form.transform((data) => ({
        ...data,
        annual_half_day_units:
            data.annual_half_day_units === ''
                ? null
                : data.annual_half_day_units,
    })).patch(
        `/leave/policy-drafts/${props.versionId}/entitlements/${props.rule.id}`,
        { preserveScroll: true, errorBag: `entitlement-edit-${props.rule.id}` },
    );
}
</script>
<template>
    <details class="mt-2 rounded-md border p-3">
        <summary class="cursor-pointer text-sm">Sunting cadangan</summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
            <label class="grid gap-1 text-sm"
                >Kategori aktif<select
                    v-model="form.category_id"
                    required
                    class="min-h-10 rounded-md border bg-background px-3"
                >
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
                >Unit separuh hari setahun (pilihan)<input
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
                Simpan cadangan
            </button>
        </form>
    </details>
</template>
