<script setup lang="ts">
import { getAssetPath } from "@/core/helpers/assets";
import { computed, ref } from "vue";
import KTUserMenu from "@/layouts/default-layout/components/menus/UserAccountMenu.vue";
import KTThemeModeSwitcher from "@/layouts/default-layout/components/theme-mode/ThemeModeSwitcher.vue";
import { ThemeModeComponent } from "@/assets/ts/layout";
import { useThemeStore } from "@/stores/theme";
import { useAuthStore } from "@/stores/auth";
import { useTahunStore } from "@/stores/tahun";

const store = useThemeStore();
const { user } = useAuthStore();

const themeMode = computed(() => {
    if (store.mode === "system") {
        return ThemeModeComponent.getSystemMode();
    }
    return store.mode;
});

const tahun = useTahunStore();
const tahuns = ref<Array<Number>>([]);
for (let i = new Date().getFullYear(); i >= new Date().getFullYear() - 2; i--) {
    tahuns.value.push(i);
}

const getUserAvatar = (photoPath: string | null | undefined) => {
    if (!photoPath) {
        return getAssetPath("media/avatars/blank.png");
    }
    if (photoPath.startsWith("http")) {
        return photoPath;
    }
    return `/storage/${photoPath}`;
};
</script>

<template>
    <!--begin::Navbar-->
    <div class="app-navbar flex-shrink-0 modern-navbar">
        <!--begin::Theme mode-->
        <!-- <div class="app-navbar-item me-10">
            <select2 class="form-select-solid w-125px" :options="tahuns" v-model="tahun.tahun"></select2>
        </div> -->
        <!--end::Theme mode-->

        <!--begin::Theme mode-->
        <div class="app-navbar-item ms-1 ms-md-3">
            <!--begin::Menu toggle-->
            <a
                href="#"
                class="modern-icon-btn theme-toggle"
                data-kt-menu-trigger="{default:'click', lg: 'hover'}"
                data-kt-menu-attach="parent"
                data-kt-menu-placement="bottom-end"
            >
                <span class="icon-wrapper">
                    <KTIcon
                        v-if="themeMode === 'light'"
                        icon-name="night-day"
                        icon-class="fs-2"
                    />
                    <KTIcon v-else icon-name="moon" icon-class="fs-2" />
                </span>
            </a>
            <!--begin::Menu toggle-->
            <KTThemeModeSwitcher />
        </div>
        <!--end::Theme mode-->

        <!--begin::User menu-->
        <div
            class="app-navbar-item ms-1 ms-md-4"
            id="kt_header_user_menu_toggle"
        >
            <!--begin::Menu wrapper-->
            <div
                class="cursor-pointer modern-user-avatar"
                data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
                data-kt-menu-attach="parent"
                data-kt-menu-placement="bottom-end"
            >
                <div class="avatar-container">
                    <img
                        :src="getUserAvatar(user?.photo)"
                        alt="user profile"
                        class="avatar-image"
                    />
                    <div class="avatar-status"></div>
                </div>
                <div class="user-info d-none d-md-flex">
                </div>
            </div>
            <KTUserMenu />
            <!--end::Menu wrapper-->
        </div>
        <!--end::User menu-->

        <!--begin::Header menu toggle-->
        <div
            class="app-navbar-item d-lg-none ms-2 me-n2"
            v-tooltip
            title="Show header menu"
        >
            <div
                class="modern-icon-btn menu-toggle"
                id="kt_app_header_menu_toggle"
            >
                <span class="icon-wrapper">
                    <KTIcon icon-name="element-4" icon-class="fs-2" />
                </span>
            </div>
        </div>
        <!--end::Header menu toggle-->
    </div>
    <!--end::Navbar-->
</template>

<style lang="scss" scoped>
.modern-navbar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

