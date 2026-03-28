import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuAction,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChevronDown,
    Folder,
    LayoutGrid,
    Settings,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const page = usePage();
    const settingsItems = useMemo(
        () => [
            { title: 'Profile', href: '/settings/profile' },
            { title: 'Password', href: '/settings/password' },
            { title: 'Two-Factor Auth', href: '/settings/two-factor' },
            { title: 'Appearance', href: '/settings/appearance' },
        ],
        [],
    );
    const isSettingsActive = page.url.startsWith('/settings');
    const [settingsOpen, setSettingsOpen] = useState(isSettingsActive);

    useEffect(() => {
        if (isSettingsActive) {
            setSettingsOpen(true);
        }
    }, [isSettingsActive]);

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
                <NavMain items={mainNavItems} />
                <SidebarMenu className="px-2">
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            asChild
                            isActive={isSettingsActive}
                            tooltip={{ children: 'Settings' }}
                        >
                            <Link href="/settings" prefetch>
                                <Settings />
                                <span>Settings</span>
                            </Link>
                        </SidebarMenuButton>
                        <SidebarMenuAction
                            aria-label={
                                settingsOpen
                                    ? 'Collapse settings menu'
                                    : 'Expand settings menu'
                            }
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                setSettingsOpen((open) => !open);
                            }}
                            showOnHover
                        >
                            <ChevronDown
                                className={`h-4 w-4 transition-transform ${
                                    settingsOpen ? 'rotate-180' : ''
                                }`}
                            />
                        </SidebarMenuAction>
                        {settingsOpen && (
                            <SidebarMenuSub>
                                {settingsItems.map((item) => (
                                    <SidebarMenuSubItem key={item.href}>
                                        <SidebarMenuSubButton
                                            asChild
                                            isActive={page.url.startsWith(
                                                item.href,
                                            )}
                                        >
                                            <Link href={item.href} prefetch>
                                                <span>{item.title}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                ))}
                            </SidebarMenuSub>
                        )}
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
