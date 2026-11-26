<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AnnualLeaveNavigation, { type AnnualAccess } from './AnnualLeaveNavigation.vue';

type Adjustment = { historical_eligible: number | boolean; reconciliation_transfer: number | null; reconciliation_burn: number | null; id: number; display_name: string; employee_no: string; leave_year: number; bucket: string; source_year: number | null; signed_units: number; reason: string; evidence_reference: string; prepared_balance: number; prepared_pending: number; status: 'PENDING' | 'APPROVED' | 'REJECTED'; before_units: number | null; after_units: number | null; decision_reason: string | null; prepared_by_name: string; decided_by_name: string | null; can_decide: boolean; created_at: string };
type SpecialCase = { id: number; request_key: string; display_name: string; employee_no: string; status: string; request_reason: string; evidence_reference: string; actual_return_date: string | null; actual_return_session: string | null; unused_half_day_units: number | null; reconciliation_transfer: number | null; reconciliation_burn: number | null };
const props = defineProps<{ specialCases: { data: SpecialCase[]; prev_page_url: string | null; next_page_url: string | null }; specialCandidates: { request_key: string; display_name: string; employee_no: string; leave_year: number }[]; access: AnnualAccess; canPrepare: boolean; year: number; employees: { id: number; employee_no: string; display_name: string }[]; adjustments: { data: Adjustment[]; prev_page_url: string | null; next_page_url: string | null } }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] } });
const prepare = useForm({ employee: '', bucket: 'AL', leave_year: props.year, historical_eligible: false, source_year: props.year, units: '', reason: '', evidence_reference: '', key: '' });
const decide = useForm({ decision: 'APPROVED', reason: '', key: '', historical_verified: false });
const special = useForm({ request_key: '', reason: '', evidence_reference: '', key: '' });
function sendSpecial() {
    special.key ||= crypto.randomUUID();
    special.post('/leave/annual/adjustments/special', { preserveScroll: true, onSuccess: () => { special.reset(); notice.value = 'Pelarasan khas disediakan. Baki belum berubah.'; } });
}
const selected = ref<number | null>(null);
const notice = ref('');
const input = 'block min-h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm';
const button = 'inline-flex min-h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50';
function send() {
    prepare.key ||= crypto.randomUUID();
    prepare.post('/leave/annual/adjustments', { preserveScroll: true, onSuccess: () => { prepare.reset(); notice.value = 'Pelarasan disediakan. Baki belum berubah.'; } });
}
function open(id: number) { selected.value = selected.value === id ? null : id; decide.reset(); decide.clearErrors(); }
function decision(id: number) {
    decide.key ||= crypto.randomUUID();
    decide.post('/leave/annual/adjustments/' + id + '/decision', { preserveScroll: true, onSuccess: () => { selected.value = null; decide.reset(); notice.value = 'Keputusan direkodkan.'; } });
}
function days(value: number | null) { return value === null ? '—' : (Number(value) / 2).toLocaleString('ms-MY', { maximumFractionDigits: 1 }) + ' hari'; }
</script>

