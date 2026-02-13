<template>
    <div class="login-container">
        <!-- Background dengan animasi -->
        <div class="background-animated">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
        </div>

        <!-- Left Side - Branding -->
        <div class="login-left">
            <div class="branding-content">
                <div class="logo-wrapper animate-fade-in">
                    <img :src="setting?.logo_depan" class="logo" alt="Logo" />
                </div>
                
                <h1 class="brand-title animate-slide-up">
                    Bergabunglah Bersama Kami
                </h1>

                <p class="brand-subtitle animate-slide-up" style="animation-delay: 0.1s">
                    Buat akun Anda dan mulai berkomunikasi dengan lebih efektif dan aman
                </p>

                <div class="feature-list animate-slide-up" style="animation-delay: 0.2s">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </div>
                        <span>Registrasi cepat & mudah</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </div>
                        <span>Keamanan data terjamin</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </div>
                        <span>Verifikasi email otomatis</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Register Form -->
        <div class="login-right">
            <div class="form-container animate-fade-in" style="animation-delay: 0.3s">
                
                <!-- Card Register -->
                <div class="login-card">
                    
                    <!-- Success Alert -->
                    <div v-if="showEmailVerificationAlert" class="alert-success">
                        <div class="alert-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                        </div>
                        <div class="alert-content">
                            <h4 class="alert-title">Registrasi Berhasil!</h4>
                            <p class="alert-text">Email verifikasi telah dikirim ke <strong>{{ formData.email }}</strong>. Silakan cek inbox/spam Anda.</p>
                        </div>
                    </div>

                    <!-- Form Content -->
                    <div v-if="!showEmailVerificationAlert">
                        <div class="card-header">
                            <h2 class="card-title">Daftar Akun</h2>
                            <p class="card-subtitle">Lengkapi informasi di bawah ini</p>
                        </div>

                        <!-- Stepper Progress -->
                        <div class="stepper-progress">
                            <div class="step-item" :class="{ active: currentStepIndex === 0, completed: currentStepIndex > 0 }">
                                <div class="step-circle">
                                    <span class="step-number" v-if="currentStepIndex === 0">1</span>
                                    <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                                <div class="step-label">
                                    <div class="step-title">Informasi Dasar</div>
                                    <div class="step-desc">Nama, Email & Telepon</div>
                                </div>
                            </div>

                            <div class="step-line" :class="{ completed: currentStepIndex > 0 }"></div>

                            <div class="step-item" :class="{ active: currentStepIndex === 1 }">
                                <div class="step-circle">
                                    <span class="step-number">2</span>
                                </div>
                                <div class="step-label">
                                    <div class="step-title">Keamanan</div>
                                    <div class="step-desc">Password Akun</div>
                                </div>
                            </div>
                        </div>

                        <form
                            class="login-form"
                            novalidate
                            id="kt_create_account_form"
                            ref="horizontalWizardRef"
                            @submit.prevent="handleStep"
                        >
                            <!-- Step 1: Credential -->
                            <div v-show="currentStepIndex === 0">
                                <Credential :formData="formData"></Credential>
                            </div>

                            <!-- Step 2: Password -->
                            <div v-show="currentStepIndex === 1">
                                <Password :formData="formData"></Password>
                            </div>

                            <!-- Action Buttons -->
                            <div class="form-actions">
                                <button
                                    v-if="currentStepIndex > 0"
                                    type="button"
                                    class="btn-back"
                                    @click="previousStep"
                                >
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="19" y1="12" x2="5" y2="12"></line>
                                        <polyline points="12 19 5 12 12 5"></polyline>
                                    </svg>
                                    Kembali
                                </button>

                                <button
                                    v-if="currentStepIndex === totalSteps - 1"
                                    type="submit"
                                    id="submit-form"
                                    ref="submitButton"
                                    class="btn-submit"
                                    :class="{ 'full-width': currentStepIndex === 0 }"
                                >
                                    <span class="indicator-label">Daftar Sekarang</span>
                                    <span class="indicator-progress">
                                        <span class="spinner"></span>
                                        Memproses...
                                    </span>
                                </button>

                                <button
                                    v-else
                                    type="submit"
                                    id="next-form"
                                    class="btn-submit full-width"
                                >
                                    <span class="indicator-label">
                                        Selanjutnya
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 0.5rem;">
                                            <line x1="5" y1="12" x2="19" y2="12"></line>
                                            <polyline points="12 5 19 12 12 19"></polyline>
                                        </svg>
                                    </span>
                                    <span class="indicator-progress">
                                        <span class="spinner"></span>
                                    </span>
                                </button>
                            </div>
                        </form>

                        <!-- Sign In Link -->
                        <div class="card-footer">
                            <span class="footer-text">Sudah punya akun?</span>
                            <router-link to="/sign-in" class="signup-link">
                                Masuk sekarang
                            </router-link>
                        </div>
                    </div>

                    <!-- After Success -->
                    <div v-else>
                        <router-link to="/sign-in" class="btn-submit">
                            Kembali ke Login
                        </router-link>
                    </div>

                </div>

            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { getAssetPath } from "@/core/helpers/assets";
