<template>
    <center>
    <div class="w-lg-500px w-100">
        <div class="text-center mb-10">
            <img
                v-if="setting?.logo"
                :src="setting.logo"
                alt="Logo"
                class="w-100px mb-8"
            />
        </div>

        <div class="card shadow-sm">
            <div class="card-body p-10 text-center">
                <!-- Success Icon -->
                <div class="mb-10">
                    <div class="symbol symbol-circle symbol-100px bg-light-success mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-success">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                </div>

                <!-- Title -->
                <h1 class="fw-bolder text-gray-900 mb-5">
                    Email Berhasil Diverifikasi!
                </h1>
                
                <!-- Message -->
                <div class="fw-semibold fs-6 text-gray-600 mb-10">
                    {{ message || 'Email Anda telah berhasil diverifikasi.' }}
                    <br>
                    <span class="text-gray-800">Sekarang Anda dapat login ke aplikasi.</span>
                </div>

                <!-- Action Button -->
                <router-link 
                    to="/sign-in" 
                    class="btn btn-lg btn-primary"
                >
                    Login Sekarang
                </router-link>
            </div>
        </div>
    </div>
    </center>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useSetting } from "@/services";
import { toast } from "vue3-toastify";

export default defineComponent({
    name: "EmailVerifySuccess",
    setup() {
        const { data: setting } = useSetting();
        const route = useRoute();
        const message = ref("");

        onMounted(() => {
            message.value = (route.query.message as string) || '';
            
            if (message.value) {
                toast.success(message.value);
            } else {
                toast.success('Email berhasil diverifikasi!');
            }
        });

        return {
            setting,
            message,
        };
    },
});
</script>

<style scoped>
.symbol-circle {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.bg-light-success {
    background-color: #e8fff3;
}

.symbol-100px {
    width: 100px;
    height: 100px;
}
</style>