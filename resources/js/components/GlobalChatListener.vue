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

// --- RESETTER (Satpam Route) ---
watch(() => route.path, (newPath) => {
    const isChatPage = newPath.includes('/chat') || newPath.includes('/messages') || newPath.includes('/group');
    
    if (!isChatPage) {
        globalChatStore.setActiveChat(null);
        globalChatStore.setActiveGroup(null);
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
    } catch (e) { console.log('Audio blocked auto-play'); }
};

// --- 2. INIT LISTENER ---
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
    
    console.log('GlobalChat: Listening initialized for user', userId);
};

// --- 3. HANDLE NOTIFIKASI (LOGIKA UTAMA) ---
const handleNotification = (data: any) => {
    
    // === A. PRIVATE CHAT ===
    if (data.type === 'new_message') {
        const isActiveChat = globalChatStore.activeChatId && 
                             String(globalChatStore.activeChatId) === String(data.sender_id);
        if (isActiveChat && !document.hidden) return; 

        let text = data.message;
        if (data.message_type === 'image') text = '📷 Mengirim gambar';
        if (data.message_type === 'file') text = 'ats Berkas';

        triggerToast(data, text, 'private');
    } 
    // === B. GROUP CHAT ===
    else if (data.type === 'new_group_message') {
        if (String(data.sender_id) === String(authStore.user?.id)) return;

        const isActiveGroup = globalChatStore.activeGroupId && 
                              String(globalChatStore.activeGroupId) === String(data.group_id);
        if (isActiveGroup && !document.hidden) return;

        let text = data.message;
        if (data.message_type === 'image') text = '📷 Mengirim gambar';
        if (data.message_type === 'file') text = '📁 Mengirim berkas';

        triggerToast(data, text, 'group');
    }
};

// --- 4. OUTPUT TOAST ---
const triggerToast = (data: any, text: string, type: 'private' | 'group') => {
    playSound(); 

    let title = "";
    let routeName = "";
    let routeParams = {};

    if (type === 'private') {
        title = `💬 ${data.sender_name}`;
        routeName = 'dashboard.private-chat';
        routeParams = { id: data.sender_id };
    } else {
        const groupName = data.group_name || 'Grup';
        title = `👥 ${groupName}: ${data.sender_name}`;
        
        routeName = 'dashboard.group-chat'; 
        routeParams = { id: data.group_id }; 
    }

    toast.info(`${title}: ${text}`, {
        autoClose: 5000,
        theme: "colored",
        position: toast.POSITION.TOP_RIGHT,
        icon: false,
        onClick: () => {
            window.focus();
            router.push({ 
                name: routeName,
                params: routeParams 
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