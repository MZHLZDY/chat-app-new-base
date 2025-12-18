<template>
    <Form
        class="form w-100"
        id="kt_login_signin_form"
        @submit="onSubmitLogin"
        :validation-schema="loginSchema"
    >
        <!--begin::Input group-->
        <div class="fv-row mb-8">
            <!--begin::Email-->
            <label class="form-label fs-6 fw-bold text-dark">Email</label>
            <Field
                class="form-control bg-transparent"
                type="email"
                name="email"
                autocomplete="off"
            />
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="email" />
                </div>
            </div>
            <!--end::Email-->
        </div>
        <!--end::Input group-->

        <!--begin::Input group-->
        <div class="fv-row mb-3">
            <!--begin::Password-->
            <label class="form-label fw-bold text-dark fs-6 mb-0">Password</label>
            <Field
                class="form-control bg-transparent"
                type="password"
                name="password"
                autocomplete="off"
            />
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="password" />
                </div>
            </div>
            <!--end::Password-->
        </div>
        <!--end::Input group-->

        <!--begin::Wrapper-->
        <div class="d-flex flex-stack flex-wrap gap-3 fs-base fw-semobold mb-8">
            <div></div>
            <!--begin::Link-->
            <!-- <router-link to="/auth/password-reset" class="link-primary">
                Lupa Password?
            </router-link> -->
            <!--end::Link-->
        </div>
        <!--end::Wrapper-->

        <!--begin::Action-->
        <div class="d-grid mb-10">
            <button
                type="submit"
                ref="submitButton"
                id="kt_sign_in_submit"
                class="btn btn-primary"
            >
                <!--begin::Indicator label-->
                <span class="indicator-label"> Masuk </span>
                <!--end::Indicator label-->

                <!--begin::Indicator progress-->
                <span class="indicator-progress">
                    Harap Tunggu...
                    <span
                        class="spinner-border spinner-border-sm align-middle ms-2"
                    ></span>
                </span>
                <!--end::Indicator progress-->
            </button>
        </div>
        <!--end::Action-->
    </Form>
</template>

<script lang="ts">
import { defineComponent, ref } from "vue";
import { ErrorMessage, Field, Form } from "vee-validate";
import { useAuthStore } from "@/stores/auth";
import { useRouter } from "vue-router";
import * as Yup from "yup";
import { toast } from "vue3-toastify";

export default defineComponent({
    name: "WithEmail",
    components: {
        Field,
        Form,
        ErrorMessage,
    },
    setup() {
        const store = useAuthStore();
        const router = useRouter();
        const submitButton = ref<HTMLButtonElement | null>(null);

        const loginSchema = Yup.object().shape({
            email: Yup.string()
                .email("Format email tidak valid")
                .required("Email tidak boleh kosong")
                .label("Email"),
            password: Yup.string()
                .min(8, "Password minimal 8 karakter")
                .required("Password tidak boleh kosong")
                .label("Password"),
        });

        const onSubmitLogin = async (values: any) => {
            if (!submitButton.value) return;

            // Disable button
            submitButton.value.disabled = true;
            submitButton.value.setAttribute("data-kt-indicator", "on");

            try {
                await store.login({
                    email: values.email,
                    password: values.password,
                });

                toast.success("Login berhasil!");
                
                // Small delay to ensure state is updated
                setTimeout(() => {
                    router.push({ name: "dashboard" });
                }, 100);

            } catch (error: any) {
                console.error("Login error:", error);
                toast.error(error || "Login gagal. Silakan coba lagi.");
            } finally {
                // Enable button
                if (submitButton.value) {
                    submitButton.value.disabled = false;
                    submitButton.value.removeAttribute("data-kt-indicator");
                }
            }
        };

        return {
            loginSchema,
            onSubmitLogin,
            submitButton,
        };
    },
});
</script>