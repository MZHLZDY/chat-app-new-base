<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';

// State untuk menyimpan total detik
const secondsElapsed = ref(0);
let timerInterval: number | null = null;

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

// Jalankan timer saat komponen aktif
onMounted(() => {
  timerInterval = window.setInterval(() => {
    secondsElapsed.value++;
  }, 1000);
});

// Bersihkan interval saat komponen tidak lagi digunakan (memory leak prevention)
onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
});
</script>

<template>
  <div class="call-timer">
    <span class="timer-text">{{ formattedTime }}</span>
  </div>
</template>

<style scoped>
.call-timer {
  display: inline-block;
  padding: 4px 12px;
  background-color: rgba(255, 255, 255, 0.1);
  border-radius: 12px;
  backdrop-filter: blur(4px);
}

.timer-text {
  font-family: 'Courier New', Courier, monospace; /* Font monospace agar angka tidak goyang saat berubah */
  font-size: 1.1rem;
  font-weight: 600;
  color: #ffffff;
  letter-spacing: 1px;
}
</style>