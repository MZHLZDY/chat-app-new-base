<script setup lang="ts">
import { computed, watch, ref, onMounted, onUnmounted } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVideoGroupCall } from '@/composables/useVideoGroupCall';
import CallAvatar from '../shared/CallAvatar.vue';
import { X, Video } from 'lucide-vue-next';

// Setup Store & Composables
const store = useCallStore();
const { rejectGroupVideoCall } = useVideoGroupCall();

// Info basic grup yang sedang ditelpon
const groupCall = computed(() => store.backendGroupCall);
// Atau ambil dari logic activeGroup yang nge-set store (store.activeGroupName, dsb)
// Karena saat startGroupVideoCall store diset pake authStore.user jadi receiver kita anggap ini nama tim/grup
const groupAvatar = computed(() => store.activeGroupAvatar || '/media/svg/avatars/blank.svg');
const groupName = computed(() => store.activeGroupName || 'Group Call'); 

// Tampilkan jika status store kita berdering/sedang nyambung dan type=video isGroup=true
const isCallingGroup = computed(() => 
    store.currentCall?.type === 'video' && 
    store.isGroupCall && 
    store.callStatus === 'calling'
);

// Evaluasi status peserta (Apakah ada yang join? atau apakah semua nolak?)
const totalParticipants = computed(() => store.groupParticipants.length);
const joinedParticipants = computed(() => 
    store.groupParticipants.filter(p => p.status === 'joined').length
);
const rejectedParticipants = computed(() => 
    store.groupParticipants.filter(p => ['declined', 'left'].includes(p.status)).length
);

// Jika minimal ada 1 orang yang join, otomatis merubah call status menjadi 'ongoing'
watch(joinedParticipants, (count) => {
    if (count > 0 && store.callStatus === 'calling') {
        console.log('✅ Ada peserta yang join! Pindah ke VideoGroupCallModal (Ongoing) stream grid');
        store.updateCallStatus('ongoing');
    }
});

// Jika semua peserta menolak / left
watch(rejectedParticipants, (count) => {
    if (count > 0 && count === totalParticipants.value && store.callStatus === 'calling') {
        console.log('❌ Semua peserta menolak panggilan grup.');
        store.updateCallStatus('rejected'); 
        setTimeout(() => {
            store.clearCurrentCall();
        }, 2000);
    }
});

const handleCancel = async () => {
    if (groupCall.value) {
        // Anggap host ingin membubarkan / membatalkan karena blm ada yg angkat
        await rejectGroupVideoCall(groupCall.value.id);
        store.clearCurrentCall();
    }
};

</script>

<template>
    <Transition name="fade">
        <div v-if="isCallingGroup" class="incoming-call-overlay">
            <div class="incoming-card glass-effect">

                <!-- Avatar Grup / Tim -->
                <div class="mb-5 d-flex justify-content-center">
                    <CallAvatar
                        :photo-url="groupAvatar"
                        :display-name="groupName"
                        size="120px"
                        :is-calling="true"
                    />
                </div>

                <!-- Info grup -->
                <h3 class="caller-name">{{ groupName }}...</h3>

                <!-- Status panggilan -->
                <p class="call-status">
                    {{ 
                        store.callStatus === 'rejected' ? 'Semua Panggilan Ditolak / Ditinggalkan' :
                        store.callStatus === 'missed' ? 'Tidak ada jawaban' :
                        'Memanggil Grup...'
                    }}
                </p>
                <small class="text-white-50">
                    Menunggu member grup bergabung... ({{ rejectedParticipants }} ditolak)
                </small>

                <div class="call-type-badge mb-5 mt-2">
                    <Video :size="18"/>
                    <span>Video Grup</span>
                </div>

                <!-- Tombol cancel -->
                <div v-if="store.callStatus === 'calling'" class="d-flex justify-content-center mt-5">
                    <button @click="handleCancel" class="btn-action btn-reject">
                        <X :size="32"/>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Gunakan styling yang sama persis seperti di VideoCallingModal kamu! */
.incoming-call-overlay {
    position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
    display: flex; justify-content: center; align-items: center;
    background-color: rgba(0, 0, 0, 0.7); z-index: 9999; backdrop-filter: blur(5px);
}
.incoming-card {
    padding: 40px; border-radius: 24px; text-align: center; width: 90%; max-width: 350px;
    background: rgba(30, 30, 40, 0.8); border: 1px solid rgba(255, 255, 255, 0.1);
    box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
}
.caller-name {
    color: white; font-weight: 700; font-size: 1.5rem; margin-bottom: 8px;
}
.call-status {
    color: #a1a1aa; font-size: 1rem; margin-bottom: 20px;
}
.call-type-badge {
    display: inline-flex; align-items: center; gap: 8px; background: rgba(255, 255, 255, 0.1);
    padding: 8px 16px; border-radius: 20px; color: white; margin: 0 auto;
}
.btn-action {
    width: 64px; height: 64px; border-radius: 50%; border: none;
    display: flex; justify-content: center; align-items: center; color: white;
    cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
}
.btn-action:hover { transform: scale(1.1); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
.btn-reject { background-color: #ff3b30; }
</style>