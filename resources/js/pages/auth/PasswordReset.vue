<template>
    <div class="reset-wrapper">
        <!-- Animated Background -->
        <div class="background-animated">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
        </div>

        <div class="reset-container">
            <div class="reset-card animate-fade-in">
                <!-- Logo -->
                <div class="logo-wrapper">
                    <img
                        :src="getAssetPath('media/logos/default-dark.svg')"
                        class="logo"
                        alt="Logo"
                    />
                </div>

                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div
                        class="step-item"
                        :class="{ active: step >= 1, done: step > 1 }"
                    >
                        <div class="step-circle">
                            <svg
                                v-if="step > 1"
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                            >
                                <polyline points="20 6 9 17 4 12" />
                            </svg>
                            <span v-else>1</span>
                        </div>
                        <span class="step-label">Email</span>
                    </div>
                    <div class="step-line" :class="{ active: step > 1 }"></div>
                    <div class="step-item" :class="{ active: step >= 2 }">
                        <div class="step-circle">
                            <span>2</span>
                        </div>
                        <span class="step-label">Reset</span>
                    </div>
                </div>

                <!-- Title -->
                <div class="reset-head">
                    <h1 class="reset-title">
                        {{
                            step === 1 ? "Lupa Password?" : "Buat Password Baru"
                        }}
                    </h1>
                    <p class="reset-subtitle">
                        {{
                            step === 1
                                ? "Masukkan email kamu, kami akan kirim kode verifikasi."
                                : "Masukkan kode OTP dari email dan buat password baru."
                        }}
                    </p>
                </div>

                <!-- Form -->
                <Form
                    class="reset-form"
                    @submit="onSubmit"
                    :validation-schema="currentSchema"
                >
                    <!-- Step 1: Email -->
                    <div
                        class="field-group"
                        :class="{ 'field-disabled': step === 2 }"
                    >
                        <label class="field-label">
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"
                                />
                                <polyline points="22,6 12,12 2,6" />
                            </svg>
                            Email
                        </label>
                        <div class="input-wrap">
                            <Field
                                class="field-input"
                                type="email"
                                name="email"
                                placeholder="nama@email.com"
                                autocomplete="off"
                                v-model="formData.email"
                                :disabled="step === 2"
                            />
                            <span v-if="step === 2" class="input-lock">
                                <svg
                                    width="13"
                                    height="13"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect
                                        x="3"
                                        y="11"
                                        width="18"
                                        height="11"
                                        rx="2"
                                        ry="2"
                                    />
                                    <path d="M7 11V7a5 5 0 0110 0v4" />
                                </svg>
                            </span>
                        </div>
                        <ErrorMessage name="email" class="field-error" />
                    </div>

                    <!-- Step 2: OTP + Passwords -->
                    <template v-if="step === 2">
                        <!-- OTP -->
                        <div class="field-group">
                            <label class="field-label">
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect
                                        x="5"
                                        y="2"
                                        width="14"
                                        height="20"
                                        rx="2"
                                        ry="2"
                                    />
                                    <line x1="12" y1="18" x2="12.01" y2="18" />
                                </svg>
                                Kode Verifikasi (OTP)
                            </label>
                            <Field
                                class="field-input field-input-otp"
                                type="text"
                                name="otp"
                                placeholder="Cek email kamu (contoh: 123456)"
                                autocomplete="off"
                                v-model="formData.otp"
                            />
                            <ErrorMessage name="otp" class="field-error" />
                        </div>

                        <!-- Password Baru -->
                        <div class="field-group">
                            <label class="field-label">
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <rect
                                        x="3"
                                        y="11"
                                        width="18"
                                        height="11"
                                        rx="2"
                                        ry="2"
                                    />
                                    <path d="M7 11V7a5 5 0 0110 0v4" />
                                </svg>
                                Password Baru
                            </label>
                            <div class="input-wrap">
                                <Field
                                    class="field-input"
                                    :type="showPassword ? 'text' : 'password'"
                                    name="password"
                                    placeholder="Minimal 8 karakter"
                                    autocomplete="off"
                                    v-model="formData.password"
                                />
                                <button
                                    type="button"
                                    class="input-eye"
                                    @click="showPassword = !showPassword"
                                >
                                    <svg
                                        v-if="!showPassword"
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                                        />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg
                                        v-else
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"
                                        />
                                        <line x1="1" y1="1" x2="23" y2="23" />
                                    </svg>
                                </button>
                            </div>
                            <ErrorMessage name="password" class="field-error" />
                        </div>

                        <!-- Konfirmasi Password -->
                        <div class="field-group">
                            <label class="field-label">
                                <svg
                                    width="14"
                                    height="14"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                >
                                    <path d="M9 11l3 3L22 4" />
                                    <path
                                        d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"
                                    />
                                </svg>
                                Konfirmasi Password
                            </label>
                            <div class="input-wrap">
                                <Field
                                    class="field-input"
                                    :type="
                                        showConfirmPassword
                                            ? 'text'
                                            : 'password'
                                    "
                                    name="password_confirmation"
                                    placeholder="Ulangi password baru"
                                    autocomplete="off"
                                    v-model="formData.password_confirmation"
                                />
                                <button
                                    type="button"
                                    class="input-eye"
                                    @click="
                                        showConfirmPassword =
                                            !showConfirmPassword
                                    "
                                >
                                    <svg
                                        v-if="!showConfirmPassword"
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                                        />
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                    <svg
                                        v-else
                                        width="16"
                                        height="16"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                    >
                                        <path
                                            d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"
                                        />
                                        <line x1="1" y1="1" x2="23" y2="23" />
                                    </svg>
                                </button>
                            </div>
                            <ErrorMessage
                                name="password_confirmation"
                                class="field-error"
                            />
                        </div>
                    </template>

                    <!-- Actions -->
                    <div class="reset-actions">
                        <button
                            type="submit"
                            ref="submitButton"
                            class="btn-submit"
                        >
                            <span class="indicator-label">
                                {{
                                    step === 1
                                        ? "Kirim Kode OTP"
                                        : "Ubah Password"
                                }}
                            </span>
                            <span class="indicator-progress">
                                <span class="spin"></span>
                                Memproses...
                            </span>
                        </button>
                        <router-link to="/sign-in" class="btn-back">
                            <svg
                                width="14"
                                height="14"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <polyline points="15 18 9 12 15 6" />
                            </svg>
                            Kembali ke Login
                        </router-link>
                    </div>
                </Form>
            </div>
        </div>
    </div>
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
        const step = ref(1);
        const showPassword = ref(false);
        const showConfirmPassword = ref(false);

        const formData = ref({
            email: "",
            otp: "",
            password: "",
            password_confirmation: "",
        });

        const currentSchema = computed(() => {
            if (step.value === 1) {
                return Yup.object().shape({
                    email: Yup.string()
                        .email("Email tidak valid")
                        .required("Email wajib diisi"),
                });
            } else {
                return Yup.object().shape({
                    otp: Yup.string().required("Kode OTP wajib diisi"),
                    password: Yup.string()
                        .min(8, "Min 8 karakter")
                        .required("Password baru wajib diisi"),
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
                    await axios.post("/auth/forgot-password/send-otp", {
                        email: formData.value.email,
                    });
                    Swal.fire({
                        text: "Kode verifikasi telah dikirim ke email Anda!",
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Ok, Masukkan Kode",
                        customClass: { confirmButton: "btn btn-primary" },
                    });
                    step.value = 2;
                } else {
                    await axios.post(
                        "/auth/forgot-password/reset",
                        formData.value
                    );
                    Swal.fire({
                        text: "Password berhasil diubah! Silakan login.",
                        icon: "success",
                        buttonsStyling: false,
                        confirmButtonText: "Login Sekarang",
                        customClass: { confirmButton: "btn btn-primary" },
                    }).then(() => router.push("/sign-in"));
                }
            } catch (error: any) {
                Swal.fire({
                    text: error.response?.data?.message || "Terjadi kesalahan.",
                    icon: "error",
                    buttonsStyling: false,
                    confirmButtonText: "Ok",
                    customClass: { confirmButton: "btn btn-danger" },
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
        transform: translateY(24px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
@keyframes float {
    0%,
    100% {
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
    animation: fadeIn 0.7s ease-out forwards;
}

/* ── Layout ─────────────────────────────────────────────────────────────── */
.reset-wrapper {
    min-height: 100vh;
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: fixed;
    inset: 0;
    overflow-y: auto;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 2rem 1rem;
    z-index: 10;
}

/* Background circles — same as sign-in */
.background-animated {
    position: fixed;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    pointer-events: none;
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

/* ── Card ───────────────────────────────────────────────────────────────── */
.reset-container {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 460px;
}
.reset-card {
    background: white;
    border-radius: 20px;
    padding: 2.5rem 2.5rem 2rem;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.18);
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

/* ── Logo ───────────────────────────────────────────────────────────────── */
.logo-wrapper {
    display: flex;
    justify-content: center;
}
.logo {
    height: 42px;
}

/* ── Step Indicator ─────────────────────────────────────────────────────── */
.step-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0;
}
.step-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
}
.step-circle {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 2px solid #e2e8f0;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 700;
    color: #a0aec0;
    transition: all 0.3s;
}
.step-item.active .step-circle {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
.step-item.done .step-circle {
    border-color: #667eea;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
}
.step-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: #a0aec0;
    transition: color 0.3s;
}
.step-item.active .step-label,
.step-item.done .step-label {
    color: #667eea;
}

.step-line {
    flex: 1;
    height: 2px;
    background: #e2e8f0;
    margin: 0 10px;
    margin-bottom: 18px;
    max-width: 80px;
    transition: background 0.3s;
}
.step-line.active {
    background: linear-gradient(90deg, #667eea, #764ba2);
}

/* ── Header ─────────────────────────────────────────────────────────────── */
.reset-head {
    text-align: center;
}
.reset-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 0.5rem;
}
.reset-subtitle {
    font-size: 0.9rem;
    color: #718096;
    margin: 0;
    line-height: 1.5;
}

/* ── Form Fields ────────────────────────────────────────────────────────── */
.reset-form {
    display: flex;
    flex-direction: column;
    gap: 1.1rem;
}

.field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.field-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: #2d3748;
    display: flex;
    align-items: center;
    gap: 6px;
}
.input-wrap {
    position: relative;
    display: flex;
    align-items: center;
}
.field-input {
    width: 100%;
    background: #f7fafc;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 11px 14px;
    font-size: 0.9rem;
    color: #1a202c;
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
    font-family: inherit;

    &:focus {
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        background: white;
    }
    &:disabled {
        background: #f0f4f8;
        color: #a0aec0;
        cursor: not-allowed;
    }
}
.field-input-otp {
    letter-spacing: 0.15em;
    font-weight: 700;
    font-size: 1rem;
}
.input-lock {
    position: absolute;
    right: 12px;
    color: #a0aec0;
    display: flex;
}
.input-eye {
    position: absolute;
    right: 10px;
    background: none;
    border: none;
    cursor: pointer;
    color: #a0aec0;
    padding: 4px;
    display: flex;
    align-items: center;
    transition: color 0.2s;
    &:hover {
        color: #667eea;
    }
}
.field-error {
    font-size: 0.78rem;
    color: #f56565;
    font-weight: 500;
}
.field-disabled .field-input {
    padding-right: 36px;
}

/* ── Actions ────────────────────────────────────────────────────────────── */
.reset-actions {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-top: 0.5rem;
}
.btn-submit {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 10px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    font-size: 0.95rem;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.4);
    position: relative;
    overflow: hidden;

    &::after {
        content: "";
        position: absolute;
        inset: 0;
        background: linear-gradient(
            90deg,
            transparent,
            rgba(255, 255, 255, 0.15),
            transparent
        );
        transform: translateX(-100%);
        transition: transform 0.4s;
    }
    &:hover::after {
        transform: translateX(100%);
    }
    &:hover {
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        transform: translateY(-1px);
    }
    &:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }
}

/* KT indicator support */
.btn-submit .indicator-progress {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.btn-submit[data-kt-indicator="on"] .indicator-label {
    display: none;
}
.btn-submit[data-kt-indicator="on"] .indicator-progress {
    display: flex;
}

.spin {
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255, 255, 255, 0.4);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}

.btn-back {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    color: #718096;
    font-size: 0.85rem;
    font-weight: 500;
    text-decoration: none;
    padding: 8px;
    border-radius: 8px;
    transition: all 0.2s;
    &:hover {
        color: #667eea;
        background: #f7fafc;
    }
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 480px) {
    .reset-card {
        padding: 2rem 1.5rem;
    }
}
</style>
