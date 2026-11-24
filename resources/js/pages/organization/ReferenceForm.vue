<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

type Reference = {
    id: number;
    code: string;
    name: string;
    active: boolean | number;
};
const props = defineProps<{ kind: 'units' | 'categories'; item?: Reference }>();
const form = useForm({
    code: props.item?.code ?? '',
    name: props.item?.name ?? '',
    active: Boolean(props.item?.active ?? true),
});
function submit() {
    const url = `/organization/references/${props.kind}`;
    const errorBag = `${props.kind}-${props.item?.id ?? 'new'}`;
    if (props.item) {
        form.patch(`${url}/${props.item.id}`, {
            preserveScroll: true,
            errorBag,
        });
    } else {
        form.post(url, {
            preserveScroll: true,
            errorBag,
            onSuccess: () => form.reset(),
        });
    }
}
</script>

<template>
    <form
        class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
        @submit.prevent="submit"
    >
        <label class="grid gap-1 text-sm"
            >Kod
            <input
                v-model="form.code"
                required
                maxlength="64"
                class="min-h-10 rounded-md border bg-background px-3"
            />
            <span v-if="form.errors.code" class="text-destructive">{{
                form.errors.code
            }}</span>
        </label>
        <label class="grid gap-1 text-sm"
            >Nama
            <input
                v-model="form.name"
                required
                maxlength="255"
                class="min-h-10 rounded-md border bg-background px-3"
            />
            <span v-if="form.errors.name" class="text-destructive">{{
                form.errors.name
            }}</span>
        </label>
        <label class="flex items-center gap-2 text-sm"
            ><input v-model="form.active" type="checkbox" /> Aktif</label
        >
        <button
            type="submit"
            :disabled="form.processing"
            class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
        >
            {{ item ? 'Simpan perubahan' : 'Tambah' }}
        </button>
    </form>
</template>
