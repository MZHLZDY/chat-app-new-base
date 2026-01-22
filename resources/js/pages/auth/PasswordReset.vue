<template>
    <center>
    <div class="bg-body d-flex flex-column align-items-stretch flex-center rounded-4 w-md-600px p-md-20 w-100"
        style="background-color: rgba(255, 255, 255, 0.3) !important; backdrop-filter: blur(9px);">
        
        <div class="text-center mb-10">
            <h1 class="text-white mb-5 fs-3x fw-bold" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                Reset Password
            </h1>
            <div class="text-white fs-4 opacity-75" style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">
                {{ step === 1 ? 'Masukkan email untuk menerima kode.' : 'Masukkan kode OTP dan password baru.' }}
            </div>
        </div>

        <Form class="form w-100" @submit="onSubmit" :validation-schema="currentSchema">
            
            <div class="fv-row mb-10">
                <label class="form-label fw-bold text-white fs-6">Email</label>
                <Field 
                    class="form-control form-control-lg bg-transparent" 
                    type="email" 
                    name="email" 
                    autocomplete="off" 
                    v-model="formData.email"
                    :disabled="step === 2" 
                />
                <ErrorMessage name="email" class="fv-plugins-message-container invalid-feedback" />
            </div>

            <div v-if="step === 2">
                <div class="fv-row mb-10">
                    <label class="form-label fw-bold text-white fs-6">Kode Verifikasi (OTP)</label>
                    <Field 
                        class="form-control form-control-lg bg-transparent" 
                        type="text" 
                        name="otp" 
                        placeholder="Cek email Anda (misal: 123456)"
                        autocomplete="off" 
                        v-model="formData.otp" 
                    />
                    <ErrorMessage name="otp" class="fv-plugins-message-container invalid-feedback" />
                </div>

                <div class="fv-row mb-10">
                  <label class="form-label fw-bold text-white fs-6">Password Baru</label>
    
    <div class="position-relative mb-3">
        <Field 
            class="form-control form-control-lg bg-transparent" 
            :type="showPassword ? 'text' : 'password'" 
            name="password" 
            placeholder="Minimal 8 karakter"
            autocomplete="off" 
            v-model="formData.password" 
        />
        
        <span class="btn btn-sm btn-icon position-absolute translate-middle top-50 end-0 me-n2"
            @click="showPassword = !showPassword">
            <i class="bi fs-2 text-white" 
               :class="showPassword ? 'bi-eye' : 'bi-eye-slash'"></i>
        </span>
    </div>

    <div class="fv-plugins-message-container">
        <div class="fv-help-block">
            <ErrorMessage name="password" />
        </div>
    </div>
</div>

<div class="fv-row mb-10">
    <label class="form-label fw-bold text-white fs-6">Konfirmasi Password</label>
    
    <div class="position-relative mb-3">
        <Field 
            class="form-control form-control-lg bg-transparent" 
            :type="showConfirmPassword ? 'text' : 'password'" 
            name="password_confirmation" 
            placeholder="Ulangi password baru"
            autocomplete="off" 
            v-model="formData.password_confirmation" 
        />
        
        <span class="btn btn-sm btn-icon position-absolute translate-middle top-50 end-0 me-n2"
            @click="showConfirmPassword = !showConfirmPassword">
            <i class="bi fs-2 text-white" 
               :class="showConfirmPassword ? 'bi-eye' : 'bi-eye-slash'"></i>
        </span>
    </div>

    <div class="fv-plugins-message-container">
        <div class="fv-help-block">
            <ErrorMessage name="password_confirmation" />
        </div>
    </div>
</div>
            </div>

            <div class="d-flex flex-wrap justify-content-center pb-lg-0">
                <button type="submit" ref="submitButton" class="btn btn-primary me-4">
                    <span class="indicator-label">
                        {{ step === 1 ? 'Kirim Kode' : 'Ubah Password' }}
                    </span>
                    <span class="indicator-progress">
                        Please wait... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                    </span>
                </button>
                <router-link to="/sign-in" class="btn btn-light">Batal</router-link>
            </div>
        </Form>
    </div>
    </center>
</template>

<script lang="ts">
import { defineComponent, ref, computed } from "vue";
import { Form, Field, ErrorMessage } from "vee-validate";
import * as Yup from "yup";
import Swal from "sweetalert2";
import axios from "axios";
import { useRouter } from "vue-router";
import { getAssetPath } from "@/core/helpers/assets";

export default defineComponent({
    name: "PasswordReset",
    components: { Form, Field, ErrorMessage },
    setup() {
        const router = useRouter();
        const submitButton = ref<HTMLButtonElement | null>(null);
        const step = ref(1); // State untuk mengontrol tampilan

        const showPassword = ref(false);
        const showConfirmPassword = ref(false);
        
        const formData = ref({
            email: '',
            otp: '',
            password: '',
            password_confirmation: ''
        });

        // Schema Validasi Dinamis
        const currentSchema = computed(() => {
            if (step.value === 1) {
                return Yup.object().shape({
                    email: Yup.string().email("Email tidak valid").required("Email wajib diisi"),
                });
            } else {
                return Yup.object().shape({
                    otp: Yup.string().required("Kode OTP wajib diisi"),
                    password: Yup.string().min(8, "Min 8 karakter").required("Password baru wajib diisi"),
                    password_confirmation: Yup.string()
                        .required("Konfirmasi wajib diisi")
                        .oneOf([Yup.ref("password")], "Password tidak sama"),
                });
            }
        });

        const onSubmit = async () => {
            if (!submitButton.value) return;
            
            submitButton.value.disabled = true;
            submitButton.value.setAttribute("data-kt-indicator", "on");

            try {
                if (step.value === 1) {
                    // --- LOGIKA STEP 1: KIRIM OTP ---
                    await axios.post("/auth/forgot-password/send-otp", { email: formData.value.email });
                    
                    Swal.fire({
                        text: "Kode verifikasi telah dikirim ke email Anda!",
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Ok, Masukkan Kode",
                        customClass: { confirmButton: "btn btn-primary" }
                    });
                    
                    step.value = 2; // Pindah ke Step 2
                } else {
                    // --- LOGIKA STEP 2: RESET PASSWORD ---
                    await axios.post("/auth/forgot-password/reset", formData.value);
                    
                    Swal.fire({
                        text: "Password berhasil diubah! Silakan login.",
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Login Sekarang",
                        customClass: { confirmButton: "btn btn-primary" }
                    }).then(() => {
                        router.push("/sign-in");
                    });
                }
            } catch (error: any) {
                Swal.fire({
                    text: error.response?.data?.message || "Terjadi kesalahan.",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: { confirmButton: "btn btn-danger" }
                });
            } finally {
                if (submitButton.value) {
                    submitButton.value.disabled = false;
                    submitButton.value.removeAttribute("data-kt-indicator");
                }
            }
        };

        return {
            formData,
            step,
            currentSchema,
            onSubmit,
            submitButton,
            getAssetPath,
            showPassword,
            showConfirmPassword,
        };
    },
});
</script>

<style lang="scss" scoped>
/* Gunakan style form-control yang sama dengan sebelumnya (border biru muda, bg transparan) */
.form-control {
    background-color: rgba(0, 0, 0, 0.2) !important;
    border: 3px solid #54b7f0 !important;
    color: #ffffff !important;
    border-radius: 10px;
}
</style>