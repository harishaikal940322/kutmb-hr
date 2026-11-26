<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ChevronDown, Search } from '@lucide/vue';
import AnnualLeaveNavigation, { type AnnualAccess } from './AnnualLeaveNavigation.vue';
import { ref, watch } from 'vue';

type Employee = {
    id: number;
    employee_no: string;
    display_name: string;
    service_start: string;
    confirmation_date: string | null;
    service_end: string | null;
    employment_status: 'ACTIVE' | 'FUTURE' | 'ENDED';
    assignment_status: 'CURRENT' | 'REVIEW' | 'NOT_APPLICABLE';
    unit: string | null;
    category: string | null;
    confirmed_half_day_units: number | null;
    entitlement_status: 'NO_ACCOUNT' | 'UNCONFIRMED' | 'CONFIRMED' | 'CREDITED';
    confirmed_at: string | null;
    tabung_capacity: { cap_units: number | null; posted_units: number | null; remaining_units: number | null; excess_units: number | null };
    rate_suggestion: {
        status: 'REVIEW' | 'NOT_IN_SERVICE' | 'PROBATION' | 'SUGGESTED';
        as_of: string; completed_years: number | null; annual_half_day_units: number | null;
        probation_half_day_units: number | null; band: string | null; message: string;
    };
};
type PaginationLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    employees: { data: Employee[]; total: number; from: number | null; to: number | null; links: PaginationLink[] };
    filters: { search: string; year: number };
    currentYear: number;
    asOf: string;
    access: AnnualAccess;
    canConfirmEntitlement: boolean;
    canCreditEntitlement: boolean;
    canManageEmployees: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] },
});
const search = ref(props.filters.search);
const year = ref(props.filters.year);
const expanded = ref<number | null>(null);
const confirmation = useForm({ year: props.filters.year, units: '', annual_units: null as number | null, reference: '', key: '' });
const confirmed = ref('');
const credit = useForm({});
const prorataLoading = ref(false);
type ProrataResult = {
    calculation_type: 'FULL_YEAR' | 'NEW_HIRE' | 'TERMINATION' | 'NEW_HIRE_TERMINATION';
    eligible_months: number; annual_half_day_units: number; prorated_half_day_units: number;
    period_start: string; period_end: string; can_use_for_confirmation: boolean;
};
const prorataResult = ref<ProrataResult | null>(null);
const prorataLabels = { FULL_YEAR: 'Tahun penuh', NEW_HIRE: 'Pekerja baharu', TERMINATION: 'Penamatan', NEW_HIRE_TERMINATION: 'Mula dan tamat dalam tahun yang sama' };
const rateDays = ref('');
let previewSequence = 0;
const prorataError = ref('');

async function previewProrata(row: Employee) {
    const sequence = ++previewSequence;
    prorataLoading.value = true;
    prorataResult.value = null;
    prorataError.value = '';
    const year = props.filters.year;
    const units = Number(rateDays.value) * 2;
    if (!Number.isInteger(units) || units < 1 || units > 100000) {
        prorataError.value = 'Masukkan kadar dalam gandaan 0.5 hari.';
        prorataLoading.value = false;
        return;
    }
    const params = new URLSearchParams({ year: String(year), annual_units: String(units) });
    try {
        const response = await fetch(`/leave/annual/entitlements/${row.id}/prorata?${params}`, {
            headers: { Accept: 'application/json' },
        });
        const result = await response.json();
        if (sequence !== previewSequence || expanded.value !== row.id || props.filters.year !== year || Number(rateDays.value) * 2 !== units) return;
        if (response.ok) {
            prorataResult.value = result as ProrataResult;
        } else {
            prorataError.value = response.status === 422 ? result.message : 'Pratonton tidak tersedia.';
        }
    } catch {
        if (sequence === previewSequence && expanded.value === row.id && props.filters.year === year) prorataError.value = 'Pratonton tidak tersedia.';
    } finally {
        if (sequence === previewSequence) prorataLoading.value = false;
    }
}

function useProrata() {
    if (!prorataResult.value?.can_use_for_confirmation) return;
    confirmation.units = String(prorataResult.value.prorated_half_day_units);
    confirmation.annual_units = prorataResult.value.annual_half_day_units;
    confirmation.key = '';
    confirmation.clearErrors();
}

