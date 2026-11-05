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
