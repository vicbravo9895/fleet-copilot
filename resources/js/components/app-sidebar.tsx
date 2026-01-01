import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { index as copilotIndex } from '@/actions/App/Http/Controllers/CopilotController';
import { dashboard } from '@/routes';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Building2, LayoutGrid, MessageSquare, Settings, Shield, Users } from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'Copilot',
        href: copilotIndex(),
        icon: MessageSquare,
    },
];

const superAdminNavItems: NavItem[] = [
    {
        title: 'Panel Admin',
        href: { url: '/super-admin', method: 'get' },
        icon: Shield,
    },
    {
        title: 'Empresas',
        href: { url: '/super-admin/companies', method: 'get' },
        icon: Building2,
    },
    {
        title: 'Todos los Usuarios',
        href: { url: '/super-admin/users', method: 'get' },
        icon: Users,
    },
];

const footerNavItems: NavItem[] = [
];

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const userRole = auth?.user?.role;
    const isSuperAdmin = userRole === 'super_admin';
    
    // Build nav items based on user role
    const navItems: NavItem[] = [...mainNavItems];
    
    // Add management items for admins and managers (not super_admin, they use separate section)
    if (userRole === 'admin' || userRole === 'manager') {
        navItems.push({
            title: 'Usuarios',
            href: { url: '/users', method: 'get' },
            icon: Users,
        });
    }
    
    // Add company settings for admins only (not super_admin)
    if (userRole === 'admin') {
        navItems.push({
            title: 'Empresa',
            href: { url: '/company', method: 'get' },
            icon: Building2,
        });
    }

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
                
                {/* Super Admin Section */}
                {isSuperAdmin && (
                    <>
                        <SidebarSeparator className="my-2" />
                        <NavMain items={superAdminNavItems} label="Super Admin" />
                    </>
                )}
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
