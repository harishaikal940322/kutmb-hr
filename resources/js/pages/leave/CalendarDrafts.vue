<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import CalendarDayEditForm from './CalendarDayEditForm.vue';
import CalendarDayForm from './CalendarDayForm.vue';
type Version = {
    id: number;
    label: string;
    effective_from: string;
    effective_to: string | null;
    confirmed_at: string | null;
    confirmed_by: number | null;
    overlapping_ids: number[];
};
type Day = {
    id: number;
    calendar_version_id: number;
    date: string;
    name: string;
    kind: string;
    original_day_id: number | null;
};
type History = {
    id: number;
    calendar_day_id: number;
    previous_date: string;
    previous_name: string;
    created_at: string;
};
defineProps<{ versions: Version[]; days: Day[]; history: History[] }>();
const form = useForm({ label: '', effective_from: '', effective_to: '' });
function submit() {
    form.post('/leave/calendar-drafts', {
        preserveScroll: true,
        errorBag: 'calendar-version',
        onSuccess: () => form.reset(),
    });
}
</script>
<template>
    <Head title="Draf Kalendar Cuti" />
    <main class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <header>
            <h1 class="text-3xl font-semibold">Draf Kalendar Cuti</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Draf data sintetik untuk pembangunan tempatan. Draf tidak
                mengesahkan kalendar atau mengubah permohonan cuti.
            </p>
        </header>
        <section class="space-y-4" aria-labelledby="new-calendar">
            <h2 id="new-calendar" class="text-xl font-semibold">
                Versi draf baharu
            </h2>
            <form
                class="grid gap-3 rounded-lg border p-4 sm:grid-cols-2"
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
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="min-h-10 rounded-md bg-primary px-4 text-primary-foreground disabled:opacity-50"
                >
                    Tambah versi draf
                </button>
            </form>
        </section>
        <section class="space-y-4" aria-labelledby="calendar-list">
            <h2 id="calendar-list" class="text-xl font-semibold">
                Versi kalendar
            </h2>
            <p v-if="!versions.length" class="text-sm text-muted-foreground">
                Tiada versi.
            </p>
            <article
                v-for="version in versions"
                :key="version.id"
                class="space-y-4 rounded-lg border p-4"
            >
                <h3 class="font-semibold">{{ version.label }}</h3>
                <p
                    v-if="version.overlapping_ids.length"
                    class="text-sm text-destructive"
                >
                    Tempoh bertindih dengan
                    {{ version.overlapping_ids.length }} versi lain. Semak
                    sebelum sebarang pengesahan.
                </p>
                <p class="text-sm text-muted-foreground">
                    {{ version.effective_from }} —
                    {{ version.effective_to ?? 'terbuka' }} ·
                    {{
                        version.confirmed_at || version.confirmed_by
                            ? 'Disahkan / terkunci'
                            : 'Draf'
                    }}
                </p>
                <ul class="space-y-3 text-sm">
                    <li
                        v-for="day in days.filter(
                            (item) => item.calendar_version_id === version.id,
                        )"
                        :key="day.id"
                        class="rounded-md border p-3"
                    >
                        <p>
                            {{ day.date }} · {{ day.name }} ·
                            {{
                                day.kind === 'holiday'
                                    ? 'Cuti umum'
                                    : 'Cuti gantian'
                            }}
                        </p>
                        <ul
                            v-if="
                                history.some(
                                    (item) => item.calendar_day_id === day.id,
                                )
                            "
                            class="mt-2 text-muted-foreground"
                            aria-label="Sejarah perubahan"
                        >
                            <li
                                v-for="change in history.filter(
                                    (item) => item.calendar_day_id === day.id,
                                )"
                                :key="change.id"
                            >
                                Sebelum ini: {{ change.previous_date }} ·
                                {{ change.previous_name }} ({{
                                    change.created_at
                                }})
                            </li>
                        </ul>
                        <CalendarDayEditForm
                            v-if="
                                !version.confirmed_at && !version.confirmed_by
                            "
                            :version-id="version.id"
                            :day="day"
                        />
                    </li>
                </ul>
                <CalendarDayForm
                    v-if="!version.confirmed_at && !version.confirmed_by"
                    :version-id="version.id"
                    :effective-from="version.effective_from"
                    :effective-to="version.effective_to"
                    :days="
                        days.filter(
                            (item) => item.calendar_version_id === version.id,
                        )
                    "
                />
            </article>
        </section>
    </main>
</template>
