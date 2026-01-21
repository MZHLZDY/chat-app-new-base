<template>
    <section class="w-100">
        <div class="fv-row mb-7">
            <label class="form-label fw-bold text-white fs-6"
            style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Password</label>
            
            <div class="position-relative mb-3">
                <Field 
                    class="form-control form-control-lg bg-transparent" 
                    type="password" 
                    name="password"
                    placeholder="Minimal 8 Karakter"
                    autocomplete="off" 
                    v-model="formData.password" 
                />
                
                <span class="btn btn-sm btn-icon position-absolute translate-middle top-50 end-0 me-n2">
                    <i class="bi bi-eye-slash fs-2 text-white" @click="togglePassword"></i>
                </span>
            </div>
            
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="password" />
                </div>
            </div>
        </div>

        <div class="fv-row mb-7">
            <label class="form-label fw-bold text-white fs-6"
            style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Konfirmasi Password</label>
            
            <div class="position-relative mb-3">
                <Field 
                    class="form-control form-control-lg bg-transparent" 
                    type="password" 
                    name="password_confirmation"
                    placeholder="Ulangi Password"
                    autocomplete="off" 
                    v-model="formData.password_confirmation" 
                />
                
                <span class="btn btn-sm btn-icon position-absolute translate-middle top-50 end-0 me-n2">
                    <i class="bi bi-eye-slash fs-2 text-white" @click="toggleConfirmPassword"></i>
                </span>
            </div>
            
            <div class="fv-plugins-message-container">
                <div class="fv-help-block">
                    <ErrorMessage name="password_confirmation" />
                </div>
            </div>
        </div>
    </section>
</template>

<script lang="ts">
import { defineComponent } from 'vue'
import { Field, ErrorMessage } from 'vee-validate'

export default defineComponent({
    name: 'Password',
    components: { Field, ErrorMessage },
    props: {
        formData: { type: Object, required: true },
    },
    setup(props) {
        return {
            formData: props.formData,
        }
    },
    methods: {
        togglePassword(ev) {
            const el = document.querySelector("[name=password]") as HTMLInputElement;
            if (el.type === 'password') {
                el.type = 'text';
                ev.target.classList.add("bi-eye");
                ev.target.classList.remove("bi-eye-slash");
            } else {
                el.type = 'password';
                ev.target.classList.remove("bi-eye");
                ev.target.classList.add("bi-eye-slash");
            }
        },
        toggleConfirmPassword(ev) {
            const el = document.querySelector("[name=password_confirmation]") as HTMLInputElement;
            if (el.type === 'password') {
                el.type = 'text';
                ev.target.classList.add("bi-eye");
                ev.target.classList.remove("bi-eye-slash");
            } else {
                el.type = 'password';
                ev.target.classList.remove("bi-eye");
                ev.target.classList.add("bi-eye-slash");
            }
        }
    }
})
</script>