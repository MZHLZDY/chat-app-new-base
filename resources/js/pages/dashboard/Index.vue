<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, onActivated } from 'vue';
import { useAuthStore } from "@/stores/auth"; 
import { themeMode } from "@/layouts/default-layout/config/helper"; 
import { Phone, MessagesSquare, ListTodo, ArrowRight, Circle, CheckCircle2, Users, MessageCircle, UsersRound } from 'lucide-vue-next';
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

// Animation triggers
const isLoaded = ref(false);

// --- LIFECYCLE ---
onMounted(() => {
  timer = setInterval(() => {
    currentTime.value = new Date();
  }, 1000);
  
  loadDashboardData();
  
  // Trigger animations after mount
  setTimeout(() => {
    isLoaded.value = true;
  }, 100);
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
        todoList.value = allTodos.filter((t: any) => {
            const statusVal = t.is_completed ?? t.completed ?? t.status;
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

const greeting = computed(() => {
  const hour = currentTime.value.getHours();
  if (hour < 12) return 'Selamat Pagi';
  if (hour < 15) return 'Selamat Siang';
  if (hour < 18) return 'Selamat Sore';
  return 'Selamat Malam';
});
</script>

<template>
  <div class="dashboard-container" :class="{ 'dark-mode': currentThemeMode === 'dark', 'loaded': isLoaded }">
    <div class="dashboard-grid">
      
      <!-- Welcome Card with Gradient Background -->
      <div class="welcome-card card-base animate-slide-up" style="animation-delay: 0.1s">
        <div class="welcome-gradient"></div>
        <div class="welcome-content">
          <div class="icon-wrapper animate-float">
            <div class="icon-circle"></div>
            <div class="icon-circle delay-1"></div>
            <div class="icon-circle delay-2"></div>
            <MessagesSquare class="icon-main" />
          </div>
          <div class="greeting-text">{{ greeting }},</div>
          <h2 class="welcome-title">{{ currentUser?.name?.toUpperCase() || 'USER' }}</h2>
          <h4 class="welcome-subtitle">Kelola semua percakapan Anda dalam satu tempat</h4>
        </div>
        <div class="button-wrapper">
          <router-link :to="{ name: 'dashboard.private-chat' }" class="btn-chat btn-chat-private">
            <MessageCircle class="btn-icon" />
            <span>Chat Pribadi</span>
          </router-link>
          <router-link :to="{ name: 'dashboard.group-chat' }" class="btn-chat btn-chat-group">
            <UsersRound class="btn-icon" />
            <span>Chat Grup</span>
          </router-link>
        </div>
      </div>

      <!-- Todo Card with Modern Design -->
      <div class="todo-card card-base animate-slide-left" style="animation-delay: 0.15s">
            <div class="todo-header">
                <div class="todo-header-left">
                    <div class="todo-icon-wrapper">
                        <ListTodo class="icon-todo" />
                    </div>
                    <div>
                        <h3 class="todo-title">Daftar Tugas</h3>
                        <p class="todo-subtitle">{{ todoList.length }} tugas pending</p>
                    </div>
                </div>
                <router-link to="/dashboard/todo-list" class="btn-todo-link" title="Lihat Semua">
                    <ArrowRight class="w-5 h-5" />
                </router-link>
            </div>

            <div class="todo-body">
                <div v-if="isLoadingTodo" class="loading-state">
                    <div class="loading-spinner"></div>
                    <p>Memuat tugas...</p>
                </div>
                
                <div v-else-if="todoList.length > 0" class="todo-list-wrapper">
                    <div 
                        v-for="(todo, index) in todoList" 
                        :key="todo.id" 
                        class="todo-item animate-fade-in"
                        :style="{ animationDelay: `${index * 0.05}s` }"
                    >
                        <div class="todo-checkbox">
                            <Circle class="circle-icon" />
                        </div>
                        <div class="todo-text">
                            <span class="todo-name">{{ todo.title }}</span>
                            <span v-if="todo.description" class="todo-desc">{{ todo.description }}</span>
                        </div>
                    </div>
                </div>

                <div v-else class="empty-state">
                    <div class="empty-icon-wrapper">
                        <CheckCircle2 class="empty-icon" />
                    </div>
                    <h4 class="empty-title">Semua Beres! 🎉</h4>
                    <p class="empty-text">Tidak ada tugas yang pending</p>
                </div>
            </div>
        </div>

      <!-- Phone Card -->
      <div class="info-card phone-card card-base animate-slide-up" style="animation-delay: 0.2s">
        <div class="phone-icon-wrapper">
          <Phone class="phone-icon"/>
        </div>
        <div class="phone-content">
          <p class="phone-label">Nomor Telepon</p>
          <p class="phone-number">{{ currentUser?.phone || '-' }}</p>
        </div>
      </div>

      <!-- Time Card with Animated Background -->
      <div class="info-card time-card card-base animate-slide-up" style="animation-delay: 0.25s">
        <div class="time-bg-decoration"></div>
        <div class="time-content">
          <h1 class="time-display">{{ formattedTime }}</h1>
          <p class="date-display">{{ formattedDate }}</p>
        </div>
      </div>

      <!-- Stats Card with Animated Counters -->
      <div class="info-card stats-card card-base animate-slide-up" style="animation-delay: 0.3s">
        <div class="stats-header">
          <h3 class="stats-title">Statistik Dashboard</h3>
        </div>
        
        <div class="stats-grid">
          <div class="stat-item animate-scale">
            <div class="stat-icon-wrapper stat-green">
              <MessageCircle class="stat-icon" />
            </div>
            <div class="stat-content">
              <div class="stat-number">{{ isLoadingStats ? '-' : formatCount(unreadMessages) }}</div>
              <p class="stat-label">Pesan Belum Dibaca</p>
            </div>
          </div>
          
          <div class="stat-item animate-scale" style="animation-delay: 0.1s">
            <div class="stat-icon-wrapper stat-blue">
              <Users class="stat-icon" />
            </div>
            <div class="stat-content">
              <div class="stat-number">{{ isLoadingStats ? '-' : formatCount(totalContacts) }}</div>
              <p class="stat-label">Kontak Personal</p>
            </div>
          </div>
          
          <div class="stat-item animate-scale" style="animation-delay: 0.2s">
            <div class="stat-icon-wrapper stat-orange">
              <UsersRound class="stat-icon" />
            </div>
            <div class="stat-content">
              <div class="stat-number">{{ isLoadingStats ? '-' : formatCount(totalGroups) }}</div>
              <p class="stat-label">Total Grup</p>
            </div>
          </div>
        </div>
      </div>
      
    </div>
  </div>
</template>

<style scoped>
/* --- ANIMATIONS --- */
@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideLeft {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes fadeIn {
  from {
    opacity: 0;
  }
  to {
    opacity: 1;
  }
}

@keyframes scaleIn {
  from {
    opacity: 0;
    transform: scale(0.9);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}

/* Animation Classes */
.animate-slide-up {
  animation: slideUp 0.6s ease-out forwards;
  opacity: 0;
}

.animate-slide-left {
  animation: slideLeft 0.6s ease-out forwards;
  opacity: 0;
}

.animate-fade-in {
  animation: fadeIn 0.4s ease-out forwards;
  opacity: 0;
}

.animate-scale {
  animation: scaleIn 0.5s ease-out forwards;
  opacity: 0;
}

.animate-float {
  animation: float 3s ease-in-out infinite;
}

/* --- LAYOUT UTAMA --- */
.dashboard-container {
  min-height: 100vh;
  padding: 1.5rem;
  background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
  transition: all 0.3s ease;
}

.dashboard-container.dark-mode {
  background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 1.25rem;
  max-width: 1400px;
  margin: 0 auto;
}

.left-column,
.right-column {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
}

/* --- CARD BASE STYLE --- */
.card-base {
  background: white;
  border-radius: 1.25rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.dark-mode .card-base {
  background: #2b2b40;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.card-base:hover {
  transform: translateY(-4px);
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
}

.dark-mode .card-base:hover {
  box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
}

/* --- WELCOME CARD --- */
.welcome-card {
  padding: 2rem 2rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  min-height: 380px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.dark-mode .welcome-card {
  background: linear-gradient(135deg, #4a5c8f 0%, #5d4a7a 100%);
}

.welcome-gradient {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
  pointer-events: none;
}

.icon-wrapper {
  position: relative;
  width: 90px;
  height: 90px;
  margin-bottom: 1.25rem;
  display: flex;
  align-items: center;
  justify-content: center;
}

.icon-circle {
  position: absolute;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.1);
  animation: ripple 3s ease-out infinite;
}

.icon-circle:nth-child(1) {
  width: 90px;
  height: 90px;
}

.icon-circle:nth-child(2) {
  width: 75px;
  height: 75px;
  animation-delay: 0.5s;
}

.icon-circle:nth-child(3) {
  width: 60px;
  height: 60px;
  animation-delay: 1s;
}

@keyframes ripple {
  0% {
    transform: scale(0.8);
    opacity: 1;
  }
  50% {
    transform: scale(1);
    opacity: 0.5;
  }
  100% {
    transform: scale(1.2);
    opacity: 0;
  }
}

.icon-main {
  width: 42px;
  height: 42px;
  color: white;
  position: relative;
  z-index: 2;
  filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
}

.greeting-text {
  font-size: 0.95rem;
  font-weight: 500;
  opacity: 0.95;
  margin-bottom: 0.35rem;
  letter-spacing: 0.5px;
}

.welcome-title {
  font-size: 1.6rem;
  font-weight: 800;
  color: white;
  margin-bottom: 0.35rem;
  letter-spacing: 1px;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
}

.welcome-subtitle {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.9);
  margin-bottom: 1.5rem;
  font-weight: 400;
}

.button-wrapper {
  display: flex;
  gap: 0.85rem;
  width: 100%;
  max-width: 450px;
  margin-top: 0.5rem;
}

.btn-chat {
  flex: 1;
  padding: 0.85rem 1.25rem;
  border-radius: 0.65rem;
  font-weight: 600;
  font-size: 0.875rem;
  color: white;
  text-decoration: none;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.btn-chat::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
  transition: left 0.5s;
}

.btn-chat:hover::before {
  left: 100%;
}

.btn-chat-private {
  background: linear-gradient(135deg, #fa930c 0%, #ff6b35 100%);
  box-shadow: 0 4px 15px rgba(250, 147, 12, 0.3);
}

.btn-chat-private:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(250, 147, 12, 0.4);
}

.btn-chat-group {
  background: linear-gradient(135deg, #1ca509 0%, #0f7a02 100%);
  box-shadow: 0 4px 15px rgba(28, 165, 9, 0.3);
}

.btn-chat-group:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(28, 165, 9, 0.4);
}

.btn-icon {
  width: 20px;
  height: 20px;
}

/* --- TODO CARD --- */
.todo-card {
  padding: 0;
  min-height: 380px;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* 2. HEADER TETAP (TIDAK BOLEH MENGECIL) */
.todo-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.25rem 1.5rem;
  border-bottom: 1px solid #f0f0f0;
  flex-shrink: 0;
  background: linear-gradient(to bottom, rgba(9, 89, 238, 0.03) 0%, transparent 100%);
}

.dark-mode .todo-header {
  border-bottom-color: #3f3f55;
  background: linear-gradient(to bottom, rgba(16, 164, 250, 0.05) 0%, transparent 100%);
}

.todo-header-left {
  display: flex;
  align-items: center;
  gap: 0.85rem;
}

.todo-icon-wrapper {
  width: 42px;
  height: 42px;
  background: linear-gradient(135deg, #f1faff 0%, #e3f5ff 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s;
}

.dark-mode .todo-icon-wrapper {
  background: linear-gradient(135deg, rgba(16, 164, 250, 0.1) 0%, rgba(16, 164, 250, 0.05) 100%);
}

.icon-todo {
  width: 22px;
  height: 22px;
  color: #0959ee;
}

.dark-mode .icon-todo {
  color: #10a4fa;
}

.todo-title {
  font-size: 1.05rem;
  font-weight: 700;
  margin: 0;
  color: #3f4254;
}

.dark-mode .todo-title {
  color: #fff;
}

.todo-subtitle {
  font-size: 0.8rem;
  color: #7e8299;
  margin: 0.2rem 0 0 0;
}

.dark-mode .todo-subtitle {
  color: #a1a5b7;
}

.btn-todo-link {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, #f1faff 0%, #e3f5ff 100%);
  color: #009ef7;
  border-radius: 9px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-todo-link:hover {
  background: #009ef7;
  color: white;
  transform: translateX(3px);
}

.dark-mode .btn-todo-link {
  background: rgba(16, 164, 250, 0.1);
}

.dark-mode .btn-todo-link:hover {
  background: #009ef7;
}

.todo-body {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  padding: 0.85rem 1.5rem 1.25rem;
}

.todo-list-wrapper {
  flex: 1;
  overflow-y: auto;
  padding-right: 8px;
  display: flex;
  flex-direction: column;
  gap: 0.6rem;
}

/* Custom Scrollbar */
.todo-list-wrapper::-webkit-scrollbar {
  width: 6px;
}

.todo-list-wrapper::-webkit-scrollbar-track {
  background: transparent;
}

.todo-list-wrapper::-webkit-scrollbar-thumb {
  background-color: #e4e6ef;
  border-radius: 10px;
}

.todo-list-wrapper::-webkit-scrollbar-thumb:hover {
  background-color: #b5b5c3;
}

.dark-mode .todo-list-wrapper::-webkit-scrollbar-thumb {
  background-color: #474761;
}

.todo-item {
  display: flex;
  align-items: flex-start;
  gap: 0.85rem;
  padding: 0.75rem 0.85rem;
  border-radius: 9px;
  flex-shrink: 0;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  background: #fafafa;
  border: 1px solid transparent;
}

.dark-mode .todo-item {
  background: #323248;
}

.todo-item:hover {
  background: white;
  border-color: #e8ecf1;
  transform: translateX(4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.dark-mode .todo-item:hover {
  background: #3a3a52;
  border-color: #474761;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.todo-checkbox {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-top: 2px;
}

.circle-icon {
  width: 20px;
  height: 20px;
  color: #b5b5c3;
  transition: all 0.3s;
}

.todo-item:hover .circle-icon {
  color: #0959ee;
  transform: scale(1.1);
}

.todo-text {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}

.todo-name {
  font-weight: 600;
  color: #3f4254;
  font-size: 0.875rem;
  line-height: 1.5;
}

.dark-mode .todo-name {
  color: #e1e1e1;
}

.todo-desc {
  font-size: 0.8rem;
  color: #7e8299;
  display: block;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.dark-mode .todo-desc {
  color: #a1a5b7;
}

/* Empty & Loading States */
.empty-state,
.loading-state {
  text-align: center;
  padding: 2rem 1.5rem;
  color: #a1a5b7;
  margin: auto;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.empty-icon-wrapper {
  width: 65px;
  height: 65px;
  background: linear-gradient(135deg, #e8fff3 0%, #d4f9e6 100%);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  margin-bottom: 1rem;
  animation: pulse 2s ease-in-out infinite;
}

.dark-mode .empty-icon-wrapper {
  background: linear-gradient(135deg, rgba(80, 205, 137, 0.1) 0%, rgba(80, 205, 137, 0.05) 100%);
}

.empty-icon {
  width: 32px;
  height: 32px;
  color: #50cd89;
}

.empty-title {
  font-size: 1rem;
  font-weight: 700;
  color: #3f4254;
  margin: 0 0 0.35rem 0;
}

.dark-mode .empty-title {
  color: #fff;
}

.empty-text {
  font-size: 0.85rem;
  color: #7e8299;
  margin: 0;
}

.loading-spinner {
  width: 35px;
  height: 35px;
  border: 3px solid #e4e6ef;
  border-top-color: #0959ee;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
  margin-bottom: 0.85rem;
}

.dark-mode .loading-spinner {
  border-color: #474761;
  border-top-color: #10a4fa;
}

.loading-state p {
  margin: 0;
  font-size: 0.875rem;
}

/* --- INFO CARDS (Right Column) --- */
.info-card {
  padding: 1.35rem 1.5rem;
  position: relative;
}

/* Phone Card */
.phone-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  min-height: 75px;
  background: linear-gradient(135deg, #f1faff 0%, #e8f5ff 100%);
}

.dark-mode .phone-card {
  background: linear-gradient(135deg, rgba(16, 164, 250, 0.1) 0%, rgba(16, 164, 250, 0.05) 100%);
}

.phone-icon-wrapper {
  width: 48px;
  height: 48px;
  background: white;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(9, 89, 238, 0.15);
  transition: all 0.3s;
}

.dark-mode .phone-icon-wrapper {
  background: #2b2b40;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.phone-card:hover .phone-icon-wrapper {
  transform: scale(1.05) rotate(5deg);
}

.phone-icon {
  width: 24px;
  height: 24px;
  color: #0959ee;
}

.dark-mode .phone-icon {
  color: #10a4fa;
}

.phone-content {
  flex: 1;
}

.phone-label {
  font-size: 0.8rem;
  color: #7e8299;
  margin: 0 0 0.2rem 0;
  font-weight: 500;
}

.phone-number {
  font-size: 1rem;
  font-weight: 700;
  color: #3f4254;
  margin: 0;
}

.dark-mode .phone-number {
  color: #fff;
}

/* Time Card */
.time-card {
  min-height: 115px;
  text-align: center;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  overflow: hidden;
}

.dark-mode .time-card {
  background: linear-gradient(135deg, #4a5c8f 0%, #5d4a7a 100%);
}

.time-bg-decoration {
  position: absolute;
  width: 150px;
  height: 150px;
  background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
  border-radius: 50%;
  top: -40px;
  right: -40px;
  animation: pulse 3s ease-in-out infinite;
}

.time-content {
  position: relative;
  z-index: 1;
}

.time-display {
  font-size: 2.25rem;
  font-weight: 800;
  color: white;
  margin: 0;
  line-height: 1;
  text-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  letter-spacing: 2px;
}

.date-display {
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.9);
  margin-top: 0.6rem;
  font-weight: 500;
}

/* Stats Card */
.stats-card {
  padding: 1.35rem 1.75rem;
  grid-column: span 2;
}

.stats-header {
  margin-bottom: 1.25rem;
  padding-bottom: 0.85rem;
  border-bottom: 1px solid #f0f0f0;
}

.dark-mode .stats-header {
  border-bottom-color: #3f3f55;
}

.stats-title {
  font-size: 1.05rem;
  font-weight: 700;
  color: #3f4254;
  margin: 0;
}

.dark-mode .stats-title {
  color: #fff;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1.25rem;
}

.stat-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.25rem;
  background: #fafafa;
  border-radius: 12px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
}

.dark-mode .stat-item {
  background: #323248;
}

.stat-item:hover {
  background: white;
  border-color: #e8ecf1;
  transform: translateY(-4px);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
}

.dark-mode .stat-item:hover {
  background: #3a3a52;
  border-color: #474761;
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
}

.stat-icon-wrapper {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.3s;
}

.stat-item:hover .stat-icon-wrapper {
  transform: scale(1.1) rotate(5deg);
}

.stat-green {
  background: linear-gradient(135deg, #e8fff3 0%, #d4f9e6 100%);
}

.stat-blue {
  background: linear-gradient(135deg, #f1faff 0%, #e3f5ff 100%);
}

.stat-orange {
  background: linear-gradient(135deg, #fff8dd 0%, #ffeeaa 100%);
}

.dark-mode .stat-green {
  background: linear-gradient(135deg, rgba(80, 205, 137, 0.15) 0%, rgba(80, 205, 137, 0.08) 100%);
}

.dark-mode .stat-blue {
  background: linear-gradient(135deg, rgba(0, 158, 247, 0.15) 0%, rgba(0, 158, 247, 0.08) 100%);
}

.dark-mode .stat-orange {
  background: linear-gradient(135deg, rgba(255, 199, 0, 0.15) 0%, rgba(255, 199, 0, 0.08) 100%);
}

.stat-icon {
  width: 28px;
  height: 28px;
}

.stat-green .stat-icon {
  color: #50cd89;
}

.stat-blue .stat-icon {
  color: #009ef7;
}

.stat-orange .stat-icon {
  color: #ffc700;
}

.stat-content {
  flex: 1;
}

.stat-number {
  font-size: 1.75rem;
  font-weight: 800;
  color: #3f4254;
  line-height: 1;
  margin-bottom: 0.4rem;
}

.dark-mode .stat-number {
  color: #fff;
}

.stat-label {
  font-size: 0.85rem;
  font-weight: 600;
  color: #7e8299;
  margin: 0;
}

.dark-mode .stat-label {
  color: #a1a5b7;
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
  .dashboard-grid {
    grid-template-columns: 1fr;
  }

  .stats-card {
    grid-column: span 1;
  }

  .stats-grid {
    grid-template-columns: 1fr;
    gap: 1rem;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 576px) {
  .dashboard-container {
    padding: 1rem;
  }

  .button-wrapper {
    flex-direction: column;
  }

  .welcome-card {
    padding: 2rem 1.5rem;
    min-height: 420px;
  }

  .welcome-title {
    font-size: 1.5rem;
  }

  .time-display {
    font-size: 2.5rem;
  }

  .stats-grid {
    gap: 0.75rem;
  }

  .stat-item {
    padding: 1.25rem;
  }
}

@media (max-width: 390px) {
  .button-wrapper {
    flex-direction: column;
  }

  .icon-wrapper {
    width: 100px;
    height: 100px;
  }

  .icon-circle:nth-child(1) {
    width: 100px;
    height: 100px;
  }

  .icon-circle:nth-child(2) {
    width: 80px;
    height: 80px;
  }

  .icon-circle:nth-child(3) {
    width: 60px;
    height: 60px;
  }

  .icon-main {
    width: 40px;
    height: 40px;
  }
}
</style>