<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    KeyRound,
    LayoutGrid,
    Menu as MenuIcon,
    ShieldCheck,
    UsersRound,
    UserRound,
} from '@lucide/vue';
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
        title: 'Direktori Pengguna',
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
const visibleMenus = page.props.visibleMenuKeys
    .map((key) => menuCatalog[key])
    .filter((item): item is NavItem => item !== undefined);
const systemAdministrationKeys = new Set([
    'iam.roles.index',
    'iam.permissions.index',
    'iam.menus.index',
]);
const systemAdministrationItems = page.props.visibleMenuKeys
    .filter((key) => systemAdministrationKeys.has(key))
    .map((key) => menuCatalog[key])
    .filter((item): item is NavItem => item !== undefined);
const workspaceItems = [
    ...mainNavItems,
    ...visibleMenus.filter((item) => !systemAdministrationItems.includes(item)),
];
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
                :admin-items="systemAdministrationItems"
            />
        </SidebarContent>

        <SidebarFooter>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
