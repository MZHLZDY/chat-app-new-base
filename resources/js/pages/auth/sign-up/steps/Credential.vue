<template>
    <section class="w-100">
        <div class="fv-row mb-7">
            <label class="form-label fw-bold text-white fs-6" 
            style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Nama</label>
            <Field 
                class="form-control form-control-lg bg-transparent" 
                type="text" 
                name="nama" 
                placeholder="Nama Panggilan"
                autocomplete="off"
                v-model="formData.nama" 
            />
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="nama" />
                </div>
            </div>
        </div>

        <div class="fv-row mb-7">
            <label class="form-label fw-bold text-white fs-6" 
            style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Email</label>
            <Field 
                class="form-control form-control-lg bg-transparent" 
                type="email" 
                name="email" 
                placeholder="Contoh: example@gmail.com"
                autocomplete="off"
                v-model="formData.email" 
            />
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="email" />
                </div>
            </div>
        </div>

        <div class="fv-row mb-7">
            <label class="form-label fw-bold text-white fs-6" 
            style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">No. Telepon</label>
            <Field 
                class="form-control form-control-lg bg-transparent" 
                type="tel" 
                name="phone" 
                placeholder="Contoh: 08xx-xxxx-xxxx"
                autocomplete="off"
                v-model="formData.phone" 
            />
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="phone" />
                </div>
            </div>
        </div>
    </section>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { Field, ErrorMessage } from 'vee-validate'

export default defineComponent({
    name: 'Credential',
    components: { Field, ErrorMessage },
    props: {
        formData: { type: Object, required: true },
    },
})
</script>