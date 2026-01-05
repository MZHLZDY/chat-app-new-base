import { defineStore } from "pinia";
import { ref } from "vue";

export const useGlobalChatStore = defineStore("globalChat", () => {
    const activeChatId = ref<string | number | null>(null);

    function setActiveChat(id: string | number | null) {
        activeChatId.value = id;
    }

    return { activeChatId, setActiveChat };
});