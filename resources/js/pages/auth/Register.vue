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

    <p class="mb-5 rounded-md border border-border bg-muted/40 px-4 py-3 text-sm leading-5 text-muted-foreground">
        This one-time setup is available only while the system has no accounts.
        The first account receives the System Admin role.
    </p>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
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
                    :aria-describedby="errors.username ? 'username-error' : undefined"
                />
                <InputError id="username-error" :message="errors.username" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    minlength="15"
                    autocomplete="new-password"
                    :aria-invalid="Boolean(errors.password)"
                    :aria-describedby="errors.password ? 'password-error' : undefined"
                />
                <InputError id="password-error" :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    minlength="15"
                    autocomplete="new-password"
                />
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
        <Link :href="login()" class="font-medium text-foreground underline underline-offset-4">
            Log in
        </Link>
    </p>
</template>
