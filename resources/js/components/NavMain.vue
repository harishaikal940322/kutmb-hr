<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Settings2 } from '@lucide/vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';
import { ref, watch } from 'vue';

const props = defineProps<{
    items: NavItem[];
    adminItems: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();
const isAdminOpen = ref(
    props.adminItems.some((item) => isCurrentUrl(item.href)),
);

watch(
    () => props.adminItems.some((item) => isCurrentUrl(item.href)),
    (hasActiveAdminItem) => {
        if (hasActiveAdminItem) isAdminOpen.value = true;
    },
);
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Ruang Kerja</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <Collapsible
                v-if="adminItems.length"
                v-model:open="isAdminOpen"
                as-child
            >
                <SidebarMenuItem>
                    <CollapsibleTrigger as-child>
                        <SidebarMenuButton
                            :is-active="
                                adminItems.some((item) =>
                                    isCurrentUrl(item.href),
                                )
                            "
                            tooltip="Pentadbiran Sistem"
                            aria-label="Buka atau tutup menu Pentadbiran Sistem"
                        >
                            <Settings2 />
                            <span>Pentadbiran Sistem</span>
                            <ChevronDown
                                class="ml-auto transition-transform"
                                :class="isAdminOpen ? 'rotate-180' : ''"
                            />
                        </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <SidebarMenuSub>
                            <SidebarMenuSubItem
                                v-for="item in adminItems"
                                :key="item.title"
                            >
                                <SidebarMenuSubButton
                                    as-child
                                    :is-active="isCurrentUrl(item.href)"
                                >
                                    <Link :href="item.href">
                                        <component :is="item.icon" />
                                        <span>{{ item.title }}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        </SidebarMenuSub>
                    </CollapsibleContent>
                </SidebarMenuItem>
            </Collapsible>
        </SidebarMenu>
    </SidebarGroup>
</template>
