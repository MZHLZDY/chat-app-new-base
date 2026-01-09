<script setup lang="ts">
import { getAssetPath } from "@/core/helpers/assets";
import { useAuthStore } from "@/stores/auth";
import { ref, onMounted } from "vue";
import { ErrorMessage, Field, Form as VForm } from "vee-validate";
import Swal from "sweetalert2/dist/sweetalert2.js";
import * as Yup from "yup";
import axios from "axios";

// State UI
const isLoading = ref(false);
const submitButton1 = ref<HTMLButtonElement | null>(null);
const updateEmailButton = ref<HTMLButtonElement | null>(null);
const updatePasswordButton = ref<HTMLButtonElement | null>(null);
const emailFormDisplay = ref(false);
const passwordFormDisplay = ref(false);
const authStore = useAuthStore();

// State Data User
const profileDetails = ref({
    photo: getAssetPath("media/avatars/blank.png"),
    name: "",
    bio: "",
    phone: "",
    email: "",
});

const avatarFile = ref<File | null>(null);
const isAvatarRemoved = ref(false);

onMounted(async () => {
    try {
        const response = await axios.get("/dashboard/profile");
        const data = response.data.data;
        
        profileDetails.value = {
            photo: data.photo || getAssetPath("media/avatars/blank.png"),
            name: data.name,
            bio: data.bio || "",
            phone: data.phone || "",
            email: data.email,
        };
    } catch (error) {
        console.error("Gagal memuat profil", error);
    }
});

// --- VALIDASI ---
const profileDetailsValidator = Yup.object().shape({
    name: Yup.string().required().label("Display Name"),
    phone: Yup.string().required().label("Phone number"),
    bio: Yup.string().max(100).label("Bio"),
});

const changeEmailSchema = Yup.object().shape({
    emailaddress: Yup.string().required().email().label("Email"),
    confirmemailpassword: Yup.string().required().label("Password"),
});

const changePasswordSchema = Yup.object().shape({
    currentpassword: Yup.string().required().label("Current password"),
    newpassword: Yup.string().min(6).required().label("New Password"),
    confirmpassword: Yup.string()
        .min(6)
        .required()
        .oneOf([Yup.ref("newpassword")], "Passwords must match")
        .label("Password Confirmation"),
});

// --- LOGIC HANDLING GAMBAR ---
const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        avatarFile.value = target.files[0];
        isAvatarRemoved.value = false;
        const reader = new FileReader();
        reader.onload = (e) => {
            profileDetails.value.photo = e.target?.result as string;
        };
        reader.readAsDataURL(target.files[0]);
    }
};

const removeImage = () => {
    profileDetails.value.photo = getAssetPath("media/avatars/blank.png");
    avatarFile.value = null;
    isAvatarRemoved.value = true;
};

const saveChanges1 = async (values: any) => {
    if (!submitButton1.value) return;

    // UI Loading
    submitButton1.value.setAttribute("data-kt-indicator", "on");
    submitButton1.value.disabled = true;

    try {
        const formData = new FormData();
        formData.append("name", values.name);
        formData.append("phone", values.phone);
        formData.append("bio", values.bio || "");

        if (avatarFile.value) {
            formData.append("avatar", avatarFile.value);
        }
        
        if (isAvatarRemoved.value) {
            formData.append("avatar_remove", "1");
        }

        // Kirim ke Backend
        const response = await axios.post("/dashboard/profile", formData, {
            headers: { "Content-Type": "multipart/form-data" }
        });

        // Update state Pinia secara manual agar CallAvatar langsung berubah
        if (profileDetails.value.photo) {
            authStore.setAuth({
                ...authStore.user, // Ambil data user lama
                name: values.name, // Update nama baru
                photo: profileDetails.value.photo, // Update foto (Base64 string)
                profile_photo_url: profileDetails.value.photo // Update url juga untuk safety
            });
        }

        Swal.fire({
            text: response.data.message || "Profil berhasil diperbarui!",
            icon: "success",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: { confirmButton: "btn btn-primary" },
        });

    } catch (error: any) {
        Swal.fire({
            text: error.response?.data?.message || "Terjadi kesalahan sistem.",
            icon: "error",
            buttonsStyling: false,
            confirmButtonText: "Ok",
            customClass: { confirmButton: "btn btn-danger" },
        });
    } finally {
        submitButton1.value.removeAttribute("data-kt-indicator");
        submitButton1.value.disabled = false;
    }
};

