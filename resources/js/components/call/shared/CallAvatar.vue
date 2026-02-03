<script setup lang="ts">
import { computed } from 'vue';
import { useAuthStore } from "@/stores/auth";

interface Props {
  photoUrl?: string | null; // Bisa terima null/string
  displayName?: string;
  size?: string;
  isCalling?: boolean;
  allowAuthFallback?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
  size: '120px',
  isCalling: false,
  photoUrl: '',
  displayName: '',
  allowAuthFallback: false, // Default true, tapi dimatikan oleh VoiceIncomingModal
});

const authStore = useAuthStore();

// --- LOGIC MENIRU HALAMAN CHAT ---
// BUKA FILE: src/components/call/shared/CallAvatar.vue

const resolvedPhotoUrl = computed(() => {
  const photo = props.photoUrl;
  const name = props.displayName || 'Unknown';

  // --- DEBUGGING LOG (Lihat di Console Browser F12) ---
  console.log('🔍 AVATAR CHECK:', {
    name: name,
    inputPhoto: photo,
    isHttp: photo?.startsWith('http'),
    fallbackMode: props.allowAuthFallback
  });

  // 1. Jika photo kosong/null
  if (!photo || photo.trim() === '') {
    if (props.allowAuthFallback) {
      return authStore.userPhotoUrl;
    }
    return `https://ui-avatars.com/api/?name=${encodeURIComponent(name)}&color=7F9CF5&background=EBF4FF`;
  }

  // 2. Jika photo sudah Full URL
  if (photo.startsWith('http')) {
    return photo;
  }

  // 3. Jika photo adalah Path Relative (Masalah sering di sini!)
  const storageUrl = import.meta.env.VITE_STORAGE_URL || 'http://localhost:8000/storage';
  let cleanBase = storageUrl.replace(/\/$/, '');
  let cleanPath = photo.startsWith('/') ? photo : `/${photo}`;

  if (cleanBase.endsWith('/storage') && cleanPath.startsWith('/storage')) {
      cleanPath = cleanPath.replace('/storage', '');
  }
  
  const result = `${cleanBase}${cleanPath}`;
  console.log('🚀 GENERATED URL:', result); // Cek apakah URL ini bisa dibuka di tab baru?
  return result;
});
</script>

<template>
  <div class="avatar-wrapper" :style="{ width: size, height: size }">
    <div v-if="isCalling" class="pulse-ring"></div>
    <div v-if="isCalling" class="pulse-ring delay"></div>

    <div class="avatar-image-container">
      <img 
        :src="resolvedPhotoUrl" 
        :alt="displayName"
        class="avatar-img"
        @error="(e) => { 
          console.error('❌ GAGAL LOAD GAMBAR:', resolvedPhotoUrl); // Ini akan muncul jika gambar rusak/404
          (e.target as HTMLImageElement).src = `https://ui-avatars.com/api/?name=${encodeURIComponent(displayName || 'U')}&background=random` 
        }"
      />
    </div>
  </div>
</template>

<style scoped>
.avatar-wrapper {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
}

.avatar-image-container {
  position: relative;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  overflow: hidden;
  z-index: 10;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  background-color: #f3f4f6;
}

.avatar-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.pulse-ring {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background-color: rgba(177, 173, 173, 0.4);
  opacity: 0;
  z-index: 1;
  animation: pulse-animation 2s infinite cubic-bezier(0.25, 0.8, 0.25, 1);
}

.pulse-ring.delay {
  animation-delay: 0.5s;
}

@keyframes pulse-animation {
  0% {
    transform: scale(1);
    opacity: 0.6;
  }
  100% {
    transform: scale(1.6);
    opacity: 0;
  }
}
</style>