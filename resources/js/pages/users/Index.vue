<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { Search, Plus, UsersRound, ShieldCheck, X } from '@lucide/vue';
import { computed, ref } from 'vue';

type RoleOption = { id: number; label: string };
type DirectoryUser = {
    id: number;
    displayName: string;
    username: string;
    status: 'ACTIVE' | 'INACTIVE' | string;
    roles: RoleOption[];
    canManage: boolean;
};
const props = defineProps<{
    users: DirectoryUser[];
    pagination: { currentPage: number; lastPage: number; perPage: number; total: number };
    filters: { search: string; status: string | null; role: number | null };
    roleOptions: RoleOption[];
    grantableRoles: RoleOption[];
    canManage: boolean;
    passwordMinimum: number;
}>();

const search = ref(props.filters.search);
const status = ref(props.filters.status ?? '');
const role = ref<number | ''>(props.filters.role ?? '');
const loading = ref(false);
const selected = ref<DirectoryUser | null>(null);
const panel = ref<'create' | 'edit' | null>(null);
const notice = ref('');
const create = useForm({ display_name: '', username: '', password: '', password_confirmation: '', role_ids: [] as number[] });
const profile = useForm({ display_name: '' });
const roles = useForm({ role_ids: [] as number[] });
const state = useForm({ status: '' });
const inputClass = 'h-11 w-full rounded-lg border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring';
const buttonClass = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg border border-input bg-background px-4 py-2 text-sm font-medium hover:bg-muted disabled:opacity-50';
const primaryClass = 'inline-flex min-h-10 items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50';
const activeFilter = computed(() => Boolean(props.filters.search || props.filters.status || props.filters.role));

function visitPage(page = 1, useDraft = false) {
    router.get('/users', {
        search: (useDraft ? search.value : props.filters.search) || undefined,
        status: (useDraft ? status.value : props.filters.status) || undefined,
        role: (useDraft ? role.value : props.filters.role) || undefined,
        page,
    }, { preserveState: true, preserveScroll: true, onStart: () => { loading.value = true; }, onFinish: () => { loading.value = false; } });
}
function resetFilters() {
    search.value = ''; status.value = ''; role.value = '';
    visitPage(1, true);
}
function openCreate() {
    selected.value = null; create.reset(); create.clearErrors(); notice.value = ''; panel.value = 'create';
}
function openEdit(user: DirectoryUser) {
    selected.value = user; profile.display_name = user.displayName;
    roles.role_ids = user.roles.map(item => item.id); profile.clearErrors(); roles.clearErrors(); state.clearErrors();
    notice.value = ''; panel.value = 'edit';
}
function closePanel() {
    create.reset('password', 'password_confirmation'); panel.value = null; selected.value = null; notice.value = '';
}
function submitCreate() {
    create.post('/users', { preserveScroll: true, onSuccess: () => { closePanel(); notice.value = 'Pengguna berjaya ditambah.'; }, onError: () => create.reset('password', 'password_confirmation') });
}
function saveProfile() {
    if (!selected.value) return;
    profile.patch('/users/' + selected.value.id + '/profile', { preserveScroll: true, onSuccess: () => { notice.value = 'Nama paparan disimpan.'; } });
}
function saveRoles() {
    if (!selected.value) return;
    roles.put('/users/' + selected.value.id + '/roles', { preserveScroll: true, onSuccess: () => { notice.value = 'Peranan disimpan.'; } });
}
function changeStatus() {
    if (!selected.value) return;
    const target = props.users.find(user => user.id === selected.value?.id) ?? selected.value;
    state.status = target.status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
    if (!window.confirm(state.status === 'INACTIVE' ? 'Nyahaktif akaun ini dan tamatkan sesinya?' : 'Aktifkan semula akaun ini?')) return;
    state.patch('/users/' + target.id + '/status', { preserveScroll: true, onSuccess: () => { closePanel(); notice.value = 'Status akaun dikemas kini.'; } });
}
</script>

