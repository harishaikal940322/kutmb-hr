<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ArrowRight, Info, ShieldCheck } from '@lucide/vue';
import { computed, watch } from 'vue';
import AnnualLeaveNavigation, { type AnnualAccess, type AnnualSection } from './AnnualLeaveNavigation.vue';
import AnnualRequestList, { type RequestPage } from './AnnualRequestList.vue';
import PartialCancellationQueue, { type PartialReviewPage } from './PartialCancellationQueue.vue';
import TeamAvailabilityPanel from './TeamAvailabilityPanel.vue';

type Workspace = {
    today: string;
    linked: boolean;
    approver: boolean;
    can_decide: boolean;
    can_view_team: boolean;
    balance: {
        annual_half_day_units: number;
        tabung_half_day_units: number;
        annual_available: number;
        tabung_available: number;
        combined_available: number;
        pending: number;
    } | null;
    future_balance: { confirmed: number; pending: number; committed: number; available: number } | null;
    history: RequestPage;
    inbox: RequestPage;
    cancellations: RequestPage | null;
    partial_reviews: PartialReviewPage | null;
};
const props = defineProps<{ access: AnnualAccess; section: AnnualSection | null; workspace: Workspace | null }>();
defineOptions({ layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] } });
const titles = {
    apply: { label: 'Mohon cuti', eyebrow: 'Permohonan baharu' },
    requests: { label: 'Permohonan saya', eyebrow: 'Rekod peribadi' },
    approvals: { label: 'Sokongan & kelulusan', eyebrow: 'Tindakan menunggu' },
    team: { label: 'Jadual unit', eyebrow: 'Perancangan unit' },
    staff: { label: 'Profil & kelayakan', eyebrow: 'Rekod staf' },
};
const heading = computed(() => props.section ? titles[props.section] : { label: 'Akses modul', eyebrow: 'Ruang kerja cuti' });
const submission = useForm({ leave_type: 'AL', start: '', end: '', session: 'FULL', reason: '', key: '' });
watch([() => submission.session, () => submission.start], ([session, start]) => {
    if (session !== 'FULL') submission.end = start;
});
const metrics = computed(() => {
    const balance = props.workspace?.balance;
    if (!balance) return [];
    return [
        { label: 'Cuti Tahunan (AL)', value: balance.annual_half_day_units, note: 'sebelum tempahan' },
        { label: 'Tabung Cuti tersedia', value: balance.tabung_available, note: 'selepas tempahan' },
        { label: 'Tempahan pending', value: balance.pending, note: 'belum ditolak rasmi' },
        { label: 'Jumlah baki tersedia', value: balance.combined_available, note: 'selepas tempahan' },
    ];
});
const isFuture = computed(() => Boolean(props.workspace && submission.leave_type === 'AL' && submission.start.slice(0, 4) === String(Number(props.workspace.today.slice(0, 4)) + 1)));
const canSubmit = computed(() => Boolean(props.workspace?.linked && (isFuture.value ? props.workspace.future_balance : props.workspace.balance)));
function submitRequest() {
    if (!canSubmit.value || !props.access.sections.includes('apply')) return;
    submission.key ||= crypto.randomUUID();
    submission.post('/leave/annual/requests', {
        preserveScroll: true,
        onSuccess: () => {
            submission.reset();
            router.visit('/leave/annual?view=workflow&section=requests');
        },
    });
}
</script>