// Modern Icon Button
.modern-icon-btn {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    
    &::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        border-radius: 50%;
        background: rgba(102, 126, 234, 0.1);
        transform: translate(-50%, -50%);
        transition: width 0.4s ease, height 0.4s ease;
    }
    
    &:hover::before {
        width: 100%;
        height: 100%;
    }
    
    &:hover {
        background: white;
        border-color: #667eea;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
        transform: translateY(-2px);
        
        .icon-wrapper i {
            color: #667eea !important;
        }
    }
    
    &:active {
        transform: translateY(0);
    }
    
    .icon-wrapper {
        position: relative;
        z-index: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        
        i {
            color: #64748b;
            transition: color 0.3s ease;
        }
    }
}

// Theme Toggle Specific
.theme-toggle {
    &:hover .icon-wrapper i {
        animation: rotateIcon 0.6s ease;
    }
}

@keyframes rotateIcon {
    0%, 100% {
        transform: rotate(0deg);
    }
    50% {
        transform: rotate(180deg);
    }
}

// User Avatar Section
.modern-user-avatar {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.5rem;
    border-radius: 12px;
    transition: all 0.3s ease;
    
    &:hover {
        background: #f8f9fa;
        
        .avatar-container {
            transform: scale(1.05);
            
            .avatar-image {
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
            }
            
            .avatar-status {
                animation: pulse 1.5s ease-in-out infinite;
            }
        }
        
        .user-name {
            color: #667eea;
        }
    }
}

.avatar-container {
    position: relative;
    width: 40px;
    height: 40px;
    transition: transform 0.3s ease;
}

.avatar-image {
    width: 100%;
    height: 100%;
    border-radius: 12px;
    object-fit: cover;
    border: 2px solid white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
}

.avatar-status {
    position: absolute;
    bottom: -2px;
    right: -2px;
    width: 12px;
    height: 12px;
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    border: 2px solid white;
    border-radius: 50%;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.8;
        transform: scale(1.1);
    }
}

// User Info
.user-info {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.15rem;
    max-width: 150px;
}

.user-name {
    font-size: 0.9rem;
    font-weight: 600;
    color: #1e293b;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
    transition: color 0.3s ease;
}

.user-role {
    font-size: 0.75rem;
    font-weight: 500;
    color: #94a3b8;
    text-transform: capitalize;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 100%;
}

// Menu Toggle
.menu-toggle {
    &:hover {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
        
        .icon-wrapper i {
            color: white !important;
        }
    }
}

// Dark Mode
[data-bs-theme="dark"] {
    .modern-icon-btn {
        background: rgba(255, 255, 255, 0.05);
        border-color: rgba(255, 255, 255, 0.1);
        
        &:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: #667eea;
        }
        
        .icon-wrapper i {
            color: rgba(255, 255, 255, 0.7);
        }
    }
    
    .modern-user-avatar {
        &:hover {
            background: rgba(255, 255, 255, 0.05);
        }
    }
    
    .user-name {
        color: rgba(255, 255, 255, 0.9);
    }
    
    .user-role {
        color: rgba(255, 255, 255, 0.5);
    }
    
    .avatar-image {
        border-color: #1e1e2d;
    }
    
    .avatar-status {
        border-color: #1e1e2d;
    }
}

// Responsive
@media (max-width: 991px) {
    .modern-icon-btn {
        width: 40px;
        height: 40px;
    }
    
    .avatar-container {
        width: 36px;
        height: 36px;
    }
    
    .avatar-status {
        width: 10px;
        height: 10px;
    }
}

@media (max-width: 575px) {
    .modern-navbar {
        gap: 0.25rem;
    }
    
    .modern-icon-btn {
        width: 38px;
        height: 38px;
    }
    
    .avatar-container {
        width: 34px;
        height: 34px;
    }
}

// Animations
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.app-navbar-item {
    animation: fadeIn 0.5s ease-out;
}

// Stagger animation for navbar items
.app-navbar-item:nth-child(1) {
    animation-delay: 0.1s;
}

.app-navbar-item:nth-child(2) {
    animation-delay: 0.2s;
}

.app-navbar-item:nth-child(3) {
    animation-delay: 0.3s;
}
</style>