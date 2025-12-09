<template>
    <VForm
        class="form card mb-10"
        @submit="submit"
        :validation-schema="formSchema"
        id="form-contact"
        ref="formRef"
    >
        <div class="card-header align-items-center">
            <h2 class="mb-0">{{ selected ? "Edit" : "Tambah" }} Kontak Baru</h2>
            <button
                type="button"
                class="btn btn-sm btn-light-danger ms-auto"
                @click="emit('close')"
            >
                Batal
                <i class="la la-times-circle p-0"></i>
            </button>
        </div>

        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6 required">
                            Nama Kontak
                        </label>
                        <Field
                            class="form-control form-control-lg form-control-solid"
                            type="text"
                            name="name"
                            autocomplete="off"
                            v-model="contact.name"
                            placeholder="Contoh: Budi Santoso"
                        />
                        <div class="fv-plugins-message-container">
                            <div class="fv-help-block">
                                <ErrorMessage name="name" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6 required">
                            Nomor Telepon
                        </label>
                        <Field
                            class="form-control form-control-lg form-control-solid"
                            type="text"
                            name="phone"
                            autocomplete="off"
                            v-model="contact.phone"
                            placeholder="08xxxxxxxxxx"
                        />
                        <div class="fv-plugins-message-container">
                            <div class="fv-help-block">
                                <ErrorMessage name="phone" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="fv-row mb-7">
                        <label class="form-label fw-bold fs-6">
                            Email (Opsional)
                        </label>
                        <Field
                            class="form-control form-control-lg form-control-solid"
                            type="text"
                            name="email"
                            autocomplete="off"
                            v-model="contact.email"
                            placeholder="email@contoh.com"
                        />
                        <div class="fv-plugins-message-container">
                            <div class="fv-help-block">
                                <ErrorMessage name="email" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer d-flex">
            <button type="submit" class="btn btn-primary btn-sm ms-auto">
                <span class="indicator-label">Simpan Kontak</span>
            </button>
        </div>
    </VForm>
</template>

<script setup lang="ts">
import { block, unblock } from "@/libs/utils";
import { onMounted, ref, watch } from "vue";
import * as Yup from "yup";
import axios from "@/libs/axios";
import { toast } from "vue3-toastify";
import ApiService from "@/core/services/ApiService";

// Interface sederhana tanpa foto
interface Contact {
    id?: number;
    name: string;
    email?: string;
    phone: string;
}

const props = defineProps({
    selected: {
        type: String,
        default: null,
    },
});

const emit = defineEmits(["close", "refresh"]);

// Inisialisasi data kontak
const contact = ref<Contact>({
    name: "",
    email: "",
    phone: "",
} as Contact);

const formRef = ref();

// Schema Validasi (Tanpa password, role, dan foto)
const formSchema = Yup.object().shape({
    name: Yup.string().required("Nama kontak harus diisi"),
    phone: Yup.string()
        .matches(/^08[0-9]\d{8,11}$/, "Nomor telepon tidak valid")
        .required("Nomor Telepon harus diisi"),
    email: Yup.string().email("Format email salah").nullable(),
});

function getEdit() {
    block(document.getElementById("form-contact"));
    ApiService.get("contacts", props.selected)
        .then(({ data }) => {
            // Ambil data dari backend
            contact.value = data.contact;
        })
        .catch((err: any) => {
            toast.error(err.response?.data?.message || "Gagal memuat data");
        })
        .finally(() => {
            unblock(document.getElementById("form-contact"));
        });
}

function submit() {
    const formData = new FormData();
    formData.append("name", contact.value.name);
    formData.append("phone", contact.value.phone);
    
    if (contact.value.email) {
        formData.append("email", contact.value.email);
    } else {
        const randomEmail = contact.value.name.replace(/\s+/g, '').toLowerCase() + Math.floor(Math.random() * 1000) + "@test.com";
        formData.append("email", randomEmail);
    }

    const url = props.selected 
        ? `/master/users/${props.selected}` 
        : "/master/users"; 

    if (props.selected) {
        formData.append("_method", "PUT");
    }

    block(document.getElementById("form-contact"));

    axios({
        method: "post",
        url: url,
        data: formData,
    })
        .then(() => {
            emit("close");
            emit("refresh");
            toast.success("Kontak berhasil disimpan!");
            formRef.value.resetForm();
        })
        .catch((err: any) => {
            console.error(err); 
            toast.error(err.response?.data?.message || "Gagal menyimpan kontak");
        })
        .finally(() => {
            unblock(document.getElementById("form-contact"));
        });
}

onMounted(async () => {
    if (props.selected) {
        getEdit();
    }
});

watch(
    () => props.selected,
    () => {
        if (props.selected) {
            getEdit();
        } else {
            // Reset form jika mode tambah
            contact.value = { name: "", email: "", phone: "" };
        }
    }
);
</script>