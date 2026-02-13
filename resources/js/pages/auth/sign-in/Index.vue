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
                    <img :src="setting?.logo" class="logo" alt="Logo" />
                </div>
                
                <h1 class="brand-title animate-slide-up">
                    Selamat Datang Kembali
                </h1>

                <p class="brand-subtitle animate-slide-up" style="animation-delay: 0.1s">
                    Masuk ke akun Anda dan mulai berkomunikasi dengan lebih efektif
                </p>

                <div class="feature-list animate-slide-up" style="animation-delay: 0.2s">
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </div>
                        <span>Chat real-time yang aman</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </div>
                        <span>Enkripsi end-to-end</span>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 11l3 3L22 4"></path>
                                <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"></path>
                            </svg>
                        </div>
                        <span>Akses multi-platform</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side - Login Form -->
        <div class="login-right">
            <div class="form-container animate-fade-in" style="animation-delay: 0.3s">
                
                <!-- Card Login -->
                <div class="login-card">
                    <div class="card-header">
                        <h2 class="card-title">Masuk</h2>
                        <p class="card-subtitle">Silakan masukkan kredensial Anda</p>
                    </div>

                    <Form @submit="onSubmitLogin" :validation-schema="loginSchema" class="login-form">
                        
                        <!-- Identifier Field (Email/Name/Phone) -->
                        <div class="form-group">
                            <label for="identifier" class="form-label">Email, Nama, atau No. Telepon</label>
                            <div class="input-wrapper">
                                <div class="input-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </div>
                                <Field
                                    id="identifier"
                                    name="identifier"
                                    type="text"
                                    class="form-input"
                                    placeholder="Masukkan Email, Nama, atau No. Telepon"
                                    autocomplete="off"
                                />
                            </div>
                            <div class="error-container">
                                <ErrorMessage name="identifier" class="error-message" />
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-wrapper">
                                <div class="input-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0110 0v4"></path>
                                    </svg>
                                </div>
                                <Field
                                    id="password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="form-input"
                                    placeholder="Minimal 8 karakter"
                                    autocomplete="off"
                                />
                                <button
                                    type="button"
                                    class="toggle-password"
                                    @click="showPassword = !showPassword"
                                    tabindex="-1"
                                >
                                    <svg v-if="!showPassword" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                            <div class="error-container">
                                <ErrorMessage name="password" class="error-message" />
                            </div>
                        </div>

                        <!-- Forgot Password Link -->
                        <div class="form-options">
                            <div></div>
                            <router-link to="/password-reset" class="forgot-link">
                                Lupa password?
                            </router-link>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            ref="submitButton"
                            class="btn-submit"
                        >
                            <span class="indicator-label">Masuk</span>
                            <span class="indicator-progress">
                                <span class="spinner"></span>
                                Harap Tunggu...
                            </span>
                        </button>

                    </Form>

                    <!-- Sign Up Link -->
                    <div class="card-footer">
                        <span class="footer-text">Belum punya akun?</span>
                        <router-link to="/sign-up" class="signup-link">
                            Daftar sekarang
                        </router-link>
                    </div>
                </div>

            </div>
        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent, ref } from "vue";
import { ErrorMessage, Field, Form } from "vee-validate";
import { useAuthStore } from "@/stores/auth";
import { useRouter } from "vue-router";
import * as Yup from "yup";
import { toast } from "vue3-toastify";
import { getAssetPath } from "@/core/helpers/assets";

