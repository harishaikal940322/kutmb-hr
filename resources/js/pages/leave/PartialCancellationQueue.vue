<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Review = {
    id: number;
    status: 'REQUESTED' | 'HR_VERIFIED';
    request_reason: string;
    actual_return_date: string | null;
    unused_half_day_units: number | null;
    display_name: string;
    request_key: string;
};

export type PartialReviewPage = {
    data: Review[];
    next_page_url: string | null;
    prev_page_url: string | null;
};

defineProps<{ page: PartialReviewPage; today: string }>();

const selected = ref<number>();
const feedback = ref('');
const form = useForm({ key: '', actual_return_date: '', decision: '', reason: '' });

function select(id: number) {
    selected.value = selected.value === id ? undefined : id;
    form.reset();
    form.clearErrors();
    feedback.value = '';
}

function submit(row: Review) {
    const action = row.status === 'REQUESTED' ? 'verify' : 'decision';
    form.key ||= crypto.randomUUID();
    form.post(`/leave/annual/requests/partial/${row.id}/${action}`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = undefined;
            form.reset();
            feedback.value = 'Tindakan direkodkan.';
        },
    });
}
</script>

<template>
    <section class="space-y-3">
        <h2 class="font-semibold">Pembatalan separa</h2>
        <p v-if="feedback" role="status" class="text-sm">{{ feedback }}</p>
        <div class="divide-y divide-border rounded-2xl border border-border bg-card shadow-sm">
            <p v-if="!page.data.length" class="p-5 text-sm text-muted-foreground">Tiada pembatalan separa menunggu tindakan anda.</p>
            <article v-for="row in page.data" :key="row.id" class="p-5">
                <button type="button" :aria-expanded="selected === row.id" :disabled="form.processing"
                    class="flex w-full flex-wrap justify-between gap-2 text-left text-sm focus-visible:ring-2 focus-visible:ring-ring" @click="select(row.id)">
                    <strong>{{ row.display_name }}</strong>
                    <span class="text-muted-foreground">{{ row.status === 'REQUESTED' ? 'Pengesahan HR' : 'Keputusan Pengurus Besar' }}</span>
                </button>
                <form v-if="selected === row.id" class="mt-4 max-w-xl space-y-3 text-sm" @submit.prevent="submit(row)">
                    <p class="break-all text-muted-foreground">{{ row.request_key }}</p>
                    <p class="whitespace-pre-wrap">{{ row.request_reason }}</p>
                    <fieldset :disabled="form.processing" class="space-y-3">
                        <label v-if="row.status === 'REQUESTED'" class="block space-y-1">
                            <span>Tarikh sebenar kembali bekerja</span>
                            <input v-model="form.actual_return_date" type="date" required :max="today" class="block min-h-10 w-full rounded-lg border border-input bg-background px-3" />
                        </label>
                        <template v-else>
                            <p>{{ row.actual_return_date }} · {{ (row.unused_half_day_units ?? 0) / 2 }} hari belum digunakan</p>
                            <label class="block space-y-1">
                                <span>Keputusan</span>
                                <select v-model="form.decision" required class="block min-h-10 w-full rounded-lg border border-input bg-background px-3">
                                    <option value="" disabled>Pilih keputusan</option>
                                    <option value="CONFIRM">Sahkan pembatalan separa</option>
                                    <option value="REJECT">Tolak pembatalan separa</option>
                                </select>
                            </label>
                        </template>
                        <label class="block space-y-1">
                            <span>Alasan</span>
                            <textarea v-model="form.reason" :required="row.status === 'REQUESTED'" maxlength="1000" class="block w-full rounded-lg border border-input bg-background p-3" />
                        </label>
                        <p v-for="error in form.errors" :key="error" role="alert" class="text-destructive">{{ error }}</p>
                        <button type="submit" class="min-h-11 rounded-lg bg-primary px-4 font-semibold text-primary-foreground disabled:opacity-50">{{ form.processing ? 'Merekod…' : 'Rekod tindakan' }}</button>
                    </fieldset>
                </form>
            </article>
        </div>
        <nav v-if="page.prev_page_url || page.next_page_url" aria-label="Halaman pembatalan separa" class="flex gap-3 text-sm">
            <Link v-if="page.prev_page_url" :href="page.prev_page_url" preserve-state preserve-scroll class="rounded-lg border border-border px-3 py-2">Sebelum</Link>
            <Link v-if="page.next_page_url" :href="page.next_page_url" preserve-state preserve-scroll class="rounded-lg border border-border px-3 py-2">Seterusnya</Link>
        </nav>
    </section>
</template>