<template>
    <Head title="Pengurusan Pengguna" />
    <main class="mx-auto w-full max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8" aria-labelledby="users-heading">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <p class="text-xs font-semibold tracking-widest text-muted-foreground uppercase">System Core · MOD-001</p>
                <h1 id="users-heading" class="text-3xl font-semibold tracking-tight">Pengurusan Pengguna</h1>
                <p class="text-sm leading-6 text-muted-foreground">Urus akaun, peranan dan status akses sistem. Rekod pekerja diurus secara berasingan.</p>
            </div>
            <button v-if="canManage" type="button" :class="primaryClass" @click="openCreate"><Plus class="size-4" /> Tambah pengguna</button>
        </header>
        <p v-if="notice" role="status" class="rounded-lg border border-border bg-muted/40 p-3 text-sm">{{ notice }}</p>

        <section v-if="panel === 'create' || (panel === 'edit' && selected)" class="space-y-5 rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6" aria-label="Borang pengguna">
            <div class="flex items-center justify-between gap-2">
                <h2 class="font-semibold">{{ panel === 'create' ? 'Pengguna baharu' : 'Urus ' + selected?.username }}</h2>
                <button type="button" :class="buttonClass" aria-label="Tutup borang" @click="closePanel"><X class="size-4" /> Tutup</button>
            </div>
            <form v-if="panel === 'create'" class="space-y-4" @submit.prevent="submitCreate">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="space-y-1 text-sm font-medium">Nama paparan<input v-model="create.display_name" :class="inputClass" required maxlength="255" /><span v-if="create.errors.display_name" class="text-xs text-destructive">{{ create.errors.display_name }}</span></label>
                    <label class="space-y-1 text-sm font-medium">Nama pengguna<input v-model="create.username" :class="inputClass" required maxlength="64" autocomplete="off" /><span v-if="create.errors.username" class="text-xs text-destructive">{{ create.errors.username }}</span></label>
                    <label class="space-y-1 text-sm font-medium">Kata laluan<input v-model="create.password" :class="inputClass" type="password" required :minlength="passwordMinimum" autocomplete="new-password" /><span v-if="create.errors.password" class="text-xs text-destructive">{{ create.errors.password }}</span><span class="block text-xs text-muted-foreground">Minimum {{ passwordMinimum }} aksara.</span></label>
                    <label class="space-y-1 text-sm font-medium">Sahkan kata laluan<input v-model="create.password_confirmation" :class="inputClass" type="password" required autocomplete="new-password" /></label>
                </div>
                <fieldset class="space-y-2"><legend class="text-sm font-medium">Peranan awal</legend>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        <label v-for="item in grantableRoles" :key="item.id" class="flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 text-sm"><input v-model="create.role_ids" type="checkbox" :value="item.id" class="size-4 accent-primary" />{{ item.label }}</label>
                    </div>
                    <p v-if="create.errors.role_ids" class="text-xs text-destructive">{{ create.errors.role_ids }}</p>
                </fieldset>
                <button :class="primaryClass" :disabled="create.processing || !create.role_ids.length" type="submit">Cipta akaun aktif</button>
            </form>
            <div v-else-if="selected" class="space-y-5">
                <form class="space-y-3" @submit.prevent="saveProfile">
                    <label class="block max-w-xl space-y-1 text-sm font-medium">Nama paparan<input v-model="profile.display_name" :class="inputClass" required maxlength="255" /><span v-if="profile.errors.display_name" class="text-xs text-destructive">{{ profile.errors.display_name }}</span></label>
                    <button type="submit" :class="buttonClass" :disabled="profile.processing">Simpan profil</button>
                </form>
                <form class="space-y-3 border-t border-border pt-5" @submit.prevent="saveRoles">
                    <fieldset class="space-y-2"><legend class="text-sm font-medium">Peranan pengguna</legend>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label v-for="item in grantableRoles" :key="item.id" class="flex min-h-10 items-center gap-2 rounded-lg border border-border px-3 text-sm"><input v-model="roles.role_ids" type="checkbox" :value="item.id" class="size-4 accent-primary" />{{ item.label }}</label>
                        </div>
                        <p v-if="roles.errors.role_ids" class="text-xs text-destructive">{{ roles.errors.role_ids }}</p>
                    </fieldset>
                    <button type="submit" :class="buttonClass" :disabled="roles.processing">Simpan peranan</button>
                    <p class="text-xs text-muted-foreground">Sesi pengguna ditamatkan apabila peranan diubah.</p>
                </form>
                <div class="space-y-2 border-t border-border pt-5">
                    <p class="text-sm font-medium">Status akaun</p>
                    <button type="button" :class="buttonClass" :disabled="state.processing" @click="changeStatus">{{ (users.find(user => user.id === selected?.id)?.status ?? selected.status) === 'ACTIVE' ? 'Nyahaktif akaun' : 'Aktifkan semula' }}</button>
                    <p v-if="state.errors.status" class="text-xs text-destructive">{{ state.errors.status }}</p>
                </div>
            </div>
        </section>

        <form class="grid gap-3 rounded-xl border border-border bg-card p-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.6fr)_minmax(0,0.8fr)_auto] lg:items-end" role="search" @submit.prevent="visitPage(1, true)">
            <label class="space-y-1 text-sm font-medium">Carian<input v-model="search" :class="inputClass" type="search" placeholder="Nama atau nama pengguna" maxlength="100" /></label>
            <label class="space-y-1 text-sm font-medium">Status<select v-model="status" :class="inputClass"><option value="">Semua status</option><option value="ACTIVE">Aktif</option><option value="INACTIVE">Tidak aktif</option></select></label>
            <label class="space-y-1 text-sm font-medium">Peranan<select v-model="role" :class="inputClass"><option value="">Semua peranan</option><option v-for="item in roleOptions" :key="item.id" :value="item.id">{{ item.label }}</option></select></label>
            <div class="flex gap-2"><button type="submit" :class="primaryClass" :disabled="loading"><Search class="size-4" /> Cari</button><button type="button" :class="buttonClass" @click="resetFilters">Reset</button></div>
        </form>

        <section class="overflow-hidden rounded-xl border border-border bg-card shadow-sm" :aria-busy="loading">
            <header class="flex items-center justify-between border-b border-border p-5"><div><h2 class="font-semibold">Senarai akaun</h2><p class="text-sm text-muted-foreground" aria-live="polite">{{ pagination.total }} pengguna</p></div><span v-if="loading" class="text-sm text-muted-foreground">Memuatkan…</span></header>
            <div v-if="users.length" class="divide-y divide-border">
                <div class="hidden grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-4 bg-muted/40 px-5 py-3 text-xs font-semibold text-muted-foreground lg:grid"><span>Nama / Nama pengguna</span><span>Peranan</span><span>Status</span><span>Tindakan</span></div>
                <article v-for="user in users" :key="user.id" class="grid gap-3 px-5 py-4 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)_auto] lg:items-center">
                    <div class="flex min-w-0 items-center gap-3"><span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-muted text-muted-foreground"><UsersRound class="size-5" /></span><div class="min-w-0"><p class="break-words font-medium">{{ user.displayName }}</p><p class="break-all font-mono text-xs text-muted-foreground">@{{ user.username }}</p></div></div>
                    <div class="flex flex-wrap gap-1.5"><span v-for="item in user.roles" :key="item.id" class="rounded-md bg-muted px-2 py-1 text-xs">{{ item.label }}</span><span v-if="!user.roles.length" class="text-xs text-muted-foreground">Tiada peranan</span></div>
                    <span class="w-fit rounded-full px-2.5 py-1 text-xs font-semibold" :class="user.status === 'ACTIVE' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' : 'bg-muted text-muted-foreground'">{{ user.status === 'ACTIVE' ? 'Aktif' : 'Tidak aktif' }}</span>
                    <button v-if="user.canManage" :class="buttonClass" type="button" @click="openEdit(user)">Urus</button><span v-else-if="canManage" class="inline-flex items-center gap-1 text-xs text-muted-foreground"><ShieldCheck class="size-4" /> Dilindungi</span><span v-else class="text-xs text-muted-foreground">Baca sahaja</span>
                </article>
            </div>
            <div v-else class="p-12 text-center"><h3 class="font-semibold">{{ activeFilter ? 'Tiada padanan' : 'Belum ada pengguna' }}</h3><p class="mt-2 text-sm text-muted-foreground">{{ activeFilter ? 'Cuba ubah penapis carian.' : 'Akaun akan muncul selepas didaftarkan.' }}</p></div>
            <nav v-if="pagination.lastPage > 1" class="flex items-center justify-between gap-3 border-t border-border p-4" aria-label="Halaman pengguna"><button type="button" :class="buttonClass" :disabled="pagination.currentPage <= 1 || loading" @click="visitPage(pagination.currentPage - 1)">Sebelum</button><span class="text-xs text-muted-foreground">{{ pagination.currentPage }} / {{ pagination.lastPage }}</span><button type="button" :class="buttonClass" :disabled="pagination.currentPage >= pagination.lastPage || loading" @click="visitPage(pagination.currentPage + 1)">Seterusnya</button></nav>
        </section>
    </main>
</template>
