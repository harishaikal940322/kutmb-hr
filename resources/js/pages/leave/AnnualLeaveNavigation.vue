<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CalendarDays, ChevronDown, ClipboardList, FileText, ListChecks, UsersRound, ShieldCheck, SlidersHorizontal } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';

export type AnnualSection = 'apply' | 'requests' | 'approvals' | 'team' | 'staff';
type NavigationSection = AnnualSection | 'calendar' | 'policy';
export type AnnualAccess = {
    sections: AnnualSection[];
    module: boolean;
    technical: boolean;
    needs_employee: boolean;
    needs_office: boolean;
    needs_unit: boolean;
    can_decide: boolean;
    can_review_partial: boolean;
    can_manage_calendar: boolean;
    can_manage_policy: boolean;
};
const props = defineProps<{ access: AnnualAccess; active: NavigationSection | null }>();
const catalog = {
    apply: { label: 'Mohon cuti', icon: ClipboardList, href: '/leave/annual?view=workflow&section=apply' },
    requests: { label: 'Permohonan saya', icon: FileText, href: '/leave/annual?view=workflow&section=requests' },
    approvals: { label: 'Sokongan & kelulusan', icon: ListChecks, href: '/leave/annual?view=workflow&section=approvals' },
    team: { label: 'Jadual unit', icon: UsersRound, href: '/leave/annual?view=workflow&section=team' },
    staff: { label: 'Profil & kelayakan', icon: UsersRound, href: '/leave/annual?section=staff' },
    calendar: { label: 'Kalendar & cuti umum', icon: CalendarDays, href: '/leave/calendar-drafts' },
    policy: { label: 'Dasar & kelayakan cuti', icon: SlidersHorizontal, href: '/leave/policy-drafts' },
};
const groups = computed(() => {
    const management: NavigationSection[] = props.access.sections.filter(item => item === 'staff');
    if (props.access.can_manage_calendar) management.push('calendar');
    if (props.access.can_manage_policy) management.push('policy');
    return [
        { label: 'Urusan cuti', items: props.access.sections.filter(item => item !== 'staff') as NavigationSection[] },
        { label: 'Pengurusan cuti', items: management },
    ].filter(group => group.items.length);
});
const open = ref(false);
const toggle = ref<HTMLButtonElement | null>(null);
watch(() => props.active, () => { open.value = false; });
async function close(event: KeyboardEvent) {
    if (!open.value) return;
    event.stopPropagation();
    open.value = false;
    await nextTick();
    toggle.value?.focus();
}
</script>

<template>
    <aside class="min-w-0 rounded-xl border border-border bg-card shadow-sm @min-[960px]/leave-module:sticky @min-[960px]/leave-module:top-4 @min-[960px]/leave-module:max-h-[calc(100dvh-2rem)] @min-[960px]/leave-module:overflow-y-auto">
        <div class="hidden border-b border-border px-4 py-4 @min-[960px]/leave-module:block">
            <div class="flex items-center gap-2.5">
                <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-border bg-muted"><CalendarDays class="size-4" aria-hidden="true" /></div>
                <div><p class="text-sm font-semibold">Submodul cuti</p><p class="text-xs text-muted-foreground">Akses akaun anda</p></div>
            </div>
        </div>
        <button ref="toggle" type="button" class="flex min-h-14 w-full items-center gap-3 rounded-xl px-4 py-3 text-left focus-visible:ring-2 focus-visible:ring-ring @min-[960px]/leave-module:hidden" :aria-expanded="open" aria-controls="annual-leave-subnav" @click="open = !open" @keydown.esc="close">
            <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-muted"><component :is="active ? catalog[active].icon : ShieldCheck" class="size-4" aria-hidden="true" /></span>
            <span class="min-w-0 flex-1"><span class="block text-xs text-muted-foreground">Navigasi submodul</span><span class="block text-sm font-semibold">{{ active ? catalog[active].label : 'Akses modul' }}</span></span>
            <ChevronDown class="size-4 shrink-0 text-muted-foreground transition-transform motion-reduce:transition-none" :class="open ? 'rotate-180' : ''" aria-hidden="true" />
        </button>
        <nav id="annual-leave-subnav" class="space-y-4 border-t border-border p-2.5 @min-[960px]/leave-module:block @min-[960px]/leave-module:border-t-0" :class="open ? 'block' : 'hidden'" aria-label="Submodul Cuti Tahunan" @keydown.esc="close">
            <div v-for="group in groups" :key="group.label" class="space-y-1">
                <p class="px-2.5 pt-1 text-xs font-medium text-muted-foreground">{{ group.label }}</p>
                <Link v-for="item in group.items" :key="item" :href="catalog[item].href" class="flex min-h-10 w-full items-center gap-2.5 rounded-md border-l-2 px-2.5 py-2 text-left text-sm leading-5 transition-colors focus-visible:ring-2 focus-visible:ring-ring motion-reduce:transition-none" :class="active === item ? 'border-primary bg-primary/10 font-semibold text-primary' : 'border-transparent font-medium text-muted-foreground hover:bg-muted hover:text-foreground'" :aria-current="active === item ? 'page' : undefined" @click="open = false">
                    <component :is="catalog[item].icon" class="size-4 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 flex-1">{{ catalog[item].label }}</span>
                </Link>
            </div>
            <p v-if="!groups.length" class="px-2.5 py-2 text-xs leading-5 text-muted-foreground">Tiada submodul yang tersedia untuk skop akaun ini.</p>
        </nav>
    </aside>
</template>