<template>
    <Head title="Cuti Tahunan" />
    <div class="@container/leave-module mx-auto flex w-full min-w-0 max-w-[100rem] flex-col bg-background text-foreground">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-4 py-4 sm:px-6">
            <div><p class="text-xs font-semibold tracking-[0.12em] text-primary uppercase">KUTMB · MOD-005</p><p class="text-sm font-semibold">Cuti Tahunan</p></div>
        </header>
        <div class="border-b border-primary/15 bg-primary/[0.045] px-4 py-2.5 text-xs leading-5 text-muted-foreground sm:px-6">
            Persekitaran pembangunan · akses mengikut kebenaran akaun dan penetapan HR.
        </div>
        <div class="grid min-w-0 items-start gap-4 px-3 py-4 sm:gap-5 sm:px-5 @min-[960px]/leave-module:grid-cols-[14rem_minmax(0,1fr)]">
            <AnnualLeaveNavigation :access="access" :active="section" />
            <section class="@container/leave-content min-w-0" aria-labelledby="annual-heading">
                <div class="mx-auto min-w-0 space-y-6 px-1 py-2 @min-[560px]/leave-content:px-3 @min-[800px]/leave-content:px-5">
                    <header class="space-y-1.5">
                        <p class="text-xs font-semibold tracking-[0.14em] text-primary uppercase">{{ heading.eyebrow }}</p>
                        <h1 id="annual-heading" class="text-2xl font-semibold tracking-tight @min-[560px]/leave-content:text-3xl">{{ heading.label }}</h1>
                    </header>
                    <div v-if="access.needs_employee || access.needs_office || access.needs_unit" class="flex items-start gap-3 rounded-xl border border-border bg-muted/30 p-4 text-sm leading-6">
                        <Info class="mt-1 size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        <div class="space-y-1">
                            <p v-if="access.needs_employee">Akaun ini perlu dipautkan kepada rekod pekerja oleh HR sebelum urusan cuti sendiri atau jadual unit tersedia.</p>
                            <p v-if="access.needs_unit">Jadual unit memerlukan rekod pekerja aktif dan satu penempatan unit aktif yang sah.</p>
                            <p v-if="access.needs_office">Akaun ini mempunyai permission kelulusan, tetapi belum mempunyai penetapan jawatan pelulus yang berkuat kuasa.</p>
                        </div>
                    </div>
                    <template v-if="workspace && section === 'apply' && workspace.linked">
                        <section aria-label="Baki kelayakan cuti" class="space-y-3">
                            <h2 class="font-semibold">Baki kelayakan cuti</h2>
                            <div v-if="metrics.length" class="grid grid-cols-2 gap-3 @min-[960px]/leave-content:grid-cols-4">
                                <article v-for="metric in metrics" :key="metric.label" class="min-w-0 rounded-2xl border border-border bg-card p-4 shadow-sm">
                                    <p class="text-xs text-muted-foreground">{{ metric.label }}</p>
                                    <p class="mt-2 text-2xl font-semibold tabular-nums">{{ (metric.value / 2).toFixed(1) }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">hari · {{ metric.note }}</p>
                                </article>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">Kelayakan tahun semasa perlu disahkan dan dikreditkan sebelum permohonan.</p>
                        </section>
                        <section v-if="workspace.future_balance" class="space-y-2 rounded-xl border border-border bg-card p-4">
                            <h2 class="font-semibold">Kelayakan tahun hadapan · belum dikreditkan</h2>
                            <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                                <div><p class="text-xs text-muted-foreground">Disahkan</p><p class="font-semibold">{{ workspace.future_balance.confirmed / 2 }} hari</p></div>
                                <div><p class="text-xs text-muted-foreground">Pending</p><p class="font-semibold">{{ workspace.future_balance.pending / 2 }} hari</p></div>
                                <div><p class="text-xs text-muted-foreground">Komitmen diluluskan</p><p class="font-semibold">{{ workspace.future_balance.committed / 2 }} hari</p></div>
                                <div><p class="text-xs text-muted-foreground">Tersedia</p><p class="font-semibold">{{ workspace.future_balance.available / 2 }} hari</p></div>
                            </div>
                            <p class="text-xs text-muted-foreground">AL tahun hadapan sahaja; tidak menggunakan Tabung atau baki negatif sebelum kredit.</p>
                        </section>
                        <form class="max-w-3xl space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm @min-[560px]/leave-content:p-7" @submit.prevent="submitRequest">
                            <section class="space-y-4">
                                <label class="block space-y-2 text-sm font-medium">Jenis cuti
                                    <select v-model="submission.leave_type" class="block min-h-11 w-full rounded-lg border border-input bg-background px-3 text-sm"><option value="AL">Cuti Tahunan (AL)</option><option value="EL">Emergency Leave (EL)</option></select>
                                    <span class="block text-xs font-normal text-muted-foreground">{{ submission.leave_type === 'AL' ? 'Alasan diperlukan untuk permohonan dengan notis singkat.' : 'Alasan kecemasan wajib.' }}</span>
                                </label>
                            </section>
                            <section class="space-y-4 border-t border-border pt-5">
                                <div><h2 class="font-semibold">2. Tarikh dan tempoh</h2><p class="mt-1 text-sm text-muted-foreground">Hari bekerja dikira mengikut kalendar dan jadual yang disahkan.</p></div>
                                <div class="grid gap-4 @min-[560px]/leave-content:grid-cols-2">
                                    <label class="space-y-2 text-sm font-medium">Tarikh mula<input v-model="submission.start" :min="submission.leave_type === 'AL' ? workspace.today : undefined" :max="submission.leave_type === 'EL' ? workspace.today : undefined" required type="date" class="block h-11 w-full rounded-lg border border-input bg-background px-3 text-sm" /></label>
                                    <label class="space-y-2 text-sm font-medium">Tarikh akhir<input v-model="submission.end" :min="submission.start" :max="submission.leave_type === 'EL' ? workspace.today : undefined" :disabled="submission.session !== 'FULL'" required type="date" class="block h-11 w-full rounded-lg border border-input bg-background px-3 text-sm disabled:opacity-60" /></label>
                                </div>
                                <div class="grid gap-3 @min-[560px]/leave-content:grid-cols-2">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm" :class="submission.session === 'FULL' ? 'border-primary bg-primary/[0.035]' : 'border-border'"><input v-model="submission.session" type="radio" name="leave-length" value="FULL" class="accent-primary" /> Penuh hari</label>
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm" :class="submission.session !== 'FULL' ? 'border-primary bg-primary/[0.035]' : 'border-border'"><input type="radio" :checked="submission.session !== 'FULL'" name="leave-length" class="accent-primary" @change="submission.session = 'AM'" /> Separuh hari</label>
                                </div>
                                <label v-if="submission.session !== 'FULL'" class="block space-y-2 text-sm font-medium">Sesi<select v-model="submission.session" class="block h-11 w-full rounded-lg border border-input bg-background px-3"><option value="AM">Pagi</option><option value="PM">Petang</option></select></label>
                            </section>
                            <section class="space-y-4 border-t border-border pt-5">
                                <h2 class="font-semibold">3. Maklumat tambahan</h2>
                                <label class="block space-y-2 text-sm font-medium">{{ submission.leave_type === 'EL' ? 'Alasan kecemasan · wajib' : 'Alasan · jika berkaitan' }}<textarea v-model="submission.reason" :required="submission.leave_type === 'EL'" rows="3" maxlength="1000" class="block w-full resize-y rounded-lg border border-input bg-background px-3 py-2.5 text-sm" /></label>
                                <p v-if="submission.leave_type === 'EL'" class="rounded-xl border border-amber-500/20 bg-amber-500/[0.07] p-4 text-sm leading-6"><strong>EL retrospektif:</strong> Pilih tarikh ketidakhadiran dalam tahun semasa.</p>
                            </section>
                            <p v-for="error in submission.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                            <div class="flex justify-end border-t border-border pt-5"><button type="submit" :disabled="submission.processing || !canSubmit" class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 disabled:opacity-50"><ArrowRight class="size-4" aria-hidden="true" />{{ submission.processing ? 'Menghantar…' : 'Hantar permohonan' }}</button></div>
                        </form>
                    </template>
                    <AnnualRequestList v-else-if="workspace && section === 'requests' && workspace.linked" :page="workspace.history" :approvals="false" />
                    <template v-else-if="workspace && section === 'approvals' && workspace.approver">
                        <AnnualRequestList v-if="workspace.can_decide" :page="workspace.inbox" approvals />
                        <PartialCancellationQueue v-if="workspace.partial_reviews" :page="workspace.partial_reviews" :today="workspace.today" />
                        <section v-if="workspace.cancellations" class="space-y-3"><h2 class="font-semibold">Pembatalan cuti</h2><AnnualRequestList :page="workspace.cancellations" approvals cancellations /></section>
                    </template>
                    <TeamAvailabilityPanel v-else-if="workspace && section === 'team' && workspace.can_view_team" :today="workspace.today" />
                    <section v-else class="space-y-3 rounded-2xl border border-border bg-card p-6 shadow-sm">
                        <ShieldCheck class="size-8 text-muted-foreground" aria-hidden="true" />
                        <h2 class="font-semibold">{{ access.can_manage_calendar || access.can_manage_policy ? 'Pengurusan cuti' : access.technical ? 'Akses pentadbiran teknikal' : 'Tiada submodul dalam skop semasa' }}</h2>
                        <p v-if="access.can_manage_calendar || access.can_manage_policy" class="text-sm leading-6 text-muted-foreground">Gunakan sub-sidebar untuk mengurus kalendar atau dasar cuti yang dibenarkan.</p>
                        <p v-else class="text-sm leading-6 text-muted-foreground">Kebenaran modul, pautan pekerja dan penetapan pegawai ditentukan oleh rekod sebenar. Akses teknikal tidak memberikan kuasa membaca atau meluluskan cuti.</p>
                    </section>
                </div>
            </section>
        </div>
    </div>
</template>