export default defineComponent({
    name: "SignInModern",
    components: {
        Field,
        Form,
        ErrorMessage,
    },
    setup() {
        const store = useAuthStore();
        const router = useRouter();
        const submitButton = ref<HTMLButtonElement | null>(null);

        const setting = ref({
            logo: getAssetPath("media/logos/default-dark.svg"),
            app: "ChatApp"
        });

        const showPassword = ref(false);

        // Validation schema - sama dengan WithEmail.vue
        const loginSchema = Yup.object().shape({
            identifier: Yup.string()
                .required("Email, Nama, atau No. Telepon wajib diisi")
                .label("Identifier"),
            password: Yup.string()
                .min(8, "Password minimal 8 karakter")
                .required("Password tidak boleh kosong")
                .label("Password"),
        });

        // Login handler - sama dengan WithEmail.vue
        const onSubmitLogin = async (values: any) => {
            if (!submitButton.value) return;

            // Disable button dan aktifkan loading
            submitButton.value.disabled = true;
            submitButton.value.setAttribute("data-kt-indicator", "on");

            try {
                // Call auth store login
                await store.login({
                    identifier: values.identifier,
                    password: values.password,
                });

                // Success notification
                toast.success("Login berhasil!");
                
                // Redirect ke dashboard
                setTimeout(() => {
                    router.push({ name: "dashboard" });
                }, 100);

            } catch (error: any) {
                console.log(error);
                
                // Error notification - menampilkan pesan error
                toast.error("Email / Nama / No. Telepon atau Password tidak ditemukan!");
                
            } finally {
                if (submitButton.value) {
                    // Remove loading indicator
                    submitButton.value.removeAttribute("data-kt-indicator");
                    // Enable button
                    submitButton.value.disabled = false;
                }
            }
        };

        return {
            setting,
            showPassword,
            loginSchema,
            onSubmitLogin,
            submitButton
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

/* --- LEFT SIDE --- */
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
    font-size: 1rem;
    opacity: 0.9;
}

.feature-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.15);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    backdrop-filter: blur(10px);
}

/* --- RIGHT SIDE --- */
.login-right {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    background: rgba(255, 255, 255, 0.05);
    backdrop-filter: blur(20px);
    position: relative;
    z-index: 1;
}

.form-container {
    width: 100%;
    max-width: 440px;
}

/* --- LOGIN CARD --- */
.login-card {
    background: white;
    border-radius: 20px;
    padding: 2.5rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
    transition: all 0.3s ease;
}

.login-card:hover {
    box-shadow: 0 25px 70px rgba(0, 0, 0, 0.2);
}

.card-header {
    margin-bottom: 2rem;
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

/* --- FORM STYLES --- */
.login-form {
    display: flex;
    flex-direction: column;
    gap: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 0.5rem;
}

.input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 12px;
    transition: all 0.3s ease;
}

.input-wrapper:focus-within {
    background: white;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.input-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 1rem;
    color: #a0aec0;
}

.input-wrapper:focus-within .input-icon {
    color: #667eea;
}

.form-input {
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

.toggle-password {
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
.error-container {
    min-height: 20px;
    margin-top: 0.35rem;
}

.error-message {
    display: block;
    font-size: 0.8rem;
    color: #f56565;
    padding-left: 0.25rem;
    animation: errorSlide 0.3s ease-out;
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

/* --- FORM OPTIONS --- */
.form-options {
    display: flex;
    justify-content: flex-end;
    align-items: center;
}

.forgot-link {
    font-size: 0.875rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 600;
    transition: color 0.2s;

    &:hover {
        color: #5a67d8;
    }
}

/* --- SUBMIT BUTTON --- */
.btn-submit {
    width: 100%;
    padding: 1rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;

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
    display: inline-block;
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
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid #e2e8f0;
}

.footer-text {
    font-size: 0.9rem;
    color: #718096;
    margin-right: 0.5rem;
}

.signup-link {
    font-size: 0.9rem;
    color: #667eea;
    text-decoration: none;
    font-weight: 700;
    transition: color 0.2s;

    &:hover {
        color: #5a67d8;
    }
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

    .form-label {
        font-size: 0.85rem;
    }

    .form-input {
        font-size: 0.9rem;
        padding: 0.8rem 0.85rem 0.8rem 0;
    }

    .input-icon {
        padding: 0 0.85rem;
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

    .form-group {
        gap: 0;
    }

    .login-form {
        gap: 1rem;
    }

    .btn-submit {
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

    .input-icon {
        padding: 0 0.75rem;

        svg {
            width: 18px;
            height: 18px;
        }
    }

    .toggle-password {
        padding: 0 0.75rem;

        svg {
            width: 18px;
            height: 18px;
        }
    }

    .form-input {
        font-size: 0.875rem;
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