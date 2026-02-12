<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, onActivated } from 'vue';
import { useAuthStore } from "@/stores/auth"; 
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { Phone, MessagesSquare, ListTodo, ArrowRight, Circle, CheckCircle2 } from 'lucide-vue-next';
import ApiService from "@/core/services/ApiService";

// --- STATE & CONFIG ---
const currentThemeMode = computed(() => themeMode.value);
const authStore = useAuthStore();
const currentUser = computed(() => authStore.user);

// Waktu Realtime
const currentTime = ref(new Date());
let timer: number | undefined;

// Data Dashboard
const unreadMessages = ref(0);
const totalContacts = ref(0);
const totalGroups = ref(0);
const isLoadingStats = ref(false);

// Data ToDo List
const todoList = ref<any[]>([]);
const isLoadingTodo = ref(false);

// --- LIFECYCLE ---
onMounted(() => {
  timer = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
  
  loadDashboardData();
});

onActivated(() => {
    loadDashboardData();
});

onUnmounted(() => {
  if (timer !== undefined) clearInterval(timer);
});

// --- METHODS ---
const loadDashboardData = () => {
    loadStats();
    loadTodos();
};

const loadStats = async () => {
  isLoadingStats.value = true;
  try {
    const response = await ApiService.get("dashboard/stats");
    if (response.data?.data?.summary) {
        const s = response.data.data.summary;
        unreadMessages.value = s.unread_messages || 0;
        totalContacts.value = s.total_contacts || 0;
        totalGroups.value = s.total_groups || 0;
    }
  } catch (error) {
    console.error("Err stats:", error);
  } finally {
    isLoadingStats.value = false;
  }
};

const loadTodos = async () => {
    isLoadingTodo.value = true;
    try {
        const response = await ApiService.get("chat/todos"); 
        let allTodos: any[] = [];

        // Deteksi Struktur Data (Array Langsung / Pagination / Wrapper)
        if (Array.isArray(response.data)) {
            allTodos = response.data;
        } else if (response.data && Array.isArray(response.data.data)) {
            allTodos = response.data.data;
        } else if (response.data && response.data.data && Array.isArray(response.data.data.data)) {
             allTodos = response.data.data.data;
        }

        // FILTER PENDING
        // Kita ambil semua yang belum selesai (tanpa limit .slice, karena sudah ada scroll)
        todoList.value = allTodos.filter((t: any) => {
            const statusVal = t.is_completed ?? t.completed ?? t.status;
            // Anggap belum selesai jika: false, 0, "0", atau null
            return statusVal === false || statusVal === 0 || statusVal === "0" || statusVal === null;
        });

    } catch (error) {
        console.error("Error todos:", error);
    } finally {
        isLoadingTodo.value = false;
    }
};

const formatCount = (count: number) => count > 99 ? '99+' : count.toString();

const formattedTime = computed(() => {
  return currentTime.value.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false });
});

const formattedDate = computed(() => {
  return currentTime.value.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
});
</script>

