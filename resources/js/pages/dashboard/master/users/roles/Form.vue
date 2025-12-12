<template>
    <VForm class="card mb-10" @submit="submit" :validation-schema="formSchema" id="form-contact">
        <div class="card-header align-items-center">
            <h2 class="mb-0">Tambah Kontak Baru</h2>
            <button type="button" class="btn btn-sm btn-light-danger ms-auto" @click="emit('close')" :disabled="isLoading">
                Batal
            </button>
        </div>

        <div class="card-body">
            <div class="alert alert-primary p-3 mb-5">
                <small>Pastikan nomor HP teman Anda sudah terdaftar di sistem.</small>
            </div>

            <div class="row">
                <div class="col-md-12">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6 required">Nama Kontak (Alias)</label>
                        <Field 
                            class="form-control form-control-lg form-control-solid" 
                            name="name" 
                            v-model="contact.name" 
                            placeholder="Misal: Teman Kuliah" 
                        />
                        <div class="text-danger mt-1"><ErrorMessage name="name" /></div>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6 required">Nomor Telepon (Terdaftar)</label>
                        <Field 
                            class="form-control form-control-lg form-control-solid" 
                            type="text" 
                            name="phone" 
                            v-model="contact.phone" 
                            placeholder="08xxxxxxxxxx" 
                        />
                        <div class="text-danger mt-1"><ErrorMessage name="phone" /></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex">
            <button type="submit" class="btn btn-primary btn-sm ms-auto" :disabled="isLoading">
                <span v-if="!isLoading">Cari & Simpan</span>
                <span v-else>Memproses...</span>
            </button>
        </div>
    </VForm>
</template>

<script setup lang="ts">
import { ref } from "vue";
import { Form as VForm, Field, ErrorMessage } from "vee-validate";
import * as Yup from "yup";
import axios from "@/libs/axios"; 
import { toast } from "vue3-toastify";

const emit = defineEmits(["close", "refresh"]);
const isLoading = ref(false);
const contact = ref({ name: "", phone: "" });

// Validasi Form
const formSchema = Yup.object().shape({
    name: Yup.string().required("Nama alias wajib diisi"),
    phone: Yup.string().required("Nomor HP wajib diisi"),
});

const submit = async () => {
    isLoading.value = true;
    try {
        // Panggil API Backend
        await axios.post("/chat/add-contact", {
            name: contact.value.name,
            phone: contact.value.phone
        });

        toast.success("Kontak berhasil disimpan!");
        emit("refresh");
        emit("close");
    } catch (err: any) {
        if (err.response?.status === 404) {
            toast.error("Gagal: Nomor HP tersebut belum terdaftar di aplikasi.");
        } else if (err.response?.status === 422) {
            toast.error(err.response.data.message);
        } else {
            toast.error("Terjadi kesalahan sistem.");
        }
    } finally {
        isLoading.value = false;
    }
};
</script>