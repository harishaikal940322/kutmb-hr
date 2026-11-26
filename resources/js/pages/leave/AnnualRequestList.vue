<script setup lang="ts">
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

type Entry = {
    id: number;
    request_key: string;
    leave_type: 'AL' | 'EL';
    status: string;
    display_name: string;
    requested_units: number;
    reason: string | null;
    starts_on: string;
    ends_on: string;
    session_mode: 'FULL' | 'AM' | 'PM' | 'UNSUPPORTED';
    is_final?: boolean;
    can_withdraw?: boolean;
    can_resubmit?: boolean;
    can_return_for_correction?: boolean;
    can_request_cancellation?: boolean;
    can_request_partial?: boolean;
    partial_status?: string | null;
    cancellation_reason?: string;
    actions: { action_code: string; created_at: string }[];
};
export type RequestPage = {
    data: Entry[];
    next_page_url: string | null;
    prev_page_url: string | null;
};
defineProps<{
    page: RequestPage;
    approvals: boolean;
    cancellations?: boolean;
}>();

const selected = ref<number>();
const message = ref('');
const form = useForm({ decision: '', reason: '', key: '' });
const correction = useForm({ start: '', end: '', session: 'FULL', reason: '', key: '' });
const labels: Record<string, string> = {
    DRAFT: 'Perlu Pembetulan',
    WAITING_UNIT_MANAGER_SUPPORT: 'Menunggu sokongan Pengurus Unit',
    WAITING_GENERAL_MANAGER_SUPPORT: 'Menunggu sokongan Pengurus Besar',
    WAITING_GENERAL_MANAGER_FINAL: 'Menunggu kelulusan Pengurus Besar',
    WAITING_SECRETARY_FINAL: 'Menunggu kelulusan Setiausaha ALK',
    APPROVED: 'Diluluskan',
    REQUESTED: 'Menunggu pengesahan HR',
    HR_VERIFIED: 'Menunggu keputusan Pengurus Besar',
    CONFIRMED: 'Disahkan',
    WAITING_CANCELLATION: 'Menunggu pembatalan',
    CANCELLED: 'Dibatalkan',
    CANCELLATION_REQUESTED: 'Pembatalan dimohon',
    CANCELLATION_CONFIRMED: 'Pembatalan disahkan',
    CANCELLATION_REJECTED: 'Pembatalan ditolak',
    REJECTED: 'Ditolak',
    SUPPORT: 'Disokong',
    NOT_SUPPORT: 'Tidak disokong',
    APPROVE: 'Lulus',
    REJECT: 'Tolak',
    RETURN_FOR_CORRECTION: 'Dipulangkan untuk pembetulan',
};

function open(row: Entry) {
    selected.value = selected.value === row.id ? undefined : row.id;
    form.reset();
    form.clearErrors();
    correction.reset();
    correction.clearErrors();
    correction.start = row.starts_on;
    correction.end = row.ends_on;
    correction.session = row.session_mode === 'UNSUPPORTED' ? 'FULL' : row.session_mode;
}

function submit(
    id: number,
    action: 'decision' | 'withdraw' | 'partial' | 'cancellation' | 'cancellation/decision',
) {
    form.key ||= crypto.randomUUID();
    message.value = '';
    form.post(`/leave/annual/requests/${id}/${action}`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = undefined;
            form.reset();
            message.value = 'Tindakan direkodkan.';
        },
    });
}

