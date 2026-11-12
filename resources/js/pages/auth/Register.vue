<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register';
import { login } from '@/routes';

defineProps<{ mfaEnabled: boolean; passwordMinimum: number }>();

defineOptions({
    layout: {
        title: 'Set up the first System Administrator',
        description:
            'Create the first account for this local development system.',
    },
});
</script>

<template>
    <Head title="Set up administrator" />

    <p
        class="mb-5 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm leading-5 text-muted-foreground"
    >
        This one-time setup is available only while the system has no accounts.
        The first account receives the System Admin role.
        {{
            mfaEnabled
                ? 'It must enroll an authenticator before the account is created.'
                : `Use a password of at least ${passwordMinimum} characters for password-only development sign-in.`
        }}
    </p>

    <section
        v-if="mfaEnabled"
        class="mb-5 space-y-2 rounded-md border border-border px-4 py-3"
        aria-labelledby="mfa-setup-heading"
    >
        <h2 id="mfa-setup-heading" class="font-semibold">
            Set up an authenticator
        </h2>
        <p class="text-sm leading-5 text-muted-foreground">
            Open your authenticator app, add an account, and scan this QR code.
            Confirm with the current six-digit code from the app.
        </p>
        <div class="flex justify-center rounded-md bg-white p-3">
            <img
                src="/register/mfa-qr-code"
                alt="First System Administrator authenticator QR code. Scan it with your authenticator app."
                width="256"
                height="256"
                class="h-64 w-64 max-w-full"
                decoding="async"
            />
        </div>
        <p class="text-sm leading-5 text-muted-foreground">
            This QR code contains the sign-in secret. Keep it private.
        </p>
    </section>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation', 'mfa_code']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <Label for="name">Display name</Label>
                <Input
                    id="name"
                    name="name"
                    type="text"
                    required
                    maxlength="255"
                    autocomplete="name"
                    v-focus
                    :aria-invalid="Boolean(errors.name)"
                    :aria-describedby="errors.name ? 'name-error' : undefined"
                />
                <InputError id="name-error" :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="username">Username</Label>
                <Input
                    id="username"
                    name="username"
                    type="text"
                    required
                    maxlength="64"
                    autocomplete="username"
                    :aria-invalid="Boolean(errors.username)"
                    :aria-describedby="
                        errors.username ? 'username-error' : undefined
                    "
                />
                <InputError id="username-error" :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :minlength="passwordMinimum"
                    autocomplete="new-password"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="
                        errors.password ? 'password-error' : undefined
                    "
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    :minlength="passwordMinimum"
                    autocomplete="new-password"
                />
            </div>

            <div v-if="mfaEnabled" class="grid gap-2">
                <Label for="mfa_code">Authenticator code</Label>
                <Input
                    id="mfa_code"
                    name="mfa_code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    minlength="6"
                    maxlength="6"
                    required
                    :aria-invalid="Boolean(errors.mfa_code)"
                    :aria-describedby="
                        errors.mfa_code ? 'mfa-code-error' : undefined
                    "
                />
                <InputError id="mfa-code-error" :message="errors.mfa_code" />
            </div>

            <Button
                type="submit"
                size="lg"
                class="w-full"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                Create System Administrator
            </Button>
        </div>
    </Form>

    <p class="mt-5 text-center text-sm text-muted-foreground">
        Already set up?
        <Link
            :href="login()"
            class="font-medium text-foreground underline underline-offset-4"
        >
            Log in
        </Link>
    </p>
</template>
