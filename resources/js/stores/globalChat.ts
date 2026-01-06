import { defineStore } from "pinia";
import { ref } from "vue";

export const useGlobalChatStore = defineStore("globalChat", () => {
    const activeChatId = ref<string | number | null>(null);
    const activeGroupId = ref<string | number | null>(null);

    function setActiveChat(id: string | number | null) {
        activeChatId.value = id;
        activeGroupId.value = null;
    }
    function setActiveGroup(id: string | number | null) {
        activeGroupId.value = id;
        activeChatId.value = null;
    }

    return { activeChatId, activeGroupId, setActiveChat, setActiveGroup };
});