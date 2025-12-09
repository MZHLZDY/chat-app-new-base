<template>
    <div class="w-lg-500px w-100">
        <main class="form w-100 fv-plugins-bootstrap5 fv-plugins-framework">
            <div class="mb-10 text-center">
                <router-link to="/">
                    <img
                        :src="setting?.logo_depan"
                        alt="Logo"
                        class="w-100px mb-8"
                    />
                </router-link>
                <h1 class="text-dark mb-3">
                    Daftar Akun <span class="text-primary">CHAT APP</span>
                </h1>
            </div>
            <div
                class="stepper stepper-links d-flex flex-column"
                id="kt_create_account_stepper"
                ref="horizontalWizardRef"
            >
                <div class="stepper-nav py-5 mt-5 d-none">
                    <div class="stepper-item current" data-kt-stepper-element="nav">
                        <h3 class="stepper-title">Akun</h3>
                    </div>
                    <div class="stepper-item" data-kt-stepper-element="nav">
                        <h3 class="stepper-title">Password</h3>
                    </div>
                </div>
                <form
                    class="mx-auto mw-600px w-100 pt-15 pb-10"
                    novalidate
                    id="kt_create_account_form"
                    @submit="handleStep"
                >
                    <div class="current" data-kt-stepper-element="content">
                        <Credential :formData="formData"></Credential>
                    </div>
                    <div data-kt-stepper-element="content">
                        <Password :formData="formData"></Password>
                    </div>
                    <div class="d-flex flex-stack pt-15">
                        <div class="mr-2">
                            <button
                                type="button"
                                class="btn btn-lg btn-light-primary me-3"
                                data-kt-stepper-action="previous"
                                @click="previousStep"
                            >
                                <KTIcon icon-name="arrow-left" icon-class="fs-4 me-1" />
                                Kembali
                            </button>
                        </div>

                        <div>
                            <button
                                type="submit"
                                id="submit-form"
                                class="btn btn-lg btn-primary me-3"
                                data-kt-stepper-action="submit"
                                v-if="currentStepIndex === totalSteps - 1"
                            >
                                <span class="indicator-label">
                                    Daftar
                                    <KTIcon icon-name="arrow-right" icon-class="fs-3 ms-2 me-0" />
                                </span>
                                <span class="indicator-progress">
                                    Memproses...
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>

                            <button
                                v-else
                                type="submit"
                                id="next-form"
                                class="btn btn-lg btn-primary"
                            >
                                <span class="indicator-label">
                                    Selanjutnya
                                    <KTIcon icon-name="arrow-right" icon-class="fs-4 ms-2 me-0" />
                                </span>
                                <span class="indicator-progress">
                                    <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                    </form>
                </div>

            <div class="border-bottom border-gray-300 w-100 mt-5 mb-10"></div>

            <div class="text-gray-400 fw-semobold fs-4 text-center">
                Sudah memiliki akun?
                <router-link to="/auth/sign-in" class="link-primary fw-bold">
                    Masuk
                </router-link>
            </div>
        </main>
    </div>
</template>

<script lang="ts">
import { getAssetPath } from "@/core/helpers/assets";
import { defineComponent, ref, onMounted, computed } from "vue";
import * as Yup from "yup";
import { StepperComponent } from "@/assets/ts/components";
import { useForm } from "vee-validate";
import Credential from "./steps/Credential.vue";
import Password from "./steps/Password.vue"; // Hanya import 2 ini
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import { blockBtn, unblockBtn } from "@/libs/utils";
import router from "@/router";
import { useSetting } from "@/services";

// Interface Sederhana
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
        Password, // VerifyEmail dan VerifyPhone SUDAH DIHAPUS
    },
    setup() {
        const { data: setting = {} } = useSetting();
        const _stepperObj = ref<StepperComponent | null>(null);
        const horizontalWizardRef = ref<HTMLElement | null>(null);
        const currentStepIndex = ref(0);

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

        // Schema Validasi (Hanya 2 Langkah)
        const createAccountSchema = [
            // Langkah 1: Data Diri
            Yup.object({
                nama: Yup.string().required("Nama tidak boleh kosong").label("Nama"),
                email: Yup.string().email().required("Email tidak boleh kosong").label("Email"),
                phone: Yup.string()
                    .matches(/^08[0-9]\d{8,11}$/, "No. Telepon tidak valid")
                    .required("No. Telepon tidak boleh kosong")
                    .label("No. Telepon"),
            }),
            // Langkah 2: Password
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
            if (_stepperObj.value) {
                return _stepperObj.value.totalStepsNumber;
            } else {
                return 1;
            }
        });

        const handleStep = handleSubmit((values) => {
            formData.value = { ...formData.value, ...values };

            if (currentStepIndex.value === 0) {
                // Dari Step 1 -> Lanjut ke Step 2
                currentStepIndex.value++;
                _stepperObj.value?.goNext();
            } else if (currentStepIndex.value === 1) {
                // Dari Step 2 -> Submit ke Backend
                formSubmit(formData.value);
            }
        });

        const previousStep = () => {
            if (!_stepperObj.value) return;
            currentStepIndex.value--;
            _stepperObj.value.goPrev();
        };

        const formSubmit = (values: CreateAccount) => {
            blockBtn("#submit-form");

            // Kirim data langsung ke Register API (Tanpa OTP)
            axios
                .post("/auth/register", values)
                .then((res) => {
                    toast.success("Akun berhasil dibuat! Silakan login.");
                    router.push({ name: "sign-in" });
                })
                .catch((err) => {
                    toast.error(err.response?.data?.message || "Terjadi kesalahan");
                    unblockBtn("#submit-form");
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
        };
    },
});
</script>