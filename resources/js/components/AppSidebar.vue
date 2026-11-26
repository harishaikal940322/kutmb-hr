<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    KeyRound,
    LayoutGrid,
    Menu as MenuIcon,
    ShieldCheck,
    UsersRound,
    UserRound,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard, profile } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Papan Pemuka',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Profil Saya',
        href: profile(),
        icon: UserRound,
    },
];
const menuCatalog: Record<string, NavItem> = {
    'leave.annual.index': {
        title: 'Cuti Tahunan',
        href: '/leave/annual',
        icon: CalendarDays,
    },
    'leave.policy.drafts.index': {
        title: 'Draf Dasar Cuti',
        href: '/leave/policy-drafts',
        icon: MenuIcon,
    },
    'leave.calendar.drafts.index': {
        title: 'Draf Kalendar Cuti',
        href: '/leave/calendar-drafts',
        icon: MenuIcon,
    },
    'employees.self': {
        title: 'Profil Pekerja Saya',
        href: '/employee-profile',
        icon: UserRound,
    },
    'organization.offices.index': {
        title: 'Penetapan Pegawai',
        href: '/organization/offices',
        icon: UsersRound,
    },
    'employees.index': {
        title: 'Pekerja',
        href: '/employees',
        icon: UsersRound,
    },
    'organization.references.index': {
        title: 'Unit dan Kategori',
        href: '/organization/references',
        icon: UsersRound,
    },
    'users.index': {
        title: 'Pengguna',
        href: '/users',
        icon: UsersRound,
    },
    'iam.roles.index': {
        title: 'Peranan',
        href: '/administration/access/roles',
        icon: ShieldCheck,
    },
    'iam.permissions.index': {
        title: 'Kebenaran',
        href: '/administration/access/permissions',
        icon: KeyRound,
    },
    'iam.menus.index': {
        title: 'Akses Menu',
        href: '/administration/access/menus',
        icon: MenuIcon,
    },
};
const page = usePage<{ visibleMenuKeys: string[] }>();
const moduleKeys = new Set(['leave.annual.index', 'leave.annual.entitlements']);
const moduleItems = computed(() =>
    page.props.visibleMenuKeys.some((key) => moduleKeys.has(key))
        ? [menuCatalog['leave.annual.index']]
        : [],
);
const systemAdministrationKeys = new Set([
    'users.index',
    'iam.roles.index',
    'iam.permissions.index',
    'iam.menus.index',
]);
const systemAdministrationItems = computed(() =>
    page.props.visibleMenuKeys
        .filter((key) => systemAdministrationKeys.has(key))
        .map((key) => menuCatalog[key])
        .filter((item): item is NavItem => item !== undefined),
);
const workspaceItems = computed(() => [
    ...mainNavItems,
    ...page.props.visibleMenuKeys
        .filter((key) => !moduleKeys.has(key) && !systemAdministrationKeys.has(key))
        .map((key) => menuCatalog[key])
        .filter((item): item is NavItem => item !== undefined),
]);
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain
                :items="workspaceItems"
                :module-items="moduleItems"
                :admin-items="systemAdministrationItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
