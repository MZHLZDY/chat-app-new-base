<template>
    <div class="d-flex flex-column flex-lg-row flex-column-fluid h-100">
        
        <div class="d-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-1 order-lg-1 position-relative aside-img"
            :style="`background-image: url('${bgImage}')`">
            
            <div class="d-flex flex-column flex-center py-15 px-5 px-md-15 w-100 h-100 bg-overlay-gradient">
                <router-link to="/" class="mb-12">
                    <img :src="setting?.logo" class="h-70px" alt="Logo" />
                </router-link>
                
                <h1 class="text-white fs-2qx fw-bolder text-center mb-7">
                    Login ke ChatApp
                </h1>

                <div class="text-white fs-base text-center opacity-75">
                    Bergabunglah dengan komunitas kami dan rasakan pengalaman <br> komunikasi yang lebih baik dan aman.
                </div>
            </div>
        </div>

        <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-2 justify-content-center" 
        style="background-color: rgba(255, 255, 255, 0.3); backdrop-filter: blur(9px);">
            <div class="d-flex flex-center flex-column flex-lg-row-fluid">
                <div class="w-100 w-md-400px w-lg-500px p-10 p-lg-15 mx-auto">
                    
                    <div class="text-start mb-10">
                        <h1 class="text-white mb-5 fs-3x fw-bold"
                        style="text-shadow: 2px 2px 4px rgba(0,0,0,0.5);">Sign In</h1>
                    </div>

                    <div class="form-wrapper">
                        <WithEmail />
                    </div>

                    <div class="text-center text-gray-200 fw-semibold fs-4">
                            Belum punya akun? 
                            <router-link to="/sign-up" class="link-primary fw-bold">DAFTAR DISINI</router-link>
                    </div>

                    <!-- <div class="d-flex flex-center flex-wrap fs-6 p-5 pb-0">
                        <div class="d-flex flex-center fw-semibold fs-6">
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
import { defineComponent, ref } from "vue";
import { getAssetPath } from "@/core/helpers/assets";
import SignInWithEmail from "@/pages/auth/sign-in/tabs/WithEmail.vue";

export default defineComponent({
    name: "SignInSideDesign",
    components: {
        WithEmail: SignInWithEmail,
    },
    setup() {
        // Ganti gambar ini dengan gambar aesthetic (unsplash/pexels)
        const bgImage = getAssetPath("media/auth/bg10.jpeg"); 
        
        const setting = ref({
            logo: getAssetPath("media/logos/default-dark.svg"), // Logo versi putih jika ada, atau sesuaikan
            app: "ChatApp"
        });

        return {
            bgImage,
            setting
        };
    },
});
</script>

<style lang="scss" scoped>
/* Custom Style untuk nuansa Instagramable */

.aside-img {
    min-height: 300px; /* Tinggi minimal di mobile */
}

/* Gradient Overlay: Membuat teks putih di atas gambar selalu terbaca & terlihat elegan */
.bg-overlay-gradient {
    background: linear-gradient(to top, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.2));
}

@media (min-width: 992px) {
    .aside-img {
        height: 100vh; /* Full height di desktop */
    }
}

/* Sedikit styling tambahan untuk input form agar lebih modern (opsional) */
:deep(.form-control) {
    /* Border Biru Muda Permanen (tidak hanya saat hover/focus) */
    border: 3px solid #54b7f0 !important; 
    /* Opsional: Ubah background jadi transparan gelap agar border lebih menonjol */
    background-color: rgba(0, 0, 0, 0.2) !important;
    /* Pastikan teks yang diketik berwarna putih/terang agar kontras */
    color: #ffffff !important;
    border-radius: 10px; /* Sedikit melengkung agar modern */
}

/* Mengatur warna placeholder (teks petunjuk) agar terlihat di background gelap */
:deep(.form-control::placeholder) {
    color: rgba(255, 255, 255, 0.6) !important;
}

/* Saat diklik (Focus), border bisa dibuat lebih tebal atau bersinar */
:deep(.form-control:focus) {
    background-color: #ffffff;
    border-color: #009ef7; /* Primary color */
    box-shadow: 0 0 10px rgba(0, 158, 247, 0.3) !important; /* Efek glow biru */
}

:deep(.btn) {
    border-radius: 12px;
    padding: 12px;
    font-weight: 700;
}
</style>
// ini index.vue untuk sign-in