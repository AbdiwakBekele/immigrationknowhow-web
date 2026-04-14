/**
 * Admin sidebar: flat main links only.
 * Settings links are rendered separately in AdminLayout.vue for super admins.
 */
export function adminMainNavItems({
    HomeIcon,
    UsersIcon,
    BriefcaseIcon,
    ShieldCheckIcon,
    StarIcon,
    BookOpenIcon,
    VideoCameraIcon,
    LinkIcon,
    Squares2X2Icon,
    ChartBarIcon,
    BanknotesIcon,
}) {
    return [
        { name: 'Dashboard', href: '/admin/dashboard', icon: HomeIcon },
        { name: 'Users', href: '/admin/users', icon: UsersIcon },
        { name: 'Subscribers', href: '/admin/subscribers', icon: UsersIcon },
        { name: 'Providers', href: '/admin/providers', icon: BriefcaseIcon },
        { name: 'Background Checks', href: '/admin/background-checks', icon: ShieldCheckIcon },
        { name: 'Reviews', href: '/admin/reviews', icon: StarIcon },
        { name: 'Library', href: '/admin/library', icon: BookOpenIcon },
        { name: 'Pending payments', href: '/admin/library-manual-payments', icon: BanknotesIcon },
        { name: 'Videos', href: '/admin/videos', icon: VideoCameraIcon },
        { name: 'Affiliates', href: '/admin/affiliates', icon: LinkIcon },
        { name: 'Service Types', href: '/admin/service-types', icon: Squares2X2Icon },
        { name: 'Reports', href: '/admin/reports', icon: ChartBarIcon },
    ];
}

export function adminSettingsNavItems() {
    return [
        { name: 'General', href: '/admin/settings' },
        { name: 'Library categories', href: '/admin/library-categories/create' },
    ];
}
