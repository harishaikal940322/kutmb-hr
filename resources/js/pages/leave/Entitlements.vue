<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronDown, Search, UsersRound } from '@lucide/vue';
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
};
type PaginationLink = { url: string | null; label: string; active: boolean };
const props = defineProps<{
    employees: { data: Employee[]; total: number; from: number | null; to: number | null; links: PaginationLink[] };
    filters: { search: string; year: number };
    currentYear: number;
    asOf: string;
    canReviewDesign: boolean;
    canUseWorkflow: boolean;
    canManageEmployees: boolean;
}>();
defineOptions({
    layout: { breadcrumbs: [{ title: 'Cuti Tahunan', href: '/leave/annual' }] },
});
const search = ref(props.filters.search);
const year = ref(props.filters.year);
const expanded = ref<number | null>(null);
watch(() => props.filters, (filters) => {
    search.value = filters.search;
    year.value = filters.year;
    expanded.value = null;
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
            <Link v-if="canUseWorkflow" href="/leave/annual?view=workflow" class="inline-flex min-h-10 items-center rounded-lg border border-border px-4 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring">Permohonan &amp; kelulusan</Link>
            <Link v-if="canReviewDesign" href="/leave/annual?view=design" class="inline-flex min-h-10 items-center rounded-lg border border-border px-4 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring">Semakan reka bentuk</Link>
        </header>
        <div class="border-b border-border bg-muted/40 px-4 py-3 text-xs leading-5 text-muted-foreground sm:px-6">
            <strong class="mr-2 text-foreground">DATA PEMBANGUNAN</strong>
            Rekod pangkalan data pembangunan sahaja. Paparan baca sahaja; kelayakan tidak dikira atau dikreditkan secara automatik.
        </div>
        <div class="grid min-w-0 items-start gap-4 px-3 py-4 sm:gap-5 sm:px-5 @min-[960px]/leave-module:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="min-w-0 rounded-xl border border-border bg-card shadow-sm @min-[960px]/leave-module:sticky @min-[960px]/leave-module:top-4">
                <div class="flex items-center gap-2.5 border-b border-border px-4 py-4">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted"><CalendarDays class="size-4" aria-hidden="true" /></div>
                    <div><p class="text-sm font-semibold">Submodul cuti</p><p class="text-xs text-muted-foreground">Pengurusan HR</p></div>
                </div>
                <nav class="space-y-1 p-2.5" aria-label="Submodul Cuti Tahunan">
                    <p class="px-2.5 pt-1 text-xs font-medium text-muted-foreground">Pengurusan cuti</p>
                    <Link href="/leave/annual" aria-current="page" class="flex min-h-10 items-center gap-2.5 rounded-md border-l-2 border-primary bg-primary/10 px-2.5 py-2 text-sm font-semibold text-primary focus-visible:ring-2 focus-visible:ring-ring">
                        <UsersRound class="size-4 shrink-0" aria-hidden="true" /> Profil &amp; kelayakan
                    </Link>
                </nav>
            </aside>
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
                                        <td class="px-3 py-4"><button type="button" :aria-expanded="expanded === row.id" :aria-controls="'employee-detail-' + row.id" class="inline-flex min-h-10 items-center gap-1 rounded-md px-2 text-sm font-medium text-primary hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring" @click="expanded = expanded === row.id ? null : row.id">Profil <ChevronDown class="size-4" :class="expanded === row.id ? 'rotate-180' : ''" aria-hidden="true" /></button></td>
                                    </tr>
                                    <tr v-show="expanded === row.id" :id="'employee-detail-' + row.id">
                                        <td colspan="5" class="bg-muted/30 px-4 py-4">
                                            <dl class="grid gap-3 sm:grid-cols-2">
                                                <div><dt class="text-xs text-muted-foreground">Tarikh mula</dt><dd class="mt-1 font-medium">{{ date(row.service_start) }}</dd></div>
                                                <div><dt class="text-xs text-muted-foreground">Tarikh pengesahan jawatan</dt><dd class="mt-1 font-medium">{{ date(row.confirmation_date) }}</dd></div>
                                                <div><dt class="text-xs text-muted-foreground">Tarikh tamat</dt><dd class="mt-1 font-medium">{{ date(row.service_end) }}</dd></div>
                                                <div><dt class="text-xs text-muted-foreground">Tarikh pengesahan kelayakan</dt><dd class="mt-1 font-medium">{{ date(row.confirmed_at) }}</dd></div>
                                            </dl>
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
