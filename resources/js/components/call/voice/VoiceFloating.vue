<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { themeMode } from "@/layouts/default-layout/config/helper";
import { Maximize2, Mic, MicOff, PhoneOff, GripHorizontal } from 'lucide-vue-next';
import CallAvatar from '../shared/CallAvatar.vue';
import CallTimer from '../shared/CallTimer.vue';

interface Props {
  remoteName: string;
  remotePhoto: string;
  isMuted: boolean;
  volumeLevel?: number; 
}

const props = withDefaults(defineProps<Props>(), {
  volumeLevel: 0,
});

const currentThemeMode = computed(() => themeMode.value);

const emit = defineEmits(['maximize', 'toggleMute', 'endCall']);

// --- 1. LOGIC DRAGGABLE (Bisa Digeser) ---
const floatingRef = ref<HTMLElement | null>(null);
const isDragging = ref(false);

// Posisi awal (Default: Kanan atas, dengan margin 20px)
const position = ref({ 
  x: window.innerWidth - 300, 
  y: 80 
}); 

// Offset mouse terhadap pojok kiri atas elemen saat diklik
const offset = ref({ x: 0, y: 0 });

const startDrag = (event: MouseEvent) => {
  // Hanya aktifkan drag jika user klik area kartu (bukan tombol control)
  // (Mencegah drag saat user sebenarnya ingin klik tombol mute)
  if ((event.target as HTMLElement).closest('button')) return;

  isDragging.value = true;
  
  // Hitung jarak cursor mouse dari pojok elemen
  offset.value = {
    x: event.clientX - position.value.x,
    y: event.clientY - position.value.y
  };

  // Pasang event listener ke window agar drag tetap jalan meski mouse keluar cepat dari elemen
  window.addEventListener('mousemove', onDrag);
  window.addEventListener('mouseup', stopDrag);
};

const onDrag = (event: MouseEvent) => {
  if (!isDragging.value) return;
  event.preventDefault(); // Mencegah seleksi teks saat drag

  // Hitung posisi baru
  let newX = event.clientX - offset.value.x;
  let newY = event.clientY - offset.value.y;

  // --- BOUNDARY CHECK (Agar tidak keluar layar) ---
  const elementWidth = 280; // Lebar kartu
  const elementHeight = 80; // Tinggi kartu
  const screenWidth = window.innerWidth;
  const screenHeight = window.innerHeight;

  // Batasi X (Horizontal)
  if (newX < 10) newX = 10; // Batas kiri
  if (newX + elementWidth > screenWidth - 10) newX = screenWidth - elementWidth - 10; // Batas kanan

  // Batasi Y (Vertikal)
  if (newY < 10) newY = 10; // Batas atas
  if (newY + elementHeight > screenHeight - 10) newY = screenHeight - elementHeight - 10; // Batas bawah

  position.value = { x: newX, y: newY };
};

const stopDrag = () => {
  isDragging.value = false;
  window.removeEventListener('mousemove', onDrag);
  window.removeEventListener('mouseup', stopDrag);
};

// --- 2. LOGIC AUDIO VISUALIZER (Simulasi) ---
const simulatedVolume = ref(0);
let audioInterval: number | null = null;

onMounted(() => {
  // Update posisi jika window di resize agar tidak hilang
  window.addEventListener('resize', () => {
    position.value = { x: window.innerWidth - 300, y: 80 };
  });

  audioInterval = window.setInterval(() => {
    simulatedVolume.value = Math.random() > 0.5 ? Math.random() * 40 : 0;
  }, 150);
});

onUnmounted(() => {
  if (audioInterval) clearInterval(audioInterval);
  window.removeEventListener('mousemove', onDrag);
  window.removeEventListener('mouseup', stopDrag);
});
</script>

