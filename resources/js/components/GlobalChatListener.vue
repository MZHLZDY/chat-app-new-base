<script setup lang="ts">
import { onUnmounted, ref, watch } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useGlobalChatStore } from "@/stores/globalChat"; 
import { db } from "@/libs/firebase";
import { ref as firebaseRef, onChildAdded, remove, off } from "firebase/database";
import { useRoute, useRouter } from "vue-router";
import { toast } from "vue3-toastify";

// --- CONFIG ---
const audio = new Audio('/media/preview.mp3'); 
const authStore = useAuthStore();
const globalChatStore = useGlobalChatStore();
const route = useRoute();
const router = useRouter(); 
watch(() => route.path, (newPath) => {
    const isChatPage = newPath.includes('/chat') || newPath.includes('/messages');
    
    if (!isChatPage) {
        console.log("User pindah ke halaman lain, reset Active Chat.");
        globalChatStore.setActiveChat(null);
    }
}, { immediate: true });
// --- STATE LISTENER ---
let notificationCallback: ((snapshot: any) => void) | null = null;
let notificationDbRef: any = null;

// --- 1. AUDIO HELPER ---
const playSound = async () => {
    try {
        audio.currentTime = 0;
        await audio.play();
    } catch (e) { console.log("Audio blocked auto-play"); }
};

// --- 2. INIT LISTENER (DENGAN CLEANUP) ---
const initNotificationListener = () => {
    const userId = authStore.user?.id;
    if (!userId) return;

    if (notificationDbRef && notificationCallback) {
        off(notificationDbRef, 'child_added', notificationCallback);
    }

    const path = `notifications/${userId}`;
    notificationDbRef = firebaseRef(db, path);

    notificationCallback = (snapshot: any) => {
        const key = snapshot.key;
        const val = snapshot.val();
        
        if (!val) return;
        remove(firebaseRef(db, `${path}/${key}`));

        handleNotification(val);
    };
    onChildAdded(notificationDbRef, notificationCallback);
    
    console.log("GlobalChat: Listening initialized for user", userId);
};

// --- 3. HANDLE NOTIFIKASI ---
const handleNotification = (data: any) => {
    if (data.type !== 'new_message') return;
    // Cek apakah chat sedang aktif
    const isActiveChat = globalChatStore.activeChatId && 
                         String(globalChatStore.activeChatId) === String(data.sender_id);
    if (isActiveChat && !document.hidden) {
        return; 
    }

    let text = data.message;
    if (data.message_type === 'image') text = '📷 Mengirim gambar';
    
    triggerToast(data, text);
};

// --- 4. OUTPUT TOAST ---
const triggerToast = (data: any, text: string) => {
    playSound(); 

    toast.info(`💬 ${data.sender_name}: ${text}`, {
        autoClose: 5000,
        theme: "colored",
        position: toast.POSITION.TOP_RIGHT,
        icon: false,
        onClick: () => {
            window.focus();
            router.push({ 
                name: 'dashboard.private-chat',
                params: { id: data.sender_id } 
            });
        }
    });
};

// --- 5. SETUP & CLEANUP ---
watch(() => authStore.user, (u) => {
    if (u) {
        initNotificationListener();
    } else {
        if (notificationDbRef && notificationCallback) {
            off(notificationDbRef, 'child_added', notificationCallback);
            notificationDbRef = null;
            notificationCallback = null;
        }
    }
}, { immediate: true });

onUnmounted(() => {
    if (notificationDbRef && notificationCallback) {
        off(notificationDbRef, 'child_added', notificationCallback);
    }
});
</script>

<template><div style="display:none"></div></template>