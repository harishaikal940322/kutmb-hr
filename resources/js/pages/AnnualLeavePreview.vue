<script setup lang="ts">
import { computed, defineComponent, h, ref, watch, type Component } from 'vue';
import {
    ArrowRight,
    Bell,
    CalendarDays,
    Check,
    CircleAlert,
    ClipboardList,
    Clock3,
    FileClock,
    FileText,
    Filter,
    Gauge,
    Info,
    Landmark,
    LayoutDashboard,
    ListChecks,
    Search,
    ShieldCheck,
    SlidersHorizontal,
    UsersRound,
    WalletCards,
    X,
} from '@lucide/vue';

type Role =
    | 'Staf'
    | 'Pengurus Unit'
    | 'Pengurus Besar'
    | 'Setiausaha ALK'
    | 'HR'
    | 'Pentadbir IT';
type Screen =
    | 'overview'
    | 'apply'
    | 'requests'
    | 'detail'
    | 'team'
    | 'approvals'
    | 'calendar'
    | 'staff'
    | 'balances'
    | 'year-end'
    | 'prorata'
    | 'changes'
    | 'reports'
    | 'audit'
    | 'notifications'
    | 'coverage';

const role = ref<Role>('Staf');
const screen = ref<Screen>('overview');
const selectedRequest = ref('AL-2026-052');
const search = ref('');
const activeFilter = ref('Semua');
const leaveType = ref<'AL' | 'EL'>('AL');
const startDate = ref('2026-12-14');
const endDate = ref('2026-12-16');
const leaveLength = ref<'Penuh hari' | 'Separuh hari'>('Penuh hari');
const session = ref<'Pagi' | 'Petang'>('Pagi');
const reason = ref('');
const toast = ref('');

watch([leaveLength, startDate], ([length, start]) => {
    if (length === 'Separuh hari' && endDate.value !== start)
        endDate.value = start;
});

const roles: Role[] = [
    'Staf',
    'Pengurus Unit',
    'Pengurus Besar',
    'Setiausaha ALK',
    'HR',
    'Pentadbir IT',
];

const screens: Record<Screen, { label: string; eyebrow: string }> = {
    overview: { label: 'Ringkasan', eyebrow: 'Ruang kerja cuti' },
    apply: { label: 'Mohon cuti', eyebrow: 'Permohonan baharu' },
    requests: { label: 'Permohonan saya', eyebrow: 'Rekod peribadi' },
    detail: { label: 'Butiran permohonan', eyebrow: 'Semakan rekod' },
    team: { label: 'Jadual unit', eyebrow: 'Perancangan unit' },
    approvals: { label: 'Sokongan & kelulusan', eyebrow: 'Tindakan menunggu' },
    calendar: {
        label: 'Kalendar & cuti umum',
        eyebrow: 'Kalendar rasmi Johor',
    },
    staff: { label: 'Profil & kelayakan', eyebrow: 'Rekod staf' },
    balances: { label: 'Baki & transaksi', eyebrow: 'Lejar cuti' },
    'year-end': { label: 'Tutup tahun', eyebrow: 'Carry forward & pelupusan' },
    prorata: { label: 'Semakan prorata', eyebrow: 'Pengiraan perkhidmatan' },
    changes: { label: 'Pindaan & pembatalan', eyebrow: 'Semakan HR / GM' },
    reports: { label: 'Laporan', eyebrow: 'Pemantauan pengurusan' },
    audit: { label: 'Jejak audit', eyebrow: 'Sejarah tindakan' },
    notifications: { label: 'Notifikasi', eyebrow: 'Makluman berkaitan' },
    coverage: { label: 'Peta liputan SRS', eyebrow: 'Indeks reka bentuk' },
};

const navByRole: Record<Role, Screen[]> = {
    Staf: [
        'overview',
        'apply',
        'requests',
        'team',
        'notifications',
        'coverage',
    ],
    'Pengurus Unit': [
        'overview',
        'approvals',
        'team',
        'apply',
        'requests',
        'notifications',
        'coverage',
    ],
    'Pengurus Besar': [
        'overview',
        'approvals',
        'changes',
        'team',
        'apply',
        'requests',
        'notifications',
        'coverage',
    ],
    'Setiausaha ALK': ['overview', 'approvals', 'notifications', 'coverage'],
    HR: [
        'overview',
        'staff',
        'calendar',
        'balances',
        'year-end',
        'prorata',
        'changes',
        'reports',
        'audit',
        'notifications',
        'coverage',
    ],
    'Pentadbir IT': ['overview', 'coverage'],
};

const icons: Partial<Record<Screen, Component>> = {
    overview: LayoutDashboard,
    apply: ClipboardList,
    requests: FileText,
    detail: FileClock,
    team: UsersRound,
    approvals: ListChecks,
    calendar: CalendarDays,
    staff: UsersRound,
    balances: WalletCards,
    'year-end': Clock3,
    prorata: Gauge,
    changes: SlidersHorizontal,
    reports: FileText,
    audit: ShieldCheck,
    coverage: Info,
};

const activeTitle = computed(() => screens[screen.value].label);
const visibleNav = computed(() => navByRole[role.value]);
const isHr = computed(() => role.value === 'HR');
const isApprover = computed(() =>
    ['Pengurus Unit', 'Pengurus Besar', 'Setiausaha ALK'].includes(role.value),
);
const canActOnRequest = computed(
    () =>
        isApprover.value &&
        !(
            role.value === 'Pengurus Besar' &&
            requestDetail.value.role === 'Pengurus Besar'
        ),
);
const isSupportStage = computed(
    () =>
        role.value === 'Pengurus Unit' ||
        (role.value === 'Pengurus Besar' &&
            Number.parseFloat(requestDetail.value.days) > 5),
);
const primaryActionLabel = computed(() => {
    if (role.value === 'Pengurus Unit') return 'Sokongan Pengurus Unit';
    if (role.value === 'Pengurus Besar' && isSupportStage.value)
        return 'Sokongan Pengurus Besar';
    return 'Keputusan akhir';
});
const requestRows = [
    {
        id: 'AL-2026-052',
        name: 'Nadia Contoh',
        type: 'Cuti Tahunan',
        dates: '14–16 Dis 2026',
        days: '3 hari',
        status: 'Menunggu Sokongan Pengurus Unit',
        unit: 'Operasi',
        role: 'Staf',
    },
    {
        id: 'AL-2026-049',
        name: 'Iman Contoh',
        type: 'Cuti Tahunan',
        dates: '29 Dis 2026 – 7 Jan 2027',
        days: '6 hari',
        status: 'Menunggu Sokongan Pengurus Besar',
        unit: 'Operasi',
        role: 'Staf',
    },
    {
        id: 'EL-2026-021',
        name: 'Rafi Contoh',
        type: 'Emergency Leave',
        dates: '21 Nov 2026',
        days: '1 hari',
        status: 'Menunggu Kelulusan Pengurus Besar',
        unit: 'Kewangan',
        role: 'Pengurus Unit',
    },
    {
        id: 'AL-2026-044',
        name: 'Nadia Contoh',
        type: 'Cuti Tahunan',
        dates: '29 Dis 2026 – 7 Jan 2027',
        days: '6 hari',
        status: 'Menunggu Kelulusan Setiausaha ALK',
        unit: 'Operasi',
        role: 'Staf',
    },
    {
        id: 'AL-2026-047',
        name: 'Rafi Contoh',
        type: 'Cuti Tahunan',
        dates: '9–11 Dis 2026',
        days: '3 hari',
        status: 'Menunggu Kelulusan Setiausaha ALK',
        unit: 'Kewangan',
        role: 'Pengurus Besar',
    },
    {
        id: 'AL-2026-041',
        name: 'Nadia Contoh',
        type: 'Cuti Tahunan',
        dates: '22 Okt 2026',
        days: '1 hari',
        status: 'Diluluskan',
        unit: 'Operasi',
        role: 'Staf',
    },
];

const displayedRequests = computed(() => {
    const query = search.value.trim().toLocaleLowerCase('ms-MY');
    let rows = requestRows;
    if (role.value === 'Staf')
        rows = rows.filter((row) => row.name === 'Nadia Contoh');
    if (role.value === 'Pengurus Unit')
        rows = rows.filter((row) => row.unit === 'Operasi');
    if (role.value === 'Setiausaha ALK') {
        rows = rows.filter(
            (row) => row.role === 'Pengurus Besar' || row.days === '6 hari',
        );
    }
    if (activeFilter.value !== 'Semua') {
        rows = rows.filter((row) => row.status.includes(activeFilter.value));
    }
    if (query) {
        rows = rows.filter((row) =>
            `${row.name} ${row.id} ${row.type} ${row.status}`
                .toLocaleLowerCase('ms-MY')
                .includes(query),
        );
    }
    return rows;
});

const dayEstimate = computed(() => {
    const from = new Date(`${startDate.value}T12:00:00`);
    const to = new Date(`${endDate.value}T12:00:00`);
    if (Number.isNaN(from.valueOf()) || Number.isNaN(to.valueOf()) || to < from)
        return '—';
    const holidays = new Set(['2026-12-24', '2026-12-25']);
    let days = 0;
    for (
        const day = new Date(from);
        day <= to;
        day.setDate(day.getDate() + 1)
    ) {
        const key = `${day.getFullYear()}-${String(day.getMonth() + 1).padStart(2, '0')}-${String(day.getDate()).padStart(2, '0')}`;
        if (day.getDay() !== 0 && day.getDay() !== 6 && !holidays.has(key)) days += 1;
    }
    return String(leaveLength.value === 'Separuh hari' ? (days > 0 ? 0.5 : 0) : days);
});

const previewBalance = { annual: 16.5, tabung: 5, pending: 2 };
const annualAvailable = previewBalance.annual - previewBalance.pending;
const totalAvailable = annualAvailable + previewBalance.tabung;
const projectedBalance = computed(() => {
    const days = Number(dayEstimate.value);
    if (!Number.isFinite(days) || days <= 0) return null;
    const tabungUsed = Math.min(previewBalance.tabung, Math.max(0, days - annualAvailable));
    return {
        annual: annualAvailable - (days - tabungUsed),
        tabung: previewBalance.tabung - tabungUsed,
        total: totalAvailable - days,
    };
});

const routePreview = computed(() => {
    const days = Number(dayEstimate.value);
    if (!Number.isFinite(days) || days <= 0) return [];
    if (role.value === 'Pengurus Besar')
        return ['Setiausaha ALK · keputusan akhir'];
    if (role.value === 'Pengurus Unit')
        return days > 5
            ? ['Pengurus Besar · sokongan', 'Setiausaha ALK · keputusan akhir']
            : ['Pengurus Besar · keputusan akhir'];
    if (days > 5)
        return [
            'Pengurus Unit · sokongan',
            'Pengurus Besar · sokongan',
            'Setiausaha ALK · keputusan akhir',
        ];
    return ['Pengurus Unit · sokongan', 'Pengurus Besar · keputusan akhir'];
});

const coverage = [
    {
        ids: 'FR-01–03',
        title: 'Profil, kelayakan & peruntukan',
        screen: 'staff' as Screen,
    },
    {
        ids: 'FR-04–05',
        title: 'Kalendar Johor & cuti ganti',
        screen: 'calendar' as Screen,
    },
    {
        ids: 'FR-06, 08–12, 15, 21',
        title: 'Tahun cuti, baki, tempahan & Tabung Cuti',
        screen: 'balances' as Screen,
    },
    {
        ids: 'FR-07, 19–20, 22–23, 29',
        title: 'Peranan, dashboard, sokongan & kelulusan',
        screen: 'approvals' as Screen,
    },
    {
        ids: 'FR-13–14',
        title: 'Prorata & lebihan penggunaan',
        screen: 'prorata' as Screen,
    },
    {
        ids: 'FR-16–18',
        title: 'Permohonan AL/EL & tempoh notis',
        screen: 'apply' as Screen,
    },
    {
        ids: 'FR-24–27',
        title: 'Pindaan, pembatalan & pemulangan sumber',
        screen: 'changes' as Screen,
    },
    {
        ids: 'BR-01–06, 20–23',
        title: 'Tahun kalendar, Johor, hari bekerja, tempoh notis & sesi cuti',
        screen: 'apply' as Screen,
    },
    {
        ids: 'BR-07–12, 38–39',
        title: 'Jadual kelayakan, kadar, prorata & semakan HR',
        screen: 'staff' as Screen,
    },
    {
        ids: 'BR-13–19',
        title: 'Carry forward, had Tabung Cuti, burn & sumber baki',
        screen: 'year-end' as Screen,
    },
    {
        ids: 'BR-24–29',
        title: 'Matriks sokongan/kelulusan, notifikasi & peranan ALK',
        screen: 'approvals' as Screen,
    },
    {
        ids: 'BR-30–37',
        title: 'Pindaan, pembatalan, keselamatan, audit & integriti',
        screen: 'changes' as Screen,
    },
    {
        ids: 'FR-28',
        title: 'Notifikasi status permohonan dan tindakan',
        screen: 'notifications' as Screen,
    },
    {
        ids: 'FR-30; RR-01–08',
        title: 'Laporan kelayakan, baki, status dan prorata',
        screen: 'reports' as Screen,
    },
    {
        ids: 'FR-31–32; NFR-01–13',
        title: 'Jejak audit, konsistensi dan integriti transaksi',
        screen: 'audit' as Screen,
    },
    {
        ids: 'RR-01–03, RR-08',
        title: 'Penyata baki AL/Tabung Cuti dan sejarah sumber transaksi',
        screen: 'balances' as Screen,
    },
    {
        ids: 'RR-04–05, RR-07',
        title: 'Prorata, status permohonan dan dashboard pengurusan',
        screen: 'reports' as Screen,
    },
    {
        ids: 'RR-06',
        title: 'Audit trail pengguna, perubahan status dan baki',
        screen: 'audit' as Screen,
    },
    {
        ids: 'CT-DEC-001–056',
        title: 'Keputusan Cuti Tahunan untuk reka bentuk',
        screen: 'coverage' as Screen,
    },
];

