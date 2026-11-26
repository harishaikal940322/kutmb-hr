<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

type Slot = { name: string; date: string; session: 'AM' | 'PM' };

const props = defineProps<{ today: string }>();
const month = ref(props.today.slice(0, 7));
const slots = ref<Slot[]>([]);
const loading = ref(false);
const error = ref('');
const selected = ref<string>();
const first = computed(() => new Date(`${month.value}-01T00:00:00Z`));
const offset = computed(() => (first.value.getUTCDay() + 6) % 7);
const monthLabel = computed(() =>
    first.value.toLocaleDateString('ms-MY', {
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }),
);
const dates = computed(() => {
    const last = new Date(
        Date.UTC(
            first.value.getUTCFullYear(),
            first.value.getUTCMonth() + 1,
            0,
        ),
    );
    return Array.from(
        { length: last.getUTCDate() },
        (_, index) => `${month.value}-${String(index + 1).padStart(2, '0')}`,
    );
});
const counts = computed(() => {
    const result: Record<string, number> = {};
    for (const slot of slots.value)
        result[slot.date] = (result[slot.date] ?? 0) + 1;
    return result;
});
const visibleSlots = computed(() =>
    selected.value
        ? slots.value.filter((slot) => slot.date === selected.value)
        : slots.value,
);

function changeMonth(step: number) {
    const next = new Date(
        Date.UTC(
            first.value.getUTCFullYear(),
            first.value.getUTCMonth() + step,
            1,
        ),
    );
    month.value = next.toISOString().slice(0, 7);
}

watch(
    month,
    async (value, _, onCleanup) => {
        const request = new AbortController();
        onCleanup(() => request.abort());
        selected.value = undefined;
        slots.value = [];
        error.value = '';
        loading.value = true;
        try {
            const response = await fetch(
                `/leave/annual/team?month=${encodeURIComponent(value)}`,
                {
                    headers: { Accept: 'application/json' },
                    signal: request.signal,
                },
            );
            if (!response.ok) {
                error.value =
                    response.status === 403
                        ? 'Kalendar tidak tersedia untuk skop unit anda.'
                        : 'Kalendar gagal dimuatkan.';
                return;
            }
            const result: { slots: Slot[] } = await response.json();
            if (!request.signal.aborted) slots.value = result.slots;
        } catch {
            if (!request.signal.aborted)
                error.value = 'Kalendar gagal dimuatkan.';
        } finally {
            if (!request.signal.aborted) loading.value = false;
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="grid gap-5 @min-[960px]/leave-content:grid-cols-[1.2fr_0.8fr]">
        <section
            class="min-w-0 rounded-2xl border border-border bg-card p-5 shadow-sm"
        >
            <div class="mb-4 flex items-center justify-between gap-2">
                <h2 class="font-semibold" aria-live="polite">
                    {{ monthLabel }}
                </h2>
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-lg border border-border hover:bg-muted"
                        aria-label="Bulan sebelum"
                        @click="changeMonth(-1)"
                    >
                        <ChevronLeft class="size-4" aria-hidden="true" />
                    </button>
                    <button
                        type="button"
                        class="grid size-9 place-items-center rounded-lg border border-border hover:bg-muted"
                        aria-label="Bulan seterusnya"
                        @click="changeMonth(1)"
                    >
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </button>
                </div>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs">
                <span
                    v-for="label in [
                        'Isn',
                        'Sel',
                        'Rab',
                        'Kha',
                        'Jum',
                        'Sab',
                        'Ahd',
                    ]"
                    :key="label"
                    class="pb-2 text-muted-foreground"
                    >{{ label }}</span
                >
                <span
                    v-for="empty in offset"
                    :key="`empty-${empty}`"
                    aria-hidden="true"
                />
                <button
                    v-for="date in dates"
                    :key="date"
                    type="button"
                    class="grid min-h-10 place-items-center rounded-md border border-transparent hover:border-border focus-visible:ring-2 focus-visible:ring-ring"
                    :class="{
                        'bg-primary/10 text-primary': counts[date],
                        'border-primary': selected === date,
                    }"
                    :aria-pressed="selected === date"
                    :aria-label="`${date}, ${counts[date] ?? 0} sesi cuti`"
                    @click="selected = selected === date ? undefined : date"
                >
                    {{ Number(date.slice(8)) }}
                </button>
            </div>
        </section>
        <section
            class="min-w-0 rounded-2xl border border-border bg-card p-5 shadow-sm"
        >
            <h2 class="font-semibold">
                Cuti diluluskan{{ selected ? ` · ${selected}` : '' }}
            </h2>
            <p
                v-if="loading"
                role="status"
                class="mt-3 text-sm text-muted-foreground"
            >
                Memuatkan kalendar…
            </p>
            <p
                v-else-if="error"
                role="alert"
                class="mt-3 text-sm text-destructive"
            >
                {{ error }}
            </p>
            <p
                v-else-if="!visibleSlots.length"
                class="mt-3 text-sm text-muted-foreground"
            >
                Tiada cuti diluluskan untuk tempoh ini.
            </p>
            <ul v-else class="mt-3 divide-y divide-border text-sm">
                <li
                    v-for="(slot, index) in visibleSlots"
                    :key="index"
                    class="flex flex-wrap items-center justify-between gap-2 py-2"
                >
                    <span>{{ slot.name }}</span>
                    <span class="text-muted-foreground"
                        >{{ slot.date }} ·
                        {{ slot.session === 'AM' ? 'Pagi' : 'Petang' }}</span
                    >
                </li>
            </ul>
        </section>
    </div>
</template>
