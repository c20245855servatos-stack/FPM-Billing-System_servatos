import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Archive, FolderKanban, LayoutGrid, ReceiptText, SquarePen } from 'lucide-react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            url: '/dashboard',
            icon: LayoutGrid,
        },
        ...(auth.role === 'user'
            ? [{ title: 'Create project', url: '/create-project', icon: SquarePen }]
            : []),
        ...(auth.role === 'user' && auth.hasProjects
            ? [{ title: 'Projects', url: '/my-projects', icon: FolderKanban }]
            : []),
        {
            title: 'Invoices',
            url: '/invoices',
            icon: ReceiptText,
        },
        ...(auth.role === 'admin'
            ? [
                { title: 'Projects', url: '/projects', icon: FolderKanban },
                { title: 'Archive', url: '/projects?show_archived=1', icon: Archive },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>
            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>
            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
