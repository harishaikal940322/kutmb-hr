<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Enter your username and password below to log in',
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Log in" />

    <div
        v-if="status"
        role="status"
        aria-live="polite"
        class="mb-4 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm font-medium"
    >
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    type="text"
                    name="username"
                    required
                    v-focus
                    autocomplete="username"
                    :aria-invalid="Boolean(errors.username)"
                    :aria-describedby="errors.username ? 'username-error' : undefined"
                />
                <InputError id="username-error" :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">Password</Label>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="errors.password ? 'password-error' : undefined"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="mt-1 w-full"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                Log in
            </Button>
        </div>
    </Form>
</template>
