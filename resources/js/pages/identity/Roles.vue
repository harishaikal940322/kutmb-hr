<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { KeyRound, Menu as MenuIcon, ShieldCheck } from '@lucide/vue';
import { reactive, ref } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import PermissionSelector from './components/PermissionSelector.vue';

type Permission = {
    code: string;
    label: string;
    description: string;
    group: string;
    systemOnly: boolean;
    submodule: string;
    action: string;
};

type Role = {
    id: number;
    code: string;
    label: string;
    isSystem: boolean;
    userCount: number;
    permissionCodes: string[];
};

type RoleTemplate = {
    key: string;
    label: string;
    description: string;
    permissions: string[];
    available: boolean;
};
const props = defineProps<{
    roles: Role[];
    permissions: Permission[];
    templates: RoleTemplate[];
}>();
const assignablePermissions = props.permissions.filter(
    (permission) => !permission.systemOnly,
);
const createForm = useForm({
    code: '',
    label: '',
    permission_codes: [] as string[],
});
const drafts = reactive(
    Object.fromEntries(
        props.roles.map((role) => [
            role.id,
            {
                label: role.label,
                permissionCodes: [...role.permissionCodes],
                errors: [] as string[],
            },
        ]),
    ) as Record<
        number,
        { label: string; permissionCodes: string[]; errors: string[] }
    >,
);
const savingRole = ref<number | null>(null);
const selectedTemplate = ref('');
const pendingTemplate = ref<RoleTemplate | null>(null);
const templateSelect = ref<HTMLSelectElement | null>(null);
const cancelTemplateButton = ref<HTMLButtonElement | null>(null);
function closeTemplateDialog() {
    pendingTemplate.value = null;
}
function confirmTemplate() {
    if (!pendingTemplate.value) return;
    createForm.permission_codes = [...pendingTemplate.value.permissions];
    selectedTemplate.value = pendingTemplate.value.key;
    closeTemplateDialog();
}
function applyTemplate(event: Event) {
    const select = event.target as HTMLSelectElement;
    if (!select.value) {
        selectedTemplate.value = '';
        return;
    }
    const template = props.templates.find((item) => item.key === select.value);
    if (!template || !template.available) {
        select.value = selectedTemplate.value;
        return;
    }
    if (createForm.permission_codes.length) {
        select.value = selectedTemplate.value;
        pendingTemplate.value = template;
        return;
    }
    createForm.permission_codes = [...template.permissions];
    selectedTemplate.value = template.key;
}
function conflicts(role: Role) {
    return role.permissionCodes.filter(
        (code) =>
            !assignablePermissions.some(
                (permission) => permission.code === code,
            ),
    );
}

function saveRole(role: Role) {
    const draft = drafts[role.id];
    savingRole.value = role.id;
    draft.errors = [];

    router.patch(
        `/administration/access/roles/${role.id}`,
        { label: draft.label, permission_codes: draft.permissionCodes },
        {
            preserveScroll: true,
            onError: (errors) => {
                draft.errors = Object.values(errors).flatMap((message) =>
                    Array.isArray(message) ? message : [message],
                );
            },
            onFinish: () => {
                savingRole.value = null;
            },
        },
    );
}