const qualityCoverage = [
    ['NFR-01–03', 'Kawalan peranan, rekod audit, baki AL/Tabung berasingan'],
    [
        'NFR-04, 07, 09',
        'Kebolehpercayaan, availability dan backup/recovery ialah kawalan platform; tiada fungsi backend diprototaip',
    ],
    [
        'NFR-05–06',
        'Paparan responsif, label jelas dan interaksi prototaip setempat',
    ],
    [
        'NFR-08',
        'Kelayakan, kalendar, hierarki dan had dipersembahkan sebagai data/config HR',
    ],
    [
        'NFR-10–11',
        'Peta traceability; rekod masa contoh dalam zon Malaysia (GMT+8)',
    ],
    [
        'NFR-12–13',
        'Validasi semula, pencegahan transaksi serentak/berganda dan idempotensi ialah prinsip backend, bukan tindakan UI',
    ],
];

const decisionGroups = [
    [
        'CT-DEC-001–009',
        'Hierarki kelulusan, baki tidak cukup dan permohonan kecemasan',
    ],
    ['CT-DEC-010–020', 'Kalendar, tempahan, had dan penggunaan baki'],
    ['CT-DEC-021–032', 'Status pending, pembetulan, EL dan pertukaran unit'],
    ['CT-DEC-033–040', 'Perubahan kelayakan, carry forward dan pelarasan HR'],
    ['CT-DEC-041–048', 'Alasan/lampiran, jadual kerja dan skop akses'],
    ['CT-DEC-049–056', 'Privasi, peranan pemohon, amaran unit dan notifikasi'],
];

const requestDetail = computed(
    () =>
        requestRows.find((row) => row.id === selectedRequest.value) ??
        requestRows[0],
);
const notificationItems = computed(() => {
    if (role.value === 'Staf') {
        return [
            {
                title: 'Permohonan memerlukan tindakan',
                body: 'AL-2026-052 · Menunggu sokongan Pengurus Unit.',
                time: 'Hari ini · 09:12',
                type: 'Status saya',
            },
            {
                title: 'Keputusan permohonan',
                body: 'AL-2026-041 · Diluluskan.',
                time: '22 Okt 2026 · 14:26',
                type: 'Status saya',
            },
        ];
    }
    if (role.value === 'Pengurus Unit') {
        return [
            {
                title: 'Permohonan staf menunggu sokongan',
                body: 'AL-2026-052 · Dalam skop Unit Operasi.',
                time: 'Hari ini · 09:12',
                type: 'Tindakan unit',
            },
        ];
    }
    if (role.value === 'Pengurus Besar') {
        return [
            {
                title: 'Permohonan menunggu keputusan',
                body: 'EL-2026-021 · Menunggu kelulusan Pengurus Besar.',
                time: 'Hari ini · 09:12',
                type: 'Tindakan',
            },
        ];
    }
    if (role.value === 'Setiausaha ALK') {
        return [
            {
                title: 'Permohonan memerlukan keputusan akhir',
                body: 'AL-2026-044 · Sokongan terdahulu tersedia.',
                time: 'Hari ini · 09:12',
                type: 'Keputusan akhir',
            },
        ];
    }
    return [
        {
            title: 'Permohonan terjejas oleh kalendar',
            body: 'Semakan HR diperlukan sebelum pelarasan baki dipertimbangkan.',
            time: '23 Nov 2026 · 10:14',
            type: 'Semakan HR',
        },
        {
            title: 'Semakan prorata',
            body: '2 kes contoh menunggu semakan HR.',
            time: '22 Nov 2026 · 15:32',
            type: 'Semakan HR',
        },
    ];
});
const workflowSteps = computed(() => {
    if (requestDetail.value.role === 'Pengurus Besar') {
        return [
            'Permohonan Pengurus Besar dihantar',
            'Keputusan akhir Setiausaha ALK',
        ];
    }
    if (Number.parseFloat(requestDetail.value.days) > 5) {
        return [
            'Permohonan dihantar oleh staf',
            'Sokongan Pengurus Unit',
            'Sokongan Pengurus Besar',
            'Keputusan akhir Setiausaha ALK',
        ];
    }
    return [
        'Permohonan dihantar oleh staf',
        'Sokongan Pengurus Unit',
        'Keputusan akhir Pengurus Besar',
    ];
});

function navigate(target: Screen) {
    if (target === 'detail' && !selectedRequest.value) return;
    screen.value = target;
}

function changeRole(value: string) {
    role.value = value as Role;
    if (!navByRole[role.value].includes(screen.value))
        screen.value = 'overview';
    activeFilter.value = 'Semua';
}

function openRequest(id: string) {
    selectedRequest.value = id;
    screen.value = 'detail';
}

function showPreviewMessage(message: string) {
    toast.value = message;
    window.setTimeout(() => {
        toast.value = '';
    }, 3200);
}

function previewSubmit() {
    if (!projectedBalance.value) {
        showPreviewMessage('Pilih tarikh dan tempoh cuti bekerja yang sah.');
        return;
    }
    const requiredReason = leaveType.value === 'EL';
    if (requiredReason && !reason.value.trim()) {
        showPreviewMessage(
            'Isi alasan kecemasan untuk melihat pratonton semakan.',
        );
        return;
    }
    showPreviewMessage(
        'Pratonton sahaja — permohonan tidak dihantar atau disimpan.',
    );
}

function previewAction(action: string) {
    showPreviewMessage(
        `Pratonton sahaja — tindakan “${action}” tidak direkodkan.`,
    );
}

function statusTone(status: string) {
    if (status.includes('Diluluskan'))
        return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
    if (status.includes('Ditolak') || status.includes('Batal'))
        return 'bg-rose-500/10 text-rose-700 dark:text-rose-300';
    if (status.includes('Menunggu'))
        return 'bg-amber-500/10 text-amber-800 dark:text-amber-300';
    return 'bg-muted text-muted-foreground';
}
const MetricCard = defineComponent({
    name: 'MetricCard',
    props: { label: String, value: String, note: String, icon: String },
    setup(props) {
        const icons: Record<string, unknown> = {
            users: ListChecks,
            year: Clock3,
            clock: Clock3,
            calendar: CalendarDays,
            list: ListChecks,
            alert: CircleAlert,
            file: ArrowRight,
            wallet: WalletCards,
            check: Check,
            gauge: Gauge,
            landmark: Landmark,
        };
        return () =>
            h(
                'article',
                {
                    class: 'rounded-2xl border border-border bg-card p-5 shadow-sm',
                },
                [
                    h(
                        'div',
                        { class: 'flex items-start justify-between gap-3' },
                        [
                            h(
                                'p',
                                { class: 'text-sm text-muted-foreground' },
                                props.label,
                            ),
                            h(
                                'span',
                                {
                                    class: 'flex size-9 items-center justify-center rounded-lg bg-primary/10 text-primary',
                                },
                                [
                                    h(icons[props.icon ?? 'file'] as never, {
                                        class: 'size-4',
                                        'aria-hidden': 'true',
                                    }),
                                ],
                            ),
                        ],
                    ),
                    h(
                        'p',
                        { class: 'mt-3 text-2xl font-semibold tracking-tight' },
                        props.value,
                    ),
                    h(
                        'p',
                        { class: 'mt-1 text-xs text-muted-foreground' },
                        props.note,
                    ),
                ],
            );
    },
});

const SummaryRow = defineComponent({
    name: 'SummaryRow',
    props: { label: String, value: String },
    setup(props) {
        return () =>
            h(
                'div',
                {
                    class: 'flex items-start justify-between gap-4 border-b border-border/70 pb-2 last:border-0 last:pb-0',
                },
                [
                    h('span', { class: 'text-muted-foreground' }, props.label),
                    h(
                        'strong',
                        { class: 'text-right font-medium' },
                        props.value,
                    ),
                ],
            );
    },
});

const ProgressRow = defineComponent({
    name: 'ProgressRow',
    props: { label: String, value: String, percent: String },
    setup(props) {
        return () =>
            h('div', { class: 'space-y-2' }, [
                h(
                    'div',
                    {
                        class: 'flex items-center justify-between gap-3 text-sm',
                    },
                    [
                        h('span', { class: 'font-medium' }, props.label),
                        h(
                            'span',
                            { class: 'text-xs text-muted-foreground' },
                            props.value,
                        ),
                    ],
                ),
                h(
                    'div',
                    { class: 'h-2 overflow-hidden rounded-full bg-muted' },
                    [
                        h('div', {
                            class: 'h-full rounded-full bg-primary',
                            style: { width: props.percent },
                        }),
                    ],
                ),
            ]);
    },
});

const Shortcut = defineComponent({
    name: 'Shortcut',
    props: { title: String, detail: String },
    emits: ['click'],
    setup(props, { emit }) {
        return () =>
            h(
                'button',
                {
                    type: 'button',
                    class: 'group min-h-24 rounded-xl border border-border p-4 text-left hover:border-primary/40 hover:bg-muted/30 focus-visible:ring-2 focus-visible:ring-ring',
                    onClick: () => emit('click'),
                },
                [
                    h('span', { class: 'text-sm font-semibold' }, props.title),
                    h(
                        'span',
                        {
                            class: 'mt-1 block text-xs leading-5 text-muted-foreground',
                        },
                        props.detail,
                    ),
                    h(
                        'span',
                        {
                            class: 'mt-2 block text-xs font-semibold text-primary group-hover:underline',
                        },
                        'Buka paparan →',
                    ),
                ],
            );
    },
});

const RequestTable = defineComponent({
    name: 'RequestTable',
    props: {
        rows: { type: Array, default: () => [] },
        tone: { type: Function, required: true },
        compact: Boolean,
    },
    emits: ['open'],
    setup(props, { emit }) {
        return () => {
            const rows = props.rows as Array<{
                id: string;
                name: string;
                type: string;
                dates: string;
                days: string;
                status: string;
                unit: string;
            }>;
            const tone = props.tone as (status: string) => string;
            const requestItems = rows.map((row) =>
                h(
                    'article',
                    {
                        key: row.id,
                        class: 'flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between',
                    },
                    [
                        h('div', { class: 'min-w-0' }, [
                            h(
                                'div',
                                { class: 'flex flex-wrap items-center gap-2' },
                                [
                                    h('strong', { class: 'text-sm' }, row.name),
                                    h(
                                        'span',
                                        {
                                            class: 'font-mono text-[11px] text-muted-foreground',
                                        },
                                        row.id,
                                    ),
                                ],
                            ),
                            h(
                                'p',
                                { class: 'mt-1 text-sm' },
                                `${row.type} · ${row.days}`,
                            ),
                            h(
                                'p',
                                { class: 'mt-1 text-xs text-muted-foreground' },
                                `${row.dates} · ${row.unit}`,
                            ),
                        ]),
                        h(
                            'div',
                            {
                                class: 'flex items-center justify-between gap-3 sm:justify-end',
                            },
                            [
                                h(
                                    'span',
                                    {
                                        class: `rounded-full px-2.5 py-1 text-[11px] font-semibold ${tone(row.status)}`,
                                    },
                                    row.status,
                                ),
                                h(
                                    'button',
                                    {
                                        type: 'button',
                                        class: 'min-h-9 rounded-lg border border-border px-3 text-xs font-semibold hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring',
                                        onClick: () => emit('open', row.id),
                                    },
                                    'Butiran',
                                ),
                            ],
                        ),
                    ],
                ),
            );

            return h(
                'section',
                {
                    class: 'overflow-hidden rounded-2xl border border-border bg-card shadow-sm',
                },
                [
                    h(
                        'div',
                        {
                            class: 'flex items-center justify-between gap-3 border-b border-border px-5 py-4',
                        },
                        [
                            h('div', [
                                h(
                                    'h2',
                                    { class: 'font-semibold' },
                                    props.compact
                                        ? 'Permohonan terkini'
                                        : 'Senarai permohonan',
                                ),
                                h(
                                    'p',
                                    {
                                        class: 'mt-1 text-xs text-muted-foreground',
                                    },
                                    'Nama, tempoh, status dan konteks tindakan',
                                ),
                            ]),
                            h(
                                'span',
                                {
                                    class: 'rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground',
                                },
                                `${rows.length} rekod`,
                            ),
                        ],
                    ),
                    rows.length
                        ? h(
                              'div',
                              { class: 'divide-y divide-border' },
                              requestItems,
                          )
                        : h('div', { class: 'px-5 py-12 text-center' }, [
                              h(
                                  'p',
                                  { class: 'font-medium' },
                                  'Tiada permohonan untuk penapis ini',
                              ),
                              h(
                                  'p',
                                  {
                                      class: 'mt-1 text-sm text-muted-foreground',
                                  },
                                  'Tukar penapis atau kata carian.',
                              ),
                          ]),
                ],
            );
        };
    },
});

