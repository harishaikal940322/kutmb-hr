<script setup lang="ts">
import { computed, ref } from 'vue';

type Permission = {
    code: string;
    label: string;
    description: string;
    group: string;
    submodule: string;
    action: string;
    systemOnly: boolean;
};
const props = defineProps<{
    permissions: Permission[];
    modelValue: string[];
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string[]] }>();
const query = ref('');
const expanded = ref<string[]>([]);
const assignable = computed(() =>
    props.permissions.filter((p) => !p.systemOnly),
);
const groups = computed(() =>
    [...new Set(assignable.value.map((p) => p.group))]
        .map((name) => {
            const all = assignable.value.filter((p) => p.group === name);
            const term = query.value.trim().toLowerCase();
            const visible = all.filter(
                (p) =>
                    !term ||
                    [p.group, p.submodule, p.action, p.label, p.code].some(
                        (s) => s.toLowerCase().includes(term),
                    ),
            );
            const actions = [...new Set(visible.map((p) => p.action))];
            const rows = [...new Set(visible.map((p) => p.submodule))].map(
                (name) => ({
                    name,
                    permissions: visible.filter((p) => p.submodule === name),
                }),
            );
            return { name, all, visible, actions, rows };
        })
        .filter((group) => group.visible.length),
);
const selectedCount = computed(
    () =>
        assignable.value.filter((p) => props.modelValue.includes(p.code))
            .length,
);
function toggleGroup(name: string) {
    expanded.value = expanded.value.includes(name)
        ? expanded.value.filter((item) => item !== name)
        : [...expanded.value, name];
}
function togglePermission(code: string, checked: boolean) {
    emit(
        'update:modelValue',
        checked
            ? [...new Set([...props.modelValue, code])]
            : props.modelValue.filter((item) => item !== code),
    );
}
</script>

<template>
    <fieldset class="min-w-0 space-y-3 rounded-lg border border-border p-4">
        <legend class="px-1 text-sm font-medium">Permissions</legend>
        <div class="flex flex-wrap items-end justify-between gap-3">
            <label class="block min-w-0 flex-1 text-sm font-medium"
                >Search permissions
                <input
                    v-model="query"
                    type="search"
                    placeholder="Search module, action or code"
                    class="mt-1 h-10 w-full rounded-md border border-input bg-background px-3 text-sm focus-visible:ring-2 focus-visible:ring-ring"
                />
            </label>
            <span class="text-sm text-muted-foreground" aria-live="polite"
                >{{ selectedCount }} / {{ assignable.length }} selected</span
            >
        </div>
        <div class="flex flex-wrap gap-3 text-sm">
            <button
                type="button"
                class="rounded text-primary underline focus-visible:ring-2 focus-visible:ring-ring"
                @click="expanded = [...new Set(assignable.map((p) => p.group))]"
            >
                Expand all
            </button>
            <button
                type="button"
                class="rounded text-primary underline focus-visible:ring-2 focus-visible:ring-ring"
                @click="expanded = []"
            >
                Collapse all
            </button>
        </div>
        <p v-if="query.trim()" class="text-xs text-muted-foreground">
            Search results are expanded; clear search to restore your expansion
            choices.
        </p>
        <p v-if="!groups.length" class="text-sm text-muted-foreground">
            No matching assignable permissions.
        </p>
        <div
            v-for="group in groups"
            :key="group.name"
            class="overflow-hidden rounded-md border border-border"
        >
            <button
                type="button"
                class="flex min-h-11 w-full items-center justify-between gap-2 bg-muted/40 px-3 text-left text-sm font-semibold focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                :aria-expanded="!!query.trim() || expanded.includes(group.name)"
                @click="toggleGroup(group.name)"
            >
                <span>{{ group.name }}</span
                ><span class="shrink-0 text-xs text-muted-foreground"
                    >{{
                        group.all.filter((p) => modelValue.includes(p.code))
                            .length
                    }}
                    / {{ group.all.length }} selected
                    <span aria-hidden="true">{{
                        query.trim() || expanded.includes(group.name)
                            ? '−'
                            : '+'
                    }}</span></span
                >
            </button>
            <div
                v-if="query.trim() || expanded.includes(group.name)"
                class="p-3"
            >
                <div class="hidden overflow-x-auto sm:block">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr>
                                <th scope="col" class="p-2">Submodule</th>
                                <th
                                    v-for="action in group.actions"
                                    :key="action"
                                    scope="col"
                                    class="p-2"
                                >
                                    {{ action }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in group.rows"
                                :key="row.name"
                                class="border-t border-border"
                            >
                                <th
                                    scope="row"
                                    class="p-2 align-top font-medium"
                                >
                                    {{ row.name }}
                                </th>
                                <td
                                    v-for="action in group.actions"
                                    :key="action"
                                    class="p-2 align-top"
                                >
                                    <template
                                        v-if="
                                            row.permissions.some(
                                                (p) => p.action === action,
                                            )
                                        "
                                        ><label
                                            v-for="permission in row.permissions.filter(
                                                (p) => p.action === action,
                                            )"
                                            :key="permission.code"
                                            class="flex cursor-pointer items-start gap-2 py-1"
                                            ><input
                                                type="checkbox"
                                                :checked="
                                                    modelValue.includes(
                                                        permission.code,
                                                    )
                                                "
                                                class="mt-1 size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                                                @change="
                                                    togglePermission(
                                                        permission.code,
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).checked,
                                                    )
                                                "
                                            /><span
                                                ><span class="block">{{
                                                    permission.label
                                                }}</span
                                                ><span
                                                    class="block font-mono text-xs break-all text-muted-foreground"
                                                    >{{ permission.code }}</span
                                                ></span
                                            ></label
                                        ></template
                                    ><span
                                        v-else
                                        class="text-muted-foreground"
                                        aria-label="Unavailable"
                                        >—</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="space-y-3 sm:hidden">
                    <div v-for="row in group.rows" :key="row.name">
                        <h4 class="text-sm font-semibold">{{ row.name }}</h4>
                        <label
                            v-for="permission in row.permissions"
                            :key="permission.code"
                            class="flex min-h-11 cursor-pointer items-start gap-2 py-2 text-sm"
                            ><input
                                type="checkbox"
                                :checked="modelValue.includes(permission.code)"
                                class="mt-1 size-4 accent-primary focus-visible:ring-2 focus-visible:ring-ring"
                                @change="
                                    togglePermission(
                                        permission.code,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            /><span
                                ><span class="block"
                                    >{{ permission.action }}:
                                    {{ permission.label }}</span
                                ><span
                                    class="block font-mono text-xs break-all text-muted-foreground"
                                    >{{ permission.code }}</span
                                ></span
                            ></label
                        >
                    </div>
                </div>
            </div>
        </div>
    </fieldset>
</template>
