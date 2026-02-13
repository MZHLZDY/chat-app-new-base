<script setup lang="ts">
import { onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import MainMenuConfig from "@/layouts/default-layout/config/MainMenuConfig";
import { sidebarMenuIcons } from "@/layouts/default-layout/config/helper";
import { useI18n } from "vue-i18n";
import { useAuthStore } from "@/stores/auth";

const { user } = useAuthStore();

const { t, te } = useI18n();
const route = useRoute();
const scrollElRef = ref<null | HTMLElement>(null);

onMounted(() => {
    if (scrollElRef.value) {
        scrollElRef.value.scrollTop = 0;
    }
});

const translate = (text: string) => {
    if (te(text)) {
        return t(text);
    } else {
        return text;
    }
};

const hasActiveChildren = (match: string) => {
    return route.path.indexOf(match) !== -1;
};

const checkPermission = (menu: string) => {
    if (user.permission && user.permission.includes('dashboard')) {
        return true;
    }
    return user?.permission?.includes(menu);
}
</script>

<template>
    <!--begin::sidebar menu-->
    <div class="app-sidebar-menu overflow-hidden flex-column-fluid modern-menu-wrapper">
        <!--begin::Menu wrapper-->
        <div id="kt_app_sidebar_menu_wrapper" class="app-sidebar-wrapper hover-scroll-overlay-y my-5" data-kt-scroll="true"
            data-kt-scroll-activate="true" data-kt-scroll-height="auto"
            data-kt-scroll-dependencies="#kt_app_sidebar_logo, #kt_app_sidebar_footer"
            data-kt-scroll-wrappers="#kt_app_sidebar_menu" data-kt-scroll-offset="5px" data-kt-scroll-save-state="true">
            <!--begin::Menu-->
            <div id="#kt_app_sidebar_menu" class="menu menu-column menu-rounded menu-sub-indention px-3 modern-menu"
                data-kt-menu="true">
                <template v-for="(item, i) in MainMenuConfig" :key="i">
                    <div v-if="item.heading && checkPermission(item.name)" class="menu-item pt-5">
                        <div class="menu-content">
                            <span class="menu-heading modern-heading">
                                {{ translate(item.heading) }}
                            </span>
                        </div>
                    </div>
                    <template v-for="(menuItem, j) in item.pages" :key="j">
                        <template v-if="menuItem.heading && checkPermission(menuItem.name)">
                            <div class="menu-item modern-menu-item">
                                <router-link v-if="menuItem.route" class="menu-link modern-link" exact-active-class="active"
                                    :to="menuItem.route">
                                    <span v-if="menuItem.keenthemesIcon || menuItem.bootstrapIcon" class="menu-icon modern-icon">
                                        <i v-if="sidebarMenuIcons === 'bootstrap'" :class="menuItem.bootstrapIcon"
                                            class="bi fs-3"></i>
                                        <KTIcon v-else-if="sidebarMenuIcons === 'keenthemes'"
                                            :icon-name="menuItem.keenthemesIcon" icon-class="fs-2" />
                                    </span>
                                    <span class="menu-title modern-title">{{
                                        translate(menuItem.heading)
                                    }}</span>
                                </router-link>
                            </div>
                        </template>
                        <div v-if="menuItem.sectionTitle && menuItem.route && checkPermission(menuItem.name)"
                            :class="{ show: hasActiveChildren(menuItem.route) }" class="menu-item menu-accordion modern-menu-item"
                            data-kt-menu-sub="accordion" data-kt-menu-trigger="click">
                            <span class="menu-link modern-link">
                                <span v-if="menuItem.keenthemesIcon || menuItem.bootstrapIcon" class="menu-icon modern-icon">
                                    <i v-if="sidebarMenuIcons === 'bootstrap'" :class="menuItem.bootstrapIcon"
                                        class="bi fs-3"></i>
                                    <KTIcon v-else-if="sidebarMenuIcons === 'keenthemes'"
                                        :icon-name="menuItem.keenthemesIcon" icon-class="fs-2" />
                                </span>
                                <span class="menu-title modern-title">{{
                                    translate(menuItem.sectionTitle)
                                }}</span>
                                <span class="menu-arrow modern-arrow"></span>
                            </span>
                            <div :class="{ show: hasActiveChildren(menuItem.route) }" class="menu-sub menu-sub-accordion modern-submenu">
                                <template v-for="(item2, k) in menuItem.sub" :key="k">
                                    <div v-if="item2.heading && checkPermission(item2.name)" class="menu-item modern-submenu-item">
                                        <router-link v-if="item2.route" class="menu-link modern-sublink" exact-active-class="active"
                                            :to="item2.route">
                                            <span class="menu-bullet modern-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                            <span class="menu-title modern-subtitle">{{
                                                translate(item2.heading)
                                            }}</span>
                                        </router-link>
                                    </div>
                                    <div v-if="item2.sectionTitle && item2.route"
                                        :class="{ show: hasActiveChildren(item2.route) }" class="menu-item menu-accordion modern-submenu-item"
                                        data-kt-menu-sub="accordion" data-kt-menu-trigger="click">
                                        <span class="menu-link modern-sublink">
                                            <span class="menu-bullet modern-bullet">
                                                <span class="bullet bullet-dot"></span>
                                            </span>
                                            <span class="menu-title modern-subtitle">{{
                                                translate(item2.sectionTitle)
                                            }}</span>
                                            <span class="menu-arrow modern-arrow"></span>
                                        </span>
                                        <div :class="{ show: hasActiveChildren(item2.route) }"
                                            class="menu-sub menu-sub-accordion modern-submenu">
                                            <template v-for="(item3, k) in item2.sub" :key="k">
                                                <div v-if="item3.heading && checkPermission(item3.name)" class="menu-item modern-submenu-item">
                                                    <router-link v-if="item3.route" class="menu-link modern-sublink"
                                                        exact-active-class="active" :to="item3.route">
                                                        <span class="menu-bullet modern-bullet">
                                                            <span class="bullet bullet-dot"></span>
                                                        </span>
                                                        <span class="menu-title modern-subtitle">{{
                                                            translate(item3.heading)
                                                        }}</span>
                                                    </router-link>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </template>
            </div>
            <!--end::Menu-->
        </div>
        <!--end::Menu wrapper-->
    </div>
    <!--end::sidebar menu-->
