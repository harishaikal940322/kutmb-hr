<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

const recoveryMode = ref(false);

defineOptions({
    layout: {
        title: 'Verify your sign-in',
        description:
            'Enter a code from your authenticator or a one-time recovery code.',
    },
});
</script>

<template>
    <Head title="Verify sign-in" />

    <p class="mb-5 text-sm leading-6 text-muted-foreground">
        Your password was accepted. Complete the second step to sign in.
    </p>

    <Form
        action="/two-factor-challenge"
        method="post"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-5"
    >
        <div class="grid gap-2">
            <Label :for="recoveryMode ? 'recovery_code' : 'code'">
                {{
                    recoveryMode
                        ? 'One-time recovery code'
                        : 'Authenticator code'
                }}
            </Label>
            <Input
                v-if="recoveryMode"
                id="recovery_code"
                name="recovery_code"
                required
                autocomplete="one-time-code"
                v-focus
                :aria-invalid="Boolean(errors.recovery_code)"
                :aria-describedby="
                    errors.recovery_code ? 'code-error' : undefined
                "
            />
            <Input
                v-else
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
            <InputError
                id="code-error"
                :message="errors.code ?? errors.recovery_code"
            />
        </div>

        <button
            type="button"
            class="min-h-11 self-start text-sm font-medium underline underline-offset-4"
            @click="recoveryMode = !recoveryMode"
        >
            {{
                recoveryMode
                    ? 'Use authenticator code instead'
                    : 'Use a recovery code instead'
            }}
        </button>

        <Button type="submit" size="lg" class="w-full" :disabled="processing">
            <Spinner v-if="processing" />
            Verify and log in
        </Button>
    </Form>
</template>