<template>
  <div class="dashboard-container" :class="{ 'dark-mode': currentThemeMode === 'dark' }">
    <div class="dashboard-grid" >
      
      <div class="left-column">
        
        <div class="welcome-card card-base">
          <div class="welcome-content">
            <div class="icon-wrapper">
              <MessagesSquare />
            </div>
            <h2 class="welcome-title">HELLO {{ currentUser?.name?.toUpperCase() || 'USER' }}!</h2>
            <h4 class="welcome-subtitle">Selamat datang di pusat obrolan Anda!</h4>
          </div>
          <div class="button-wrapper">
            <router-link :to="{ name: 'dashboard.private-chat' }" class="btn-chat-private">
              LIHAT CHAT PRIBADI
            </router-link>
            <router-link :to="{ name: 'dashboard.group-chat' }" class="btn-chat-group">
              LIHAT CHAT GRUP
            </router-link>
          </div>
        </div>

        <div class="todo-card card-base">
            <div class="todo-header">
                <div class="d-flex align-items-center gap-2">
                    <ListTodo class="text-primary icon-todo" />
                    <h3 class="todo-title">To Do List Kamu</h3>
                </div>
                <router-link to="/dashboard/todo-list" class="btn-todo-link" title="Lihat Semua">
                    <ArrowRight class="w-5 h-5" />
                </router-link>
            </div>

            <div class="todo-body">
                <div v-if="isLoadingTodo" class="loading-state">
                    <span class="loading-spinner-sm"></span> Memuat...
                </div>
                
                <div v-else-if="todoList.length > 0" class="todo-list-wrapper">
                    <div v-for="todo in todoList" :key="todo.id" class="todo-item">
                        <div class="todo-icon">
                            <Circle class="w-5 h-5 text-gray-400" />
                        </div>
                        <div class="todo-text">
                            <span class="todo-name">{{ todo.title }}</span>
                            <span v-if="todo.description" class="todo-desc">{{ todo.description }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state">
                    <CheckCircle2 class="w-10 h-10 text-success mb-2" />
                    <p class="mb-0">Semua tugas selesai! <br><small class="text-muted">Tidak ada pending task.</small></p>
                </div>
            </div>
        </div>

      </div>

      <div class="right-column">
        
        <div class="info-card phone-card card-base">
          <p class="phone-text">
            <Phone class="phone-icon"/> : {{ currentUser?.phone || '-' }}
          </p>
        </div>

        <div class="info-card time-card card-base">
          <h1 class="time-display">{{ formattedTime }}</h1>
          <p class="date-display">{{ formattedDate }}</p>
        </div>

        <div class="info-card stats-card card-base">
          <div class="stat-item">
            <div class="stat-circle stat-circle-green">
              <span>{{ isLoadingStats ? '-' : formatCount(unreadMessages) }}</span>
            </div>
            <p class="stat-label">Pesan Belum Dibaca</p>
          </div>
          
          <div class="stat-item">
            <div class="stat-circle stat-circle-blue">
              <span>{{ isLoadingStats ? '-' : formatCount(totalContacts) }}</span>
            </div>
            <p class="stat-label">Kontak Personal</p>
          </div>
          
          <div class="stat-item">
            <div class="stat-circle stat-circle-orange">
              <span>{{ isLoadingStats ? '-' : formatCount(totalGroups) }}</span>
            </div>
            <p class="stat-label">Total Grup</p>
          </div>
        </div>
        
      </div>
      
    </div>
  </div>
</template>

<style scoped>
/* --- LAYOUT UTAMA --- */
.dashboard-container {
  min-height: 100vh;
  padding: 2rem;
  background-color: #f5f5f5;
  transition: background-color 0.3s;
}
.dashboard-container.dark-mode { background-color: #1e1e2d; }

.dashboard-grid {
  display: grid;
  /* Grid 2 Kolom: Kiri (Main) 1.2 bagian, Kanan (Side) 0.8 bagian */
  /* Ini menjaga agar card kanan tidak terlalu lebar (sesuai request dimensi lama) */
  grid-template-columns: 1.2fr 0.8fr; 
  gap: 1.5rem;
  max-width: 1400px;
  margin: 0 auto;
}

.left-column, .right-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* --- CARD BASE STYLE --- */
.card-base {
  background: white;
  border-radius: 1rem;
  box-shadow: 0 4px 12px rgba(0,0,0,0.05);
  transition: transform 0.2s, box-shadow 0.2s;
  overflow: hidden;
}
.dark-mode .card-base { background: #2b2b40; box-shadow: 0 4px 12px rgba(0,0,0,0.2); }
.card-base:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.1); }

/* --- WELCOME CARD --- */
.welcome-card {
  padding: 2.5rem;
  flex: 1; /* Mengisi ruang yang tersedia jika card lain pendek */
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  min-height: 320px;
}
.icon-wrapper {
  padding: 1.2rem;
  background: #eef4ff;
  border-radius: 50%;
  margin-bottom: 1.5rem;
}
.dark-mode .icon-wrapper { background: #3a3a52; }
.icon-wrapper svg { width: 48px; height: 48px; color: #0959ee; }

.welcome-title { font-size: 1.8rem; font-weight: 800; color: #0959ee; margin-bottom: 0.5rem; }
.welcome-subtitle { font-size: 1rem; color: #7e8299; margin-bottom: 2rem; }
.dark-mode .welcome-title { color: #10a4fa; }
.dark-mode .welcome-subtitle { color: #a1a5b7; }

.button-wrapper { display: flex; gap: 1rem; width: 100%; max-width: 500px; }
.btn-chat-private, .btn-chat-group {
  flex: 1; padding: 0.8rem; border-radius: 0.5rem; font-weight: 700; color: white;
  text-decoration: none; display: flex; align-items: center; justify-content: center;
  transition: background 0.2s;
}
.btn-chat-private { background: #fa930c; } .btn-chat-private:hover { background: #d67a00; }
.btn-chat-group { background: #1ca509; } .btn-chat-group:hover { background: #147a06; }

/* --- TODO CARD (Baru) --- */
.todo-card {
    padding: 1.5rem;
    /* 1. KITA KUNCI TINGGI CARD-NYA */
    height: 400px; /* Card tidak akan pernah lebih tinggi dari ini */
    display: flex;
    flex-direction: column;
    /* Opsional: Agar background tetap rapi */
    overflow: hidden; 
}

.todo-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 1rem;
    border-bottom: 1px solid #f0f0f0;
    margin-bottom: 1rem;
    /* Header tidak boleh mengecil/tergencet */
    flex-shrink: 0; 
}
.todo-body {
    /* Mengisi sisa ruang kosong di bawah header */
    flex: 1; 
    display: flex;
    flex-direction: column;
    /* PENTING: min-height: 0 diperlukan agar flex-child bisa scroll di Firefox/Chrome */
    min-height: 0; 
}
.dark-mode .todo-header { border-bottom-color: #3f3f55; }
.todo-title { font-size: 1.1rem; font-weight: 700; margin: 0; color: #3f4254; }
.dark-mode .todo-title { color: #fff; }
.icon-todo { width: 24px; height: 24px; color: #0959ee; }

.btn-todo-link {
    width: 32px; height: 32px; background: #f1faff; color: #009ef7;
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.btn-todo-link:hover { background: #009ef7; color: white; }

.todo-list-wrapper {
    /* 2. AREA INI YANG AKAN SCROLL */
    flex: 1; /* Mengambil semua ruang yang tersedia di .todo-body */
    overflow-y: auto; /* Munculkan scrollbar jika konten panjang */
    padding-right: 8px; /* Jarak agar teks tidak tertutup scrollbar */
    
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
}

/* --- KUSTOMISASI SCROLLBAR (Agar terlihat cantik di dalam card) --- */
.todo-list-wrapper::-webkit-scrollbar {
    width: 6px;
}
.todo-list-wrapper::-webkit-scrollbar-track {
    background: transparent;
}
.todo-list-wrapper::-webkit-scrollbar-thumb {
    background-color: #e4e6ef; /* Warna scrollbar soft */
    border-radius: 10px;
}
.todo-list-wrapper::-webkit-scrollbar-thumb:hover {
    background-color: #b5b5c3;
}
.dark-mode .todo-list-wrapper::-webkit-scrollbar-thumb {
    background-color: #474761;
}

/* Sisa style item tetap sama */
.todo-item {
    display: flex; align-items: flex-start; gap: 0.8rem;
    padding: 0.5rem; border-radius: 6px;
    flex-shrink: 0; /* Mencegah item gepeng */
    transition: background 0.2s;
}
.todo-item:hover { background: #f9f9f9; }
.dark-mode .todo-item:hover { background: #323248; }

.todo-name { font-weight: 600; color: #3f4254; font-size: 0.95rem; line-height: 1.4; }
.todo-desc { font-size: 0.8rem; color: #b5b5c3; display: block; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
.dark-mode .todo-name { color: #e1e1e1; }

.icon-todo { width: 24px; height: 24px; color: #0959ee; }
.todo-title { font-size: 1.1rem; font-weight: 700; margin: 0; color: #3f4254; }
.dark-mode .todo-title { color: #fff; }

.btn-todo-link {
    width: 32px; height: 32px; background: #f1faff; color: #009ef7;
    border-radius: 8px; display: flex; align-items: center; justify-content: center;
    transition: all 0.2s;
}
.btn-todo-link:hover { background: #009ef7; color: white; }

.empty-state, .loading-state { 
    text-align: center; 
    padding: 2rem; 
    color: #a1a5b7;
    /* Agar pesan kosong ada di tengah vertikal */
    margin: auto; 
}
/* --- INFO CARDS (Kanan) --- */
.info-card { padding: 1.5rem; text-align: center; }

/* Phone */
.phone-card { display: flex; align-items: center; justify-content: center; min-height: 80px; }
.phone-text { font-size: 1.1rem; font-weight: 600; color: #3f4254; display: flex; align-items: center; gap: 0.5rem; margin: 0; }
.phone-icon { color: #0959ee; }
.dark-mode .phone-text { color: #fff; }

/* Time */
.time-card { min-height: 120px; }
.time-display { font-size: 2.5rem; font-weight: 800; color: #0959ee; margin: 0; line-height: 1; }
.date-display { font-size: 1rem; color: #7e8299; margin-top: 0.5rem; }
.dark-mode .time-display { color: #10a4fa; }

/* Stats */
.stats-card {
    display: flex; justify-content: space-around; padding: 2rem 8.5rem;
    flex-wrap: wrap; gap: 1rem;
}
.stat-item { flex: 1; min-width: 80px; }
.stat-circle {
    width: 70px; height: 70px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.4rem; font-weight: 700; margin: 0 auto 0.5rem;
}
.stat-circle-green { background: #e8fff3; color: #50cd89; border: 3px solid #50cd89; }
.stat-circle-blue { background: #f1faff; color: #009ef7; border: 3px solid #009ef7; }
.stat-circle-orange { background: #fff8dd; color: #ffc700; border: 3px solid #ffc700; }
.dark-mode .stat-circle-green { background: rgba(80, 205, 137, 0.1); }
.dark-mode .stat-circle-blue { background: rgba(0, 158, 247, 0.1); }
.dark-mode .stat-circle-orange { background: rgba(255, 199, 0, 0.1); }

.stat-label { font-size: 0.8rem; font-weight: 600; color: #7e8299; margin: 0; }

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .dashboard-grid { grid-template-columns: 1fr; }
  .welcome-card { order: 1; }
  .right-column { order: 2; display: grid; grid-template-columns: 1fr 1fr; } /* Tablet: Info card jadi 2 kolom */
  .stats-card { grid-column: span 2; }
  .todo-card { order: 3; }
}

@media (max-width: 576px) {
  .right-column { display: flex; flex-direction: column; }
  .button-wrapper { flex-direction: column; }
}

@media (max-width: 390px) {
  .right-column { display: flex;}
  .button-wrapper { flex-direction: column; }
}

.loading-spinner-sm {
    display: inline-block; width: 16px; height: 16px; border: 2px solid #ccc;
    border-top-color: #0959ee; border-radius: 50%; animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
</style>