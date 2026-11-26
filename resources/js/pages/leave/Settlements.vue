<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AnnualLeaveNavigation, { type AnnualAccess } from './AnnualLeaveNavigation.vue';

type Status = 'OUTSTANDING' | 'IN_PROGRESS' | 'SETTLED';
type Case = { id: number; employee_no: string; display_name: string; exit_date: string; credited_units: number; final_entitlement_units: number; annual_before: number; tabung_before: number; deficit_units: number; offset_units: number; residual_units: number; evidence_reference: string; reason: string; maker_name: string; status: Status; external_reference: string | null; settlement_date: string | null; confirmer_name: string | null; can_start: boolean; can_evidence: boolean; can_confirm: boolean };
const props = defineProps<{ access: AnnualAccess; canPrepare: boolean; today: string; status: Status | null; counts: Record<Status, number>; rows: { data: Case[]; prev_page_url: string | null; next_page_url: string | null }; employees: { id: number; employee_no: string; display_name: string; service_end: string }[] }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] } });
const labels: Record<Status, string> = { OUTSTANDING: 'Belum lunas', IN_PROGRESS: 'Dalam proses', SETTLED: 'Sudah lunas' };
const statuses: Status[] = ['OUTSTANDING', 'IN_PROGRESS', 'SETTLED'];
const input = 'block min-h-10 w-full rounded-lg border border-input bg-background px-3 py-2 text-sm';
const button = 'inline-flex min-h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50';
const prepare = useForm({ employee: '', key: '', reference: '', reason: '' });
const action = useForm({ key: '', note: '', reference: '', date: '' });
const selected = ref<number | null>(null);
const step = ref<'start' | 'evidence' | 'confirm'>('start');
const notice = ref('');
function days(value: number) { return (Number(value) / 2).toLocaleString('ms-MY', { maximumFractionDigits: 1 }) + ' hari'; }
function filter(status: Status | null) { router.get('/leave/annual/settlements', status ? { status } : {}, { preserveScroll: true }); }
function send() {
    prepare.key ||= crypto.randomUUID();
    prepare.post('/leave/annual/settlements', { preserveScroll: true, onSuccess: () => { prepare.reset(); notice.value = 'Kes disediakan. Baki belum berubah.'; } });
}
function open(id: number, type: typeof step.value) {
    selected.value = id; step.value = type; action.reset(); action.clearErrors();
}
function submit() {
    action.key ||= crypto.randomUUID();
    action.post('/leave/annual/settlements/' + selected.value + '/' + step.value, { preserveScroll: true, onSuccess: () => { selected.value = null; action.reset(); notice.value = 'Tindakan direkodkan.'; } });
}
</script>

