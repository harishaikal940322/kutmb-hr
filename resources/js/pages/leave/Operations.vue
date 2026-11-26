<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AnnualLeaveNavigation, { type AnnualAccess } from './AnnualLeaveNavigation.vue';

type Row = Record<string, string | number | null>;
type Page = { data: Row[]; prev_page_url: string | null; next_page_url: string | null; total: number };
type Mode = 'ledger' | 'reports' | 'year-end' | 'record';
const props = defineProps<{
    access: AnnualAccess; mode: Mode; year: number; currentYear: number; today?: string;
    entries?: Page; balance?: { annual_half_day_units: number; tabung_half_day_units: number } | null; employeeName?: string;
    summary?: Row[]; reviews?: Page; accounts?: Page; employees?: { id: number; employee_no: string; display_name: string }[];
    selectedEmployee?: number | null; cap?: number | null; preview?: Record<string, number | string | boolean> | null;
    closeEligibility?: { can_post: boolean; reason: string | null; snapshot: { annual: number; pending: number; tabung: number; transfer: number } | null } | null;
}>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] } });
const titles = { ledger: 'Baki & transaksi saya', reports: 'Laporan & semakan HR', 'year-end': 'Pratonton tutup tahun', record: 'Rekod bagi pihak staf' };
const year = ref(props.year);
const employee = ref<number | ''>(props.selectedEmployee ?? '');
const cap = ref<number | ''>(props.cap ?? '');
const success = ref(false);
const closeForm = useForm({ employee: 0, year: props.year, cap: 0, annual: 0, pending: 0, tabung: 0, transfer: 0, reason: '', acknowledged: false });
const closeNotice = ref('');
const form = useForm({ employee: '', leave_type: 'AL', start: '', end: '', session: 'FULL', reason: '', recording_reason: '', consent_reference: '', confirmed_at: '', consent_acknowledged: false, key: '' });
const input = 'block min-h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm';
const button = 'inline-flex min-h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50';
function filter() {
    router.get('/leave/annual/' + props.mode, { year: year.value, ...(props.mode === 'year-end' ? { employee: employee.value || undefined, cap: cap.value === '' ? undefined : cap.value } : {}) });
}
function postClose() {
    const snapshot = props.closeEligibility?.snapshot;
    if (!props.closeEligibility?.can_post || !snapshot || !props.selectedEmployee || props.cap == null) return;
    closeForm.employee = props.selectedEmployee;
    closeForm.year = props.year;
    closeForm.cap = props.cap;
    Object.assign(closeForm, snapshot);
    closeForm.post('/leave/annual/year-end', { preserveScroll: true, onSuccess: () => { closeForm.reset(); closeNotice.value = 'Pindahan tutup tahun direkodkan.'; } });
}
function submit() {
    if (form.session !== 'FULL') form.end = form.start;
    form.key ||= crypto.randomUUID();
    success.value = false;
    form.post('/leave/annual/record', { preserveScroll: true, onSuccess: () => { form.reset(); success.value = true; } });
}
function days(value: unknown) {
    return value === null || value === undefined ? 'Belum direkodkan' : (Number(value) / 2).toLocaleString('ms-MY', { maximumFractionDigits: 1 }) + ' hari';
}
const previewFields = [
    ['posted_annual_half_day_units', 'Baki AL dipos'], ['pending_annual_hold_half_day_units', 'Tempahan pending'],
    ['free_annual_half_day_units', 'Baki AL bebas'], ['tabung_capacity_half_day_units', 'Kapasiti Tabung'],
    ['transfer_half_day_units', 'Cadangan pindahan'], ['untransferred_half_day_units', 'Baki perlu semakan HR'],
];
</script>

