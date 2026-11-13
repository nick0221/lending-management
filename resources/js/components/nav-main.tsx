import { Link } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useEffect, useState } from 'react';
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
    useSidebar,
} from '@/components/ui/sidebar';
import type { IsCurrentOrParentUrlFn } from '@/hooks/use-current-url';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavEntry, NavGroupItem, NavItem } from '@/types';

export function NavMain({
    items,
    label = 'Platform',
}: {
    items: NavEntry[];
    label?: string;
}) {
    const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    'items' in item ? (
                        <NavGroup
                            key={item.title}
                            group={item}
                            isCurrentOrParentUrl={isCurrentOrParentUrl}
                        />
                    ) : (
                        <NavLink
                            key={item.title}
                            item={item}
                            isActive={isCurrentUrl(item.href)}
                        />
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}

function NavLink({ item, isActive }: { item: NavItem; isActive: boolean }) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isActive}
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function NavGroup({
    group,
    isCurrentOrParentUrl,
}: {
    group: NavGroupItem;
    isCurrentOrParentUrl: IsCurrentOrParentUrlFn;
}) {
    const { currentUrl } = useCurrentUrl();
    const { isMobile, state, setOpen: setSidebarOpen } = useSidebar();
    const hasActiveChild = group.items.some((item) =>
        isCurrentOrParentUrl(item.href),
    );
    const [open, setOpen] = useState(hasActiveChild);

    useEffect(() => {
        if (hasActiveChild) {
            setOpen(true);
        }
    }, [currentUrl, hasActiveChild]);

    const handleOpenChange = (nextOpen: boolean) => {
        // In icon mode the children are hidden, so expand the sidebar instead
        // of toggling a group the user cannot see.
        if (!isMobile && state === 'collapsed') {
            setOpen(true);
            setSidebarOpen(true);

            return;
        }

        setOpen(nextOpen);
    };

    return (
        <SidebarMenuItem>
            <Collapsible
                open={open}
                onOpenChange={handleOpenChange}
                className="group/collapsible"
            >
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        isActive={hasActiveChild}
                        tooltip={{ children: group.title }}
                    >
                        {group.icon && <group.icon />}
                        <span className="truncate">{group.title}</span>
                        <ChevronDown className="ml-auto transition-transform duration-200 group-data-[collapsible=icon]:hidden group-data-[state=open]/collapsible:rotate-180" />
                    </SidebarMenuButton>
                </CollapsibleTrigger>

                <CollapsibleContent>
                    <SidebarMenuSub>
                        {group.items.map((item) => (
                            <SidebarMenuSubItem key={item.title}>
                                <SidebarMenuSubButton
                                    asChild
                                    isActive={isCurrentOrParentUrl(item.href)}
                                >
                                    <Link href={item.href} prefetch>
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuSubButton>
                            </SidebarMenuSubItem>
                        ))}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </Collapsible>
        </SidebarMenuItem>
    );
}
