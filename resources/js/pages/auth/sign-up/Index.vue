<template>
    <div class="d-flex flex-column flex-lg-row flex-column-fluid h-100">

        <div class="d-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-1 order-lg-1 position-relative aside-img"
             :style="`background-image: url('${getAssetPath('media/misc/auth-bg.png')}')`">

            <div class="position-absolute top-0 start-0 w-100 h-100"
                 style="background-color: rgba(0, 0, 0, 0.5); z-index: 1;">
            </div>

            <div class="d-flex flex-column flex-center py-7 py-lg-15 px-5 px-md-15 w-100 position-relative" style="z-index: 2;">
                <router-link to="/" class="mb-0 mb-lg-12">
                    <img :src="setting?.logo_depan" alt="Logo" class="h-60px h-lg-75px" />
                </router-link>

                <h1 class="text-white fs-2qx fw-bolder text-center mb-7">
                    Registrasi Akun
                </h1>
                <div class="text-white fs-base text-center opacity-75">
                    Bergabunglah dengan komunitas kami dan rasakan pengalaman <br>
                    komunikasi yang lebih baik dan aman.
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-2 justify-content-center" style="background-color: rgba(255, 255, 255, 0.3); backdrop-filter: blur(9px);">
            <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                <div class="w-lg-500px p-10">

                    <div class="text-center mb-10">
                        <h1 class="text-white mb-5 fs-3x fw-bold"
                        style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Sign Up</h1>
                    </div>

                    <div v-if="showEmailVerificationAlert" class="alert alert-success d-flex align-items-center mb-10">
                        <i class="ki-duotone ki-shield-tick fs-2hx text-success me-4">
                            <span class="path1"></span>
                            <span class="path2"></span>
                        </i>
                        <div class="d-flex flex-column">
                            <h4 class="mb-1 text-success">Registrasi Berhasil!</h4>
                            <span>Email verifikasi telah dikirim ke <strong>{{ formData.email }}</strong>. Cek inbox/spam.</span>
                        </div>
                    </div>

                    <div v-if="!showEmailVerificationAlert"
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
                            class="mx-auto w-100 pb-10"
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

                            <div class="d-flex flex-stack pt-10">
                                <div class="mr-2">
                                    <button
                                        type="button"
                                        class="btn btn-lg btn-light-primary me-3"
                                        data-kt-stepper-action="previous"
                                        @click="previousStep"
                                        v-if="currentStepIndex > 0"
                                    >
                                        <KTIcon icon-name="arrow-left" icon-class="fs-4 me-1" />
                                        Kembali
                                    </button>
                                </div>

                                <div>
                                    <button
                                        type="submit"
                                        id="submit-form"
                                        class="btn btn-lg btn-primary w-100 mb-5"
                                        data-kt-stepper-action="submit"
                                        v-if="currentStepIndex === totalSteps - 1"
                                    >
                                        <span class="indicator-label">
                                            Daftar
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
                                        class="btn btn-lg btn-primary w-100 mb-5"
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

                    <div v-else class="text-center pt-10">
                        <router-link to="/sign-in" class="btn btn-lg btn-primary w-100">
                            Kembali ke Login
                        </router-link>
                    </div>

                    <div class="text-center text-gray-200 fw-bold fs-4">
                            Sudah punya akun?
                            <router-link to="/sign-in" class="link-primary fw-bolder">
                                LOGIN DISINI
                            </router-link>
                    </div>

                    <!-- <div class="d-flex flex-center flex-wrap fs-6 p-5 pb-0">
                        <div class="d-flex flex-center fw-bold fs-6">
                            <a href="#" class="text-muted text-hover-primary px-2" target="_blank">Tentang</a>
                            <a href="#" class="text-muted text-hover-primary px-2" target="_blank">Support</a>
                        </div>
                    </div> -->

                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts">
// ... (Bagian script tidak ada perubahan, tetap sama seperti sebelumnya)
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
            if (_stepperObj.value) {
                return _stepperObj.value.totalStepsNumber;
            } else {
                return 1;
            }
        });

        const handleStep = handleSubmit((values) => {
            formData.value = { ...formData.value, ...values };

            if (currentStepIndex.value === 0) {
                currentStepIndex.value++;
                _stepperObj.value?.goNext();
            } else if (currentStepIndex.value === 1) {
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

            axios
                .post("/auth/register", values)
                .then((res) => {
                    toast.success(res.data.message);
                    showEmailVerificationAlert.value = true;
                    window.scrollTo({ top: 0, behavior: 'smooth' });
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
            showEmailVerificationAlert,
        };
    },
});
</script>
// ini index.vue untuk sign-up