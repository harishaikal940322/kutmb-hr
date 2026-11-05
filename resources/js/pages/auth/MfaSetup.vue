<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

defineProps<{
    enabled: boolean;
    pendingSecret: string | null;
    recoveryCodes?: string[] | null;
    status?: string | null;
}>();

defineOptions({
    layout: {
        title: 'Multi-factor authentication',
        description: 'Set up and verify an authenticator for your account.',
    },
});
</script>

<template>
    <Head title="Multi-factor authentication" />

    <div
        v-if="status"
        role="status"
        aria-live="polite"
        class="mb-5 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm leading-5"
    >
        {{ status }}
    </div>

    <section
        v-if="recoveryCodes?.length"
        aria-labelledby="recovery-codes-heading"
        class="mb-5 space-y-3 rounded-md border border-border p-4"
        role="alert"
    >
        <h2 id="recovery-codes-heading" class="font-semibold">
            Save your one-time recovery codes
        </h2>
        <p class="text-sm leading-5 text-muted-foreground">
            Each code works once if you cannot access your authenticator. These
            codes will not be shown again.
        </p>
        <ul class="grid grid-cols-2 gap-2 font-mono text-sm">
            <li v-for="code in recoveryCodes" :key="code">{{ code }}</li>
        </ul>
    </section>

    <section v-if="enabled" class="space-y-3">
        <h2 class="font-semibold">Authenticator enabled</h2>
        <p class="text-sm leading-6 text-muted-foreground">
            Sign-ins require a current authenticator code or an unused recovery
            code. The factor cannot be removed through this development
            interface.
        </p>
    </section>

    <div v-else class="space-y-5">
        <div
            v-if="pendingSecret"
            class="space-y-3 rounded-md border border-border p-4"
        >
            <h2 class="font-semibold">Add this key to your authenticator</h2>
            <p class="text-sm leading-5 text-muted-foreground">
                Use a time-based one-time password (TOTP) account. Enter this
                key manually if your app cannot scan a QR code.
            </p>
            <code
                class="block rounded bg-muted p-3 text-sm font-semibold tracking-wider break-all"
                >{{ pendingSecret }}</code
            >
        </div>

        <Form
            v-if="!pendingSecret"
            action="/security/mfa/setup"
            method="post"
            v-slot="{ processing }"
        >
            <Button
                type="submit"
                size="lg"
                class="w-full"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Start authenticator setup
            </Button>
        </Form>

        <Form
            v-else
            action="/security/mfa/confirm"
            method="post"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-4"
        >
            <div class="grid gap-2">
                <Label for="code">Six-digit authenticator code</Label>
                <Input
                    id="code"
                    name="code"
                    required
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    minlength="6"
                    maxlength="6"
                    v-focus
                    :aria-invalid="Boolean(errors.code)"
                    :aria-describedby="errors.code ? 'code-error' : undefined"
                />
                <InputError id="code-error" :message="errors.code" />
            </div>
            <Button
                type="submit"
                size="lg"
                class="w-full"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Verify and enable MFA
            </Button>
        </Form>
    </div>
</template>
