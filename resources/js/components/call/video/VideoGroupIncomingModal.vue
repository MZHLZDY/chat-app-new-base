<script setup lang="ts">
import { computed } from 'vue';
import { useCallStore } from '@/stores/callStore';
import { useVideoGroupCall } from '@/composables/useVideoGroupCall';
import CallAvatar from '../shared/CallAvatar.vue';
import { Phone, X, Video } from 'lucide-vue-next';

const store = useCallStore();
const { answerGroupVideoCall, rejectGroupVideoCall } = useVideoGroupCall();

// Tampilkan kalau ada panggilan masuk, dan it's group, dan masih 'ringing'
const isIncomingGroup = computed(() => 
    !!store.incomingCall && 
    store.incomingCall?.type === 'video' && 
    store.incomingCall?.isGroup === true &&
    store.incomingCall?.status === 'ringing'
);

const incomingCall = computed(() => store.incomingCall);
const callerInfo = computed(() => incomingCall.value?.caller);

const handleAccept = async () => {
    if (incomingCall.value) {
        await answerGroupVideoCall(incomingCall.value.id as number);
    }
};

const handleReject = async () => {
    if (incomingCall.value) {
        await rejectGroupVideoCall(incomingCall.value.id as number);
    }
};

</script>

<template>
    <Transition name="fade">
        <div v-if="isIncomingGroup" class="incoming-call-overlay">
            <div class="incoming-card glass-effect pulse-animation">
                
                <h6 class="mb-4 text-white-50">✨ Panggilan Grup Masuk</h6>

                <div class="mb-5 d-flex justify-content-center">
                    <CallAvatar
                        :photo-url="callerInfo?.avatar || callerInfo?.profile_photo_url || callerInfo?.photo"
                        :display-name="callerInfo?.name || 'Grup'"
                        size="120px"
                        :is-calling="true"
                    />
                </div>

                <h3 class="caller-name">{{ callerInfo?.name }} Memanggil</h3>
                <p class="call-status">Panggilan video grup...</p>

                <div class="call-type-badge mb-5">
                    <Video :size="18"/>
                    <span>Video Grup</span>
                </div>

                <div class="action-buttons">
                    <button @click="handleReject" class="btn-action btn-reject">
                        <X :size="32"/>
                    </button>
                    <button @click="handleAccept" class="btn-action btn-accept pulse-green">
                        <Phone :size="32"/>
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
/* Gunakan styling yang sama persis seperti di VideoIncomingModal personal kamu! */
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
.action-buttons {
    display: flex; justify-content: center; gap: 30px; margin-top: 20px;
}
.btn-action {
    width: 64px; height: 64px; border-radius: 50%; border: none;
    display: flex; justify-content: center; align-items: center; color: white;
    cursor: pointer; transition: transform 0.2s, box-shadow 0.2s;
}
.btn-action:hover { transform: scale(1.1); }
.btn-reject { background-color: #ff3b30; box-shadow: 0 10px 20px rgba(255, 59, 48, 0.3); }
.btn-accept { background-color: #34c759; box-shadow: 0 10px 20px rgba(52, 199, 89, 0.3); }
.pulse-green { animation: pulseGreen 2s infinite; }
@keyframes pulseGreen {
    0% { box-shadow: 0 0 0 0 rgba(52, 199, 89, 0.7); }
    70% { box-shadow: 0 0 0 20px rgba(52, 199, 89, 0); }
    100% { box-shadow: 0 0 0 0 rgba(52, 199, 89, 0); }
}
</style>