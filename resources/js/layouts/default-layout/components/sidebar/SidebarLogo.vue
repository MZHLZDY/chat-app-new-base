<script setup lang="ts">
import { onMounted, ref } from "vue";
import { ToggleComponent } from "@/assets/ts/components";
import { getAssetPath } from "@/core/helpers/assets";
import {
    layout,
    sidebarToggleDisplay,
    themeMode,
} from "@/layouts/default-layout/config/helper";
import { useSetting } from "@/services";

interface IProps {
    sidebarRef: HTMLElement | null;
}

const props = defineProps<IProps>();

const { data: setting = {} } = useSetting()

const toggleRef = ref<HTMLFormElement | null>(null);

onMounted(() => {
    setTimeout(() => {
        const toggleObj = ToggleComponent.getInstance(
            toggleRef.value!
        ) as ToggleComponent | null;

        if (toggleObj === null) {
            return;
        }

        // Add a class to prevent sidebar hover effect after toggle click
        toggleObj.on("kt.toggle.change", function () {
            // Set animation state
            props.sidebarRef?.classList.add("animating");

            // Wait till animation finishes
            setTimeout(function () {
                // Remove animation state
                props.sidebarRef?.classList.remove("animating");
            }, 300);
        });
    }, 1);
});
</script>

<template>
    <!--begin::Logo-->
    <div class="app-sidebar-logo px-6 modern-logo" id="kt_app_sidebar_logo">
        <!--begin::Logo image-->
        <router-link to="/" class="logo-link">
            <img v-if="layout === 'dark-sidebar' ||
                (themeMode === 'dark' && layout === 'light-sidebar')
                " alt="Logo" :src="setting?.logo" class="h-80px app-sidebar-logo-default logo-image" />
            <img v-if="themeMode === 'light' && layout === 'light-sidebar'" alt="Logo" :src="setting?.logo"
                class="h-50px app-sidebar-logo-default logo-image" />
            <img alt="Logo" :src="setting?.logo" class="h-65px app-sidebar-logo-minimize logo-image" />
        </router-link>
        <!--end::Logo image-->
        <!--begin::Sidebar toggle-->
        <div v-if="sidebarToggleDisplay" ref="toggleRef" id="kt_app_sidebar_toggle"
            class="app-sidebar-toggle modern-toggle"
            data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body"
            data-kt-toggle-name="app-sidebar-minimize">
            <KTIcon icon-name="black-left-line" icon-class="fs-3 rotate-180 ms-1" />
        </div>
        <!--end::Sidebar toggle-->
    </div>
    <!--end::Logo-->
</template>

<style lang="scss" scoped>
.modern-logo {
    background: rgba(255, 255, 255, 0.03);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 1.5rem 1.5rem !important;
    min-height: 90px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: relative;
}

.logo-link {
    display: flex;
    align-items: center;
    transition: transform 0.3s ease;
    
    &:hover {
        transform: scale(1.05);
    }
}

.logo-image {
    filter: brightness(1.1);
    transition: filter 0.3s ease;
    
    &:hover {
        filter: brightness(1.3);
    }
}

.modern-toggle {
    width: 32px !important;
    height: 32px !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    border: none !important;
    border-radius: 8px !important;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
    transition: all 0.3s ease !important;
    position: absolute !important;
    top: 50% !important;
    right: -16px !important;
    transform: translateY(-50%) !important;
    
    &:hover {
        transform: translateY(-50%) scale(1.1) !important;
        box-shadow: 0 6px 16px rgba(102, 126, 234, 0.6) !important;
    }
    
    &:active {
        transform: translateY(-50%) scale(0.95) !important;
    }
    
    i {
        color: white !important;
    }
}

// Minimize state
body[data-kt-app-sidebar-minimize="on"] {
    .modern-logo {
        justify-content: center;
    }
    
    .modern-toggle {
        right: -16px !important;
    }
}
</style>