import { defineComponent, ref, onMounted, computed } from "vue";
import * as Yup from "yup";
import { StepperComponent } from "@/assets/ts/components";
import { useForm } from "vee-validate";
import Credential from "./steps/Credential.vue";
import Password from "./steps/Password.vue";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { blockBtn, unblockBtn } from "@/libs/utils";
import router from "@/router";
import { useSetting } from "@/services";

interface ICredential {
    nama?: string;
    email?: string;
    phone?: string;
}

interface IPassword {
    password?: string;
    password_confirmation?: string;
}

interface CreateAccount extends ICredential, IPassword {}

export default defineComponent({
    name: "sign-up",
    components: {
        Credential,
        Password,
    },
    setup() {
        const { data: setting = {} } = useSetting();
        const _stepperObj = ref<StepperComponent | null>(null);
        const horizontalWizardRef = ref<HTMLElement | null>(null);
        const currentStepIndex = ref(0);
        const showEmailVerificationAlert = ref(false);
        const submitButton = ref<HTMLButtonElement | null>(null);

        const formData = ref<CreateAccount>({
            nama: "",
            email: "",
            phone: "",
            password: "",
            password_confirmation: "",
        });

        onMounted(() => {
            _stepperObj.value = StepperComponent.createInsance(
                horizontalWizardRef.value as HTMLElement
            );
        });

        const createAccountSchema = [
            Yup.object({
                nama: Yup.string().required("Nama tidak boleh kosong").label("Nama"),
                email: Yup.string().email().required("Email tidak boleh kosong").label("Email"),
                phone: Yup.string()
                    .matches(/^08[0-9]\d{8,11}$/, "No. Telepon tidak valid")
                    .required("No. Telepon tidak boleh kosong")
                    .label("No. Telepon"),
            }),
            Yup.object({
                password: Yup.string()
                    .min(8, "Password minimal terdiri dari 8 karakter")
                    .required("Password tidak boleh kosong")
                    .label("Password"),
                password_confirmation: Yup.string()
                    .oneOf([Yup.ref("password")], "Konfirmasi Password tidak sesuai")
                    .required("Konfirmasi Password tidak boleh kosong")
                    .label("Konfirmasi Password"),
            }),
        ];

        const currentSchema = computed(() => {
            return createAccountSchema[currentStepIndex.value];
        });

        const { resetForm, handleSubmit } = useForm<ICredential | IPassword>({
            validationSchema: currentSchema,
        });

        const totalSteps = computed(() => {
            return 2;
        });

        const handleStep = handleSubmit((values) => {
            formData.value = { ...formData.value, ...values };

            if (currentStepIndex.value === 0) {
                currentStepIndex.value++;
                if (_stepperObj.value) {
                    _stepperObj.value.goNext();
                }
            } else if (currentStepIndex.value === 1) {
                formSubmit(formData.value);
            }
        });

        const previousStep = () => {
            if (currentStepIndex.value > 0) {
                currentStepIndex.value--;
                if (_stepperObj.value) {
                    _stepperObj.value.goPrev();
                }
            }
        };

        const formSubmit = (values: CreateAccount) => {
            if (submitButton.value) {
                submitButton.value.disabled = true;
                submitButton.value.setAttribute("data-kt-indicator", "on");
            } else {
                blockBtn("#submit-form");
            }

            axios
                .post("/auth/register", values)
                .then((res) => {
                    toast.success(res.data.message);
                    showEmailVerificationAlert.value = true;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                })
                .catch((err) => {
                    toast.error(err.response?.data?.message || "Terjadi kesalahan");
                })
                .finally(() => {
                    if (submitButton.value) {
                        submitButton.value.removeAttribute("data-kt-indicator");
                        submitButton.value.disabled = false;
                    } else {
                        unblockBtn("#submit-form");
                    }
                });
        };

        return {
            horizontalWizardRef,
            previousStep,
            handleStep,
            totalSteps,
            currentStepIndex,
            getAssetPath,
            formData,
            setting,
            showEmailVerificationAlert,
            submitButton,
        };
    },
});
</script>

