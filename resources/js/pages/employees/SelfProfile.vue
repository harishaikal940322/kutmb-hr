<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
defineProps<{
    employee: {
        employee_no: string;
        display_name: string;
        service_start: string;
        confirmation_date: string | null;
        service_end: string | null;
    };
    assignment: {
        unit: string;
        category: string;
        effective_from: string;
    } | null;
}>();
</script>
<template>
    <Head title="Profil Pekerja Saya" />
    <main class="mx-auto w-full max-w-3xl space-y-6 px-4 py-8 sm:px-6">
        <header>
            <h1 class="text-3xl font-semibold">Profil Pekerja Saya</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Paparan pembangunan tempatan.
            </p>
        </header>
        <dl class="grid gap-4 rounded-lg border p-5 sm:grid-cols-2">
            <div>
                <dt class="text-sm text-muted-foreground">Nombor pekerja</dt>
                <dd>{{ employee.employee_no }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Nama penuh</dt>
                <dd>{{ employee.display_name }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Mula perkhidmatan</dt>
                <dd>{{ employee.service_start }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">Pengesahan</dt>
                <dd>{{ employee.confirmation_date ?? 'Belum ditetapkan' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    Tamat perkhidmatan
                </dt>
                <dd>{{ employee.service_end ?? 'Belum ditetapkan' }}</dd>
            </div>
            <template v-if="assignment"
                ><div>
                    <dt class="text-sm text-muted-foreground">Unit</dt>
                    <dd>{{ assignment.unit }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">Kategori</dt>
                    <dd>{{ assignment.category }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-muted-foreground">
                        Mula penempatan
                    </dt>
                    <dd>{{ assignment.effective_from }}</dd>
                </div></template
            >
            <p v-else class="text-sm text-muted-foreground sm:col-span-2">
                Tiada penempatan semasa yang boleh dipaparkan.
            </p>
        </dl>
    </main>
</template>
