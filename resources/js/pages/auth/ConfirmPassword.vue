<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineOptions({
    layout: {
        title: 'Confirm your password',
        description:
            'Re-enter your password before changing account security settings.',
    },
});
</script>

<template>
    <Head title="Confirm password" />
    <p class="mb-5 text-sm leading-6 text-muted-foreground">
        For your security, confirm your password before setting up an
        authenticator.
    </p>
    <Form
        action="/user/confirm-password"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                v-focus
                :aria-invalid="Boolean(errors.password)"
                :aria-describedby="
                    errors.password ? 'password-error' : undefined
                "
            />
            <InputError id="password-error" :message="errors.password" />
        </div>
        <Button type="submit" size="lg" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Confirm password
        </Button>
    </Form>
</template>