<style lang="scss" scoped>
/* --- ANIMATIONS --- */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes float {
    0%, 100% {
        transform: translate(0, 0);
    }
    33% {
        transform: translate(30px, -30px);
    }
    66% {
        transform: translate(-20px, 20px);
    }
}

@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}

@keyframes errorSlide {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fadeIn 0.8s ease-out forwards;
    opacity: 0;
}

.animate-slide-up {
    animation: slideUp 0.8s ease-out forwards;
    opacity: 0;
}

/* --- LAYOUT --- */
.login-container {
    display: flex;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}

/* Animated Background */
.background-animated {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    overflow: hidden;
}

.circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    animation: float 20s ease-in-out infinite;
}

.circle-1 {
    width: 300px;
    height: 300px;
    top: -100px;
    left: -100px;
}

.circle-2 {
    width: 400px;
    height: 400px;
    top: 50%;
    right: -150px;
    animation-delay: -5s;
    animation-duration: 25s;
}

.circle-3 {
    width: 250px;
    height: 250px;
    bottom: -80px;
    left: 40%;
    animation-delay: -10s;
    animation-duration: 30s;
}

/* --- LEFT SIDE (BRANDING) --- */
.login-left {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    position: relative;
    z-index: 1;
}

.branding-content {
    max-width: 500px;
    color: white;
}

.logo-wrapper {
    margin-bottom: 2.5rem;
}

.logo {
    height: 50px;
    filter: brightness(0) invert(1);
}

.brand-title {
    font-size: 2.75rem;
    font-weight: 800;
    margin-bottom: 1rem;
    line-height: 1.2;
    text-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.brand-subtitle {
    font-size: 1.1rem;
    line-height: 1.6;
    opacity: 0.95;
    margin-bottom: 3rem;
}

.feature-list {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    font-size: 1.05rem;
    font-weight: 500;
}

.feature-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.2);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    svg {
        color: white;
    }
}

/* --- RIGHT SIDE (FORM) --- */
.login-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(40px);
    position: relative;
    z-index: 1;
}

.form-container {
    width: 100%;
    max-width: 480px;
}

/* --- CARD --- */
.login-card {
    background: white;
    border-radius: 24px;
    padding: 2rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

.login-card:hover {
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
}

.card-header {
    margin-bottom: 1.5rem;
    text-align: center;
}

.card-title {
    font-size: 1.75rem;
    font-weight: 800;
    color: #1a202c;
    margin-bottom: 0.5rem;
}

.card-subtitle {
    font-size: 0.95rem;
    color: #718096;
    margin: 0;
}

/* --- STEPPER PROGRESS --- */
.stepper-progress {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 1.5rem;
    position: relative;
}

.step-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.step-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #e2e8f0;
    border: 3px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    transition: all 0.3s ease;
}