<template>
  <div :class="{ 'dark-mode': currentThemeMode === 'dark' }">
  <div 
    ref="floatingRef"
    class="floating-card glass-effect"
    :class="{ 'is-dragging': isDragging }"
    :style="{ top: `${position.y}px`, left: `${position.x}px` }"
    @mousedown="startDrag"
  >
    
    <div class="drag-handle">
      <GripHorizontal :size="16" class="grip-icon" />
    </div>

    <div class="mini-visual-wrapper">
      <div 
        class="mini-pulse"
        :style="{ 
          transform: `scale(${1 + (simulatedVolume / 100) * 0.8})`, 
          opacity: simulatedVolume > 5 ? 0.5 : 0 
        }"
      ></div>

      <CallAvatar 
        :photo-url="props.remotePhoto" 
        :display-name="props.remoteName"
        size="48px"
        :is-calling="false"
      />
    </div>

    <div class="mini-info">
      <h4 class="remote-name-mini truncate">{{ props.remoteName }}</h4>
      <div class="timer-wrapper">
        <CallTimer />
      </div>
    </div>

    <div class="mini-controls">
      <button 
        @click.stop="emit('toggleMute')" 
        class="mini-btn mute-btn"
        :class="{ 'active': props.isMuted }"
        title="Mute"
      >
        <component :is="props.isMuted ? MicOff : Mic" :size="16" />
      </button>

      <button 
        @click.stop="emit('maximize')" 
        class="mini-btn maximize-btn"
        title="Maximize"
      >
        <Maximize2 :size="16" />
      </button>

      <button 
        @click.stop="emit('endCall')" 
        class="mini-btn end-btn"
        title="End Call"
      >
        <PhoneOff :size="16" />
      </button>
    </div>
  </div>
</div>
</template>

<style scoped>
.floating-card {
  position: fixed; /* Wajib fixed agar bisa melayang */
  width: 280px;
  height: 80px;
  
  display: flex;
  align-items: center;
  padding: 10px 12px;
  gap: 10px;
  
  /* Glassmorphism Dark Theme */
  background: rgba(27, 182, 73, 0.5); 
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border-radius: 16px;
  border: 1px solid rgba(82, 245, 145, 0.15);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
  
  z-index: 10000;
  cursor: grab; /* Cursor tangan terbuka */
  user-select: none; /* Mencegah blok teks saat drag */
  transition: box-shadow 0.2s, transform 0.1s;
}

.dark-mode .floating-card {
  background: rgba(5, 85, 38, 0.75); 
}

/* State saat sedang didrag */
.floating-card.is-dragging {
  cursor: grabbing; /* Cursor tangan menggenggam */
  box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); /* Shadow lebih dalam */
  transform: scale(1.02); /* Efek sedikit membesar */
  border-color: rgba(255, 255, 255, 0.3);
}

/* Indikator Grip */
.drag-handle {
  display: flex;
  align-items: center;
  justify-content: center;
  color: rgba(255, 255, 255, 0.4);
  margin-right: -4px;
}

/* Avatar Section */
.mini-visual-wrapper {
  position: relative;
  width: 48px;
  height: 48px;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-shrink: 0;
}

.mini-pulse {
  position: absolute;
  width: 100%;
  height: 100%;
  border-radius: 50%;
  background-color: rgba(76, 217, 100, 0.6);
  z-index: -1;
  transition: transform 0.1s ease-out;
}

/* Info Section */
.mini-info {
  flex: 1;
  display: flex;
  flex-direction: column;
  justify-content: center;
  overflow: hidden;
}

.remote-name-mini {
  margin: 0;
  color: white;
  font-size: 0.9rem;
  font-weight: 600;
}

.timer-wrapper {
  font-size: 0.8rem;
  opacity: 0.8;
  color: #ccc;
}

.truncate {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Controls */
.mini-controls {
  display: flex;
  gap: 6px;
}

.mini-btn {
  width: 30px;
  height: 30px;
  border-radius: 50%;
  border: none;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  transition: all 0.2s;
  color: white;
  flex-shrink: 0;
}

.mute-btn { background: rgba(255, 255, 255, 0.15); }
.mute-btn:hover { background: rgba(255, 255, 255, 0.25); }
.mute-btn.active { background: white; color: #333; }

.maximize-btn { background: rgba(50, 150, 255, 0.3); }
.maximize-btn:hover { background: rgba(50, 150, 255, 0.5); }

.end-btn { background: rgba(255, 59, 48, 0.8); }
.end-btn:hover { background: rgba(255, 59, 48, 1); }

/* Mobile Optimization: Disable drag di mobile agar tidak konflik dengan scroll, 
   atau bisa tetap diaktifkan tapi boundary-nya ketat */
@media (max-width: 480px) {
  .floating-card {
    width: calc(100vw - 32px);
    /* Di mobile sebaiknya fix position (bottom/top) saja agar UX lebih simpel */
    /* Namun jika ingin tetap drag, code JS di atas sudah handle screen width */
  }
}
</style>