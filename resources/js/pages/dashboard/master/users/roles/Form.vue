<template>
    <VForm
        class="card mb-10"
        @submit="submit"
        :validation-schema="formSchema"
        id="form-contact"
        ref="formRef"
        v-slot="{ errors }"
    >
        <div class="card-header align-items-center">
            <h2 class="mb-0">{{ selected ? "Edit" : "Tambah" }} Kontak Baru</h2>
            <button
                type="button"
                class="btn btn-sm btn-light-danger ms-auto"
                @click="emit('close')"
                :disabled="isLoading"
            >
                Batal <i class="fas fa-times ms-2"></i>
            </button>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6 required">Nama Kontak</label>
                        <Field
                            class="form-control form-control-lg form-control-solid"
                            :class="{ 'is-invalid': errors.name }"
                            type="text"
                            name="name"
                            autocomplete="off"
                            v-model="contact.name"
                            placeholder="Contoh: Budi Santoso"
                        />
                        <div class="invalid-feedback">
                            <ErrorMessage name="name" />
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6 required">Nomor Telepon</label>
                        <Field
                            class="form-control form-control-lg form-control-solid"
                            :class="{ 'is-invalid': errors.phone }"
                            type="text"
                            name="phone"
                            autocomplete="off"
                            v-model="contact.phone"
                            placeholder="08xxxxxxxxxx"
                        />
                        <div class="invalid-feedback">
                            <ErrorMessage name="phone" />
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6">Email Kontak</label>
                        <Field
                            class="form-control form-control-lg form-control-solid"
                            :class="{ 'is-invalid': errors.email }"
                            type="text"
                            name="email"
                            autocomplete="off"
                            v-model="contact.email"
                            placeholder="email@contoh.com"
                        />
                        <div class="invalid-feedback">
                            <ErrorMessage name="email" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex">
            <button type="submit" class="btn btn-primary btn-sm ms-auto" :disabled="isLoading">
                <span v-if="!isLoading" class="indicator-label">Simpan Kontak</span>
                <span v-else class="indicator-progress">
                    Mohon tunggu... <span class="spinner-border spinner-border-sm align-middle ms-2"></span>
                </span>
            </button>
        </div>
    </VForm>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from "vue";
import { Form as VForm, Field, ErrorMessage } from "vee-validate"; // WAJIB DI-IMPORT
import * as Yup from "yup";
import axios from "@/libs/axios"; // Pastikan path axios benar, atau ganti 'axios' biasa
import { toast } from "vue3-toastify";

// --- Props & Emits ---
const props = defineProps({
    selected: {
        type: [String, Number], // Bisa ID string (UUID) atau Number
        default: null,
    },
});

const emit = defineEmits(["close", "refresh"]);

// --- State ---
const isLoading = ref(false);
const formRef = ref<any>(null);

const contact = ref({
    name: "",
    email: "",
    phone: "",
});

// --- Schema Validasi ---
const formSchema = Yup.object().shape({
    name: Yup.string().required("Nama kontak wajib diisi"),
    phone: Yup.string()
        .matches(/^[0-9]+$/, "Nomor telepon hanya boleh angka")
        .min(10, "Minimal 10 digit")
        .max(15, "Maksimal 15 digit")
        .required("Nomor Telepon wajib diisi"),
    email: Yup.string().email("Format email tidak valid").nullable(),
});

// --- Functions ---

// 1. Get Data (Edit Mode)
const getEdit = async () => {
    isLoading.value = true;
    try {
        // Sesuaikan endpoint ini dengan route Laravel kamu
        // Contoh: Route::get('master/users/{id}', ...)
        const { data } = await axios.get(`/master/users/${props.selected}`);
        
        // Mapping data dari backend ke state local
        // Sesuaikan 'data.data' dengan struktur JSON response kamu
        const result = data.data || data; 
        
        contact.value = {
            name: result.name,
            email: result.email,
            phone: result.phone,
        };
    } catch (err: any) {
        console.error(err);
        toast.error(err.response?.data?.message || "Gagal memuat data user");
    } finally {
        isLoading.value = false;
    }
};

// 2. Submit Form
const submit = async () => {
    isLoading.value = true;

    const formData = new FormData();
    formData.append("name", contact.value.name);
    formData.append("phone", contact.value.phone);
    formData.append("email", contact.value.email || "");
    
    // Jika User baru, biasanya butuh password default
    if (!props.selected) {
        formData.append("password", "12345678"); 
        formData.append("password_confirmation", "12345678");
    }

    // Tentukan URL & Method
    let url = "/master/users/store"; // URL Create
    if (props.selected) {
        url = `/master/users/${props.selected}`; // URL Update
        formData.append("_method", "PUT"); // Method Spoofing Laravel
    }

    try {
        await axios.post(url, formData);
        
        toast.success(`Berhasil ${props.selected ? 'mengupdate' : 'menyimpan'} kontak!`);
        emit("refresh"); // Refresh list di parent
        emit("close");   // Tutup modal
        
    } catch (err: any) {
        console.error(err);
        // Tampilkan pesan error spesifik jika ada
        if (err.response?.status === 422) {
             // Error validasi (misal email/phone duplikat)
             const errors = err.response.data.errors;
             // Jika ingin set error ke field form:
             if (formRef.value) formRef.value.setErrors(errors);
             toast.error("Validasi gagal. Cek kembali data anda.");
        } else {
             toast.error(err.response?.data?.message || "Terjadi kesalahan sistem.");
        }
    } finally {
        isLoading.value = false;
    }
};

// --- Lifecycle & Watchers ---

onMounted(() => {
    if (props.selected) {
        getEdit();
    }
});

watch(
    () => props.selected,
    (newVal) => {
        if (newVal) {
            getEdit();
        } else {
            // Reset form jika beralih ke mode tambah
            contact.value = { name: "", email: "", phone: "" };
            if (formRef.value) formRef.value.resetForm();
        }
    }
);
</script>