function resetPreview() {
    ++previewSequence;
    rateDays.value = '';
    prorataResult.value = null;
    prorataError.value = '';
    prorataLoading.value = false;
}

watch(rateDays, () => {
    ++previewSequence;
    prorataResult.value = null;
    prorataError.value = '';
    prorataLoading.value = false;
});

function expand(row: Employee) {
    expanded.value = expanded.value === row.id ? null : row.id;
    confirmation.reset();
    confirmation.clearErrors();
    credit.clearErrors();
    confirmed.value = '';
    resetPreview();
}

function postCredit(row: Employee) {
    credit.post(`/leave/annual/entitlements/${row.id}/credit`, {
        preserveScroll: true,
        onSuccess: () => {
            confirmed.value = 'Kelayakan dikreditkan.';
        },
    });
}

function confirm(row: Employee) {
    confirmation.year = props.filters.year;
    confirmation.key ||= crypto.randomUUID();
    confirmation.post(`/leave/annual/entitlements/${row.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => {
            confirmation.reset();
            confirmed.value = 'Kelayakan disahkan.';
        },
    });
}
watch([() => props.filters.search, () => props.filters.year], ([nextSearch, nextYear]) => {
    search.value = nextSearch;
    year.value = nextYear;
    expanded.value = null;
    confirmation.reset();
    confirmation.clearErrors();
    credit.clearErrors();
    confirmed.value = '';
    resetPreview();
});
const statusLabels = {
    NO_ACCOUNT: 'Akaun belum tersedia',
    UNCONFIRMED: 'Belum disahkan',
    CONFIRMED: 'Disahkan · belum dikreditkan',
    CREDITED: 'Disahkan & dikreditkan',
};
const employmentLabels = { ACTIVE: 'Aktif', FUTURE: 'Belum mula', ENDED: 'Tamat' };
function filter() {
    router.get('/leave/annual', { search: search.value, year: year.value }, { preserveState: true, preserveScroll: true, replace: true });
}
function clearFilters() {
    search.value = '';
    year.value = props.currentYear;
    filter();
}
function days(units: number | null) {
    return units === null ? 'Belum disahkan' : new Intl.NumberFormat('ms-MY', { maximumFractionDigits: 1 }).format(units / 2) + ' hari';
}
function date(value: string | null) {
    return value ? new Intl.DateTimeFormat('ms-MY', { dateStyle: 'medium', timeZone: 'UTC' }).format(new Date(value.slice(0, 10) + 'T00:00:00Z')) : 'Belum direkodkan';
}
</script>

<template>
    <Head title="Cuti Tahunan · Profil & Kelayakan" />
    <div class="@container/leave-module mx-auto flex w-full min-w-0 max-w-[100rem] flex-col bg-background text-foreground">
        <header class="flex flex-wrap items-center justify-between gap-3 border-b border-border bg-card px-4 py-4 sm:px-6">
            <div>
                <p class="text-xs font-semibold tracking-[0.12em] text-primary uppercase">KUTMB · MOD-005</p>
                <p class="text-sm font-semibold">Cuti Tahunan</p>
            </div>

        </header>
        <div class="border-b border-border bg-muted/40 px-4 py-3 text-xs leading-5 text-muted-foreground sm:px-6">
            <strong class="mr-2 text-foreground">DATA PEMBANGUNAN</strong>
            Rekod pangkalan data pembangunan sahaja. Gunakan amaun yang disahkan untuk data sintetik pembangunan.
        </div>
        <div class="grid min-w-0 items-start gap-4 px-3 py-4 sm:gap-5 sm:px-5 @min-[960px]/leave-module:grid-cols-[14rem_minmax(0,1fr)]">
            <AnnualLeaveNavigation :access="access" active="staff" />
            <section class="min-w-0 space-y-6 px-1 py-2" aria-labelledby="entitlement-heading">
                <header class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold tracking-[0.14em] text-primary uppercase">Rekod staf</p>
                        <h1 id="entitlement-heading" class="mt-1 text-2xl font-semibold tracking-tight">Profil &amp; kelayakan</h1>
                        <p class="mt-2 text-sm text-muted-foreground">Semak profil, penempatan semasa dan kelayakan tahunan yang direkodkan.</p>
                    </div>
                    <Link v-if="canManageEmployees" href="/employees" class="inline-flex min-h-10 items-center rounded-lg border border-border px-4 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring">Direktori pekerja</Link>
                </header>
                <section class="rounded-2xl border border-border bg-card p-4 shadow-sm sm:p-5">
                    <form class="flex flex-wrap items-end gap-3" @submit.prevent="filter">
                        <label class="min-w-0 flex-1 basis-48 space-y-1 text-sm font-medium">Cari staf
                            <span class="relative block"><Search class="absolute top-3 left-3 size-4 text-muted-foreground" aria-hidden="true" /><input v-model="search" type="search" maxlength="100" placeholder="Nama atau ID staf" class="h-10 w-full rounded-lg border border-input bg-background pr-3 pl-9 text-sm" /></span>
                        </label>
                        <label class="space-y-1 text-sm font-medium">Tahun
                            <input v-model.number="year" type="number" required min="1900" :max="currentYear + 1" class="block h-10 w-28 rounded-lg border border-input bg-background px-3 text-sm" />
                        </label>
                        <button type="submit" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring">Cari</button>
                        <button type="button" class="min-h-10 rounded-lg border border-border px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring" @click="clearFilters">Reset</button>
                    </form>
                    <p class="mt-4 text-xs text-muted-foreground">{{ employees.total }} rekod · Penempatan pada {{ date(asOf) }} · Kelayakan tahun {{ filters.year }}</p>
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full min-w-[660px] text-left text-sm">
                            <thead class="border-b border-border text-xs text-muted-foreground">
                                <tr>
                                    <th scope="col" class="px-3 py-3 font-medium">Staf / ID</th>
                                    <th scope="col" class="px-3 py-3 font-medium">Unit &amp; kategori</th>
                                    <th scope="col" class="px-3 py-3 font-medium">Kelayakan {{ filters.year }}</th>
                                    <th scope="col" class="px-3 py-3 font-medium">Status</th>
                                    <th scope="col" class="px-3 py-3 font-medium">Butiran</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <template v-for="row in employees.data" :key="row.id">
                                    <tr>
                                        <td class="px-3 py-4"><strong class="block font-semibold">{{ row.display_name }}</strong><span class="text-xs text-muted-foreground">{{ row.employee_no }}</span></td>
                                        <td class="px-3 py-4"><span v-if="row.assignment_status === 'CURRENT'">{{ row.unit }}<span class="block text-xs text-muted-foreground">{{ row.category }}</span></span><span v-else class="text-muted-foreground">{{ row.assignment_status === 'REVIEW' ? 'Perlu semakan penempatan' : 'Tiada penempatan semasa' }}</span></td>
                                        <td class="px-3 py-4 font-medium">{{ days(row.confirmed_half_day_units) }}</td>
                                        <td class="px-3 py-4"><span class="inline-block rounded-full px-2.5 py-1 text-xs font-medium" :class="row.entitlement_status === 'CREDITED' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-foreground'">{{ statusLabels[row.entitlement_status] }}</span><span class="mt-1 block text-xs text-muted-foreground">{{ employmentLabels[row.employment_status] }}</span></td>
                                        <td class="px-3 py-4"><button type="button" :aria-expanded="expanded === row.id" :aria-controls="'employee-detail-' + row.id" class="inline-flex min-h-10 items-center gap-1 rounded-md px-2 text-sm font-medium text-primary hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring" :disabled="confirmation.processing || credit.processing" @click="expand(row)">Profil <ChevronDown class="size-4" :class="expanded === row.id ? 'rotate-180' : ''" aria-hidden="true" /></button></td>
                                    </tr>
                                    <tr v-show="expanded === row.id" :id="'employee-detail-' + row.id">
                                        <td colspan="5" class="bg-muted/30 px-4 py-4">
                                            <dl class="grid gap-3 sm:grid-cols-2">
                                                <div><dt class="text-xs text-muted-foreground">Tarikh mula</dt><dd class="mt-1 font-medium">{{ date(row.service_start) }}</dd></div>
                                                <div><dt class="text-xs text-muted-foreground">Tarikh pengesahan jawatan</dt><dd class="mt-1 font-medium">{{ date(row.confirmation_date) }}</dd></div>
                                                <div><dt class="text-xs text-muted-foreground">Tarikh tamat</dt><dd class="mt-1 font-medium">{{ date(row.service_end) }}</dd></div>
                                                <div><dt class="text-xs text-muted-foreground">Tarikh pengesahan kelayakan</dt><dd class="mt-1 font-medium">{{ date(row.confirmed_at) }}</dd></div>
                                            </dl>
                                            <section v-if="expanded === row.id" class="mt-4 space-y-3 rounded-lg border border-border bg-card p-4">
                                                <div class="flex flex-wrap items-start justify-between gap-2">
                                                    <div>
                                                        <h2 class="font-semibold">Kadar automatik berdasarkan tempoh perkhidmatan</h2>
                                                        <p class="mt-1 text-xs text-muted-foreground">Cadangan kategori demo · Tarikh rujukan {{ date(row.rate_suggestion.as_of) }}</p>
                                                    </div>
                                                    <span class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium">{{ row.rate_suggestion.status === 'SUGGESTED' ? 'Kadar dikenal pasti' : row.rate_suggestion.status === 'PROBATION' ? 'Probation' : 'Semakan HR' }}</span>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <div><p class="text-xs text-muted-foreground">Tempoh lengkap</p><p class="mt-1 text-lg font-semibold">{{ row.rate_suggestion.completed_years === null ? '—' : row.rate_suggestion.completed_years + ' tahun' }}</p></div>
                                                    <div><p class="text-xs text-muted-foreground">Band</p><p class="mt-1 text-lg font-semibold">{{ row.rate_suggestion.band ?? '—' }}</p></div>
                                                    <div><p class="text-xs text-muted-foreground">{{ row.rate_suggestion.status === 'PROBATION' ? 'Kredit probation (belum dipos)' : 'Kadar tahunan cadangan' }}</p><p class="mt-1 text-xl font-semibold text-primary">{{ days(row.rate_suggestion.annual_half_day_units ?? row.rate_suggestion.probation_half_day_units) }}</p></div>
                                                </div>
                                                <p class="text-sm text-muted-foreground">{{ row.rate_suggestion.message }}</p>
                                                <button v-if="canConfirmEntitlement && row.rate_suggestion.status === 'SUGGESTED' && row.rate_suggestion.annual_half_day_units !== null" type="button" class="min-h-10 rounded-lg border border-border px-3 text-sm font-semibold" @click="rateDays = String(row.rate_suggestion.annual_half_day_units / 2)">Guna kadar cadangan untuk pratonton</button>
                                            </section>
                                            <section v-if="expanded === row.id" class="mt-4 space-y-3 rounded-lg border border-border bg-card p-4">
                                                <div><h2 class="font-semibold">Kapasiti Tabung Cuti</h2><p class="mt-1 text-xs text-muted-foreground">Had berdasarkan kelayakan disahkan {{ filters.year }} · dibandingkan dengan baki Tabung dipos semasa, bukan baki sejarah atau ramalan.</p></div>
                                                <div class="grid gap-3 sm:grid-cols-3">
                                                    <div><p class="text-xs text-muted-foreground">Had disahkan</p><p class="mt-1 text-xl font-semibold">{{ days(row.tabung_capacity.cap_units) }}</p></div>
                                                    <div><p class="text-xs text-muted-foreground">Baki Tabung semasa</p><p class="mt-1 text-xl font-semibold">{{ row.tabung_capacity.posted_units === null ? 'Tiada akaun' : days(row.tabung_capacity.posted_units) }}</p></div>
                                                    <div><p class="text-xs text-muted-foreground">Ruang tambahan</p><p class="mt-1 text-xl font-semibold text-primary">{{ days(row.tabung_capacity.remaining_units) }}</p></div>
                                                </div>
                                                <div v-if="row.tabung_capacity.cap_units !== null && row.tabung_capacity.cap_units > 0 && row.tabung_capacity.posted_units !== null" role="meter" aria-label="Penggunaan kapasiti Tabung" :aria-valuenow="Math.min(row.tabung_capacity.posted_units, row.tabung_capacity.cap_units)" :aria-valuemin="0" :aria-valuemax="row.tabung_capacity.cap_units" class="h-2 overflow-hidden rounded-full bg-muted">
                                                    <div class="h-full rounded-full bg-primary" :style="{ width: Math.min(100, row.tabung_capacity.posted_units / row.tabung_capacity.cap_units * 100) + '%' }" />
                                                </div>
                                                <p v-if="(row.tabung_capacity.excess_units ?? 0) > 0" class="text-sm text-destructive">Lebihan {{ days(row.tabung_capacity.excess_units) }} memerlukan semakan HR. Tiada pelupusan automatik.</p>
                                                <p class="text-xs text-muted-foreground">Kadar cadangan tidak mengubah had. Apabila kelayakan tahun dipilih disahkan, kapasiti dikira semula tanpa kredit ke Tabung.</p>
                                            </section>
                                            <p v-if="expanded === row.id && confirmed" role="status" class="mt-4 text-sm">{{ confirmed }}</p>
                                            <form v-if="expanded === row.id && canConfirmEntitlement"
                                                class="mt-4 space-y-4 border-t border-border pt-4" @submit.prevent="previewProrata(row)">
                                                <div>
                                                    <p class="font-semibold">Pengiraan kelayakan &amp; prorata</p>
                                                    <p class="mt-1 text-sm text-muted-foreground">Gunakan kadar cadangan di atas atau masukkan kadar tahunan yang telah disemak HR. Hasil pengiraan tidak mengubah kelayakan yang telah disahkan.</p>
                                                </div>
                                                <div class="grid gap-3 sm:grid-cols-2">
                                                    <label class="block space-y-1 text-sm">
                                                        <span>Kadar tahunan (hari)</span>
                                                        <input v-model="rateDays" type="number" required min="0.5" max="50000" step="0.5" placeholder="Contoh: 20" class="block h-10 w-full rounded-lg border border-input bg-background px-3" />
                                                    </label>
                                                    <div class="self-end"><button type="submit" :disabled="prorataLoading" class="min-h-10 rounded-lg border border-border px-4 text-sm font-semibold disabled:opacity-50">{{ prorataLoading ? 'Mengira…' : 'Kira kelayakan' }}</button></div>
                                                </div>
                                                <div v-if="prorataResult" role="status" class="space-y-3 rounded-lg border border-border bg-card p-4">
                                                    <p class="text-sm font-medium">{{ prorataLabels[prorataResult.calculation_type] }} · {{ date(prorataResult.period_start) }} hingga {{ date(prorataResult.period_end) }}</p>
                                                    <div class="grid gap-3 sm:grid-cols-3">
                                                        <div><p class="text-xs text-muted-foreground">Kadar tahunan</p><p class="mt-1 text-lg font-semibold">{{ days(prorataResult.annual_half_day_units) }}</p></div>
                                                        <div><p class="text-xs text-muted-foreground">Bulan layak</p><p class="mt-1 text-lg font-semibold">{{ prorataResult.eligible_months }} / 12</p></div>
                                                        <div><p class="text-xs text-muted-foreground">Hasil pengiraan</p><p class="mt-1 text-xl font-semibold text-primary">{{ days(prorataResult.prorated_half_day_units) }}</p></div>
                                                    </div>
                                                    <p class="text-xs text-muted-foreground">Pratonton sahaja. Tiada perubahan kepada baki atau lejar. Kes penamatan memerlukan semakan penyelesaian HR.</p>
                                                    <button v-if="prorataResult.can_use_for_confirmation && ['NO_ACCOUNT', 'UNCONFIRMED'].includes(row.entitlement_status)" type="button" class="min-h-10 rounded-lg border border-border px-3 text-sm font-semibold" @click="useProrata">Guna hasil untuk pengesahan</button>
                                                </div>
                                                <p v-if="prorataError" role="alert" class="text-sm text-destructive">{{ prorataError }}</p>
                                                <p class="text-xs leading-5 text-muted-foreground">Kadar probation 6 hari merupakan peraturan kredit berasingan, bukan kadar tahunan untuk diproratakan. Kenaikan pangkat, penamatan pertengahan bulan dan pembundaran tepat di tengah masih memerlukan keputusan HR.</p>
                                            </form>
                                            <form v-if="expanded === row.id && canCreditEntitlement && row.entitlement_status === 'CONFIRMED'"
                                                class="mt-4 space-y-3 border-t border-border pt-4" @submit.prevent="postCredit(row)">
                                                <p v-for="error in credit.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                                                <button type="submit" :disabled="credit.processing" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                                                    {{ credit.processing ? 'Merekod…' : 'Kreditkan kelayakan 1 Januari' }}
                                                </button>
                                            </form>
                                            <form
                                                v-if="expanded === row.id && canConfirmEntitlement && ['NO_ACCOUNT', 'UNCONFIRMED'].includes(row.entitlement_status)"
                                                class="mt-4 space-y-3 border-t border-border pt-4"
                                                @submit.prevent="confirm(row)"
                                            >
                                                <fieldset :disabled="confirmation.processing" class="grid gap-3 sm:grid-cols-2">
                                                    <legend class="mb-2 font-semibold">Sahkan kelayakan sintetik {{ filters.year }}</legend>
                                                    <label class="space-y-1 text-sm">Bilangan unit separuh hari
                                                        <input v-model="confirmation.units" type="number" required min="1" step="1" class="block h-10 w-full rounded-lg border border-input bg-background px-3" @input="confirmation.annual_units = null; confirmation.key = ''" />
                                                        <span v-if="confirmation.annual_units !== null" class="text-xs text-muted-foreground">Daripada pengiraan server; akan disemak semula semasa pengesahan.</span>
                                                    </label>
                                                    <label class="space-y-1 text-sm">Rujukan pengesahan
                                                        <input v-model="confirmation.reference" type="text" required maxlength="120" class="block h-10 w-full rounded-lg border border-input bg-background px-3" />
                                                    </label>
                                                </fieldset>
                                                <p v-for="error in confirmation.errors" :key="error" role="alert" class="text-sm text-destructive">{{ error }}</p>
                                                <button type="submit" :disabled="confirmation.processing" class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground disabled:opacity-50">
                                                    {{ confirmation.processing ? 'Mengesahkan…' : 'Sahkan kelayakan' }}
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="employees.data.length === 0"><td colspan="5" class="px-4 py-12 text-center text-muted-foreground">Tiada rekod pekerja sepadan. Gunakan data sintetik pembangunan untuk semakan.</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <nav v-if="employees.links.length > 3" class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-4" aria-label="Halaman rekod pekerja">
                        <span class="text-xs text-muted-foreground">{{ employees.from }}–{{ employees.to }} daripada {{ employees.total }}</span>
                        <div class="flex flex-wrap gap-1">
                            <template v-for="(item, index) in employees.links" :key="index">
                                <Link v-if="item.url" :href="item.url" :aria-current="item.active ? 'page' : undefined" preserve-scroll class="inline-flex min-h-9 items-center rounded-md border border-border px-3 text-xs font-medium focus-visible:ring-2 focus-visible:ring-ring" :class="item.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'">{{ index === 0 ? 'Sebelum' : index === employees.links.length - 1 ? 'Seterusnya' : item.label }}</Link>
                                <span v-else class="inline-flex min-h-9 items-center px-2 text-xs text-muted-foreground">{{ index === 0 ? 'Sebelum' : index === employees.links.length - 1 ? 'Seterusnya' : item.label }}</span>
                            </template>
                        </div>
                    </nav>
                </section>
                <p class="text-xs leading-5 text-muted-foreground">Kelayakan yang disahkan ialah amaun rujukan, bukan baki tersedia. Baki, tempahan pending dan transaksi akan disambungkan dalam submodul berikutnya. Penempatan dipaparkan pada tarikh semasa, bukan sejarah tahun pilihan.</p>
            </section>
        </div>
    </div>
</template>
