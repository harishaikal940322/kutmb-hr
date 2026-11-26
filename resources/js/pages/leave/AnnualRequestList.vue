<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Entry = {
    id: number;
    request_key: string;
    status: string;
    display_name: string;
    requested_units: number;
    reason: string | null;
    starts_on: string;
    ends_on: string;
    is_final?: boolean;
    actions: { action_code: string; created_at: string }[];
};
export type RequestPage = {
    data: Entry[];
    next_page_url: string | null;
    prev_page_url: string | null;
};
defineProps<{ page: RequestPage; approvals: boolean }>();

const selected = ref<number>();
const message = ref('');
const form = useForm({ decision: '', reason: '', key: '' });
const labels: Record<string, string> = {
    WAITING_UNIT_MANAGER_SUPPORT: 'Menunggu sokongan Pengurus Unit',
    WAITING_GENERAL_MANAGER_SUPPORT: 'Menunggu sokongan Pengurus Besar',
    WAITING_GENERAL_MANAGER_FINAL: 'Menunggu kelulusan Pengurus Besar',
    WAITING_SECRETARY_FINAL: 'Menunggu kelulusan Setiausaha ALK',
    APPROVED: 'Diluluskan',
    REJECTED: 'Ditolak',
    SUPPORT: 'Disokong',
    NOT_SUPPORT: 'Tidak disokong',
    APPROVE: 'Lulus',
    REJECT: 'Tolak',
};

function open(id: number) {
    selected.value = selected.value === id ? undefined : id;
    form.reset();
    form.clearErrors();
}

function decide(id: number) {
    form.key ||= crypto.randomUUID();
    message.value = '';
    form.post(`/leave/annual/requests/${id}/decision`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = undefined;
            form.reset();
            message.value = 'Tindakan direkodkan.';
        },
    });
}
</script>

<template>
    <p v-if="message" role="status" class="text-sm">{{ message }}</p>
    <section
        class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
    >
        <p v-if="!page.data.length" class="p-5 text-sm text-muted-foreground">
            {{
                approvals
                    ? 'Tiada permohonan menunggu tindakan anda.'
                    : 'Tiada permohonan cuti dalam rekod anda.'
            }}
        </p>
        <article v-for="row in page.data" :key="row.id">
            <button
                type="button"
                class="flex w-full flex-wrap items-center justify-between gap-3 p-5 text-left text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                :aria-expanded="selected === row.id"
                @click="open(row.id)"
            >
                <span>
                    <strong>{{
                        approvals ? row.display_name : 'Cuti Tahunan'
                    }}</strong>
                    <span class="block text-muted-foreground"
                        >{{ row.starts_on }} – {{ row.ends_on }} ·
                        {{ row.requested_units / 2 }} hari</span
                    >
                </span>
                <span class="rounded-full bg-muted px-3 py-1 text-xs">{{
                    labels[row.status] ?? row.status
                }}</span>
            </button>
            <div v-if="selected === row.id" class="space-y-3 px-5 pb-5 text-sm">
                <p class="break-all text-muted-foreground">
                    {{ row.request_key }}
                </p>
                <p v-if="row.reason" class="whitespace-pre-wrap">
                    {{ row.reason }}
                </p>
                <ul
                    v-if="row.actions.length"
                    class="space-y-1 text-muted-foreground"
                    aria-label="Sejarah keputusan"
                >
                    <li v-for="(action, index) in row.actions" :key="index">
                        {{ labels[action.action_code] ?? action.action_code }} ·
                        {{ action.created_at }}
                    </li>
                </ul>
                <form
                    v-if="approvals"
                    class="max-w-xl space-y-3"
                    @submit.prevent="decide(row.id)"
                >
                    <fieldset :disabled="form.processing" class="space-y-3">
                        <label class="block space-y-1">
                            <span>Keputusan</span>
                            <select
                                v-model="form.decision"
                                required
                                class="block min-h-11 w-full rounded-lg border border-input bg-background px-3"
                            >
                                <option value="" disabled>
                                    Pilih keputusan
                                </option>
                                <option v-if="row.is_final" value="APPROVE">
                                    Luluskan
                                </option>
                                <option v-if="row.is_final" value="REJECT">
                                    Tolak
                                </option>
                                <option v-if="!row.is_final" value="SUPPORT">
                                    Sokong
                                </option>
                                <option
                                    v-if="!row.is_final"
                                    value="NOT_SUPPORT"
                                >
                                    Tidak sokong
                                </option>
                            </select>
                        </label>
                        <label class="block space-y-1">
                            <span>Alasan</span>
                            <textarea
                                v-model="form.reason"
                                maxlength="1000"
                                :required="
                                    ['REJECT', 'NOT_SUPPORT'].includes(
                                        form.decision,
                                    )
                                "
                                class="block w-full rounded-lg border border-input bg-background p-3"
                            />
                        </label>
                        <p
                            v-for="error in form.errors"
                            :key="error"
                            role="alert"
                            class="text-destructive"
                        >
                            {{ error }}
                        </p>
                        <button
                            type="submit"
                            class="min-h-11 rounded-lg bg-primary px-4 font-semibold text-primary-foreground disabled:opacity-50"
                        >
                            {{
                                form.processing ? 'Merekod…' : 'Rekod keputusan'
                            }}
                        </button>
                    </fieldset>
                </form>
            </div>
        </article>
    </section>
    <nav
        v-if="page.prev_page_url || page.next_page_url"
        aria-label="Halaman permohonan"
        class="flex gap-3 text-sm"
    >
        <Link
            v-if="page.prev_page_url"
            :href="page.prev_page_url"
            preserve-state
            preserve-scroll
            class="rounded-lg border border-border px-3 py-2"
            >Sebelum</Link
        >
        <Link
            v-if="page.next_page_url"
            :href="page.next_page_url"
            preserve-state
            preserve-scroll
            class="rounded-lg border border-border px-3 py-2"
            >Seterusnya</Link
        >
    </nav>
</template>
