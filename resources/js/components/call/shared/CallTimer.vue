<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { themeMode } from "@/layouts/default-layout/config/helper"; 

// Terima startTime dari luar
const props = defineProps<{
  startTime?: string | Date;
}>();

// State untuk menyimpan total detik
const secondsElapsed = ref(0);
let timerInterval: number | null = null;

// Fungsi hitung selisih waktu
const updateTimer = () => {
  if (!props.startTime) {
    secondsElapsed.value = 0;
    return;
  }

  const start = new Date(props.startTime).getTime();
  const now = new Date().getTime();

  // Rumus waktu sekarang - waktu mulai = durasi asli
  const diff = Math.floor((now - start) / 1000);
  secondsElapsed.value = diff > 0 ? diff : 0;
};

const currentThemeMode = computed(() => themeMode.value);

onMounted(() => {
  updateTimer(); //Hitung langsung pas load (biar ga nunggu 1 detik)
  timerInterval = window.setInterval(updateTimer, 1000); // update tiap detik
});

// Mengubah detik menjadi format MM:SS atau HH:MM:SS
const formattedTime = computed(() => {
  const hrs = Math.floor(secondsElapsed.value / 3600);
  const mins = Math.floor((secondsElapsed.value % 3600) / 60);
  const secs = secondsElapsed.value % 60;

  const parts = [
    mins.toString().padStart(2, '0'),
    secs.toString().padStart(2, '0')
  ];

  // Jika durasi sudah lebih dari 1 jam, tambahkan bagian jam di depan
  if (hrs > 0) {
    parts.unshift(hrs.toString().padStart(2, '0'));
  }

  return parts.join(':');
});

// Bersihkan interval saat komponen tidak lagi digunakan (memory leak prevention)
onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
  <div class="call-timer" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    <span class="timer-text">{{ formattedTime }}</span>
  </div>
</template>

<style scoped>
.call-timer {
  display: inline-block;
  padding: 4px 12px;
  background-color: rgba(88, 87, 87, 0.644);
  border-radius: 12px;
  backdrop-filter: blur(4px);
}

.dark-mode {
  background-color: rgba(255, 255, 255, 0.1);
}

.timer-text {
  font-family: 'Courier New', Courier, monospace; /* Font monospace agar angka tidak goyang saat berubah */
  font-size: 1.1rem;
  font-weight: 600;
  color: #ffffff;
  letter-spacing: 1px;
}
</style>