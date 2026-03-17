<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { X, Search, Check, Loader2 } from 'lucide-vue-next';
import axios from '@/libs/axios';
import { useVoiceGroupCall } from '@/composables/useVoiceGroupCall';
import { useVideoGroupCall } from '@/composables/useVideoGroupCall';
import { useCallStore } from '@/stores/callStore';
import { useAuthStore } from '@/stores/authStore';

const emit = defineEmits(['close']);

const callStore = useCallStore();
const authStore = useAuthStore();
const { upgradeToGroupCall } = useVoiceGroupCall();

// State
const searchQuery = ref('');
const contacts = ref<any[]>([]);
const selectedIds = ref<number[]>([]);
const isLoadingContacts = ref(false);
const isSubmitting = ref(false);

// Ambil data kontak dari backend (Sesuaikan endpoint dengan API kamu)
const fetchContacts = async () => {
  isLoadingContacts.value = true;
  try {
    // Kita menampung balasan langsung ke dalam variabel 'data'
    const { data } = await axios.get('/master/users');
    
    // Opsional: log ke console menggunakan variabel 'data' (bukan response)
    // console.log('Cek isi data:', data); 

    // Ambil array user-nya (jika dibungkus "data", ambil data.data, jika tidak, langsung ambil data)
    const usersArray = data.data ? data.data : data;

    // Filter agar user yang sedang menelepon saat ini dan diri sendiri tidak muncul di daftar
    const currentOpponentId = callStore.currentCall?.caller.id === authStore.user?.id 
      ? callStore.currentCall?.receiver.id 
      : callStore.currentCall?.caller.id;

    // Masukkan data ke dalam state yang merender list kontak
    contacts.value = usersArray.filter((u: any) => 
      u.id !== authStore.user?.id && u.id !== currentOpponentId
    );
  } catch (error) {
    console.error('Gagal memuat kontak:', error);
  } finally {
    isLoadingContacts.value = false;
  }
};

// Filter kontak berdasarkan pencarian
const filteredContacts = computed(() => {
  if (!searchQuery.value) return contacts.value;
  return contacts.value.filter(c => 
    c.name.toLowerCase().includes(searchQuery.value.toLowerCase())
  );
});

// Toggle pilihan user
const toggleSelection = (id: number) => {
  const index = selectedIds.value.indexOf(id);
  if (index > -1) {
    selectedIds.value.splice(index, 1);
  } else {
    selectedIds.value.push(id);
  }
};

// Eksekusi penambahan peserta
const handleAddParticipants = async () => {
  if (selectedIds.value.length === 0) return;
  
  isSubmitting.value = true;
  await upgradeToGroupCall(selectedIds.value);
  isSubmitting.value = false;
  
  // Tutup modal setelah berhasil
  emit('close');
};

onMounted(() => {
  fetchContacts();
});
</script>

<template>
  <div class="modal-overlay">
    <div class="modal-content glass-panel">
      <div class="modal-header">
        <h3 class="modal-title">Tambah Peserta</h3>
        <button @click="emit('close')" class="close-btn">
          <X :size="20" />
        </button>
      </div>

      <div class="search-container">
        <Search class="search-icon" :size="18" />
        <input 
          v-model="searchQuery" 
          type="text" 
          placeholder="Cari nama kontak..." 
          class="search-input"
        />
      </div>

      <div class="contacts-list custom-scrollbar">
        <div v-if="isLoadingContacts" class="loading-state">
          <Loader2 class="animate-spin" :size="24" />
          <span>Memuat kontak...</span>
        </div>
        
        <div v-else-if="filteredContacts.length === 0" class="empty-state">
          <span>Tidak ada kontak ditemukan.</span>
        </div>

        <div 
          v-else
          v-for="contact in filteredContacts" 
          :key="contact.id"
          class="contact-item"
          @click="toggleSelection(contact.id)"
        >
          <img 
            :src="contact.photo || contact.profile_photo_url || '/default-avatar.png'" 
            class="contact-avatar" 
            alt="avatar"
          />
          <span class="contact-name">{{ contact.name }}</span>
          
          <div class="checkbox" :class="{ 'is-checked': selectedIds.includes(contact.id) }">
            <Check v-if="selectedIds.includes(contact.id)" :size="14" class="check-icon" />
          </div>
        </div>
      </div>

      <div class="modal-footer">
        <button 
          @click="handleAddParticipants" 
          class="submit-btn"
          :disabled="selectedIds.length === 0 || isSubmitting"
        >
          <Loader2 v-if="isSubmitting" class="animate-spin" :size="18" />
          <span v-else>Tambah ({{ selectedIds.length }})</span>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}

.modal-content {
  width: 90%;
  max-width: 400px;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.dark-mode .modal-content {
  background: #1e1e2d;
  color: white;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px 20px;
  border-bottom: 1px solid #eee;
}

.dark-mode .modal-header { border-color: #333; }

.modal-title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 600;
}

.close-btn {
  background: transparent;
  border: none;
  cursor: pointer;
  color: #888;
}

.search-container {
  padding: 12px 20px;
  position: relative;
}

.search-input {
  width: 100%;
  padding: 10px 10px 10px 36px;
  border: 1px solid #ddd;
  border-radius: 8px;
  background: #f9f9f9;
  outline: none;
}

.dark-mode .search-input {
  background: #2b2b40;
  border-color: #444;
  color: white;
}

.search-icon {
  position: absolute;
  left: 30px;
  top: 50%;
  transform: translateY(-50%);
  color: #888;
}

.contacts-list {
  max-height: 300px;
  overflow-y: auto;
  padding: 0 10px;
}

.contact-item {
  display: flex;
  align-items: center;
  padding: 10px;
  cursor: pointer;
  border-radius: 8px;
  transition: background 0.2s;
}

.contact-item:hover { background: #f0f0f0; }
.dark-mode .contact-item:hover { background: #2b2b40; }

.contact-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  object-fit: cover;
  margin-right: 12px;
}

.contact-name {
  flex: 1;
  font-weight: 500;
}

.checkbox {
  width: 22px;
  height: 22px;
  border: 2px solid #ccc;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.checkbox.is-checked {
  background: #0959ee;
  border-color: #0959ee;
}

.check-icon { color: white; }

.modal-footer {
  padding: 16px 20px;
  border-top: 1px solid #eee;
}
.dark-mode .modal-footer { border-color: #333; }

.submit-btn {
  width: 100%;
  padding: 12px;
  background: #0959ee;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  justify-content: center;
  align-items: center;
}

.submit-btn:disabled {
  background: #a0c0f9;
  cursor: not-allowed;
}

.loading-state, .empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 30px 0;
  color: #888;
  gap: 10px;
}
</style>