function resubmit(id: number) {
    correction.key ||= crypto.randomUUID();
    correction.post(`/leave/annual/requests/${id}/resubmit`, {
        preserveScroll: true,
        onSuccess: () => {
            selected.value = undefined;
            correction.reset();
            message.value = 'Permohonan yang dibetulkan dihantar semula.';
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
                :disabled="form.processing"
                @click="open(row)"
            >
                <span>
                    <strong>{{
                        approvals
                            ? row.display_name
                            : row.leave_type === 'EL'
                              ? 'Emergency Leave'
                              : 'Cuti Tahunan'
                    }}</strong>
                    <span class="block text-muted-foreground"
                        >{{ row.leave_type }} · {{ row.starts_on }} –
                        {{ row.ends_on }} ·
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
                <p v-if="row.cancellation_reason" class="whitespace-pre-wrap">
                    Alasan pembatalan: {{ row.cancellation_reason }}
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
                <p v-if="row.partial_status" class="text-muted-foreground">
                    Pembatalan separa · {{ labels[row.partial_status] ?? row.partial_status }}
                </p>
                <form
                    v-if="approvals"
                    class="max-w-xl space-y-3"
                    @submit.prevent="
                        submit(
                            row.id,
                            cancellations
                                ? 'cancellation/decision'
                                : 'decision',
                        )
                    "
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
                                <option v-if="cancellations" value="CONFIRM">
                                    Sahkan pembatalan
                                </option>
                                <option
                                    v-if="!cancellations && row.is_final"
                                    value="APPROVE"
                                >
                                    Luluskan
                                </option>
                                <option
                                    v-if="cancellations || row.is_final"
                                    value="REJECT"
                                >
                                    Tolak
                                </option>
                                <option
                                    v-if="!cancellations && !row.is_final"
                                    value="SUPPORT"
                                >
                                    Sokong
                                </option>
                                <option
                                    v-if="!cancellations && !row.is_final"
                                    value="NOT_SUPPORT"
                                >
                                    Tidak sokong
                                </option>
                                <option v-if="!cancellations && row.can_return_for_correction" value="RETURN_FOR_CORRECTION">
                                    Pulangkan untuk pembetulan
                                </option>
                            </select>
                        </label>
                        <label class="block space-y-1">
                            <span>Alasan</span>
                            <textarea
                                v-model="form.reason"
                                maxlength="1000"
                                :required="
                                    ['REJECT', 'NOT_SUPPORT', 'RETURN_FOR_CORRECTION'].includes(
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
                <form
                    v-else-if="row.can_resubmit"
                    class="max-w-xl space-y-3"
                    @submit.prevent="resubmit(row.id)"
                >
                    <label class="block space-y-1">
                        <span>Tarikh mula</span>
                        <input v-model="correction.start" type="date" required :disabled="correction.processing" class="block min-h-11 w-full rounded-lg border border-input bg-background px-3" />
                    </label>
                    <label class="block space-y-1">
                        <span>Tarikh akhir</span>
                        <input v-model="correction.end" type="date" required :min="correction.start" :disabled="correction.processing" class="block min-h-11 w-full rounded-lg border border-input bg-background px-3" />
                    </label>
                    <label class="block space-y-1">
                        <span>Sesi</span>
                        <select v-model="correction.session" :disabled="correction.processing" class="block min-h-11 w-full rounded-lg border border-input bg-background px-3">
                            <option value="FULL">Sehari penuh</option>
                            <option value="AM">Separuh hari pagi</option>
                            <option value="PM">Separuh hari petang</option>
                        </select>
                    </label>
                    <label class="block space-y-1">
                        <span>Sebab / catatan</span>
                        <textarea v-model="correction.reason" maxlength="1000" :disabled="correction.processing" class="block w-full rounded-lg border border-input bg-background p-3" />
                    </label>
                    <p v-for="error in correction.errors" :key="error" role="alert" class="text-destructive">{{ error }}</p>
                    <button type="submit" :disabled="correction.processing" class="min-h-11 rounded-lg bg-primary px-4 font-semibold text-primary-foreground disabled:opacity-50">
                        {{ correction.processing ? 'Menghantar…' : 'Hantar semula untuk semakan' }}
                    </button>
                </form>
                <form
                    v-else-if="row.can_withdraw || row.can_request_cancellation || row.can_request_partial"
                    class="max-w-xl space-y-3"
                    @submit.prevent="
                        submit(
                            row.id,
                            row.can_withdraw ? 'withdraw' : row.can_request_partial ? 'partial' : 'cancellation',
                        )
                    "
                >
                    <label
                        v-if="row.can_request_cancellation || row.can_request_partial"
                        class="block space-y-1"
                    >
                        <span>Alasan pembatalan</span>
                        <textarea
                            v-model="form.reason"
                            required
                            maxlength="1000"
                            :disabled="form.processing"
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
                        :disabled="form.processing"
                        class="min-h-11 rounded-lg border border-border px-4 font-semibold hover:bg-muted disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Merekod…'
                                : row.can_withdraw
                                  ? 'Tarik balik permohonan'
                                  : row.can_request_partial ? 'Mohon pembatalan separa' : 'Mohon pembatalan'
                        }}
                    </button>
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