</template>

<style lang="scss" scoped>
// Menu Wrapper
.modern-menu-wrapper {
    background: transparent;
}

.modern-menu {
    padding-top: 0.5rem;
}

// Menu Heading
.modern-heading {
    color: rgba(255, 255, 255, 0.5) !important;
    font-size: 0.75rem !important;
    font-weight: 700 !important;
    text-transform: uppercase !important;
    letter-spacing: 1px !important;
    padding: 0.5rem 1rem !important;
}

// Menu Item
.modern-menu-item {
    margin-bottom: 0.25rem;
    transition: all 0.3s ease;
}

// Menu Link
.modern-link {
    padding: 0.75rem 1rem !important;
    border-radius: 12px !important;
    transition: all 0.3s ease !important;
    position: relative;
    overflow: hidden;
    
    &::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.1), transparent);
        transition: left 0.5s;
    }
    
    &:hover {
        background: rgba(102, 126, 234, 0.15) !important;
        transform: translateX(4px);
        
        &::before {
            left: 100%;
        }
        
        .modern-icon {
            color: #667eea !important;
            transform: scale(1.1);
        }
        
        .modern-title {
            color: rgba(255, 255, 255, 0.95) !important;
        }
    }
    
    &.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
        
        .modern-icon {
            color: white !important;
        }
        
        .modern-title {
            color: white !important;
            font-weight: 600 !important;
        }
        
        &::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 60%;
            background: white;
            border-radius: 4px 0 0 4px;
        }
    }
}

// Menu Icon
.modern-icon {
    color: rgba(255, 255, 255, 0.6) !important;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    margin-right: 0.75rem;
}

// Menu Title
.modern-title {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.95rem !important;
    font-weight: 500 !important;
    transition: all 0.3s ease;
}

// Menu Arrow
.modern-arrow {
    color: rgba(255, 255, 255, 0.5) !important;
    transition: all 0.3s ease;
}

.menu-item.show > .menu-link .modern-arrow {
    transform: rotate(90deg);
    color: rgba(255, 255, 255, 0.8) !important;
}

// Submenu
.modern-submenu {
    background: rgba(0, 0, 0, 0.15) !important;
    border-radius: 8px;
    margin-top: 0.25rem;
    padding: 0.5rem 0;
}

.modern-submenu-item {
    margin-bottom: 0;
}

.modern-sublink {
    padding: 0.65rem 1rem 0.65rem 3rem !important;
    border-radius: 8px !important;
    transition: all 0.3s ease !important;
    
    &:hover {
        background: rgba(102, 126, 234, 0.1) !important;
        
        .modern-bullet .bullet {
            background: #667eea !important;
            transform: scale(1.3);
        }
        
        .modern-subtitle {
            color: rgba(255, 255, 255, 0.95) !important;
        }
    }
    
    &.active {
        background: rgba(102, 126, 234, 0.2) !important;
        
        .modern-bullet .bullet {
            background: #667eea !important;
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.6);
        }
        
        .modern-subtitle {
            color: #667eea !important;
            font-weight: 600 !important;
        }
    }
}

// Submenu Bullet
.modern-bullet {
    .bullet {
        background: rgba(255, 255, 255, 0.3) !important;
        width: 6px !important;
        height: 6px !important;
        transition: all 0.3s ease;
    }
}

// Submenu Title
.modern-subtitle {
    color: rgba(255, 255, 255, 0.7) !important;
    font-size: 0.9rem !important;
    font-weight: 400 !important;
    transition: all 0.3s ease;
}

// Scrollbar Styling
.app-sidebar-wrapper::-webkit-scrollbar {
    width: 6px;
}

.app-sidebar-wrapper::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}

.app-sidebar-wrapper::-webkit-scrollbar-thumb {
    background: rgba(102, 126, 234, 0.3);
    border-radius: 10px;
    
    &:hover {
        background: rgba(102, 126, 234, 0.5);
    }
}

// Minimize State
body[data-kt-app-sidebar-minimize="on"] {
    .modern-title,
    .modern-arrow,
    .modern-heading {
        display: none !important;
    }
    
    .modern-icon {
        margin-right: 0;
    }
    
    .modern-link {
        justify-content: center;
    }
}

// Animation keyframes
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(-10px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.modern-menu-item {
    animation: slideIn 0.3s ease-out;
}

// Stagger animation for menu items
@for $i from 1 through 20 {
    .modern-menu-item:nth-child(#{$i}) {
        animation-delay: #{$i * 0.05}s;
    }
}
</style>