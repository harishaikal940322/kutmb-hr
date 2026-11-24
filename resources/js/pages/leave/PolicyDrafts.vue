<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import PolicyEditForm from './PolicyEditForm.vue';
type Version = {
    id: number;
    label: string;
    effective_from: string;
    effective_to: string | null;
    working_weekdays: number[] | null;
    work_start: string | null;
    session_boundary: string | null;
    work_end: string | null;
    confirmed_at: string | null;
    confirmed_by: number | null;
};
type History = {
    id: number;
    policy_version_id: number;
    previous_state: Omit<Version, 'id' | 'confirmed_at' | 'confirmed_by'>;
    created_at: string;
};
defineProps<{ versions: Version[]; history: History[] }>();
const weekdays = [
    'Isnin',
    'Selasa',
    'Rabu',
    'Khamis',
    'Jumaat',
    'Sabtu',
    'Ahad',
];
const form = useForm({
    label: '',
    effective_from: '',
    effective_to: '',
    working_weekdays: [] as number[],
    work_start: '',
    session_boundary: '',
    work_end: '',
});
function submit() {
    form.transform((data) => ({
        ...data,
        working_weekdays: data.working_weekdays.length
            ? data.working_weekdays
            : null,
    })).post('/leave/policy-drafts', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <Head title="Draf Dasar Cuti" />
    <main class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <header>
            <h1 class="text-3xl font-semibold">Draf Dasar Cuti</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Data sintetik untuk pembangunan. Medan kosong belum ditetapkan;
                draf tidak mengaktifkan dasar atau pengiraan cuti.
            </p>
        </header>
        <section class="space-y-4" aria-labelledby="new-policy">
            <h2 id="new-policy" class="text-xl font-semibold">
                Versi draf baharu
            </h2>
            <form
                class="grid gap-4 rounded-lg border p-4 sm:grid-cols-2"
                @submit.prevent="submit"
            >
                <label class="grid gap-1 text-sm"
                    >Nama versi<input
                        v-model="form.label"
                        required
                        maxlength="255"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.label
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Mula berkuat kuasa<input
                        v-model="form.effective_from"
                        required
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.effective_from
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tamat berkuat kuasa (pilihan)<input
                        v-model="form.effective_to"
                        type="date"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.effective_to
                    }}</span></label
                >
                <fieldset class="space-y-2 text-sm sm:col-span-2">
                    <legend>Hari bekerja (kosong = belum ditetapkan)</legend>
                    <div class="flex flex-wrap gap-4">
                        <label
                            v-for="(day, index) in weekdays"
                            :key="day"
                            class="flex items-center gap-1"
                            ><input
                                v-model="form.working_weekdays"
                                type="checkbox"
                                :value="index + 1"
                            />
                            {{ day }}</label
                        >
                    </div>
                    <span class="text-destructive">{{
                        form.errors.working_weekdays
                    }}</span>
                </fieldset>
                <label class="grid gap-1 text-sm"
                    >Mula bekerja<input
                        v-model="form.work_start"
                        type="time"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.work_start
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Sempadan sesi pagi/petang<input
                        v-model="form.session_boundary"
                        type="time"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.session_boundary
                    }}</span></label
                >
                <label class="grid gap-1 text-sm"
                    >Tamat bekerja<input
                        v-model="form.work_end"
                        type="time"
                        class="min-h-10 rounded-md border bg-background px-3"
                    /><span class="text-destructive">{{
                        form.errors.work_end
                    }}</span></label
                >
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
                >
                    Tambah draf
                </button>
            </form>
        </section>
        <section class="space-y-4" aria-labelledby="policy-list">
            <h2 id="policy-list" class="text-xl font-semibold">Versi dasar</h2>
            <p v-if="!versions.length" class="text-sm text-muted-foreground">
                Tiada versi.
            </p>
            <ul class="divide-y rounded-lg border">
                <li
                    v-for="version in versions"
                    :key="version.id"
                    class="space-y-1 p-4"
                >
                    <p class="font-semibold">{{ version.label }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ version.effective_from }} —
                        {{ version.effective_to ?? 'terbuka' }} ·
                        {{
                            version.confirmed_at || version.confirmed_by
                                ? 'Disahkan / terkunci'
                                : 'Draf'
                        }}
                    </p>
                    <p class="text-sm">
                        Hari:
                        {{
                            version.working_weekdays
                                ?.map((day) => weekdays[day - 1])
                                .join(', ') || 'Belum ditetapkan'
                        }}
                    </p>
                    <p class="text-sm">
                        Masa: {{ version.work_start ?? '—' }} /
                        {{ version.session_boundary ?? '—' }} /
                        {{ version.work_end ?? '—' }}
                    </p>
                    <details
                        v-if="
                            history.some(
                                (item) => item.policy_version_id === version.id,
                            )
                        "
                        class="text-sm text-muted-foreground"
                    >
                        <summary class="cursor-pointer">
                            Sejarah perubahan
                        </summary>
                        <ul class="mt-2 space-y-2">
                            <li
                                v-for="change in history.filter(
                                    (item) =>
                                        item.policy_version_id === version.id,
                                )"
                                :key="change.id"
                            >
                                {{ change.created_at }} ·
                                {{ change.previous_state.label }} ·
                                {{ change.previous_state.effective_from }} —
                                {{
                                    change.previous_state.effective_to ??
                                    'terbuka'
                                }}
                                · Hari:
                                {{
                                    change.previous_state.working_weekdays
                                        ?.map((day) => weekdays[day - 1])
                                        .join(', ') || 'Belum ditetapkan'
                                }}
                                · Masa:
                                {{ change.previous_state.work_start ?? '—' }} /
                                {{
                                    change.previous_state.session_boundary ??
                                    '—'
                                }}
                                / {{ change.previous_state.work_end ?? '—' }}
                            </li>
                        </ul>
                    </details>
                    <PolicyEditForm
                        v-if="!version.confirmed_at && !version.confirmed_by"
                        :version="version"
                    />
                </li>
            </ul>
        </section>
    </main>
</template>