const updateEmail = async (values: any) => {
    if (!updateEmailButton.value) return;
    
    updateEmailButton.value.setAttribute("data-kt-indicator", "on");
    
    try {
        await axios.post("/dashboard/profile/email", {
            email: values.emailaddress,
            password: values.confirmemailpassword
        });

        profileDetails.value.email = values.emailaddress;
        emailFormDisplay.value = false;
        
        Swal.fire({ text: "Email updated successfully!", icon: "success", confirmButtonText: "Ok" });
    } catch (error: any) {
        Swal.fire({ text: error.response?.data?.message || "Error updating email", icon: "error", confirmButtonText: "Ok" });
    } finally {
        updateEmailButton.value?.removeAttribute("data-kt-indicator");
    }
};

const updatePassword = async (values: any, { resetForm }: any) => {
    if (!updatePasswordButton.value) return;

    updatePasswordButton.value.setAttribute("data-kt-indicator", "on");

    try {
        await axios.post("/dashboard/profile/password", {
            current_password: values.currentpassword,
            password: values.newpassword,
            password_confirmation: values.confirmpassword
        });

        passwordFormDisplay.value = false;
        resetForm(); 
        Swal.fire({ text: "Password changed successfully!", icon: "success", confirmButtonText: "Ok" });
    } catch (error: any) {
        Swal.fire({ text: error.response?.data?.message || "Error changing password", icon: "error", confirmButtonText: "Ok" });
    } finally {
        updatePasswordButton.value?.removeAttribute("data-kt-indicator");
    }
};
</script>

