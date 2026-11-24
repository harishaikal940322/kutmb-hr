<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
type Account = { id: number; username: string };
const props = defineProps<{
    employeeId: number;
    linkedUserId: number | null;
    accounts: Account[];
}>();
const form = useForm({ user_id: '', reason: '' });
function submit() {
    form.transform((data) => ({
        ...data,
        user_id: props.linkedUserId ? null : data.user_id || null,
    })).put(`/employees/${props.employeeId}/account`, {
        preserveScroll: true,
        errorBag: `account-${props.employeeId}`,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <details class="mt-4 rounded-md border p-3">
        <summary class="cursor-pointer text-sm font-medium">
            Pautan akaun
        </summary>
        <form class="mt-3 grid gap-3 sm:grid-cols-2" @submit.prevent="submit">
            <p v-if="linkedUserId" class="text-sm sm:col-span-2">
                Akaun dipaut:
                {{
                    accounts.find((account) => account.id === linkedUserId)
                        ?.username ?? 'Tidak tersedia'
                }}. Nyahpaut sebelum memilih akaun lain.
            </p>
            <label v-else class="grid gap-1 text-sm"
                >Akaun aktif<select
                    v-model="form.user_id"
                    required
                    class="min-h-10 rounded-md border bg-background px-3"
                >
                    <option value="">Pilih akaun</option>
                    <option
                        v-for="account in accounts"
                        :key="account.id"
                        :value="account.id"
                    >
                        {{ account.username }}
                    </option>
                </select></label
            >
            <span v-if="form.errors.user_id" class="text-sm text-destructive">{{
                form.errors.user_id
            }}</span>
            <label class="grid gap-1 text-sm"
                >Sebab<input
                    v-model="form.reason"
                    required
                    maxlength="1000"
                    class="min-h-10 rounded-md border bg-background px-3"
                /><span class="text-destructive">{{
                    form.errors.reason
                }}</span></label
            >
            <button
                type="submit"
                :disabled="form.processing"
                class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
            >
                {{ linkedUserId ? 'Nyahpaut akaun' : 'Paut akaun' }}
            </button>
        </form>
    </details>
</template>
