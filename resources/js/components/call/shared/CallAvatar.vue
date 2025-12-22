<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  photoUrl: string;       // URL foto profil
  displayName: string;    // Nama user (untuk alt text)
  size?: string;          // Ukuran avatar (default: 120px)
  isCalling?: boolean;    // Status untuk mengaktifkan animasi pulse
}

const props = withDefaults(defineProps<Props>(), {
  size: '120px',
  isCalling: false,
});

// Computed style untuk ukuran dinamis
const avatarStyle = computed(() => ({
  width: props.size,
  height: props.size,
}));
</script>

<template>
  <div class="call-avatar-wrapper" :style="avatarStyle">
    
    <div v-if="isCalling" class="pulse-ring delay-1"></div>
    <div v-if="isCalling" class="pulse-ring delay-2"></div>

    <div class="avatar-image-container">
      <img 
        :src="photoUrl" 
        :alt="displayName" 
        class="avatar-img"
      />
    </div>
  </div>
</template>

<style scoped>
/* Wrapper Utama */
.call-avatar-wrapper {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  /* Pastikan tidak ada margin default yang mengganggu layout parent */
}

/* Image Styling */
.avatar-image-container {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  overflow: hidden;
  z-index: 10; /* Pastikan gambar selalu di atas animasi pulse */
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); /* Sedikit bayangan agar pop-up */
  background-color: #ddd; /* Placeholder color jika gambar loading */
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Pulse Animation Logic */
.pulse-ring {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background-color: rgba(255, 255, 255, 0.4); /* Warna putih transparan */
  opacity: 0;
  z-index: 1; /* Di bawah gambar */
  animation: pulse-animation 2s infinite cubic-bezier(0.25, 0.8, 0.25, 1);
}

/* Delay agar animasi tidak bertumpuk bersamaan, menciptakan efek gelombang */
.delay-1 {
  animation-delay: 0s;
}

.delay-2 {
  animation-delay: 1s; /* Muncul di tengah-tengah animasi ring pertama */
}

/* Keyframes untuk efek membesar dan menghilang */
@keyframes pulse-animation {
  0% {
    transform: scale(1);
    opacity: 0.6;
  }
  100% {
    transform: scale(2.5); /* Membesar hingga 2.5x ukuran asli */
    opacity: 0; /* Menghilang perlahan */
  }
}
</style>