<template>
    <Head :title="titles[mode]" />
    <div class="@container/leave-module mx-auto flex w-full min-w-0 max-w-[100rem] flex-col bg-background text-foreground">
        <header class="border-b border-border bg-card px-4 py-4 sm:px-6"><p class="text-xs font-semibold tracking-widest text-primary uppercase">KUTMB · MOD-005</p><p class="text-sm font-semibold">Cuti Tahunan</p></header>
        <div class="grid min-w-0 items-start gap-4 px-3 py-4 sm:px-5 @min-[960px]/leave-module:grid-cols-[14rem_minmax(0,1fr)]">
            <AnnualLeaveNavigation :access="access" :active="mode" />
            <main class="min-w-0 space-y-5 px-1 py-2">
                <header><p class="text-xs font-semibold text-primary uppercase">Data pembangunan</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">{{ titles[mode] }}</h1></header>
                <form v-if="mode !== 'record'" class="flex flex-wrap items-end gap-3 rounded-xl border border-border bg-card p-4" @submit.prevent="filter">
                    <label class="space-y-1 text-sm font-medium">Tahun<input v-model.number="year" type="number" min="1900" :max="currentYear + 1" required :class="input" /></label>
                    <template v-if="mode === 'year-end'">
                        <label class="min-w-48 flex-1 space-y-1 text-sm font-medium">Pekerja<select v-model="employee" required :class="input"><option value="">Pilih pekerja</option><option v-for="item in employees" :key="item.id" :value="item.id">{{ item.employee_no }} · {{ item.display_name }}</option></select></label>
                        <label class="space-y-1 text-sm font-medium">Cap disahkan HR (unit separuh hari)<input v-model.number="cap" type="number" min="0" max="100000" required :class="input" /></label>
                    </template>
                    <button :class="button" type="submit">{{ mode === 'year-end' ? 'Kira pratonton' : 'Semak' }}</button>
                </form>
                <template v-if="mode === 'ledger'">
                    <p class="text-sm text-muted-foreground">{{ employeeName }} · AL tahun dipilih; Tabung ialah lejar berterusan semua tahun.</p>
                    <div v-if="balance" class="grid gap-3 sm:grid-cols-2"><div class="rounded-xl border border-border bg-card p-4"><p class="text-sm text-muted-foreground">AL dipos</p><p class="mt-2 text-2xl font-semibold">{{ days(balance.annual_half_day_units) }}</p></div><div class="rounded-xl border border-border bg-card p-4"><p class="text-sm text-muted-foreground">Tabung dipos</p><p class="mt-2 text-2xl font-semibold">{{ days(balance.tabung_half_day_units) }}</p></div></div>
                    <section class="overflow-x-auto rounded-xl border border-border bg-card p-4"><h2 class="mb-3 font-semibold">Lejar transaksi</h2><table class="w-full min-w-[520px] text-left text-sm"><thead class="border-b border-border text-muted-foreground"><tr><th class="p-2">Tarikh rekod</th><th class="p-2">Akaun</th><th class="p-2">Jenis</th><th class="p-2">Perubahan</th></tr></thead><tbody class="divide-y divide-border"><tr v-for="row in entries?.data" :key="row.id"><td class="p-2">{{ row.created_at }}</td><td class="p-2">{{ row.annual_account_id ? 'AL' : 'Tabung ' + (row.source_year ?? '') }}</td><td class="p-2 break-all">{{ row.kind }}</td><td class="p-2">{{ Number(row.signed_half_day_units) > 0 ? '+' : '' }}{{ days(row.signed_half_day_units) }}</td></tr></tbody></table><p v-if="!entries?.data.length" class="p-3 text-sm text-muted-foreground">Tiada transaksi.</p><nav class="mt-3 flex gap-4 text-sm text-primary"><Link v-if="entries?.prev_page_url" :href="entries.prev_page_url">Sebelum</Link><Link v-if="entries?.next_page_url" :href="entries.next_page_url">Seterusnya</Link></nav></section>
                </template>
                <template v-else-if="mode === 'reports'">
                    <section class="rounded-xl border border-border bg-card p-4"><h2 class="font-semibold">Ringkasan status permohonan {{ year }}</h2><p class="mt-1 text-xs text-muted-foreground">Bilangan permohonan dan tempoh dimohon, bukan jumlah penggunaan rasmi.</p><div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><article v-for="row in summary" :key="String(row.leave_type) + row.status" class="rounded-lg border border-border p-3"><p class="text-xs break-words text-muted-foreground">{{ row.leave_type }} · {{ row.status }}</p><p class="mt-1 text-xl font-semibold">{{ row.request_count }} permohonan</p><p class="text-sm">{{ days(row.units) }}</p></article></div><p v-if="!summary?.length" class="mt-3 text-sm text-muted-foreground">Tiada permohonan.</p></section>
                    <section class="overflow-x-auto rounded-xl border border-border bg-card p-4"><h2 class="mb-3 font-semibold">Kelayakan & baki pekerja</h2><table class="w-full min-w-[620px] text-left text-sm"><thead class="border-b border-border text-muted-foreground"><tr><th class="p-2">Staf</th><th class="p-2">Kelayakan</th><th class="p-2">AL dipos</th><th class="p-2">AL pending</th><th class="p-2">Tabung dipos</th><th class="p-2">Tabung pending</th></tr></thead><tbody class="divide-y divide-border"><tr v-for="row in accounts?.data" :key="row.id"><td class="p-2">{{ row.display_name }}<span class="block text-xs text-muted-foreground">{{ row.employee_no }}</span></td><td class="p-2">{{ days(row.confirmed_half_day_units) }}</td><td class="p-2">{{ days(row.annual_posted) }}</td><td class="p-2">{{ days(row.annual_pending) }}</td><td class="p-2">{{ days(row.tabung_posted) }}</td><td class="p-2">{{ days(row.tabung_pending) }}</td></tr></tbody></table><nav class="mt-3 flex gap-4 text-sm text-primary"><Link v-if="accounts?.prev_page_url" :href="accounts.prev_page_url">Sebelum</Link><Link v-if="accounts?.next_page_url" :href="accounts.next_page_url">Seterusnya</Link></nav></section>
                    <section class="overflow-x-auto rounded-xl border border-border bg-card p-4"><h2 class="mb-2 font-semibold">Kes semakan HR</h2><p class="mb-3 text-sm text-muted-foreground">EL retrospektif atau AL lewat ditolak. Ini rekod semakan, bukan keputusan kehadiran atau potongan gaji.</p><table class="w-full min-w-[560px] text-left text-sm"><thead class="border-b border-border text-muted-foreground"><tr><th class="p-2">Staf</th><th class="p-2">Rujukan</th><th class="p-2">Jenis</th><th class="p-2">Dibuka</th></tr></thead><tbody class="divide-y divide-border"><tr v-for="row in reviews?.data" :key="row.id"><td class="p-2">{{ row.display_name }}</td><td class="p-2 break-all">{{ row.request_key }}</td><td class="p-2">{{ row.review_code }}</td><td class="p-2">{{ row.opened_at }}</td></tr></tbody></table><p v-if="!reviews?.data.length" class="p-3 text-sm text-muted-foreground">Tiada kes semakan.</p><nav class="mt-3 flex gap-4 text-sm text-primary"><Link v-if="reviews?.prev_page_url" :href="reviews.prev_page_url">Sebelum</Link><Link v-if="reviews?.next_page_url" :href="reviews.next_page_url">Seterusnya</Link></nav></section>
                </template>
                <template v-else-if="mode === 'year-end'">
                    <p class="rounded-xl border border-border bg-muted/30 p-4 text-sm leading-6">Pratonton dahulu. Baki pending tidak dipindahkan. Pindahan manual hanya tersedia jika kedua-dua tahun sudah dikreditkan, cap tidak berubah dan tiada lebihan atau defisit. Tiada pelupusan automatik.</p>
                    <div v-if="preview" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3"><article v-for="[key, label] in previewFields" :key="key" class="rounded-xl border border-border bg-card p-4"><p class="text-sm text-muted-foreground">{{ label }}</p><p class="mt-2 text-xl font-semibold">{{ days(preview[key]) }}</p></article></div>
                    <p v-if="closeNotice" role="status" class="text-sm">{{ closeNotice }}</p>
                    <p v-if="closeEligibility && !closeEligibility.can_post" class="rounded-lg border border-border bg-muted/30 p-3 text-sm">{{ closeEligibility.reason }}</p>
                    <form v-if="closeEligibility?.can_post" class="space-y-4 rounded-xl border border-border bg-card p-4" @submit.prevent="postClose">
                        <h2 class="font-semibold">Pengesahan pindahan</h2>
                        <label class="space-y-1 text-sm font-medium">Sebab / rujukan pengesahan<textarea v-model="closeForm.reason" required maxlength="1000" :class="input" /></label>
                        <label class="flex gap-2 text-sm"><input v-model="closeForm.acknowledged" type="checkbox" required class="accent-primary" /> Saya telah menyemak baki, tempahan dan cap. Pos pindahan yang dipratonton sahaja.</label>
                        <p v-for="error in closeForm.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                        <button :class="button" :disabled="closeForm.processing" type="submit">Sahkan dan pos pindahan</button>
                    </form>
                </template>
                <form v-else class="max-w-3xl space-y-5 rounded-xl border border-border bg-card p-5" @submit.prevent="submit">
                    <p class="text-sm text-muted-foreground">Rekod hanya selepas staf memberi persetujuan dan mengesahkan permohonan. Staf kekal sebagai pemohon; akaun HR direkodkan sebagai pelaksana.</p>
                    <label class="space-y-1 text-sm font-medium">Pekerja<select v-model="form.employee" required :class="input"><option value="">Pilih pekerja</option><option v-for="item in employees" :key="item.id" :value="String(item.id)">{{ item.employee_no }} · {{ item.display_name }}</option></select></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1 text-sm font-medium">Jenis cuti<select v-model="form.leave_type" :class="input"><option value="AL">AL</option><option value="EL">EL</option></select></label><label class="space-y-1 text-sm font-medium">Sesi<select v-model="form.session" :class="input"><option value="FULL">Penuh hari</option><option value="AM">Pagi</option><option value="PM">Petang</option></select></label><label class="space-y-1 text-sm font-medium">Mula<input v-model="form.start" type="date" required :min="form.leave_type === 'AL' ? today : undefined" :max="form.leave_type === 'EL' ? today : undefined" :class="input" /></label><label class="space-y-1 text-sm font-medium">Tamat<input v-model="form.end" type="date" :required="form.session === 'FULL'" :disabled="form.session !== 'FULL'" :min="form.start" :max="form.leave_type === 'EL' ? today : undefined" :class="input" /></label></div>
                    <label class="space-y-1 text-sm font-medium">Alasan cuti<textarea v-model="form.reason" :required="form.leave_type === 'EL'" maxlength="1000" :class="input" /></label>
                    <label class="space-y-1 text-sm font-medium">Sebab rekod oleh HR<textarea v-model="form.recording_reason" required maxlength="1000" :class="input" /></label>
                    <label class="space-y-1 text-sm font-medium">Rujukan bukti persetujuan (bukan kandungan sulit)<input v-model="form.consent_reference" required maxlength="120" :class="input" /></label>
                    <label class="space-y-1 text-sm font-medium">Masa pengesahan staf (waktu Malaysia)<input v-model="form.confirmed_at" type="datetime-local" required :class="input" /></label>
                    <label class="flex items-start gap-2 text-sm"><input v-model="form.consent_acknowledged" type="checkbox" required class="mt-1 accent-primary" /> Saya mengesahkan persetujuan staf telah diterima sebelum penghantaran.</label>
                    <p v-for="error in form.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p><p v-if="success" role="status" class="text-sm">Permohonan berjaya dihantar.</p><button type="submit" :class="button" :disabled="form.processing">Hantar permohonan staf</button>
                </form>
            </main>
        </div>
    </div>
</template>