const MiniCalendar = defineComponent({
    name: 'MiniCalendar',
    props: { team: Boolean },
    setup(props) {
        const labels = ['Isn', 'Sel', 'Rab', 'Kha', 'Jum', 'Sab', 'Aha'];
        const days: Array<number | null> = [
            null,
            ...Array.from({ length: 31 }, (_, i) => i + 1),
        ];
        return () =>
            h('div', { class: 'mt-5 grid grid-cols-7 gap-1.5 text-center' }, [
                ...labels.map((label) =>
                    h(
                        'span',
                        {
                            class: 'py-2 text-[10px] font-semibold text-muted-foreground',
                            key: label,
                        },
                        label,
                    ),
                ),
                ...days.map((day, index) =>
                    h(
                        'span',
                        {
                            key: `d-${index}`,
                            class: [
                                'flex min-h-9 items-center justify-center rounded-lg text-xs',
                                day ? 'text-foreground' : '',
                                day === 15
                                    ? 'bg-primary font-semibold text-primary-foreground'
                                    : '',
                                day === 25
                                    ? 'bg-amber-500/10 font-semibold text-amber-800 dark:text-amber-300'
                                    : '',
                                props.team && [14, 16, 29].includes(day ?? 0)
                                    ? 'ring-1 ring-emerald-500/50'
                                    : '',
                            ].join(' '),
                        },
                        day ? String(day) : '',
                    ),
                ),
            ]);
    },
});

const UnitSchedule = defineComponent({
    name: 'UnitSchedule',
    props: { limited: Boolean },
    setup(props) {
        return () =>
            h(
                'div',
                { class: 'mt-4 divide-y divide-border' },
                [
                    {
                        name: 'Nadia Contoh',
                        date: '14–16 Dis',
                        type: 'AL',
                        status: 'Diluluskan',
                    },
                    {
                        name: 'Iman Contoh',
                        date: '29–30 Dis',
                        type: 'AL',
                        status: 'Diluluskan',
                    },
                    {
                        name: 'Rafi Contoh',
                        date: '21 Nov',
                        type: 'EL',
                        status: 'Menunggu',
                    },
                ]
                    .filter(
                        (row) => !props.limited || row.status === 'Diluluskan',
                    )
                    .map((row) =>
                        h(
                            'div',
                            {
                                key: row.name,
                                class: 'flex items-center justify-between gap-3 py-3 text-sm',
                            },
                            [
                                h('div', [
                                    h('p', { class: 'font-medium' }, row.name),
                                    h(
                                        'p',
                                        {
                                            class: 'text-xs text-muted-foreground',
                                        },
                                        `${row.date}${props.limited ? '' : ` · ${row.type}`}`,
                                    ),
                                ]),
                                h(
                                    'span',
                                    {
                                        class: `rounded-full px-2 py-1 text-[10px] ${row.status === 'Diluluskan' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-amber-500/10 text-amber-800 dark:text-amber-300'}`,
                                    },
                                    row.status,
                                ),
                            ],
                        ),
                    ),
            );
    },
});

const CalendarRow = defineComponent({
    name: 'CalendarRow',
    props: { day: String, title: String, tag: String },
    setup(props) {
        return () =>
            h(
                'div',
                {
                    class: 'flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border px-4 py-3 text-sm',
                },
                [
                    h('div', [
                        h(
                            'p',
                            { class: 'text-xs text-muted-foreground' },
                            props.day,
                        ),
                        h('p', { class: 'mt-1 font-medium' }, props.title),
                    ]),
                    h(
                        'span',
                        {
                            class: 'rounded-full bg-muted px-2.5 py-1 text-[10px] text-muted-foreground',
                        },
                        props.tag,
                    ),
                ],
            );
    },
});
</script>