<template>
    <Head title="Pelarasan baki" />
    <div class="@container/leave-module mx-auto flex w-full min-w-0 max-w-[100rem] flex-col bg-background text-foreground">
        <header class="border-b border-border bg-card px-4 py-4 sm:px-6"><p class="text-xs font-semibold tracking-widest text-primary uppercase">KUTMB · MOD-005</p><p class="text-sm font-semibold">Cuti Tahunan</p></header>
        <div class="grid min-w-0 items-start gap-4 px-3 py-4 sm:px-5 @min-[960px]/leave-module:grid-cols-[14rem_minmax(0,1fr)]">
            <AnnualLeaveNavigation :access="access" active="adjustments" />
            <main class="min-w-0 space-y-5 px-1 py-2">
                <header><p class="text-xs font-semibold text-primary uppercase">Data pembangunan · {{ year }}</p><h1 class="mt-1 text-2xl font-semibold">Pelarasan baki</h1><p class="mt-2 text-sm text-muted-foreground">Penyedia dan pelulus mesti berbeza. Baki berubah hanya selepas kelulusan bebas.</p></header>
                <p v-if="notice" role="status" class="rounded-lg border border-border bg-muted/30 p-3 text-sm">{{ notice }}</p>
                <form v-if="canPrepare" class="space-y-4 rounded-xl border border-border bg-card p-5" @submit.prevent="send">
                    <h2 class="font-semibold">Sediakan pelarasan baki</h2>
                    <label class="space-y-1 text-sm font-medium">Pekerja<select v-model="prepare.employee" required :class="input"><option value="">Pilih pekerja</option><option v-for="item in employees" :key="item.id" :value="String(item.id)">{{ item.employee_no }} · {{ item.display_name }}</option></select></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1 text-sm font-medium">Akaun<select v-model="prepare.bucket" :class="input"><option value="AL">Cuti Tahunan</option><option value="TABUNG">Tabung Cuti</option></select></label><label class="space-y-1 text-sm font-medium">Perubahan (unit separuh hari, +/-)<input v-model="prepare.units" type="number" min="-100000" max="100000" required :class="input" /></label><label v-if="prepare.bucket === 'TABUNG'" class="space-y-1 text-sm font-medium">Tahun sumber<input v-model.number="prepare.source_year" type="number" min="1900" :max="year" required :class="input" /></label></div>
                    <label class="block space-y-1 text-sm font-medium">Tahun akaun AL<input v-model.number="prepare.leave_year" type="number" min="1900" :max="year" required :class="input" /></label>
                    <div v-if="prepare.leave_year < year" class="space-y-2 rounded-lg border border-border bg-muted/30 p-3 text-sm">
                        <p>Tahun tertutup: hanya pelarasan AL positif yang baru layak. Rekonsiliasi mengikut kapasiti Tabung sebenar ketika diluluskan; lebihan burn.</p>
                        <p>Pengurangan AL sejarah belum dibenarkan: kaedah menangani baki yang telah dipindah, diburn atau digunakan masih perlu disahkan. Tiada debit automatik.</p>
                        <Link v-if="access.can_view_reports" :href="'/leave/annual/reports?year=' + prepare.leave_year + '&deficits_only=1'" class="text-sm font-medium text-primary underline">Lihat defisit dipos tahun ini dalam laporan HR</Link>
                        <label class="flex items-start gap-2"><input v-model="prepare.historical_eligible" type="checkbox" required class="mt-1 accent-primary" /> <span>Saya mengesahkan bukti ini mewakili kelayakan baru yang belum pernah dipindahkan atau diburn, bukan pembatalan cuti/semakan kehadiran yang memerlukan laluan khusus.</span></label>
                    </div>
                    <label class="space-y-1 text-sm font-medium">Sebab<textarea v-model="prepare.reason" required maxlength="1000" :class="input" /></label><label class="space-y-1 text-sm font-medium">Rujukan bukti<input v-model="prepare.evidence_reference" required maxlength="120" :class="input" /></label>
                    <p v-for="error in prepare.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                    <button type="submit" :class="button" :disabled="prepare.processing">Hantar untuk semakan</button>
                </form>
                <section class="space-y-4 rounded-xl border border-border bg-card p-5">
                    <h2 class="font-semibold">Pelarasan khas selepas cuti tamat</h2>
                    <p class="text-sm text-muted-foreground">CR-Q03 · Rujuk permohonan asal dan bukti kehadiran. HR menyemak tarikh/sesi sebenar, Pengurus Besar mengesahkan. Baki hanya dipulangkan selepas keputusan akhir. AL tahun tertutup yang layak dipindah ke Tabung mengikut kapasiti semasa; lebihan burn. Sumber asal dan debit yang lengkap diperlukan.</p>
                    <form v-if="canPrepare" class="space-y-3" @submit.prevent="sendSpecial">
                        <label class="block space-y-1 text-sm font-medium">Permohonan asal<input v-model="special.request_key" list="special-requests" required maxlength="80" :class="input" placeholder="Masukkan rujukan permohonan cuti" /><datalist id="special-requests"><option v-for="row in specialCandidates" :key="row.request_key" :value="row.request_key">{{ row.employee_no }} · {{ row.display_name }} · {{ row.leave_year }}</option></datalist></label>
                        <label class="block space-y-1 text-sm font-medium">Sebab pelarasan<textarea v-model="special.reason" required maxlength="1000" :class="input" /></label>
                        <label class="block space-y-1 text-sm font-medium">Rujukan bukti kehadiran<input v-model="special.evidence_reference" required maxlength="120" :class="input" /></label>
                        <p v-for="error in special.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                        <button type="submit" :class="button" :disabled="special.processing">Hantar pelarasan khas untuk semakan HR</button>
                    </form>
                    <Link v-if="access.can_review_partial" href="/leave/annual?view=workflow&section=approvals" class="text-sm font-medium text-primary">Buka senarai tindakan semakan HR</Link>
                    <div v-for="row in specialCases.data" :key="row.id" class="space-y-1 border-t border-border pt-3 text-sm">
                        <div class="flex flex-wrap justify-between gap-2"><strong>{{ row.employee_no }} · {{ row.display_name }}</strong><span>{{ { REQUESTED: 'Menunggu HR', HR_VERIFIED: 'Menunggu Pengurus Besar', CONFIRMED: 'Disahkan', REJECTED: 'Ditolak' }[row.status] ?? row.status }}</span></div>
                        <p class="break-all text-xs text-muted-foreground">{{ row.request_key }}</p>
                        <p class="whitespace-pre-wrap">{{ row.request_reason }}</p>
                        <p class="text-xs text-muted-foreground">Bukti: {{ row.evidence_reference }}</p>
                        <p v-if="row.actual_return_date">{{ row.actual_return_date }} {{ row.actual_return_session }} · {{ days(row.unused_half_day_units) }}</p>
                        <p v-if="row.reconciliation_transfer !== null">Rekonsiliasi tahun tertutup: pindah {{ days(row.reconciliation_transfer) }} · burn {{ days(row.reconciliation_burn) }}</p>
                    </div>
                    <p v-if="!specialCases.data.length" class="text-sm text-muted-foreground">Tiada rekod pelarasan khas.</p>
                    <nav class="flex gap-4 text-sm text-primary"><Link v-if="specialCases.prev_page_url" :href="specialCases.prev_page_url">Sebelum</Link><Link v-if="specialCases.next_page_url" :href="specialCases.next_page_url">Seterusnya</Link></nav>
                </section>
                <section class="divide-y divide-border rounded-xl border border-border bg-card">
                    <h2 class="p-4 font-semibold">Rekod pelarasan</h2>
                    <article v-for="row in adjustments.data" :key="row.id" class="space-y-3 p-4">
                        <div class="flex flex-wrap justify-between gap-3"><div><h3 class="font-medium">{{ row.display_name }} · {{ row.employee_no }}</h3><p class="text-xs text-muted-foreground">{{ row.bucket }} · {{ row.leave_year }}<template v-if="row.source_year"> · Sumber {{ row.source_year }}</template></p></div><div class="text-right"><p class="font-semibold">{{ Number(row.signed_units) > 0 ? '+' : '' }}{{ days(row.signed_units) }}</p><p class="text-xs text-muted-foreground">{{ { PENDING: 'Menunggu semakan', APPROVED: 'Diluluskan', REJECTED: 'Ditolak' }[row.status] ?? row.status }}</p></div></div>
                        <p class="text-sm whitespace-pre-wrap">{{ row.reason }}</p><p class="text-xs text-muted-foreground">Rujukan: {{ row.evidence_reference }} · Penyedia: {{ row.prepared_by_name }}</p>
                        <p class="text-sm">Baki ketika disediakan: {{ days(row.prepared_balance) }} · Pending: {{ days(row.prepared_pending) }}</p>
                        <p v-if="row.status === 'APPROVED'" class="text-sm">Baki sebelum: {{ days(row.before_units) }} · Selepas pelarasan: {{ days(row.after_units) }}</p>
                        <p v-if="row.historical_eligible && row.status === 'APPROVED'" class="text-sm">Rekonsiliasi: pindah {{ days(row.reconciliation_transfer) }} · burn {{ days(row.reconciliation_burn) }}</p>
                        <p v-if="row.decided_by_name" class="text-sm text-muted-foreground">{{ row.decided_by_name }}: {{ row.decision_reason }}</p>
                        <button v-if="row.can_decide" type="button" class="min-h-10 rounded-lg border border-border px-4 text-sm" @click="open(row.id)">Semak keputusan</button>
                        <form v-if="selected === row.id && row.can_decide" class="space-y-3 border-t border-border pt-3" @submit.prevent="decision(row.id)">
                            <label class="space-y-1 text-sm font-medium">Keputusan<select v-model="decide.decision" :class="input"><option value="APPROVED">Lulus dan pos</option><option value="REJECTED">Tolak</option></select></label>
                            <label v-if="row.historical_eligible && decide.decision === 'APPROVED'" class="flex items-start gap-2 text-sm"><input v-model="decide.historical_verified" type="checkbox" required class="mt-1 accent-primary" /><span>Saya telah menyemak bukti secara bebas: baki baru layak, bukan pemulihan burn, bukan kes pembatalan atau kehadiran, dan tiada pertindihan.</span></label>
                            <label class="space-y-1 text-sm font-medium">Sebab keputusan<textarea v-model="decide.reason" required maxlength="1000" :class="input" /></label>
                            <p v-for="error in decide.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p><button type="submit" :class="button" :disabled="decide.processing">Sahkan keputusan</button>
                        </form>
                    </article>
                    <p v-if="!adjustments.data.length" class="p-4 text-sm text-muted-foreground">Tiada pelarasan.</p>
                    <nav class="flex gap-4 p-4 text-sm text-primary"><Link v-if="adjustments.prev_page_url" :href="adjustments.prev_page_url">Sebelum</Link><Link v-if="adjustments.next_page_url" :href="adjustments.next_page_url">Seterusnya</Link></nav>
                </section>
            </main>
        </div>
    </div>
</template>
