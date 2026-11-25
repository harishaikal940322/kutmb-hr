<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AnnualLeavePreview from '@/pages/AnnualLeavePreview.vue';

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
    <AnnualLeavePreview />
</template>
