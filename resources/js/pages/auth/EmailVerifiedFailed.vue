<template>
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
                <!-- Error Icon -->
                <div class="mb-10">
                    <div class="symbol symbol-circle symbol-100px bg-light-danger mx-auto">
                        <svg xmlns="http://www.w3.org/2000/svg" width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-danger">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="15" y1="9" x2="9" y2="15"></line>
                            <line x1="9" y1="9" x2="15" y2="15"></line>
                        </svg>
                    </div>
                </div>

                <!-- Title -->
                <h1 class="fw-bolder text-gray-900 mb-5">
                    Verifikasi Email Gagal
                </h1>
                
                <!-- Message -->
                <div class="fw-semibold fs-6 text-gray-600 mb-10">
                    {{ message || 'Link verifikasi tidak valid atau sudah kadaluarsa.' }}
                    <br>
                    <span class="text-gray-800">Silakan minta link verifikasi baru.</span>
                </div>

                <!-- Resend Form -->
                <form @submit.prevent="resendVerification" class="mb-5">
                    <div class="fv-row mb-5">
                        <input
                            type="email"
                            class="form-control form-control-lg"
                            placeholder="Masukkan email Anda"
                            v-model="email"
                            required
                        />
                    </div>
                    
                    <button 
                        type="submit"
                        class="btn btn-primary btn-lg w-100"
                        :disabled="loading"
                    >
                        <span v-if="!loading">
                            Kirim Ulang Email Verifikasi
                        </span>
                        <span v-else>
                            <span class="spinner-border spinner-border-sm me-2"></span>
                            Mengirim...
                        </span>
                    </button>
                </form>

                <!-- Back to Login -->
                <div class="text-center">
                    <router-link 
                        to="/sign-in" 
                        class="link-primary fw-bold"
                    >
                        Kembali ke Login
                    </router-link>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import axios from "@/libs/axios";
import { useSetting } from "@/services";
import { toast } from "vue3-toastify";
import { getAssetPath } from "@/core/helpers/assets";

export default defineComponent({
    name: "EmailVerifyFailed",
    setup() {
        const { data: setting } = useSetting();
        const route = useRoute();
        const router = useRouter();
        const message = ref("");
        const email = ref("");
        const loading = ref(false);

        onMounted(() => {
            message.value = (route.query.message as string) || '';
            
            if (message.value) {
                toast.error(message.value);
            } else {
                toast.error('Verifikasi email gagal!');
            }
        });

        const resendVerification = async () => {
            if (!email.value) {
                toast.error('Silakan masukkan email Anda');
                return;
            }

            loading.value = true;

            try {
                const response = await axios.post('/auth/email/resend', {
                    email: email.value
                });
                
                toast.success(response.data.message || 'Email verifikasi telah dikirim!');
                
                setTimeout(() => {
                    router.push({ name: 'sign-in' });
                }, 2000);
                
            } catch (error: any) {
                toast.error(error.response?.data?.message || 'Gagal mengirim email');
            } finally {
                loading.value = false;
            }
        };

        return {
            setting,
            message,
            email,
            loading,
            resendVerification,
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

.bg-light-danger {
    background-color: #fff5f8;
}

.symbol-100px {
    width: 100px;
    height: 100px;
}
</style>