<template>
    <div class="card mb-5 mb-xl-10">
        <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#kt_account_profile_details" aria-expanded="true" aria-controls="kt_account_profile_details">
            <div class="card-title m-0">
                <h3 class="fw-bold m-0">Profil Saya</h3>
            </div>
        </div>
        
        <div id="kt_account_profile_details" class="collapse show">
            <VForm id="kt_account_profile_details_form" class="form" novalidate @submit="saveChanges1" :validation-schema="profileDetailsValidator" :initial-values="profileDetails">
                
                <div class="card-body border-top p-9">
                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Avatar</label>
                        <div class="col-lg-8">
                            <div class="image-input image-input-outline" data-kt-image-input="true" :style="{ backgroundImage: `url(${getAssetPath('/media/avatars/blank.png')})` }">
                                <div class="image-input-wrapper w-125px h-125px" :style="`background-image: url(${profileDetails.photo})`"></div>
                                
                                <label class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="change" data-bs-toggle="tooltip" title="Change avatar">
                                    <i class="bi bi-pencil-fill fs-7"></i>
                                    <input type="file" name="avatar" accept=".png, .jpg, .jpeg" @change="onFileChange" />
                                    <input type="hidden" name="avatar_remove" />
                                </label>

                                <span class="btn btn-icon btn-circle btn-active-color-primary w-25px h-25px bg-body shadow" data-kt-image-input-action="remove" data-bs-toggle="tooltip" @click="removeImage()" title="Remove avatar">
                                    <i class="bi bi-x fs-2"></i>
                                </span>
                            </div>
                            <div class="form-text">Tipe file: png, jpg, jpeg. Maks 2MB.</div>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label required fw-semibold fs-6">Nama Lengkap</label>
                        <div class="col-lg-8 fv-row">
                            <Field type="text" name="name" class="form-control form-control-lg form-control-solid" placeholder="Nama Lengkap Anda" v-model="profileDetails.name"/>
                            <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="name" /></div></div>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Bio / Status</label>
                        <div class="col-lg-8 fv-row">
                            <Field type="text" name="bio" class="form-control form-control-lg form-control-solid" placeholder="Contoh: Sibuk, Di Gym..." v-model="profileDetails.bio"/>
                            <div class="form-text">Status singkat ini akan muncul di profil Anda.</div>
                        </div>
                    </div>

                    <div class="row mb-6">
                        <label class="col-lg-4 col-form-label fw-semibold fs-6">Nomor Telepon</label>
                        <div class="col-lg-8 fv-row">
                            <Field type="tel" name="phone" class="form-control form-control-lg form-control-solid" placeholder="08123xxxx" v-model="profileDetails.phone"/>
                            <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="phone" /></div></div>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-end py-6 px-9">
                    <button type="submit" id="kt_account_profile_details_submit" ref="submitButton1" class="btn btn-primary">
                        <span class="indicator-label">Simpan Perubahan</span>
                        <span class="indicator-progress">Menyimpan... <span class="spinner-border spinner-border-sm align-middle ms-2"></span></span>
                    </button>
                </div>
            </VForm>
        </div>
    </div>

    <div class="card mb-5 mb-xl-10">
        <div class="card-header border-0 cursor-pointer" role="button" data-bs-toggle="collapse" data-bs-target="#kt_account_signin_method">
            <div class="card-title m-0"><h3 class="fw-bold m-0">Metode Login</h3></div>
        </div>
        
        <div id="kt_account_signin_method" class="collapse show">
            <div class="card-body border-top p-9">
                
                <div class="d-flex flex-wrap align-items-center mb-8">
                    <div id="kt_signin_email" :class="{ 'd-none': emailFormDisplay }">
                        <div class="fs-4 fw-bold mb-1">Email Address</div>
                        <div class="fs-6 fw-semibold text-gray-600">{{ profileDetails.email }}</div>
                    </div>
                    
                    <div id="kt_signin_email_edit" :class="{ 'd-none': !emailFormDisplay }" class="flex-row-fluid">
                        <VForm id="kt_signin_change_email" class="form" novalidate @submit="updateEmail" :validation-schema="changeEmailSchema">
                            <div class="row mb-6">
                                <div class="col-lg-6 mb-4 mb-lg-0">
                                    <div class="fv-row mb-0">
                                        <label for="emailaddress" class="form-label fs-6 fw-bold mb-3">Email Baru</label>
                                        <Field type="email" class="form-control form-control-lg form-control-solid" name="emailaddress" placeholder="Email Baru" />
                                        <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="emailaddress" /></div></div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="fv-row mb-0">
                                        <label for="confirmemailpassword" class="form-label fs-6 fw-bold mb-3">Konfirmasi Password Saat Ini</label>
                                        <Field type="password" class="form-control form-control-lg form-control-solid" name="confirmemailpassword" />
                                        <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="confirmemailpassword" /></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex">
                                <button type="submit" ref="updateEmailButton" class="btn btn-primary me-2 px-6">Update Email</button>
                                <button type="button" class="btn btn-color-gray-500 btn-active-light-primary px-6" @click="emailFormDisplay = !emailFormDisplay">Batal</button>
                            </div>
                        </VForm>
                    </div>
                    
                    <div id="kt_signin_email_button" :class="{ 'd-none': emailFormDisplay }" class="ms-auto">
                        <button class="btn btn-light fw-bold px-6" @click="emailFormDisplay = !emailFormDisplay">Ganti Email</button>
                    </div>
                </div>

                <div class="separator separator-dashed my-6"></div>

                <div class="d-flex flex-wrap align-items-center mb-8">
                    <div id="kt_signin_password" :class="{ 'd-none': passwordFormDisplay }">
                        <div class="fs-4 fw-bold mb-1">Password</div>
                        <div class="fs-6 fw-semibold text-gray-600">************</div>
                    </div>
                    
                    <div id="kt_signin_password_edit" class="flex-row-fluid" :class="{ 'd-none': !passwordFormDisplay }">
                        <VForm id="kt_signin_change_password" class="form" novalidate @submit="updatePassword" :validation-schema="changePasswordSchema">
                            <div class="row mb-6">
                                <div class="col-lg-4">
                                    <div class="fv-row mb-0">
                                        <label for="currentpassword" class="form-label fs-6 fw-bold mb-3">Password Saat Ini</label>
                                        <Field type="password" class="form-control form-control-lg form-control-solid" name="currentpassword" />
                                        <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="currentpassword" /></div></div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="fv-row mb-0">
                                        <label for="newpassword" class="form-label fs-6 fw-bold mb-3">Password Baru</label>
                                        <Field type="password" class="form-control form-control-lg form-control-solid" name="newpassword" />
                                        <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="newpassword" /></div></div>
                                    </div>
                                </div>
                                <div class="col-lg-4">
                                    <div class="fv-row mb-0">
                                        <label for="confirmpassword" class="form-label fs-6 fw-bold mb-3">Konfirmasi Password Baru</label>
                                        <Field type="password" class="form-control form-control-lg form-control-solid" name="confirmpassword" />
                                        <div class="fv-plugins-message-container"><div class="fv-help-block"><ErrorMessage name="confirmpassword" /></div></div>
                                    </div>
                                </div>
                            </div>
                            <div class="d-flex">
                                <button type="submit" ref="updatePasswordButton" class="btn btn-primary me-2 px-6">Ganti Password</button>
                                <button type="button" @click="passwordFormDisplay = !passwordFormDisplay" class="btn btn-color-gray-500 btn-active-light-primary px-6">Batal</button>
                            </div>
                        </VForm>
                    </div>

                    <div id="kt_signin_password_button" class="ms-auto" :class="{ 'd-none': passwordFormDisplay }">
                        <button @click="passwordFormDisplay = !passwordFormDisplay" class="btn btn-light fw-bold">Reset Password</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>