<template>
    <Head title="Penyelesaian baki" />
    <div class="@container/leave-module mx-auto flex w-full min-w-0 max-w-[100rem] flex-col bg-background text-foreground">
        <header class="border-b border-border bg-card px-4 py-4 sm:px-6"><p class="text-xs font-semibold tracking-widest text-primary uppercase">KUTMB · MOD-005</p><p class="text-sm font-semibold">Cuti Tahunan</p></header>
        <div class="grid min-w-0 items-start gap-4 px-3 py-4 sm:px-5 @min-[960px]/leave-module:grid-cols-[14rem_minmax(0,1fr)]">
            <AnnualLeaveNavigation :access="access" active="settlements" />
            <main class="min-w-0 space-y-5 px-1 py-2">
                <header><p class="text-xs font-semibold uppercase text-primary">Data pembangunan · TB-06</p><h1 class="mt-1 text-2xl font-semibold">Penyelesaian baki</h1><p class="mt-2 text-sm text-muted-foreground">Prorata akhir, offset Tabung dan rekod penyelesaian luar. Tiada pengiraan RM atau potongan gaji.</p></header>
                <div class="grid gap-3 sm:grid-cols-3">
                    <button v-for="item in statuses" :key="item" type="button" :aria-pressed="status === item" class="rounded-xl border bg-card p-4 text-left focus-visible:ring-2 focus-visible:ring-ring" :class="status === item ? 'border-primary' : 'border-border'" @click="filter(item)"><span class="block text-sm text-muted-foreground">{{ labels[item] }}</span><strong class="mt-1 block text-2xl">{{ counts[item] }}</strong></button>
                </div>
                <button v-if="status" type="button" class="text-sm font-medium text-primary underline" @click="filter(null)">Lihat semua kes</button>
                <p v-if="notice" role="status" class="rounded-lg border border-border bg-muted/30 p-3 text-sm">{{ notice }}</p>
                <form v-if="canPrepare" class="space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5" @submit.prevent="send">
                    <h2 class="font-semibold">Sediakan kes baharu</h2><p class="text-sm text-muted-foreground">Tahun tamat semasa sahaja. Tarikh tamat mesti sudah berlalu. Sistem menyemak kelayakan, kadar disahkan, baki dan sumber Tabung; kes polisi belum lengkap disekat.</p>
                    <label class="block text-sm">Pekerja<select v-model="prepare.employee" required :class="input"><option value="">Pilih pekerja tamat perkhidmatan</option><option v-for="item in employees" :key="item.id" :value="String(item.id)">{{ item.employee_no }} · {{ item.display_name }} · {{ item.service_end }}</option></select></label>
                    <label class="block text-sm">Rujukan bukti kelayakan dan tamat perkhidmatan<input v-model="prepare.reference" required maxlength="120" :class="input" /></label>
                    <label class="block text-sm">Sebab / catatan<textarea v-model="prepare.reason" required maxlength="1000" :class="input" /></label>
                    <p v-for="error in prepare.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                    <button :class="button" :disabled="prepare.processing">Sediakan untuk semakan HR</button>
                </form>
                <section class="space-y-3">
                    <h2 class="font-semibold">Rekod penyelesaian</h2>
                    <article v-for="row in rows.data" :key="row.id" class="space-y-3 rounded-xl border border-border bg-card p-4 sm:p-5">
                        <div class="flex flex-wrap items-start justify-between gap-3"><div><h3 class="font-semibold">{{ row.employee_no }} · {{ row.display_name }}</h3><p class="text-xs text-muted-foreground">Tamat: {{ row.exit_date }} · Kes #{{ row.id }}</p></div><span class="rounded-md border border-border px-2 py-1 text-xs font-medium">{{ labels[row.status] }}</span></div>
                        <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div><dt class="text-muted-foreground">AL asal dipos</dt><dd>{{ days(row.annual_before) }}</dd></div><div><dt class="text-muted-foreground">Kelayakan awal</dt><dd>{{ days(row.credited_units) }}</dd></div><div><dt class="text-muted-foreground">Kelayakan akhir</dt><dd>{{ days(row.final_entitlement_units) }}</dd></div>
                            <div><dt class="text-muted-foreground">Defisit selepas prorata</dt><dd>{{ days(row.deficit_units) }}</dd></div><div><dt class="text-muted-foreground">Tabung FIFO</dt><dd>{{ days(row.offset_units) }} daripada {{ days(row.tabung_before) }}</dd></div><div><dt class="text-muted-foreground">Baki penyelesaian luar</dt><dd class="font-semibold">{{ days(row.residual_units) }}</dd></div>
                        </dl>
                        <p class="text-xs text-muted-foreground">Penyedia: {{ row.maker_name }} · Bukti: {{ row.evidence_reference }}</p><p class="whitespace-pre-wrap text-sm">{{ row.reason }}</p>
                        <p v-if="row.external_reference" class="text-sm">Rujukan penyelesaian: {{ row.external_reference }} · {{ row.settlement_date }}</p>
                        <p v-if="row.confirmer_name" class="text-sm">Disahkan oleh {{ row.confirmer_name }}. Status lunas ialah rekod penyelesaian luar; lejar asal tidak dipadam.</p>
                        <div class="flex flex-wrap gap-2"><button v-if="row.can_start" type="button" :class="button" @click="open(row.id, 'start')">Sahkan prorata & offset</button><button v-if="row.can_evidence" type="button" :class="button" @click="open(row.id, 'evidence')">Rekod bukti penyelesaian</button><button v-if="row.can_confirm" type="button" :class="button" @click="open(row.id, 'confirm')">Sahkan sudah lunas</button></div>
                        <form v-if="selected === row.id" class="space-y-3 rounded-lg border border-border p-3" @submit.prevent="submit">
                            <p class="text-sm font-medium">{{ step === 'start' ? 'Sahkan jumlah dan pos prorata/offset sekali sahaja' : step === 'evidence' ? 'Rekod penyelesaian luar (atau bukti offset penuh jika baki sifar)' : 'Sahkan bukti penyelesaian secara bebas' }}</p>
                            <template v-if="step === 'evidence'"><label class="block text-sm">Rujukan bukti<input v-model="action.reference" required maxlength="120" :class="input" /></label><label class="block text-sm">Tarikh penyelesaian<input v-model="action.date" type="date" required :min="row.exit_date" :max="today" :class="input" /></label></template>
                            <label class="block text-sm">Catatan<textarea v-model="action.note" required maxlength="1000" :class="input" /></label>
                            <p v-for="error in action.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                            <div class="flex gap-3"><button :class="button" :disabled="action.processing">Sahkan tindakan</button><button type="button" class="text-sm" @click="selected = null">Batal</button></div>
                        </form>
                    </article>
                    <p v-if="!rows.data.length" class="rounded-xl border border-border bg-card p-4 text-sm text-muted-foreground">Tiada kes penyelesaian untuk paparan ini.</p>
                    <nav class="flex gap-4 text-sm text-primary"><Link v-if="rows.prev_page_url" :href="rows.prev_page_url">Sebelum</Link><Link v-if="rows.next_page_url" :href="rows.next_page_url">Seterusnya</Link></nav>
                </section>
            </main>
        </div>
    </div>
</template>