.step-number {
    font-size: 1rem;
    font-weight: 700;
    color: #a0aec0;
}

.step-label {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}

.step-title {
    font-size: 0.8rem;
    font-weight: 700;
    color: #a0aec0;
    transition: color 0.3s;
}

.step-desc {
    font-size: 0.7rem;
    color: #cbd5e0;
}

.step-line {
    width: 50px;
    height: 3px;
    background: #e2e8f0;
    margin: 0 0.75rem;
    transition: all 0.3s ease;
}

/* Active Step */
.step-item.active .step-circle {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.step-item.active .step-number {
    color: white;
}

.step-item.active .step-title {
    color: #667eea;
}

.step-item.active .step-desc {
    color: #718096;
}

/* Completed Step */
.step-item.completed .step-circle {
    background: #48bb78;
    border-color: #48bb78;
}

.step-item.completed .step-circle svg {
    color: white;
}

.step-item.completed .step-title {
    color: #48bb78;
}

.step-line.completed {
    background: #48bb78;
}

/* --- FORM STYLES --- */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

:deep(.form-group) {
    display: flex;
    flex-direction: column;
}

:deep(.form-label) {
    font-size: 0.875rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

:deep(.input-wrapper) {
    position: relative;
    display: flex;
    align-items: center;
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
}

:deep(.input-wrapper:focus-within) {
    background: white;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

:deep(.input-icon) {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1rem;
    color: #a0aec0;
}

:deep(.input-wrapper:focus-within .input-icon) {
    color: #667eea;
}

:deep(.form-input) {
    flex: 1;
    padding: 0.875rem 1rem 0.875rem 0;
    border: none;
    background: transparent;
    font-size: 0.95rem;
    color: #2d3748;
    outline: none;

    &::placeholder {
        color: #a0aec0;
    }
}

:deep(.toggle-password) {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1rem;
    background: transparent;
    border: none;
    color: #a0aec0;
    cursor: pointer;
    transition: color 0.2s;

    &:hover {
        color: #667eea;
    }
}

/* Error Container & Message */
:deep(.error-container) {
    min-height: 20px;
    margin-top: 0.35rem;
}

:deep(.error-message) {
    display: block;
    font-size: 0.8rem;
    color: #f56565;
    padding-left: 0.25rem;
    animation: errorSlide 0.3s ease-out;
}

/* --- FORM ACTIONS --- */
.form-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
}

.btn-back {
    flex: 1;
    padding: 1rem;
    border: 2px solid #e2e8f0;
    background: white;
    color: #4a5568;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    transition: all 0.3s ease;

    &:hover {
        border-color: #cbd5e0;
        background: #f7fafc;
    }
}

.btn-submit {
    flex: 2;
    padding: 1rem;
    border: none;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 1rem;
    font-weight: 700;
    border-radius: 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;

    &.full-width {
        flex: 1;
        width: 100%;
    }

    &::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    &:hover:not(:disabled)::before {
        left: 100%;
    }

    &:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    &:disabled {
        opacity: 0.8;
        cursor: not-allowed;
    }
}

/* Loading Indicator */
.indicator-label {
    display: flex;
    align-items: center;
    justify-content: center;
}

.indicator-progress {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-submit[data-kt-indicator="on"] {
    .indicator-label {
        display: none;
    }
    
    .indicator-progress {
        display: flex;
    }
}

.spinner {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

/* --- CARD FOOTER --- */
.card-footer {
    text-align: center;
    margin-top: 1.25rem;
    padding-top: 1.25rem;
    border-top: 1px solid #e2e8f0;
}

.footer-text {
    font-size: 0.875rem;
    color: #718096;
    margin-right: 0.5rem;
}

.signup-link {
    font-size: 0.875rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;

    &:hover {
        color: #5a67d8;
    }
}

/* --- SUCCESS ALERT --- */
.alert-success {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
    padding: 1.25rem;
    border-radius: 16px;
    display: flex;
    gap: 0.85rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(72, 187, 120, 0.3);
}

.alert-icon {
    flex-shrink: 0;
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.alert-content {
    flex: 1;
}

.alert-title {
    font-size: 1rem;
    font-weight: 700;
    margin: 0 0 0.4rem 0;
}

.alert-text {
    font-size: 0.9rem;
    margin: 0;
    opacity: 0.95;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
    .login-container {
        flex-direction: column;
    }

    .login-left {
        padding: 2.5rem 2rem;
        min-height: auto;
    }

    .login-right {
        padding: 2rem;
        background: white;
        backdrop-filter: none;
    }

    .brand-title {
        font-size: 2rem;
    }

    .brand-subtitle {
        font-size: 1rem;
        margin-bottom: 2rem;
    }

    .feature-list {
        gap: 1rem;
    }

    .feature-item {
        font-size: 0.95rem;
    }

    .login-card {
        padding: 2rem 1.75rem;
        box-shadow: none;
    }

    .card-title {
        font-size: 1.6rem;
    }

    .step-line {
        width: 40px;
    }
}

@media (max-width: 768px) {
    .login-left {
        padding: 2rem 1.5rem;
    }

    .login-right {
        padding: 1.5rem;
    }

    .brand-title {
        font-size: 1.75rem;
    }

    .brand-subtitle {
        font-size: 0.95rem;
    }

    .feature-list {
        display: none;
    }

    .login-card {
        padding: 1.75rem 1.5rem;
    }

    .card-title {
        font-size: 1.5rem;
    }

    .card-subtitle {
        font-size: 0.9rem;
    }

    .stepper-progress {
        margin-bottom: 1.25rem;
    }

    .step-circle {
        width: 38px;
        height: 38px;
    }

    .step-title {
        font-size: 0.75rem;
    }

    .step-desc {
        font-size: 0.65rem;
    }

    .step-line {
        width: 30px;
        margin: 0 0.5rem;
    }
}

@media (max-width: 576px) {
    .login-left {
        padding: 1.5rem 1rem;
        min-height: 200px;
    }

    .login-right {
        padding: 1rem;
    }

    .logo {
        height: 40px;
    }

    .logo-wrapper {
        margin-bottom: 1.5rem;
    }

    .brand-title {
        font-size: 1.5rem;
        margin-bottom: 0.75rem;
    }

    .brand-subtitle {
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .login-card {
        padding: 1.5rem 1.25rem;
        border-radius: 16px;
    }

    .card-title {
        font-size: 1.35rem;
    }

    .login-form {
        gap: 1rem;
    }

    .step-label {
        display: none;
    }

    .step-line {
        width: 50px;
    }

    .btn-submit, .btn-back {
        padding: 0.9rem;
        font-size: 0.95rem;
    }

    .card-footer {
        margin-top: 1.25rem;
        padding-top: 1.25rem;
        font-size: 0.85rem;
    }

    .footer-text,
    .signup-link {
        font-size: 0.85rem;
    }
}

@media (max-width: 400px) {
    .login-card {
        padding: 1.25rem 1rem;
    }

    .step-circle {
        width: 36px;
        height: 36px;
    }

    .step-number {
        font-size: 0.95rem;
    }
}

/* Landscape Mobile */
@media (max-width: 992px) and (orientation: landscape) {
    .login-left {
        min-height: auto;
        padding: 1.5rem 2rem;
    }

    .brand-title {
        font-size: 1.5rem;
    }

    .brand-subtitle {
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .feature-list {
        display: none;
    }
}
</style>