<template>
    <div
        class="mx-auto flex w-full min-w-0 max-w-7xl flex-col bg-background text-foreground"
    >
        <header
            class="flex flex-col gap-3 border-b border-border bg-card px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
        >
            <div class="flex min-w-0 items-center gap-3">
                <div class="min-w-0">
                    <p
                        class="truncate text-xs font-semibold tracking-[0.12em] text-primary uppercase"
                    >
                        KUTMB · MOD-005
                    </p>
                    <p class="truncate text-sm font-semibold">
                        Cuti Tahunan
                    </p>
                </div>
            </div>
            <div class="flex min-w-0 flex-wrap items-center gap-3">
                <label
                    for="preview-role"
                    class="text-xs font-medium text-muted-foreground"
                    >Simulasi peranan</label
                >
                <select
                    id="preview-role"
                    :value="role"
                    class="h-10 max-w-44 rounded-lg border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                    @change="
                        changeRole(($event.target as HTMLSelectElement).value)
                    "
                >
                    <option v-for="item in roles" :key="item" :value="item">
                        {{ item }}
                    </option>
                </select>
            </div>
        </header>

        <div
            class="border-b border-primary/15 bg-primary/[0.045] px-4 py-2.5 text-xs leading-5 text-muted-foreground sm:px-6"
        >
            <span
                class="mr-2 inline-flex items-center gap-1.5 font-semibold text-primary"
                ><Info class="size-3.5" aria-hidden="true" />REKA BENTUK
                SAHAJA</span
            >
            Semakan reka bentuk admin dalam persekitaran pembangunan sahaja.
            Simulasi peranan bukan kebenaran akaun. Semua rekod dan baki adalah
            contoh sintetik; tiada permohonan, kelulusan atau pelarasan sebenar.
        </div>

        <nav
            class="flex flex-wrap gap-1.5 border-b border-border bg-card px-4 py-3 sm:px-6"
            aria-label="Submodul Cuti Tahunan (simulasi)"
        >
            <button
                v-for="item in visibleNav"
                :key="item"
                type="button"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring motion-reduce:transition-none"
                :class="
                    screen === item
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                "
                :aria-current="screen === item ? 'page' : undefined"
                @click="navigate(item)"
            >
                <component
                    :is="icons[item] ?? FileText"
                    class="size-4 shrink-0"
                    aria-hidden="true"
                />
                <span>{{ screens[item].label }}</span>
            </button>
        </nav>

        <section class="min-w-0" aria-labelledby="page-heading">
                <div
                    class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8 lg:py-8"
                >
                    <header
                        class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between"
                    >
                        <div class="space-y-1.5">
                            <p
                                class="text-xs font-semibold tracking-[0.14em] text-primary uppercase"
                            >
                                {{ screens[screen].eyebrow }}
                            </p>
                            <h1
                                id="page-heading"
                                class="text-2xl font-semibold tracking-tight sm:text-3xl"
                            >
                                {{
                                    screen === 'overview'
                                        ? role === 'Staf'
                                            ? 'Ringkasan Cuti Tahunan'
                                            : role === 'HR'
                                              ? 'Ringkasan pengurusan cuti'
                                              : role === 'Setiausaha ALK'
                                                ? 'Giliran kelulusan akhir'
                                                : role === 'Pentadbir IT'
                                                  ? 'Akses teknikal sahaja'
                                                  : `Ruang kerja ${role}`
                                        : activeTitle
                                }}
                            </h1>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    screen === 'overview'
                                        ? role === 'Staf'
                                            ? 'Semak kelayakan, tempahan baki dan tindakan cuti anda dalam satu paparan.'
                                            : role === 'HR'
                                              ? 'Pantau kelayakan, baki, carry forward dan semakan yang memerlukan perhatian.'
                                              : role === 'Setiausaha ALK'
                                                ? 'Semua permohonan GM dan permohonan melebihi lima hari yang memerlukan keputusan anda.'
                                                : role === 'Pentadbir IT'
                                                  ? 'Peranan teknikal tidak melihat atau memutuskan permohonan Cuti Tahunan.'
                                                  : 'Semak tindakan dan maklumat cuti dalam skop peranan anda.'
                                        : 'Semua kandungan di sini ialah pratonton reka bentuk. Tindakan sebenar bergantung pada kebenaran dan workflow yang diluluskan.'
                                }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-if="
                                    [
                                        'Staf',
                                        'Pengurus Unit',
                                        'Pengurus Besar',
                                    ].includes(role) && screen === 'overview'
                                "
                                type="button"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                @click="navigate('apply')"
                            >
                                <ClipboardList
                                    class="size-4"
                                    aria-hidden="true"
                                />
                                Mohon cuti
                            </button>
                            <button
                                v-if="screen === 'overview' && isApprover"
                                type="button"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-border bg-card px-4 text-sm font-semibold hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                                @click="navigate('approvals')"
                            >
                                <ListChecks class="size-4" aria-hidden="true" />
                                Semak giliran
                            </button>
                            <button
                                v-if="screen === 'overview' && isHr"
                                type="button"
                                class="inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                                @click="navigate('staff')"
                            >
                                <Search class="size-4" aria-hidden="true" />
                                Cari staf
                            </button>
                        </div>
                    </header>

                    <template v-if="screen === 'overview'">
                        <section
                            v-if="role === 'Pentadbir IT'"
                            class="rounded-2xl border border-border bg-card p-6 shadow-sm sm:p-8"
                        >
                            <div
                                class="flex size-12 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="size-6"
                                    aria-hidden="true"
                                />
                            </div>
                            <h2 class="mt-5 text-lg font-semibold">
                                MOD-005 tidak dipaparkan untuk peranan ini
                            </h2>
                            <p
                                class="mt-2 max-w-2xl text-sm leading-6 text-muted-foreground"
                            >
                                Pentadbir IT mengurus akses teknikal melalui
                                System Administration. Paparan permohonan, baki,
                                sokongan, kelulusan, pindaan dan dokumen cuti
                                tidak termasuk dalam ruang ini.
                            </p>
                        </section>

                        <template v-else-if="role === 'Setiausaha ALK'">
                            <div
                                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <article
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        Menunggu keputusan akhir
                                    </p>
                                    <p class="mt-3 text-3xl font-semibold">
                                        2
                                        <span
                                            class="text-sm font-medium text-muted-foreground"
                                            >permohonan</span
                                        >
                                    </p>
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Semua permohonan GM dan AL lebih lima
                                        hari
                                    </p>
                                </article>
                                <article
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        Konteks sokongan tersedia
                                    </p>
                                    <p class="mt-3 text-3xl font-semibold">
                                        3
                                        <span
                                            class="text-sm font-medium text-muted-foreground"
                                            >peringkat</span
                                        >
                                    </p>
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Paparan terdahulu bagi permohonan yang
                                        berkaitan
                                    </p>
                                </article>
                                <article
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:col-span-2 xl:col-span-1"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        Skop akses
                                    </p>
                                    <p class="mt-3 text-lg font-semibold">
                                        Keputusan akhir sahaja
                                    </p>
                                    <p
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Tiada permohonan sendiri atau baki
                                        peribadi
                                    </p>
                                </article>
                            </div>
                            <RequestTable
                                :rows="displayedRequests"
                                :tone="statusTone"
                                @open="openRequest"
                            />
                            <p
                                class="flex items-start gap-2 rounded-xl border border-border bg-muted/35 p-4 text-sm leading-6 text-muted-foreground"
                            >
                                <Info
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />Keputusan bagi semua permohonan Pengurus Besar
                                dirutekan kepada Setiausaha ALK mengikut
                                CT-DEC-001. Pengerusi ALK bukan pelulus akhir
                                dalam baseline reka bentuk ini.
                            </p>
                        </template>

                        <template v-else-if="role === 'HR'">
                            <div
                                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <MetricCard
                                    label="Profil dalam pratonton"
                                    value="128"
                                    note="Data contoh sahaja"
                                    icon="users"
                                />
                                <MetricCard
                                    label="Carry forward"
                                    value="12"
                                    note="Perlu semakan HR"
                                    icon="year"
                                />
                                <MetricCard
                                    label="Semakan prorata"
                                    value="2"
                                    note="Belum disahkan"
                                    icon="clock"
                                />
                                <MetricCard
                                    label="Kesan kalendar"
                                    value="3"
                                    note="Menunggu semakan"
                                    icon="calendar"
                                />
                            </div>
                            <section
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            Pintasan kerja HR
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Data kelayakan, baki, kalendar dan
                                            audit mengikut skop HR.
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary"
                                        >Paparan sintetik</span
                                    >
                                </div>
                                <div
                                    class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                                >
                                    <Shortcut
                                        title="Profil & kelayakan"
                                        detail="Kategori, tahun dan peruntukan"
                                        @click="navigate('staff')"
                                    />
                                    <Shortcut
                                        title="Kalendar cuti umum"
                                        detail="Cuti Johor dan cadangan cuti ganti"
                                        @click="navigate('calendar')"
                                    />
                                    <Shortcut
                                        title="Lejar & tutup tahun"
                                        detail="AL, Tabung Cuti dan carry forward"
                                        @click="navigate('balances')"
                                    />
                                    <Shortcut
                                        title="Laporan & jejak audit"
                                        detail="Transaksi dan sejarah tindakan"
                                        @click="navigate('reports')"
                                    />
                                </div>
                            </section>
                            <RequestTable
                                :rows="displayedRequests"
                                :tone="statusTone"
                                @open="openRequest"
                            />
                        </template>

                        <template
                            v-else-if="
                                role === 'Pengurus Unit' ||
                                role === 'Pengurus Besar'
                            "
                        >
                            <div
                                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
                            >
                                <MetricCard
                                    label="Menunggu tindakan"
                                    :value="
                                        role === 'Pengurus Unit' ? '3' : '2'
                                    "
                                    note="Dalam skop contoh"
                                    icon="list"
                                />
                                <MetricCard
                                    label="Cuti diluluskan bulan ini"
                                    value="8"
                                    note="Hari staf · contoh"
                                    icon="calendar"
                                />
                                <MetricCard
                                    label="Semakan operasi"
                                    value="2"
                                    note="Tarikh bertindih"
                                    icon="alert"
                                />
                                <MetricCard
                                    v-if="role === 'Pengurus Besar'"
                                    label="Pindaan / pembatalan"
                                    value="1"
                                    note="Perlu tindakan GM"
                                    icon="file"
                                />
                                <MetricCard
                                    v-else
                                    label="Baki relevan pending"
                                    value="6.5"
                                    note="Bukan lejar transaksi"
                                    icon="wallet"
                                />
                            </div>
                            <section
                                class="grid gap-4 xl:grid-cols-[1.5fr_1fr]"
                            >
                                <RequestTable
                                    :rows="displayedRequests"
                                    :tone="statusTone"
                                    @open="openRequest"
                                />
                                <div
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <div class="flex items-center gap-2">
                                        <CalendarDays
                                            class="size-4 text-primary"
                                            aria-hidden="true"
                                        />
                                        <h2 class="font-semibold">
                                            Jadual unit
                                        </h2>
                                    </div>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Nama dan tarikh cuti yang relevan dalam
                                        skop seliaan.
                                    </p>
                                    <UnitSchedule :limited="false" /><button
                                        type="button"
                                        class="mt-4 text-sm font-semibold text-primary hover:underline"
                                        @click="navigate('team')"
                                    >
                                        Buka jadual unit
                                        <span aria-hidden="true">→</span>
                                    </button>
                                </div>
                            </section>
                        </template>

                        <template v-else>
                            <div
                                class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5"
                            >
                                <MetricCard
                                    label="Kelayakan tahun ini"
                                    value="20"
                                    note="hari · contoh"
                                    icon="wallet"
                                />
                                <MetricCard
                                    label="Digunakan"
                                    value="3.5"
                                    note="hari · tahun 2026"
                                    icon="check"
                                />
                                <MetricCard
                                    label="Tempahan pending"
                                    value="2"
                                    note="hari · baki sementara"
                                    icon="clock"
                                />
                                <MetricCard
                                    label="Baki tersedia"
                                    value="14.5"
                                    note="selepas tempahan"
                                    icon="gauge"
                                />
                                <MetricCard
                                    label="Tabung Cuti"
                                    value="5"
                                    note="asing daripada AL semasa"
                                    icon="landmark"
                                />
                            </div>
                            <section
                                class="grid gap-4 xl:grid-cols-[1.35fr_1fr]"
                            >
                                <div
                                    class="rounded-2xl border border-border bg-card shadow-sm"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3 border-b border-border p-5"
                                    >
                                        <div>
                                            <h2 class="font-semibold">
                                                Permohonan terkini
                                            </h2>
                                            <p
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                Status, tarikh dan tempahan
                                                baki.
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            class="text-sm font-semibold text-primary hover:underline"
                                            @click="navigate('requests')"
                                        >
                                            Lihat semua
                                        </button>
                                    </div>
                                    <RequestTable
                                        :rows="displayedRequests"
                                        compact
                                        :tone="statusTone"
                                        @open="openRequest"
                                    />
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div>
                                            <h2 class="font-semibold">
                                                Disember 2026
                                            </h2>
                                            <p
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                Kalendar cuti Johor · contoh
                                            </p>
                                        </div>
                                        <CalendarDays
                                            class="size-5 text-primary"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <MiniCalendar />
                                    <p
                                        class="mt-3 text-xs leading-5 text-muted-foreground"
                                    >
                                        Kiraan rasmi menggunakan kalendar cuti
                                        umum terkini yang diselenggara HR.
                                    </p>
                                </div>
                            </section>
                            <section class="grid gap-4 md:grid-cols-2">
                                <div
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <h2 class="font-semibold">
                                        Perjalanan kelayakan
                                    </h2>
                                    <div class="mt-4 space-y-3">
                                        <ProgressRow
                                            label="Cuti Tahunan semasa"
                                            value="14.5 hari tersedia"
                                            percent="72%"
                                        /><ProgressRow
                                            label="Tabung Cuti"
                                            value="5 hari"
                                            percent="35%"
                                        />
                                    </div>
                                    <p
                                        class="mt-4 text-xs text-muted-foreground"
                                    >
                                        Baki AL dan Tabung Cuti dipaparkan
                                        secara berasingan.
                                    </p>
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <h2 class="font-semibold">Makluman</h2>
                                    <div class="mt-4 flex gap-3 text-sm">
                                        <span
                                            class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-700"
                                            ><Bell
                                                class="size-4"
                                                aria-hidden="true"
                                        /></span>
                                        <p class="leading-6">
                                            Permohonan AL-2026-052 masih
                                            menunggu sokongan. Tempahan baki 2
                                            hari dikira dalam baki tersedia.
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </template>
                    </template>

                    <template v-else-if="screen === 'apply'">
                        <section aria-label="Baki kelayakan cuti" class="mb-5 space-y-3">
                            <div>
                                <h2 class="font-semibold">Baki kelayakan cuti</h2>
                                <p class="text-sm text-muted-foreground">Data contoh · AL dan EL berkongsi baki yang sama.</p>
                            </div>
                            <div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
                                <MetricCard label="Cuti Tahunan (AL)" :value="previewBalance.annual.toFixed(1)" note="hari · sebelum tempahan" icon="wallet" />
                                <MetricCard label="Tabung Cuti" :value="previewBalance.tabung.toFixed(1)" note="hari · baki berasingan" icon="landmark" />
                                <MetricCard label="Tempahan pending" :value="previewBalance.pending.toFixed(1)" note="hari · belum ditolak rasmi" icon="clock" />
                                <MetricCard label="Jumlah baki tersedia" :value="totalAvailable.toFixed(1)" note="hari · selepas tempahan" icon="gauge" />
                            </div>
                        </section>
                        <div
                            class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.75fr)]"
                        >
                            <form
                                class="space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-7"
                                @submit.prevent="previewSubmit"
                            >
                                <section class="space-y-4">
                                    <div>
                                        <h2 class="font-semibold">
                                            1. Jenis permohonan
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Pilih jenis cuti. EL menggunakan
                                            baki Cuti Tahunan yang sama.
                                        </p>
                                    </div>
                                    <label class="space-y-2 text-sm font-medium">
                                        Jenis cuti
                                        <select
                                            v-model="leaveType"
                                            class="block min-h-11 w-full rounded-lg border border-input bg-background px-3 text-sm"
                                        >
                                            <option value="AL">Cuti Tahunan (AL)</option>
                                            <option value="EL">Emergency Leave (EL)</option>
                                        </select>
                                        <span class="block text-xs font-normal text-muted-foreground">
                                            {{ leaveType === 'AL' ? 'Permohonan Cuti Tahunan biasa. Alasan pilihan.' : 'Alasan kecemasan wajib; dokumen sokongan pilihan.' }}
                                        </span>
                                    </label>
                                </section>
                                <section
                                    class="space-y-4 border-t border-border pt-5"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            2. Tarikh dan tempoh
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Pilih tempoh cuti untuk melihat
                                            anggaran reka bentuk.
                                        </p>
                                    </div>
                                    <div class="grid gap-4 sm:grid-cols-2">
                                        <label
                                            class="space-y-2 text-sm font-medium"
                                            >Tarikh mula<input
                                                v-model="startDate"
                                                type="date"
                                                class="block h-11 w-full rounded-lg border border-input bg-background px-3 text-sm" /></label
                                        ><label
                                            class="space-y-2 text-sm font-medium"
                                            >Tarikh akhir<input
                                                v-model="endDate"
                                                type="date"
                                                :min="startDate"
                                                :disabled="leaveLength === 'Separuh hari'"
                                                class="block h-11 w-full rounded-lg border border-input bg-background px-3 text-sm disabled:opacity-60"
                                        /></label>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label
                                            v-for="option in [
                                                'Penuh hari',
                                                'Separuh hari',
                                            ]"
                                            :key="option"
                                            class="flex cursor-pointer items-center gap-3 rounded-xl border p-3 text-sm"
                                            :class="
                                                leaveLength === option
                                                    ? 'border-primary bg-primary/[0.035]'
                                                    : 'border-border'
                                            "
                                            ><input
                                                v-model="leaveLength"
                                                type="radio"
                                                :value="option"
                                                class="accent-primary"
                                            /><span>{{ option }}</span></label
                                        >
                                    </div>
                                    <label
                                        v-if="leaveLength === 'Separuh hari'"
                                        class="space-y-2 text-sm font-medium"
                                        >Sesi<select
                                            v-model="session"
                                            class="block h-11 w-full rounded-lg border border-input bg-background px-3"
                                        >
                                            <option>Pagi</option>
                                            <option>Petang</option></select
                                        ><span
                                            class="block text-xs font-normal text-muted-foreground"
                                            >Pagi: cuti 8:00–13:00. Petang: cuti
                                            13:00–17:00.</span
                                        ></label
                                    >
                                    <div
                                        class="flex items-start gap-3 rounded-xl border border-primary/15 bg-primary/[0.035] p-4 text-sm"
                                    >
                                        <Info
                                            class="mt-0.5 size-4 shrink-0 text-primary"
                                            aria-hidden="true"
                                        /><span
                                            >Anggaran hari bekerja:
                                            <strong
                                                >{{ dayEstimate }} hari</strong
                                            >. Angka ini contoh UI; pengiraan
                                            rasmi menggunakan kalendar HR dan
                                            aturan hari bekerja yang berkuat
                                            kuasa.</span
                                        >
                                    </div>
                                </section>
                                <section
                                    class="space-y-4 border-t border-border pt-5"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            3. Maklumat tambahan
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Butiran ini dipaparkan hanya kepada
                                            pihak yang berkenaan dalam workflow.
                                        </p>
                                    </div>
                                    <label class="space-y-2 text-sm font-medium"
                                        >{{ leaveType === 'EL' ? 'Alasan kecemasan' : 'Alasan' }}
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >{{
                                                leaveType === 'EL'
                                                    ? '· wajib'
                                                    : '· pilihan'
                                            }}</span
                                        ><textarea
                                            v-model="reason"
                                            rows="3"
                                            :required="leaveType === 'EL'"
                                            maxlength="1000"
                                            :placeholder="
                                                leaveType === 'EL'
                                                    ? 'Nyatakan alasan kecemasan'
                                                    : 'Nyatakan alasan jika berkaitan'
                                            "
                                            class="block w-full resize-y rounded-lg border border-input bg-background px-3 py-2.5 text-sm"
                                        ></textarea></label
                                    ><label
                                        v-if="leaveType === 'EL'"
                                        class="space-y-2 text-sm font-medium"
                                        >Dokumen sokongan
                                        <span
                                            class="font-normal text-muted-foreground"
                                            >· pilihan</span
                                        ><input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png"
                                            class="block w-full rounded-lg border border-input bg-background px-3 py-2 text-sm"
                                        /><span
                                            class="block text-xs font-normal text-muted-foreground"
                                            >Pratonton sahaja. Fail tidak dimuat
                                            naik.</span
                                        ></label
                                    >
                                    <p
                                        v-if="leaveType === 'EL'"
                                        class="rounded-xl border border-amber-500/20 bg-amber-500/[0.07] p-4 text-sm leading-6"
                                    >
                                        <strong>EL retrospektif:</strong> had
                                        penghantaran ditentukan melalui
                                        konfigurasi HR. Bilangan hari dan kaedah
                                        kiraan masih perlu pengesahan HR; tiada
                                        had hardcoded dalam reka bentuk.
                                        Pemakluman awal kepada penyokong dibuat
                                        di luar sistem, contohnya WhatsApp, dan
                                        bukan kepada pelulus (CT-DEC-027/028).
                                    </p>
                                </section>
                                <div
                                    class="flex flex-wrap justify-end gap-2 border-t border-border pt-5"
                                >
                                    <button
                                        type="button"
                                        class="min-h-11 rounded-lg border border-border px-4 text-sm font-semibold hover:bg-muted"
                                        @click="previewAction('Simpan draf')"
                                    >
                                        Simpan draf (pratonton)</button
                                    ><button
                                        type="submit"
                                        class="inline-flex min-h-11 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90"
                                    >
                                        <ArrowRight
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Semak permohonan
                                    </button>
                                </div>
                            </form>
                            <aside
                                class="h-fit space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Ringkasan permohonan
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Pratonton perubahan medan.
                                    </p>
                                </div>
                                <div class="space-y-3 text-sm">
                                    <SummaryRow
                                        label="Jenis"
                                        :value="
                                            leaveType === 'EL'
                                                ? 'Emergency Leave'
                                                : 'Cuti Tahunan'
                                        "
                                    /><SummaryRow
                                        label="Tempoh"
                                        :value="`${dayEstimate} hari${leaveLength === 'Separuh hari' ? ` · sesi ${session.toLowerCase()}` : ''}`"
                                    /><SummaryRow
                                        label="Baki AL selepas tempahan"
                                        :value="`${annualAvailable.toFixed(1)} hari`"
                                    /><SummaryRow
                                        label="Tabung Cuti tersedia"
                                        :value="`${previewBalance.tabung.toFixed(1)} hari`"
                                    /><SummaryRow
                                        label="Anggaran AL selepas permohonan"
                                        :value="projectedBalance ? `${projectedBalance.annual.toFixed(1)} hari` : '—'"
                                    /><SummaryRow
                                        label="Anggaran Tabung selepas permohonan"
                                        :value="projectedBalance ? `${projectedBalance.tabung.toFixed(1)} hari` : '—'"
                                    /><SummaryRow
                                        label="Jumlah baki unjuran"
                                        :value="projectedBalance ? `${projectedBalance.total.toFixed(1)} hari` : '—'"
                                    />
                                    <p v-if="projectedBalance && projectedBalance.total < 0" class="rounded-lg border border-amber-500/20 bg-amber-500/[0.07] p-3 text-sm" role="status">
                                        Baki unjuran negatif. Permohonan tertakluk kepada semakan dan kelulusan biasa.
                                    </p>
                                </div>
                                <div class="border-t border-border pt-4">
                                    <p
                                        class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                    >
                                        Aliran semakan (rujukan)
                                    </p>
                                    <p v-if="!routePreview.length" class="mt-3 text-sm text-muted-foreground">Pilih tarikh cuti bekerja yang sah.</p>
                                    <ol class="mt-3 space-y-2">
                                        <li
                                            v-for="(
                                                step, index
                                            ) in routePreview"
                                            :key="step"
                                            class="flex gap-3 text-sm"
                                        >
                                            <span
                                                class="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                                                >{{ index + 1 }}</span
                                            ><span class="leading-6">{{
                                                step
                                            }}</span>
                                        </li>
                                    </ol>
                                    <p
                                        class="mt-3 text-xs leading-5 text-muted-foreground"
                                    >
                                        Peringkat wajib tidak dilangkau.
                                        Kelulusan sebenar hanya diproses melalui
                                        workflow berkuasa.
                                    </p>
                                </div>
                            </aside>
                        </div>
                    </template>

                    <template v-else-if="screen === 'requests'">
                        <section class="space-y-4">
                            <div
                                class="rounded-2xl border border-border bg-card p-5"
                            >
                                <h2 class="font-semibold">Status workflow</h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Label ikut Seksyen 11 SRS; keputusan sebenar
                                    tidak dibuat dalam pratonton.
                                </p>
                                <div class="mt-4 flex flex-wrap gap-2">
                                    <span
                                        v-for="status in [
                                            'Draf',
                                            'Menunggu Sokongan Pengurus Unit',
                                            'Menunggu Kelulusan Pengurus Besar',
                                            'Menunggu Sokongan Pengurus Besar',
                                            'Menunggu Kelulusan Setiausaha ALK',
                                            'Menunggu Semakan Semula',
                                            'Perlu Pembetulan',
                                            'Diluluskan',
                                            'Ditolak',
                                            'Menunggu Pembatalan',
                                            'Dalam Proses Pembatalan',
                                            'Dibatalkan',
                                        ]"
                                        :key="status"
                                        class="rounded-full px-2.5 py-1 text-[10px] font-medium"
                                        :class="statusTone(status)"
                                        >{{ status }}</span
                                    >
                                </div>
                            </div>
                            <section
                                class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                            >
                                <div
                                    class="flex flex-col gap-4 border-b border-border p-5 sm:flex-row sm:items-end sm:justify-between"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            Sejarah permohonan
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Status, tarikh cuti dan tempahan
                                            yang berkaitan.
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="filter in [
                                                'Semua',
                                                'Menunggu',
                                                'Diluluskan',
                                                'Ditolak',
                                            ]"
                                            :key="filter"
                                            type="button"
                                            class="min-h-9 rounded-lg border px-3 text-xs font-semibold"
                                            :class="
                                                activeFilter === filter
                                                    ? 'border-primary bg-primary text-primary-foreground'
                                                    : 'border-border hover:bg-muted'
                                            "
                                            @click="activeFilter = filter"
                                        >
                                            {{ filter }}
                                        </button>
                                    </div>
                                </div>
                                <RequestTable
                                    :rows="displayedRequests"
                                    :tone="statusTone"
                                    @open="openRequest"
                                />
                            </section>
                        </section>
                    </template>

                    <template v-else-if="screen === 'detail'">
                        <div
                            class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.7fr)]"
                        >
                            <section
                                class="space-y-5 rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-7"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3"
                                >
                                    <div>
                                        <p
                                            class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            {{ requestDetail.id }}
                                        </p>
                                        <h2 class="mt-1 text-xl font-semibold">
                                            {{ requestDetail.type }}
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{ requestDetail.name }} ·
                                            {{ requestDetail.unit }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full px-3 py-1.5 text-xs font-semibold"
                                        :class="
                                            statusTone(requestDetail.status)
                                        "
                                        >{{ requestDetail.status }}</span
                                    >
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <SummaryRow
                                        label="Tarikh cuti"
                                        :value="requestDetail.dates"
                                    /><SummaryRow
                                        label="Jumlah dimohon"
                                        :value="requestDetail.days"
                                    /><SummaryRow
                                        label="Jenis permohonan"
                                        :value="
                                            requestDetail.type ===
                                            'Emergency Leave'
                                                ? 'EL · retrospektif'
                                                : 'AL · biasa'
                                        "
                                    /><SummaryRow
                                        label="Tempahan baki"
                                        value="3 hari · sumber AL semasa"
                                    />
                                </div>
                                <div
                                    class="rounded-xl border border-border p-4"
                                >
                                    <h3 class="text-sm font-semibold">
                                        Alasan pemohon
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-muted-foreground"
                                    >
                                        {{
                                            requestDetail.type ===
                                            'Emergency Leave'
                                                ? 'Kecemasan keluarga · data contoh'
                                                : 'Urusan peribadi · pilihan bagi AL biasa'
                                        }}
                                    </p>
                                    <p
                                        v-if="
                                            requestDetail.type ===
                                            'Emergency Leave'
                                        "
                                        class="mt-2 text-xs text-muted-foreground"
                                    >
                                        Akses alasan dan lampiran dihadkan
                                        kepada pihak berkuasa dalam skop
                                        permohonan (CT-DEC-049).
                                    </p>
                                </div>
                                <div class="space-y-3">
                                    <h3 class="text-sm font-semibold">
                                        Jejak workflow
                                    </h3>
                                    <ol class="space-y-3">
                                        <li
                                            v-for="(
                                                step, index
                                            ) in workflowSteps"
                                            :key="`${step}-${index}`"
                                            class="flex gap-3 text-sm"
                                        >
                                            <span
                                                class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full border border-border text-xs text-muted-foreground"
                                                >{{ index + 1 }}</span
                                            ><span>{{ step }}</span>
                                        </li>
                                    </ol>
                                </div>
                            </section>
                            <aside
                                class="h-fit space-y-4 rounded-2xl border border-border bg-card p-5 shadow-sm"
                            >
                                <h2 class="font-semibold">
                                    Tindakan pratonton
                                </h2>
                                <p
                                    class="text-sm leading-6 text-muted-foreground"
                                >
                                    Butang ini hanya menggambarkan pilihan UI.
                                    Tiada keputusan atau transaksi direkodkan.
                                </p>
                                <div v-if="canActOnRequest" class="grid gap-2">
                                    <button
                                        type="button"
                                        class="min-h-10 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                        @click="
                                            previewAction(primaryActionLabel)
                                        "
                                    >
                                        {{ primaryActionLabel }}
                                        (pratonton)</button
                                    ><button
                                        type="button"
                                        class="min-h-10 rounded-lg border border-border px-3 text-sm font-semibold"
                                        @click="
                                            previewAction('Perlu pembetulan')
                                        "
                                    >
                                        Perlu pembetulan</button
                                    ><button
                                        type="button"
                                        class="min-h-10 rounded-lg border border-rose-500/25 px-3 text-sm font-semibold text-rose-700 dark:text-rose-300"
                                        @click="
                                            previewAction(
                                                isSupportStage
                                                    ? 'Tidak menyokong'
                                                    : 'Tolak',
                                            )
                                        "
                                    >
                                        {{
                                            isSupportStage
                                                ? 'Tidak menyokong'
                                                : 'Tolak'
                                        }}
                                        (pratonton)
                                    </button>
                                </div>
                                <div
                                    v-else-if="
                                        role === 'Staf' &&
                                        requestDetail.status.includes(
                                            'Menunggu',
                                        )
                                    "
                                    class="grid gap-2"
                                >
                                    <button
                                        type="button"
                                        class="min-h-10 rounded-lg border border-border px-3 text-sm font-semibold"
                                        @click="
                                            previewAction(
                                                'Tarik balik permohonan',
                                            )
                                        "
                                    >
                                        Tarik balik (pratonton)
                                    </button>
                                    <p
                                        class="text-xs leading-5 text-muted-foreground"
                                    >
                                        Tindakan tarik balik hanya tersedia
                                        mengikut status permohonan.
                                    </p>
                                </div>
                                <p
                                    v-else-if="
                                        role === 'Pengurus Besar' &&
                                        requestDetail.role === 'Pengurus Besar'
                                    "
                                    class="rounded-lg bg-muted/40 p-3 text-xs leading-5 text-muted-foreground"
                                >
                                    Permohonan sendiri dirutekan kepada
                                    Setiausaha ALK; tiada tindakan kendiri
                                    dibenarkan.
                                </p>
                                <p
                                    v-else
                                    class="rounded-lg bg-muted/40 p-3 text-xs leading-5 text-muted-foreground"
                                >
                                    Tiada tindakan permohonan untuk peranan ini
                                    pada paparan ini.
                                </p>
                                <p
                                    class="text-xs leading-5 text-muted-foreground"
                                >
                                    Kuasa tindakan ditentukan oleh peranan dan
                                    peringkat semasa. Tindakan pelulus
                                    sebelumnya kekal dalam sejarah.
                                </p>
                            </aside>
                        </div>
                    </template>

                    <template v-else-if="screen === 'approvals'">
                        <div
                            class="grid gap-4 md:grid-cols-[minmax(0,1fr)_18rem]"
                        >
                            <div class="space-y-4">
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        v-for="filter in [
                                            'Semua',
                                            'Menunggu sokongan',
                                            'Menunggu kelulusan',
                                        ]"
                                        :key="filter"
                                        type="button"
                                        class="min-h-9 rounded-lg border px-3 text-xs font-semibold"
                                        :class="
                                            activeFilter === filter
                                                ? 'border-primary bg-primary text-primary-foreground'
                                                : 'border-border hover:bg-muted'
                                        "
                                        @click="activeFilter = filter"
                                    >
                                        {{ filter }}
                                    </button>
                                </div>
                                <RequestTable
                                    :rows="displayedRequests"
                                    :tone="statusTone"
                                    @open="openRequest"
                                />
                            </div>
                            <aside
                                class="h-fit rounded-2xl border border-border bg-card p-5 shadow-sm"
                            >
                                <h2 class="font-semibold">Panduan skop</h2>
                                <ul
                                    class="mt-3 space-y-3 text-sm leading-6 text-muted-foreground"
                                >
                                    <li v-if="role === 'Pengurus Unit'">
                                        Hanya rekod dalam unit seliaan. Baki
                                        relevan boleh dilihat; sejarah transaksi
                                        penuh milik HR.
                                    </li>
                                    <li v-if="role === 'Pengurus Besar'">
                                        Kelulusan akhir ≤5 hari; sokongan >5
                                        hari; semakan pindaan dan pembatalan
                                        yang dibenarkan.
                                    </li>
                                    <li v-if="role === 'Setiausaha ALK'">
                                        Semua permohonan GM dan permohonan >5
                                        hari yang telah melalui sokongan perlu.
                                    </li>
                                    <li>
                                        Amaran pertindihan unit membantu
                                        pertimbangan operasi dan tidak menolak
                                        permohonan secara automatik.
                                    </li>
                                    <li>
                                        Pengganti pelulus belum ditetapkan
                                        (CT-DEC-019); jangan anggap delegasi
                                        atau bypass.
                                    </li>
                                </ul>
                            </aside>
                        </div>
                    </template>

                    <template v-else-if="screen === 'team'">
                        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                            <section
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            Disember 2026
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Paparan staf dan tarikh dalam skop
                                            unit.
                                        </p>
                                    </div>
                                    <div class="flex gap-2">
                                        <button
                                            type="button"
                                            class="size-9 rounded-lg border border-border"
                                            aria-label="Bulan sebelum"
                                        >
                                            ‹</button
                                        ><button
                                            type="button"
                                            class="size-9 rounded-lg border border-border"
                                            aria-label="Bulan seterusnya"
                                        >
                                            ›
                                        </button>
                                    </div>
                                </div>
                                <MiniCalendar team />
                                <div
                                    class="mt-4 flex flex-wrap gap-4 text-xs text-muted-foreground"
                                >
                                    <span class="inline-flex items-center gap-2"
                                        ><span
                                            class="size-2 rounded-full bg-emerald-500"
                                        />Diluluskan</span
                                    ><span
                                        class="inline-flex items-center gap-2"
                                        ><span
                                            class="size-2 rounded-full bg-primary"
                                        />Hari ini</span
                                    ><span
                                        class="inline-flex items-center gap-2"
                                        ><span
                                            class="size-2 rounded-full bg-amber-500"
                                        />Cuti umum</span
                                    >
                                </div>
                            </section>
                            <section
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                            >
                                <h2 class="font-semibold">
                                    Ketidakhadiran diluluskan
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Staf biasa hanya melihat nama dan tarikh
                                    rakan seunit.
                                </p>
                                <UnitSchedule :limited="role === 'Staf'" />
                                <p
                                    class="mt-4 rounded-xl bg-muted/45 p-3 text-xs leading-5 text-muted-foreground"
                                >
                                    Jenis cuti, alasan, baki dan dokumen tidak
                                    dipaparkan kepada rakan seunit (CT-DEC-052).
                                    Jadual unit/pending tertakluk skop akses
                                    berkuasa.
                                </p>
                            </section>
                        </div>
                    </template>

                    <template v-else-if="screen === 'staff'">
                        <section
                            class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Profil & kelayakan staf
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Semakan contoh untuk tujuan reka bentuk
                                        sahaja; tiada rekod HR sebenar.
                                    </p>
                                </div>
                                <div class="flex w-full max-w-xl gap-2">
                                    <label class="sr-only" for="staff-search"
                                        >Cari staf</label
                                    >
                                    <div class="relative min-w-0 flex-1">
                                        <Search
                                            class="absolute top-3 left-3 size-4 text-muted-foreground"
                                            aria-hidden="true"
                                        /><input
                                            id="staff-search"
                                            v-model="search"
                                            type="search"
                                            placeholder="Nama atau ID staf"
                                            class="h-10 w-full rounded-lg border border-input bg-background pr-3 pl-9 text-sm"
                                        />
                                    </div>
                                    <button
                                        type="button"
                                        class="inline-flex h-10 items-center gap-2 rounded-lg border border-border px-3 text-sm"
                                    >
                                        <Filter
                                            class="size-4"
                                            aria-hidden="true"
                                        />
                                        Penapis
                                    </button>
                                </div>
                            </div>
                            <div class="mt-5 overflow-x-auto">
                                <table
                                    class="w-full min-w-[680px] text-left text-sm"
                                >
                                    <thead
                                        class="border-b border-border text-xs text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="px-3 py-3 font-medium">
                                                Staf / ID
                                            </th>
                                            <th class="px-3 py-3 font-medium">
                                                Unit & kategori
                                            </th>
                                            <th class="px-3 py-3 font-medium">
                                                Tahun
                                            </th>
                                            <th class="px-3 py-3 font-medium">
                                                Kelayakan
                                            </th>
                                            <th class="px-3 py-3 font-medium">
                                                Status
                                            </th>
                                            <th class="px-3 py-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="row in [
                                                {
                                                    name: 'Nadia Contoh',
                                                    id: 'ST-0001',
                                                    unit: 'Operasi · Eksekutif',
                                                    year: '2026',
                                                    entitlement: '20 hari',
                                                    status: 'Aktif',
                                                },
                                                {
                                                    name: 'Iman Contoh',
                                                    id: 'ST-0002',
                                                    unit: 'Operasi · Staf',
                                                    year: '2026',
                                                    entitlement: '18 hari',
                                                    status: 'Aktif',
                                                },
                                                {
                                                    name: 'Rafi Contoh',
                                                    id: 'ST-0003',
                                                    unit: 'Kewangan · Pengurus Unit',
                                                    year: '2026',
                                                    entitlement: '22 hari',
                                                    status: 'Aktif',
                                                },
                                            ].filter((row) =>
                                                `${row.name} ${row.id} ${row.unit}`
                                                    .toLowerCase()
                                                    .includes(
                                                        search.toLowerCase(),
                                                    ),
                                            )"
                                            :key="row.id"
                                        >
                                            <td class="px-3 py-4">
                                                <strong class="block">{{
                                                    row.name
                                                }}</strong
                                                ><span
                                                    class="text-xs text-muted-foreground"
                                                    >{{ row.id }}</span
                                                >
                                            </td>
                                            <td class="px-3 py-4">
                                                {{ row.unit }}
                                            </td>
                                            <td class="px-3 py-4">
                                                {{ row.year }}
                                            </td>
                                            <td class="px-3 py-4">
                                                {{ row.entitlement }}
                                            </td>
                                            <td class="px-3 py-4">
                                                <span
                                                    class="rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs text-emerald-700 dark:text-emerald-300"
                                                    >{{ row.status }}</span
                                                >
                                            </td>
                                            <td class="px-3 py-4">
                                                <button
                                                    type="button"
                                                    class="text-xs font-semibold text-primary"
                                                    @click="
                                                        navigate('balances')
                                                    "
                                                >
                                                    Lihat baki
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </template>

                    <template v-else-if="screen === 'calendar'">
                        <div class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                            <section
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-3"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            Kalendar rasmi Cuti Umum KUTMB ·
                                            Johor
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Tahun 2026 · dikendalikan HR
                                        </p>
                                    </div>
                                    <label class="text-sm"
                                        >Tahun<select
                                            class="ml-2 h-9 rounded-lg border border-input bg-background px-2"
                                        >
                                            <option>2026</option>
                                            <option>2027</option>
                                        </select></label
                                    >
                                </div>
                                <div class="mt-5"><MiniCalendar /></div>
                                <div class="mt-4 space-y-2">
                                    <CalendarRow
                                        day="25 Dis 2026"
                                        title="Hari Krismas"
                                        tag="Cuti umum"
                                    /><CalendarRow
                                        day="7 Dis 2026"
                                        title="Cuti gantian · contoh"
                                        tag="Perlu semakan HR"
                                    />
                                </div>
                            </section>
                            <aside class="space-y-4">
                                <div
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <h2 class="font-semibold">Urus kalendar</h2>
                                    <p
                                        class="mt-1 text-sm leading-6 text-muted-foreground"
                                    >
                                        Tambah atau ubah cuti umum Johor serta
                                        catat sumber dan tarikh kuat kuasa.
                                    </p>
                                    <div class="mt-4 grid gap-2">
                                        <button
                                            type="button"
                                            class="min-h-10 rounded-lg bg-primary px-3 text-sm font-semibold text-primary-foreground"
                                            @click="
                                                previewAction(
                                                    'Tambah cuti umum',
                                                )
                                            "
                                        >
                                            Tambah cuti umum (pratonton)</button
                                        ><button
                                            type="button"
                                            class="min-h-10 rounded-lg border border-border px-3 text-sm font-semibold"
                                            @click="navigate('changes')"
                                        >
                                            Semak permohonan terjejas
                                        </button>
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-amber-500/20 bg-amber-500/[0.06] p-5"
                                >
                                    <h2 class="font-semibold">Cuti ganti</h2>
                                    <p
                                        class="mt-2 text-sm leading-6 text-muted-foreground"
                                    >
                                        Jika cuti umum jatuh Ahad, paparkan
                                        cadangan Isnin; jika Isnin juga cuti
                                        umum, cadangan Selasa. HR menentukan
                                        tarikh akhir. Sabtu tidak menghasilkan
                                        cuti ganti (CT-DEC-047).
                                    </p>
                                    <p
                                        class="mt-3 text-xs text-muted-foreground"
                                    >
                                        Pindaan pada permohonan yang sudah
                                        dihantar/diluluskan memerlukan semakan
                                        HR (CT-DEC-010).
                                    </p>
                                </div>
                            </aside>
                        </div>
                    </template>

                    <template v-else-if="screen === 'balances'">
                        <section class="space-y-4">
                            <div class="grid gap-4 md:grid-cols-3">
                                <MetricCard
                                    label="AL tahun 2026"
                                    value="14.5"
                                    note="baki tersedia · contoh"
                                    icon="wallet"
                                /><MetricCard
                                    label="Tempahan pending"
                                    value="2.0"
                                    note="ditolak sementara daripada baki"
                                    icon="clock"
                                /><MetricCard
                                    label="Tabung Cuti"
                                    value="5.0"
                                    note="sumber baki berasingan"
                                    icon="landmark"
                                />
                            </div>
                            <div
                                class="rounded-2xl border border-border bg-card shadow-sm"
                            >
                                <div
                                    class="flex flex-wrap items-start justify-between gap-3 border-b border-border p-5"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            Lejar baki (contoh)
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Transaksi memaparkan sumber asal,
                                            sebelum dan selepas.
                                        </p>
                                    </div>
                                    <label class="text-sm"
                                        >Tahun<select
                                            class="ml-2 h-9 rounded-lg border border-input bg-background px-2"
                                        >
                                            <option>2026</option>
                                            <option>2025</option>
                                        </select></label
                                    >
                                </div>
                                <div class="overflow-x-auto">
                                    <table
                                        class="w-full min-w-[760px] text-left text-sm"
                                    >
                                        <thead
                                            class="border-b border-border bg-muted/35 text-xs text-muted-foreground"
                                        >
                                            <tr>
                                                <th
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    Tarikh
                                                </th>
                                                <th
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    Transaksi
                                                </th>
                                                <th
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    Sumber
                                                </th>
                                                <th
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    Perubahan
                                                </th>
                                                <th
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    Baki
                                                </th>
                                                <th
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    Rujukan
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr
                                                v-for="row in [
                                                    {
                                                        date: '01 Jan 2026',
                                                        action: 'Baki awal',
                                                        source: 'AL 2026',
                                                        change: '+20.0',
                                                        balance: '20.0',
                                                        ref: 'Kelayakan tahunan',
                                                    },
                                                    {
                                                        date: '14 Feb 2026',
                                                        action: 'Penggunaan diluluskan',
                                                        source: 'AL 2026',
                                                        change: '−3.0',
                                                        balance: '17.0',
                                                        ref: 'AL-2026-010',
                                                    },
                                                    {
                                                        date: '04 Sep 2026',
                                                        action: 'Tempahan pending',
                                                        source: 'AL 2026',
                                                        change: '−2.0 sementara',
                                                        balance: '15.0 unjuran',
                                                        ref: 'EL-2026-018',
                                                    },
                                                    {
                                                        date: '31 Dis 2025',
                                                        action: 'Bawa ke Tabung Cuti',
                                                        source: 'Tabung · asal 2025',
                                                        change: '+5.0',
                                                        balance: '5.0',
                                                        ref: 'CF-2025-01',
                                                    },
                                                ]"
                                                :key="row.date + row.action"
                                            >
                                                <td class="px-4 py-3">
                                                    {{ row.date }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 font-medium"
                                                >
                                                    {{ row.action }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    {{ row.source }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    {{ row.change }}
                                                </td>
                                                <td class="px-4 py-3">
                                                    {{ row.balance }}
                                                </td>
                                                <td
                                                    class="px-4 py-3 font-mono text-xs"
                                                >
                                                    {{ row.ref }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div
                                    class="flex items-start gap-2 border-t border-border p-4 text-xs leading-5 text-muted-foreground"
                                >
                                    <Info
                                        class="mt-0.5 size-4 shrink-0 text-primary"
                                        aria-hidden="true"
                                    />AL tahun semasa digunakan dahulu; Tabung
                                    Cuti digunakan apabila baki AL tidak
                                    mencukupi. Data jadual ialah sintetik.
                                </div>
                            </div>
                        </section>
                    </template>

                    <template v-else-if="screen === 'year-end'">
                        <div class="grid gap-5 xl:grid-cols-[1.1fr_0.9fr]">
                            <section
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <h2 class="font-semibold">
                                            Pratonton penutupan 2026
                                        </h2>
                                        <p
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            Semak baki hujung tahun sebelum
                                            pelaksanaan berkuasa.
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-800 dark:text-amber-300"
                                        >Pratonton</span
                                    >
                                </div>
                                <div class="mt-5 overflow-x-auto">
                                    <table
                                        class="w-full min-w-[640px] text-left text-sm"
                                    >
                                        <thead
                                            class="border-b border-border text-xs text-muted-foreground"
                                        >
                                            <tr>
                                                <th
                                                    class="px-3 py-3 font-medium"
                                                >
                                                    Staf
                                                </th>
                                                <th
                                                    class="px-3 py-3 font-medium"
                                                >
                                                    Baki AL akhir
                                                </th>
                                                <th
                                                    class="px-3 py-3 font-medium"
                                                >
                                                    Had Tabung
                                                </th>
                                                <th
                                                    class="px-3 py-3 font-medium"
                                                >
                                                    Cadangan bawa
                                                </th>
                                                <th
                                                    class="px-3 py-3 font-medium"
                                                >
                                                    Lebihan
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border">
                                            <tr
                                                v-for="row in [
                                                    {
                                                        name: 'Nadia Contoh',
                                                        al: '4.5 hari',
                                                        cap: '10 hari',
                                                        transfer: '4.5 hari',
                                                        excess: 'Tiada',
                                                    },
                                                    {
                                                        name: 'Iman Contoh',
                                                        al: '12 hari',
                                                        cap: '10 hari',
                                                        transfer: '10 hari',
                                                        excess: '2 hari · burn',
                                                    },
                                                    {
                                                        name: 'Rafi Contoh',
                                                        al: '−1 hari',
                                                        cap: '8 hari',
                                                        transfer:
                                                            'Perlu semakan',
                                                        excess: 'HR tentukan',
                                                    },
                                                ]"
                                                :key="row.name"
                                            >
                                                <td
                                                    class="px-3 py-4 font-medium"
                                                >
                                                    {{ row.name }}
                                                </td>
                                                <td class="px-3 py-4">
                                                    {{ row.al }}
                                                </td>
                                                <td class="px-3 py-4">
                                                    {{ row.cap }}
                                                </td>
                                                <td class="px-3 py-4">
                                                    {{ row.transfer }}
                                                </td>
                                                <td class="px-3 py-4">
                                                    {{ row.excess }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p
                                    class="mt-4 text-xs leading-5 text-muted-foreground"
                                >
                                    Tiada penutupan atau pelarasan sebenar
                                    berlaku. Semakan HR dan pengesahan transaksi
                                    diperlukan mengikut status kes.
                                </p>
                            </section>
                            <aside class="space-y-4">
                                <div
                                    class="rounded-2xl border border-border bg-card p-5"
                                >
                                    <h2 class="font-semibold">
                                        Urutan semakan
                                    </h2>
                                    <ol class="mt-4 space-y-3 text-sm">
                                        <li
                                            v-for="(step, index) in [
                                                'Semak penggunaan dan tempahan pending',
                                                'Semak had Tabung Cuti mengikut kelayakan',
                                                'Tandakan kes negatif / transaksi belum selesai',
                                                'HR sahkan hasil penutupan',
                                            ]"
                                            :key="step"
                                            class="flex gap-3"
                                        >
                                            <span
                                                class="flex size-6 items-center justify-center rounded-full bg-muted text-xs font-semibold"
                                                >{{ index + 1 }}</span
                                            ><span class="leading-6">{{
                                                step
                                            }}</span>
                                        </li>
                                    </ol>
                                </div>
                                <button
                                    type="button"
                                    class="min-h-11 w-full rounded-lg border border-border text-sm font-semibold"
                                    @click="
                                        previewAction('Sahkan penutupan tahun')
                                    "
                                >
                                    Sahkan (pratonton sahaja)
                                </button>
                            </aside>
                        </div>
                    </template>

                    <template v-else-if="screen === 'prorata'">
                        <div class="grid gap-5 xl:grid-cols-[1fr_0.8fr]">
                            <section
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm sm:p-6"
                            >
                                <h2 class="font-semibold">
                                    Semakan kelayakan prorata (contoh)
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Masukkan maklumat untuk memaparkan konsep
                                    ringkasan; tiada kadar sebenar digunakan.
                                </p>
                                <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                    <label class="space-y-2 text-sm font-medium"
                                        >Staf<select
                                            class="block h-11 w-full rounded-lg border border-input bg-background px-3"
                                        >
                                            <option>
                                                Nadia Contoh · ST-0001
                                            </option>
                                            <option>
                                                Iman Contoh · ST-0002
                                            </option>
                                        </select></label
                                    ><label
                                        class="space-y-2 text-sm font-medium"
                                        >Tarikh notis tamat<input
                                            type="date"
                                            value="2026-08-15"
                                            class="block h-11 w-full rounded-lg border border-input bg-background px-3" /></label
                                    ><label
                                        class="space-y-2 text-sm font-medium"
                                        >Kelayakan tahunan disahkan HR<input
                                            type="text"
                                            value="20 hari · contoh"
                                            readonly
                                            class="block h-11 w-full rounded-lg border border-input bg-muted px-3" /></label
                                    ><label
                                        class="space-y-2 text-sm font-medium"
                                        >Tempoh perkhidmatan<input
                                            type="text"
                                            value="Jan – Ogos 2026 · contoh"
                                            readonly
                                            class="block h-11 w-full rounded-lg border border-input bg-muted px-3"
                                    /></label>
                                </div>
                                <div class="mt-5 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="min-h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                                        @click="previewAction('Kira prorata')"
                                    >
                                        Paparkan konsep pengiraan</button
                                    ><button
                                        type="button"
                                        class="min-h-10 rounded-lg border border-border px-4 text-sm font-semibold"
                                        @click="
                                            previewAction('Simpan semakan HR')
                                        "
                                    >
                                        Simpan semakan (pratonton)
                                    </button>
                                </div>
                            </section>
                            <aside class="space-y-4">
                                <div
                                    class="rounded-2xl border border-border bg-card p-5"
                                >
                                    <h2 class="font-semibold">Hasil semakan</h2>
                                    <div class="mt-4 space-y-3">
                                        <SummaryRow
                                            label="Kelayakan prorata"
                                            value="13.5 hari · contoh"
                                        /><SummaryRow
                                            label="Digunakan"
                                            value="15 hari · contoh"
                                        /><SummaryRow
                                            label="Lebihan"
                                            value="1.5 hari"
                                        /><SummaryRow
                                            label="Tabung Cuti layak"
                                            value="1 hari · semakan"
                                        />
                                    </div>
                                    <p
                                        class="mt-4 rounded-lg bg-amber-500/[0.07] p-3 text-xs leading-5"
                                    >
                                        Sumber undang-undang/polisi dan
                                        penyelesaian lebihan memerlukan
                                        pengesahan HR; sistem tidak membuat
                                        potongan gaji automatik.
                                    </p>
                                </div>
                                <p
                                    class="rounded-xl border border-border bg-muted/35 p-4 text-xs leading-5 text-muted-foreground"
                                >
                                    Kaedah bilangan bulan, hari sebenar dan
                                    pembundaran hendaklah sejajar dengan SRS dan
                                    keputusan HR yang berkuat kuasa. Nilai di
                                    atas tidak menetapkan kadar.
                                </p>
                            </aside>
                        </div>
                    </template>

                    <template v-else-if="screen === 'changes'">
                        <section class="space-y-4">
                            <div
                                class="rounded-2xl border border-border bg-card p-5"
                            >
                                <h2 class="font-semibold">
                                    Senarai pindaan dan pembatalan
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Peranan GM melihat tindakan pembatalan; HR
                                    menyemak kesan baki / kalendar dan pelarasan
                                    yang memerlukan pengesahan.
                                </p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <article
                                    v-for="item in [
                                        {
                                            id: 'AL-2026-036',
                                            title: 'Permohonan diluluskan · pembatalan diminta',
                                            person: 'Nadia Contoh',
                                            detail: 'Cuti bermula 14 Dis 2026 · 3 hari',
                                            status: 'Menunggu keputusan GM',
                                        },
                                        {
                                            id: 'AL-2026-018',
                                            title: 'Perubahan kalendar · semakan HR',
                                            person: 'Rafi Contoh',
                                            detail: 'Perubahan cuti umum mungkin mengubah jumlah hari',
                                            status: 'Pelarasan perlu pengesahan HR',
                                        },
                                    ]"
                                    :key="item.id"
                                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <p
                                                class="font-mono text-xs text-muted-foreground"
                                            >
                                                {{ item.id }}
                                            </p>
                                            <h3 class="mt-1 font-semibold">
                                                {{ item.title }}
                                            </h3>
                                            <p class="mt-2 text-sm">
                                                {{ item.person }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{ item.detail }}
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-800 dark:text-amber-300"
                                            >{{ item.status }}</span
                                        >
                                    </div>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button
                                            type="button"
                                            class="min-h-9 rounded-lg border border-border px-3 text-xs font-semibold"
                                            @click="
                                                previewAction('Lihat sejarah')
                                            "
                                        >
                                            Lihat sejarah</button
                                        ><button
                                            v-if="role === 'Pengurus Besar'"
                                            type="button"
                                            class="min-h-9 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground"
                                            @click="
                                                previewAction(
                                                    'Sahkan pembatalan',
                                                )
                                            "
                                        >
                                            Keputusan GM (pratonton)</button
                                        ><button
                                            v-if="isHr"
                                            type="button"
                                            class="min-h-9 rounded-lg bg-primary px-3 text-xs font-semibold text-primary-foreground"
                                            @click="
                                                previewAction(
                                                    'Sahkan pelarasan HR',
                                                )
                                            "
                                        >
                                            Semak pelarasan (pratonton)
                                        </button>
                                    </div>
                                    <p
                                        class="mt-3 text-xs leading-5 text-muted-foreground"
                                    >
                                        Status kelulusan asal kekal sehingga
                                        pembatalan sah; pelarasan baki disimpan
                                        sebagai transaksi berasingan selepas
                                        semakan yang diwajibkan.
                                    </p>
                                </article>
                            </div>
                        </section>
                    </template>

                    <template v-else-if="screen === 'reports'">
                        <section class="space-y-5">
                            <div
                                class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                            >
                                <div class="flex flex-wrap items-end gap-3">
                                    <label class="space-y-1 text-xs font-medium"
                                        >Jenis laporan<select
                                            class="block h-10 min-w-52 rounded-lg border border-input bg-background px-3 text-sm"
                                        >
                                            <option>
                                                Kelayakan & penggunaan
                                            </option>
                                            <option>Status permohonan</option>
                                            <option>
                                                Tabung Cuti & carry forward
                                            </option>
                                            <option>
                                                Penyata Tabung Cuti & transaksi
                                            </option>
                                            <option>
                                                Permohonan mengikut status dan
                                                pelulus
                                            </option>
                                            <option>Prorata</option>
                                            <option>
                                                Pindaan & pembatalan
                                            </option>
                                            <option>
                                                Dashboard pengurusan (agregat)
                                            </option>
                                        </select></label
                                    ><label
                                        class="space-y-1 text-xs font-medium"
                                        >Tahun<select
                                            class="block h-10 rounded-lg border border-input bg-background px-3 text-sm"
                                        >
                                            <option>2026</option>
                                            <option>2025</option>
                                        </select></label
                                    ><label
                                        class="space-y-1 text-xs font-medium"
                                        >Unit<select
                                            class="block h-10 rounded-lg border border-input bg-background px-3 text-sm"
                                        >
                                            <option>Semua unit</option>
                                            <option>Operasi</option>
                                            <option>Kewangan</option>
                                        </select></label
                                    ><button
                                        type="button"
                                        class="h-10 rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
                                        @click="previewAction('Jana laporan')"
                                    >
                                        Jana pratonton
                                    </button>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <MetricCard
                                    label="Kelayakan direkod"
                                    value="2,416"
                                    note="hari · contoh agregat"
                                    icon="wallet"
                                /><MetricCard
                                    label="Digunakan / diluluskan"
                                    value="1,802"
                                    note="hari · contoh agregat"
                                    icon="check"
                                /><MetricCard
                                    label="Permohonan menunggu"
                                    value="14"
                                    note="permohonan · contoh"
                                    icon="clock"
                                />
                            </div>
                            <section
                                class="rounded-2xl border border-border bg-card p-5"
                            >
                                <h2 class="font-semibold">Ringkasan laporan</h2>
                                <div class="mt-4 space-y-3">
                                    <ProgressRow
                                        label="Operasi · kadar penggunaan"
                                        value="74%"
                                        percent="74%"
                                    /><ProgressRow
                                        label="Kewangan · kadar penggunaan"
                                        value="61%"
                                        percent="61%"
                                    /><ProgressRow
                                        label="Permohonan diluluskan"
                                        value="86%"
                                        percent="86%"
                                    />
                                </div>
                            </section>
                            <p class="text-xs leading-5 text-muted-foreground">
                                Export dan laporan sebenar belum disambungkan.
                                Data agregat ini sintetik.
                            </p>
                        </section>
                    </template>

                    <template v-else-if="screen === 'audit'">
                        <section
                            class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                        >
                            <div class="border-b border-border p-5">
                                <h2 class="font-semibold">
                                    Jejak audit & transaksi (contoh)
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Tindakan, pelaku, masa, alasan dan kesan
                                    baki mengikut sumber asal.
                                </p>
                            </div>
                            <div class="overflow-x-auto">
                                <table
                                    class="w-full min-w-[780px] text-left text-sm"
                                >
                                    <thead
                                        class="border-b border-border bg-muted/35 text-xs text-muted-foreground"
                                    >
                                        <tr>
                                            <th class="px-4 py-3 font-medium">
                                                Tarikh & masa
                                            </th>
                                            <th class="px-4 py-3 font-medium">
                                                Pegawai
                                            </th>
                                            <th class="px-4 py-3 font-medium">
                                                Tindakan
                                            </th>
                                            <th class="px-4 py-3 font-medium">
                                                Rujukan
                                            </th>
                                            <th class="px-4 py-3 font-medium">
                                                Butiran
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="row in [
                                                {
                                                    time: '23 Nov 2026 · 10:14',
                                                    actor: 'HR Contoh',
                                                    action: 'Kalendar dikemas kini',
                                                    ref: 'CAL-2026-02',
                                                    detail: 'Sebelum / selepas · permohonan terjejas: 2',
                                                },
                                                {
                                                    time: '22 Nov 2026 · 15:32',
                                                    actor: 'GM Contoh',
                                                    action: 'Sokongan direkodkan',
                                                    ref: 'AL-2026-049',
                                                    detail: 'Menunggu keputusan akhir · catatan disimpan',
                                                },
                                                {
                                                    time: '21 Nov 2026 · 09:05',
                                                    actor: 'Sistem',
                                                    action: 'Tempahan baki',
                                                    ref: 'EL-2026-021',
                                                    detail: 'AL semasa · 1 hari sementara',
                                                },
                                            ]"
                                            :key="row.time"
                                        >
                                            <td
                                                class="px-4 py-3 whitespace-nowrap"
                                            >
                                                {{ row.time }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ row.actor }}
                                            </td>
                                            <td class="px-4 py-3 font-medium">
                                                {{ row.action }}
                                            </td>
                                            <td
                                                class="px-4 py-3 font-mono text-xs"
                                            >
                                                {{ row.ref }}
                                            </td>
                                            <td class="px-4 py-3">
                                                {{ row.detail }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                class="flex items-start gap-2 border-t border-border p-4 text-xs leading-5 text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="mt-0.5 size-4 shrink-0 text-primary"
                                    aria-hidden="true"
                                />Paparan audit dalam sistem sebenar perlu
                                dikawal dan direkod; contoh ini tidak membaca
                                atau menulis audit sebenar.
                            </div>
                        </section>
                    </template>

                    <template v-else-if="screen === 'notifications'">
                        <section
                            class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-3 border-b border-border p-5"
                            >
                                <div>
                                    <h2 class="font-semibold">
                                        Notifikasi berkaitan peranan
                                    </h2>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        Makluman hanya untuk pemohon atau
                                        pegawai yang perlu bertindak.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="min-h-9 rounded-lg border border-border px-3 text-xs font-semibold"
                                    @click="
                                        previewAction('Tandakan semua dibaca')
                                    "
                                >
                                    Tandakan semua dibaca (pratonton)
                                </button>
                            </div>
                            <div class="divide-y divide-border">
                                <article
                                    v-for="item in notificationItems"
                                    :key="item.title"
                                    class="flex gap-3 p-5"
                                >
                                    <span
                                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                                        ><Bell
                                            class="size-4"
                                            aria-hidden="true"
                                    /></span>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <h3 class="text-sm font-semibold">
                                                {{ item.title }}
                                            </h3>
                                            <span
                                                class="rounded-full bg-muted px-2 py-0.5 text-[10px] text-muted-foreground"
                                                >{{ item.type }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-1 text-sm leading-5 text-muted-foreground"
                                        >
                                            {{ item.body }}
                                        </p>
                                        <p
                                            class="mt-2 text-xs text-muted-foreground"
                                        >
                                            {{ item.time }}
                                        </p>
                                    </div>
                                    <span
                                        class="mt-2 size-2 shrink-0 rounded-full bg-primary"
                                        aria-label="Belum dibaca"
                                    />
                                </article>
                            </div>
                            <p
                                class="border-t border-border px-5 py-4 text-xs leading-5 text-muted-foreground"
                            >
                                Pemakluman awal EL kepada Pengurus Unit dibuat
                                di luar sistem mengikut CT-DEC-028; notifikasi
                                aplikasi tidak menggantikannya.
                            </p>
                        </section>
                    </template>

                    <template v-else-if="screen === 'coverage'">
                        <section class="space-y-5">
                            <div
                                class="rounded-2xl border border-border bg-card p-5 sm:p-6"
                            >
                                <h2 class="font-semibold">
                                    Peta reka bentuk kepada SRS Cuti Tahunan
                                    v0.5
                                </h2>
                                <p
                                    class="mt-1 text-sm leading-6 text-muted-foreground"
                                >
                                    Indeks ini memetakan keperluan kepada
                                    paparan. Ia tidak mengubah ID FR, BR, status
                                    workflow, RR, NFR atau CT-DEC.
                                </p>
                                <div class="mt-5 divide-y divide-border">
                                    <div
                                        v-for="item in coverage"
                                        :key="item.ids"
                                        class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <p
                                                class="font-mono text-xs font-semibold text-primary"
                                            >
                                                {{ item.ids }}
                                            </p>
                                            <p class="mt-1 text-sm font-medium">
                                                {{ item.title }}
                                            </p>
                                        </div>
                                        <button
                                            v-if="
                                                item.screen !== 'coverage' &&
                                                visibleNav.includes(item.screen)
                                            "
                                            type="button"
                                            class="inline-flex min-h-9 items-center gap-2 self-start rounded-lg border border-border px-3 text-xs font-semibold hover:bg-muted"
                                            @click="navigate(item.screen)"
                                        >
                                            Buka paparan
                                            <ArrowRight
                                                class="size-3.5"
                                                aria-hidden="true"
                                            /></button
                                        ><span
                                            v-else
                                            class="self-start text-xs text-muted-foreground"
                                            >Rujuk indeks keputusan</span
                                        >
                                    </div>
                                </div>
                            </div>
                            <div
                                class="rounded-2xl border border-border bg-card p-5 sm:p-6"
                            >
                                <h2 class="font-semibold">
                                    Baseline keputusan CT-DEC
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Semua 56 keputusan digunakan sebagai
                                    baseline reka bentuk. Butiran penuh kekal
                                    dalam daftar isu dan SRS.
                                </p>
                                <div
                                    class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <article
                                        v-for="item in decisionGroups"
                                        :key="item[0]"
                                        class="rounded-xl border border-border p-4"
                                    >
                                        <p
                                            class="font-mono text-xs font-semibold text-primary"
                                        >
                                            {{ item[0] }}
                                        </p>
                                        <p
                                            class="mt-2 text-sm leading-5 text-muted-foreground"
                                        >
                                            {{ item[1] }}
                                        </p>
                                    </article>
                                </div>
                                <p
                                    class="mt-4 rounded-xl bg-amber-500/[0.07] p-4 text-xs leading-5 text-muted-foreground"
                                >
                                    Sebahagian butiran masih ditandakan pending
                                    HR confirmation dalam daftar keputusan.
                                    Paparan UI mengekalkan konfigurasi/semakan
                                    manual bagi nilai yang belum ditetapkan; ia
                                    tidak mencipta polisi baru.
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-border bg-card p-5 sm:p-6"
                            >
                                <h2 class="font-semibold">
                                    Keperluan kualiti & operasi
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    Kawalan sistem yang tidak boleh ditunjukkan
                                    sebagai fungsi UI kekal tanggungjawab
                                    backend dan operasi.
                                </p>
                                <div
                                    class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                                >
                                    <article
                                        v-for="item in qualityCoverage"
                                        :key="item[0]"
                                        class="rounded-xl border border-border p-4"
                                    >
                                        <p
                                            class="font-mono text-xs font-semibold text-primary"
                                        >
                                            {{ item[0] }}
                                        </p>
                                        <p
                                            class="mt-2 text-xs leading-5 text-muted-foreground"
                                        >
                                            {{ item[1] }}
                                        </p>
                                    </article>
                                </div>
                                <p
                                    class="mt-4 text-xs leading-5 text-muted-foreground"
                                >
                                    RR-07 dalam SRS turut menyebut lebihan MC.
                                    Paparan contoh ini tidak memuatkan data MC
                                    individu atau butiran kesihatan; laporan
                                    sensitif memerlukan skop akses yang
                                    berkuasa.
                                </p>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <MetricCard
                                    label="Functional requirements"
                                    value="32"
                                    note="FR-01–32"
                                    icon="list"
                                /><MetricCard
                                    label="Decision baseline"
                                    value="56"
                                    note="CT-DEC-001–056"
                                    icon="check"
                                /><MetricCard
                                    label="Laporan / bukan fungsi"
                                    value="RR + NFR"
                                    note="FR-28–32 berkaitan"
                                    icon="file"
                                />
                            </div>
                        </section>
                    </template>

                    <footer
                        class="border-t border-border pt-4 text-xs leading-5 text-muted-foreground"
                    >
                        Reka bentuk Vue MOD-005 · SRS v0.5 · data sintetik ·
                        tiada sambungan backend, API atau pangkalan data.
                    </footer>
                </div>
        </section>

        <Transition
            enter-active-class="transition duration-200 motion-reduce:transition-none"
            enter-from-class="translate-y-2 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="toast"
                role="status"
                aria-live="polite"
                class="fixed right-4 bottom-20 z-[60] flex max-w-sm items-start gap-3 rounded-xl border border-border bg-card px-4 py-3 text-sm shadow-lg sm:bottom-5"
            >
                <Info
                    class="mt-0.5 size-4 shrink-0 text-primary"
                    aria-hidden="true"
                /><span>{{ toast }}</span
                ><button
                    type="button"
                    class="ml-auto text-muted-foreground"
                    aria-label="Tutup mesej"
                    @click="toast = ''"
                >
                    <X class="size-4" aria-hidden="true" />
                </button>
            </div>
        </Transition>
    </div>
</template>
