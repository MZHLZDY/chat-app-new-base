import type { MenuItem } from "@/layouts/default-layout/config/types";

const MainMenuConfig: Array<MenuItem> = [
    {
        pages: [
            {
                heading: "Dashboard",
                name: "dashboard",
                route: "/dashboard",
                keenthemesIcon: "element-11",
            },
        ],
    },

    // WEBSITE
    {
        heading: "Website",
        route: "/dashboard/website",
        name: "website",
        pages: [
            // MASTER
            {
                sectionTitle: "Chat",
                route: "/master",
                keenthemesIcon: "message-text-2",
                name: "master",
                sub: [
                            {
                                heading: "Private Chat",
                                name: "private-chat",
                                route: "/dashboard/private-chat",
                            },
                            {
                                heading: "Group Chat",
                                name: "group-chat",
                                route: "/dashboard/group-chat",
                            },
                ],
            },
            {
                heading: "Setting",
                route: "/dashboard/setting",
                name: "setting",
                keenthemesIcon: "setting-2",
            },
        ],
    },
];

export default MainMenuConfig;
