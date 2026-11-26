<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AnnualLeaveNavigation, { type AnnualAccess } from './AnnualLeaveNavigation.vue';

type Adjustment = { id: number; display_name: string; employee_no: string; leave_year: number; bucket: string; source_year: number | null; signed_units: number; reason: string; evidence_reference: string; prepared_balance: number; prepared_pending: number; status: 'PENDING' | 'APPROVED' | 'REJECTED'; before_units: number | null; after_units: number | null; decision_reason: string | null; prepared_by_name: string; decided_by_name: string | null; can_decide: boolean; created_at: string };
const props = defineProps<{ access: AnnualAccess; canPrepare: boolean; year: number; employees: { id: number; employee_no: string; display_name: string }[]; adjustments: { data: Adjustment[]; prev_page_url: string | null; next_page_url: string | null } }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] } });
const prepare = useForm({ employee: '', bucket: 'AL', source_year: props.year, units: '', reason: '', evidence_reference: '', key: '' });
const decide = useForm({ decision: 'APPROVED', reason: '', key: '' });
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
                    <h2 class="font-semibold">Sediakan pelarasan tahun semasa</h2>
                    <label class="space-y-1 text-sm font-medium">Pekerja<select v-model="prepare.employee" required :class="input"><option value="">Pilih pekerja</option><option v-for="item in employees" :key="item.id" :value="String(item.id)">{{ item.employee_no }} · {{ item.display_name }}</option></select></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="space-y-1 text-sm font-medium">Akaun<select v-model="prepare.bucket" :class="input"><option value="AL">Cuti Tahunan</option><option value="TABUNG">Tabung Cuti</option></select></label><label class="space-y-1 text-sm font-medium">Perubahan (unit separuh hari, +/-)<input v-model="prepare.units" type="number" min="-100000" max="100000" required :class="input" /></label><label v-if="prepare.bucket === 'TABUNG'" class="space-y-1 text-sm font-medium">Tahun sumber<input v-model.number="prepare.source_year" type="number" min="1900" :max="year" required :class="input" /></label></div>
                    <label class="space-y-1 text-sm font-medium">Sebab<textarea v-model="prepare.reason" required maxlength="1000" :class="input" /></label><label class="space-y-1 text-sm font-medium">Rujukan bukti<input v-model="prepare.evidence_reference" required maxlength="120" :class="input" /></label>
                    <p v-for="error in prepare.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                    <button type="submit" :class="button" :disabled="prepare.processing">Hantar untuk semakan</button>
                </form>
                <section class="divide-y divide-border rounded-xl border border-border bg-card">
                    <h2 class="p-4 font-semibold">Rekod pelarasan</h2>
                    <article v-for="row in adjustments.data" :key="row.id" class="space-y-3 p-4">
                        <div class="flex flex-wrap justify-between gap-3"><div><h3 class="font-medium">{{ row.display_name }} · {{ row.employee_no }}</h3><p class="text-xs text-muted-foreground">{{ row.bucket }} · {{ row.leave_year }}<template v-if="row.source_year"> · Sumber {{ row.source_year }}</template></p></div><div class="text-right"><p class="font-semibold">{{ Number(row.signed_units) > 0 ? '+' : '' }}{{ days(row.signed_units) }}</p><p class="text-xs text-muted-foreground">{{ { PENDING: 'Menunggu semakan', APPROVED: 'Diluluskan', REJECTED: 'Ditolak' }[row.status] ?? row.status }}</p></div></div>
                        <p class="text-sm whitespace-pre-wrap">{{ row.reason }}</p><p class="text-xs text-muted-foreground">Rujukan: {{ row.evidence_reference }} · Penyedia: {{ row.prepared_by_name }}</p>
                        <p class="text-sm">Baki ketika disediakan: {{ days(row.prepared_balance) }} · Pending: {{ days(row.prepared_pending) }}</p>
                        <p v-if="row.status === 'APPROVED'" class="text-sm">Baki sebelum: {{ days(row.before_units) }} · Selepas: {{ days(row.after_units) }}</p>
                        <p v-if="row.decided_by_name" class="text-sm text-muted-foreground">{{ row.decided_by_name }}: {{ row.decision_reason }}</p>
                        <button v-if="row.can_decide" type="button" class="min-h-10 rounded-lg border border-border px-4 text-sm" @click="open(row.id)">Semak keputusan</button>
                        <form v-if="selected === row.id && row.can_decide" class="space-y-3 border-t border-border pt-3" @submit.prevent="decision(row.id)">
                            <label class="space-y-1 text-sm font-medium">Keputusan<select v-model="decide.decision" :class="input"><option value="APPROVED">Lulus dan pos</option><option value="REJECTED">Tolak</option></select></label>
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
