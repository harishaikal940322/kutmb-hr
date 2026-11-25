<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AnnualLeavePreview from '@/pages/AnnualLeavePreview.vue';

defineProps<{ canViewEntitlements?: boolean }>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Cuti Tahunan',
                href: '/leave/annual',
            },
        ],
    },
});
</script>

<template>
    <Head title="Cuti Tahunan" />
    <div v-if="canViewEntitlements" class="mx-auto flex w-full max-w-[100rem] justify-end px-4 pt-3">
        <Link href="/leave/annual" class="inline-flex min-h-10 items-center rounded-lg border border-border bg-card px-4 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring">
            Buka Profil &amp; Kelayakan (data pembangunan)
        </Link>
    </div>
    <AnnualLeavePreview />
</template>
