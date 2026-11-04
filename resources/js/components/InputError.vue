<script setup lang="ts">
defineProps<{
    message?: string;
    id?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p
            :id="id"
            role="alert"
            class="text-sm leading-5 text-red-700 dark:text-red-400"
        >
            {{ message }}
        </p>
    </div>
</template>