function deleteRole(role: Role) {
    if (role.isSystem || role.userCount > 0) return;
    if (!window.confirm(`Delete the ${role.label} role?`)) return;

    router.delete(`/administration/access/roles/${role.id}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Roles and permissions" />

    <main
        class="mx-auto w-full max-w-6xl space-y-7 px-4 py-7 sm:px-6 lg:px-8"
        aria-labelledby="roles-heading"
    >
        <header class="space-y-2">
            <p
                class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
            >
                System administration
            </p>
            <h1
                id="roles-heading"
                class="text-3xl font-semibold tracking-tight sm:text-4xl"
            >
                Roles and permissions
            </h1>
            <p
                class="max-w-3xl text-sm leading-6 text-muted-foreground sm:text-base"
            >
                Define reusable roles and choose the system permissions each
                role can use. Account invitations and role assignment to users
                are not available here.
            </p>
            <nav
                class="flex flex-wrap gap-2 pt-2"
                aria-label="Access management"
            >
                <span
                    class="inline-flex min-h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground"
                    ><ShieldCheck
                        class="size-4"
                        aria-hidden="true"
                    />Roles</span
                >
                <Link
                    href="/administration/access/permissions"
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    ><KeyRound
                        class="size-4"
                        aria-hidden="true"
                    />Permissions</Link
                >
                <Link
                    href="/administration/access/menus"
                    class="inline-flex min-h-10 items-center gap-2 rounded-md border border-input px-3 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    ><MenuIcon class="size-4" aria-hidden="true" />Menu
                    access</Link
                >
            </nav>
        </header>

        <section
            class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6"
            aria-labelledby="create-role-heading"
        >
            <div class="mb-5 space-y-1">
                <h2 id="create-role-heading" class="text-lg font-semibold">
                    Create a role
                </h2>
                <p class="text-sm text-muted-foreground">
                    Role keys are permanent identifiers. Use a short lowercase
                    name with hyphens.
                </p>
            </div>
            <form
                class="grid gap-5 lg:grid-cols-[minmax(13rem,0.7fr)_minmax(14rem,1fr)_minmax(0,2fr)] lg:items-start"
                @submit.prevent="
                    createForm.post('/administration/access/roles', {
                        preserveScroll: true,
                        onSuccess: () => {
                            createForm.reset();
                            selectedTemplate = '';
                        },
                    })
                "
            >
                <div class="space-y-2">
                    <label for="role-code" class="text-sm font-medium"
                        >Role key</label
                    >
                    <input
                        id="role-code"
                        v-model="createForm.code"
                        name="code"
                        required
                        maxlength="80"
                        pattern="[a-z][a-z0-9-]*"
                        placeholder="account-reviewer"
                        class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <p
                        v-if="createForm.errors.code"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ createForm.errors.code }}
                    </p>
                </div>
                <div class="space-y-2">
                    <label for="role-label" class="text-sm font-medium"
                        >Display name</label
                    >
                    <input
                        id="role-label"
                        v-model="createForm.label"
                        name="label"
                        required
                        maxlength="160"
                        placeholder="Account Reviewer"
                        class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    />
                    <p
                        v-if="createForm.errors.label"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ createForm.errors.label }}
                    </p>
                </div>
                <div class="min-w-0 space-y-3">
                    <label for="role-template" class="block text-sm font-medium"
                        >Optional role template</label
                    >
                    <select
                        id="role-template"
                        ref="templateSelect"
                        :value="selectedTemplate"
                        class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                        @change="applyTemplate"
                    >
                        <option value="">Choose a template</option>
                        <option
                            v-for="template in templates"
                            :key="template.key"
                            :value="template.key"
                            :disabled="!template.available"
                        >
                            {{ template.label
                            }}{{ template.available ? '' : ' (unavailable)' }}
                        </option>
                    </select>
                    <p
                        v-for="template in templates"
                        :key="template.key"
                        class="text-xs text-muted-foreground"
                    >
                        {{ template.label }}: {{ template.description }}
                        <span v-if="!template.available"
                            >Required permissions are unavailable.</span
                        ><span v-else
                            >Includes
                            {{ template.permissions.join(', ') }}.</span
                        >
                    </p>
                    <PermissionSelector
                        v-model="createForm.permission_codes"
                        :permissions="permissions"
                    />
                    <p
                        v-if="createForm.errors.permission_codes"
                        class="text-sm text-destructive"
                        role="alert"
                    >
                        {{ createForm.errors.permission_codes }}
                    </p>
                </div>
                <div class="lg:col-span-3 lg:flex lg:justify-end">
                    <button
                        type="submit"
                        :disabled="createForm.processing"
                        class="inline-flex min-h-11 w-full items-center justify-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-60 lg:w-auto"
                    >
                        {{
                            createForm.processing ? 'Creating…' : 'Create role'
                        }}
                    </button>
                </div>
            </form>
        </section>

        <section class="space-y-4" aria-labelledby="existing-roles-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2
                        id="existing-roles-heading"
                        class="text-xl font-semibold"
                    >
                        Configured roles
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ roles.length }}
                        {{ roles.length === 1 ? 'role' : 'roles' }} in the
                        access catalog
                    </p>
                </div>
            </div>
            <div v-if="roles.length" class="space-y-4">
                <article
                    v-for="role in roles"
                    :key="role.id"
                    class="rounded-xl border border-border bg-card p-5 shadow-sm sm:p-6"
                >
                    <div
                        class="mb-5 flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-lg font-semibold">
                                    {{ role.label }}
                                </h3>
                                <span
                                    v-if="role.isSystem"
                                    class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-semibold text-primary"
                                    >Protected system role</span
                                >
                            </div>
                            <p
                                class="mt-1 font-mono text-xs text-muted-foreground"
                            >
                                {{ role.code }}
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-muted px-3 py-1.5 text-xs font-medium text-muted-foreground"
                            >{{ role.userCount }}
                            {{
                                role.userCount === 1 ? 'account' : 'accounts'
                            }}</span
                        >
                    </div>

                    <form
                        v-if="!role.isSystem"
                        class="space-y-5"
                        @submit.prevent="saveRole(role)"
                    >
                        <div class="max-w-xl space-y-2">
                            <label
                                :for="`role-label-${role.id}`"
                                class="text-sm font-medium"
                                >Display name</label
                            >
                            <input
                                :id="`role-label-${role.id}`"
                                v-model="drafts[role.id].label"
                                required
                                maxlength="160"
                                class="h-11 w-full rounded-md border border-input bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            />
                        </div>
                        <PermissionSelector
                            v-model="drafts[role.id].permissionCodes"
                            :permissions="permissions"
                        />
                        <p
                            v-if="conflicts(role).length"
                            role="alert"
                            class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            This role has protected or unrecognized permissions
                            that cannot be edited here:
                            {{ conflicts(role).join(', ') }}. Review them
                            outside this screen before saving.
                        </p>
                        <p
                            v-if="drafts[role.id].errors.length"
                            role="alert"
                            class="rounded-md bg-destructive/10 p-3 text-sm text-destructive"
                        >
                            {{ drafts[role.id].errors.join(' ') }}
                        </p>
                        <div
                            class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
                        >
                            <button
                                type="button"
                                :disabled="role.userCount > 0"
                                :title="
                                    role.userCount > 0
                                        ? 'Remove all account assignments before deleting this role.'
                                        : undefined
                                "
                                class="inline-flex min-h-11 items-center justify-center rounded-md px-4 text-sm font-medium text-destructive hover:bg-destructive/10 focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto"
                                @click="deleteRole(role)"
                            >
                                Delete role
                            </button>
                            <button
                                type="submit"
                                :disabled="
                                    savingRole === role.id ||
                                    conflicts(role).length > 0
                                "
                                class="inline-flex min-h-11 items-center justify-center rounded-md bg-primary px-5 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-60"
                            >
                                {{
                                    savingRole === role.id
                                        ? 'Saving…'
                                        : 'Save role'
                                }}
                            </button>
                        </div>
                    </form>
                    <div v-else class="space-y-3 border-t border-border pt-4">
                        <h4 class="text-sm font-semibold">
                            Fixed system permissions
                        </h4>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="code in role.permissionCodes"
                                :key="code"
                                class="rounded-md bg-muted px-2.5 py-1.5 font-mono text-xs"
                                >{{ code }}</span
                            >
                        </div>
                        <p class="text-sm leading-5 text-muted-foreground">
                            This role is reserved for the System Administrator
                            and cannot be renamed, deleted, or delegated through
                            this screen.
                        </p>
                    </div>
                </article>
            </div>
            <div
                v-else
                class="rounded-xl border border-dashed border-border px-6 py-12 text-center"
            >
                <h3 class="font-semibold">No roles configured</h3>
                <p class="mt-2 text-sm text-muted-foreground">
                    Create a role to begin organizing system permissions.
                </p>
            </div>
        </section>
    </main>
    <Dialog
        :open="pendingTemplate !== null"
        @update:open="
            (open) => {
                if (!open) closeTemplateDialog();
            }
        "
    >
        <DialogContent
            :show-close-button="false"
            @open-auto-focus="
                (event) => {
                    event.preventDefault();
                    cancelTemplateButton?.focus();
                }
            "
            @close-auto-focus="
                (event) => {
                    event.preventDefault();
                    templateSelect?.focus();
                }
            "
        >
            <DialogHeader>
                <DialogTitle>Replace permission selection?</DialogTitle>
                <DialogDescription
                    >Applying {{ pendingTemplate?.label }} replaces the current
                    permission selection. You can review and edit it before
                    saving the role.</DialogDescription
                >
            </DialogHeader>
            <DialogFooter>
                <button
                    ref="cancelTemplateButton"
                    type="button"
                    class="min-h-11 rounded-md border border-input px-4 text-sm font-medium hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring"
                    @click="closeTemplateDialog"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    class="min-h-11 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring"
                    @click="confirmTemplate"
                >
                    Replace selection
                </button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
