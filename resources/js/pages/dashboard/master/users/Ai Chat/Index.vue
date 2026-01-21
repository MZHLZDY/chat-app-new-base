<script setup lang="ts">
import { ref, onMounted, nextTick, watch, onUnmounted } from "vue";
import axios from "@/libs/axios";
import { useAuthStore } from "@/stores/authStore";
import { toast } from "vue3-toastify";
import { useRouter } from "vue-router";

const router = useRouter();
const authStore = useAuthStore();

// --- STATE ---
const messages = ref<any[]>([]);
const newMessage = ref("");
const isLoading = ref(false);
const isSending = ref(false);
const showScrollButton = ref(false);

// Refs untuk manipulasi DOM
const chatBodyRef = ref<HTMLElement | null>(null);
const bottomRef = ref<HTMLElement | null>(null);

// Profil Bot
const botProfile = {
    name: "AI Assistant",
    avatar: "https://cdn-icons-png.flaticon.com/512/4712/4712027.png",
    status: "Online",
};

// --- LOGIC UTAMA ---
const fetchMessages = async () => {
    isLoading.value = true;
    try {
        const response = await axios.get("chat/ai-messages");
        messages.value = response.data.data;
        await nextTick();
        scrollToBottom("auto");
    } catch (error) {
        console.error("Gagal load history:", error);
        toast.error("Gagal memuat riwayat chat.");
    } finally {
        isLoading.value = false;
    }
};

// 2. Kirim Pesan
const sendMessage = async () => {
    if (!newMessage.value.trim() || isSending.value) return;

    const text = newMessage.value;
    newMessage.value = "";
    isSending.value = true;
    const tempId = Date.now();
    messages.value.push({
        id: tempId,
        message: text,
        sender_type: "user",
        created_at: new Date().toISOString(),
    });

    // Scroll smooth saat kirim pesan
    scrollToBottom("smooth");

    try {
        const response = await axios.post("chat/ai-messages/send", {
            message: text,
        });

        messages.value = messages.value.filter((m) => m.id !== tempId);
        messages.value.push(response.data.user_message);
        setTimeout(() => {
            messages.value.push(response.data.bot_message);
            scrollToBottom("smooth");
        }, 100);
    } catch (error) {
        console.error(error);
        toast.error("Maaf, AI sedang sibuk/error.");
        messages.value = messages.value.filter((m) => m.id !== tempId);
    } finally {
        isSending.value = false;
    }
};

// --- SCROLL LOGIC ---
const scrollToBottom = (behavior: "auto" | "smooth" = "smooth") => {
    nextTick(() => {
        if (bottomRef.value) {
            bottomRef.value.scrollIntoView({
                behavior: behavior,
                block: "end",
            });
        }
    });
};

const handleScroll = () => {
    if (!chatBodyRef.value) return;

    const { scrollTop, scrollHeight, clientHeight } = chatBodyRef.value;
    const distanceFromBottom = scrollHeight - scrollTop - clientHeight;
    showScrollButton.value = distanceFromBottom > 300;
};

// --- LIFECYCLE HOOKS ---
onMounted(() => {
    fetchMessages();
});

watch(
    () => messages.value.length,
    () => {
        nextTick(() => {
            if (isSending.value) {
                scrollToBottom("smooth");
            }
        });
    }
);
</script>

<template>
    <div class="d-flex justify-content-center w-100 h-100">
        <div
            class="card card-chat shadow-lg border-0 w-100"
            style="max-width: 900px; height: 85vh"
        >
            <div
                class="card-header bg-white border-bottom-0 shadow-sm d-flex align-items-center py-3"
                style="z-index: 10"
            >
                <button
                    @click="router.go(-1)"
                    class="btn btn-icon btn-sm btn-light-primary me-3"
                >
                    <i class="fas fa-arrow-left fs-4"></i>
                </button>

                <div class="symbol symbol-45px me-3 position-relative">
                    <img
                        :src="botProfile.avatar"
                        alt="Bot"
                        class="bg-light-info p-1 rounded-circle"
                    />
                    <span
                        class="position-absolute bottom-0 end-0 p-1 bg-success border border-white rounded-circle"
                    ></span>
                </div>

                <div class="d-flex flex-column">
                    <h5 class="fw-bold mb-0 text-dark">
                        {{ botProfile.name }}
                    </h5>
                    <span
                        class="text-muted fs-7 fw-semibold d-flex align-items-center"
                    >
                        <span class="pulse-dot bg-success me-2"></span>
                        {{ botProfile.status }}
                    </span>
                </div>
            </div>

            <div
                ref="chatBodyRef"
                @scroll="handleScroll"
                class="card-body chat-background overflow-auto p-4 position-relative"
            >
                <div
                    v-if="isLoading"
                    class="d-flex justify-content-center align-items-center h-100"
                >
                    <div
                        class="spinner-border text-primary"
                        role="status"
                    ></div>
                </div>

                <div
                    v-else-if="messages.length === 0"
                    class="d-flex flex-column justify-content-center align-items-center h-100 text-muted opacity-50"
                >
                    <i class="fas fa-robot fs-1 mb-3"></i>
                    <p class="fs-5 fw-bold">Mulai percakapan sekarang!</p>
                </div>

                <div v-else class="d-flex flex-column gap-3">
                    <TransitionGroup name="message-fade">
                        <div
                            v-for="(msg, index) in messages"
                            :key="msg.id || index"
                            class="d-flex w-100"
                            :class="
                                msg.sender_type === 'user'
                                    ? 'justify-content-end'
                                    : 'justify-content-start'
                            "
                        >
                            <div
                                v-if="msg.sender_type === 'bot'"
                                class="symbol symbol-35px me-3 align-self-end mb-1"
                            >
                                <img
                                    :src="botProfile.avatar"
                                    class="rounded-circle"
                                />
                            </div>

                            <div
                                class="message-bubble p-3 shadow-sm"
                                :class="
                                    msg.sender_type === 'user'
                                        ? 'user-bubble'
                                        : 'bot-bubble'
                                "
                            >
                                <div
                                    class="message-text"
                                    style="white-space: pre-wrap"
                                >
                                    {{ msg.message }}
                                </div>
                                <div
                                    class="message-time d-flex align-items-center justify-content-end mt-1"
                                >
                                    <span class="me-1">{{
                                        new Date(
                                            msg.created_at
                                        ).toLocaleTimeString([], {
                                            hour: "2-digit",
                                            minute: "2-digit",
                                        })
                                    }}</span>
                                    <i
                                        v-if="msg.sender_type === 'user'"
                                        class="fas fa-check-double text-white-50 fs-9"
                                    ></i>
                                </div>
                            </div>
                        </div>
                    </TransitionGroup>

                    <div
                        v-if="isSending"
                        class="d-flex align-items-center ms-5 mt-2"
                    >
                        <div
                            class="typing-indicator bg-white shadow-sm px-3 py-2 rounded-4"
                        >
                            <span></span><span></span><span></span>
                        </div>
                    </div>

                    <div ref="bottomRef" style="height: 1px"></div>
                </div>

                <Transition name="fade">
                    <button
                        v-if="showScrollButton"
                        @click="scrollToBottom('smooth')"
                        class="btn btn-primary btn-icon rounded-circle shadow position-sticky start-50 translate-middle-x"
                        style="
                            bottom: 20px;
                            z-index: 5;
                            margin-top: -50px;
                            left: 50%;
                        "
                    >
                        <i class="fas fa-arrow-down"></i>
                    </button>
                </Transition>
            </div>

            <div class="card-footer bg-white border-top-0 py-3 px-4 shadow-top">
                <form
                    @submit.prevent="sendMessage"
                    class="d-flex align-items-center position-relative"
                >
                    <input
                        v-model="newMessage"
                        type="text"
                        class="form-control form-control-solid rounded-pill ps-5 py-3 pe-5 bg-light"
                        placeholder="Ketik pesan Anda di sini..."
                        :disabled="isSending"
                        style="padding-right: 60px"
                    />
                    <button
                        type="submit"
                        class="btn btn-icon btn-primary rounded-circle position-absolute end-0 me-2 shadow-sm send-btn"
                        :disabled="!newMessage || isSending"
                    >
                        <i
                            v-if="!isSending"
                            class="fas fa-paper-plane fs-4"
                        ></i>
                        <span
                            v-else
                            class="spinner-border spinner-border-sm"
                        ></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* --- Layout & Background --- */
.card-chat {
    border-radius: 1.5rem;
    overflow: hidden;
}

.chat-background {
    background-color: #f4f6f9;
    background-image: radial-gradient(#e2e8f0 1px, transparent 1px);
    background-size: 20px 20px;
}

/* --- Message Bubbles --- */
.message-bubble {
    max-width: 75%;
    min-width: 80px;
    font-size: 0.95rem;
    line-height: 1.5;
    position: relative;
}

.user-bubble {
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: #fff;
    border-radius: 18px 18px 4px 18px;
}

.bot-bubble {
    background: #ffffff;
    color: #333;
    border-radius: 18px 18px 18px 4px;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.message-time {
    font-size: 0.7rem;
    opacity: 0.8;
}

/* --- Typing Indicator --- */
.typing-indicator span {
    display: inline-block;
    width: 6px;
    height: 6px;
    background-color: #aaa;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out both;
    margin: 0 2px;
}
.typing-indicator span:nth-child(1) {
    animation-delay: -0.32s;
}
.typing-indicator span:nth-child(2) {
    animation-delay: -0.16s;
}

@keyframes typing {
    0%,
    80%,
    100% {
        transform: scale(0);
    }
    40% {
        transform: scale(1);
    }
}

/* --- Transitions --- */
.message-fade-enter-active,
.message-fade-leave-active {
    transition: all 0.3s ease;
}
.message-fade-enter-from,
.message-fade-leave-to {
    opacity: 0;
    transform: translateY(10px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.3s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

/* --- Scrollbar --- */
.card-body::-webkit-scrollbar {
    width: 6px;
}
.card-body::-webkit-scrollbar-track {
    background: transparent;
}
.card-body::-webkit-scrollbar-thumb {
    background-color: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}
.card-body::-webkit-scrollbar-thumb:hover {
    background-color: rgba(0, 0, 0, 0.2);
}

/* --- Misc --- */
.pulse-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.shadow-top {
    box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.02);
}
.send-btn {
    transition: transform 0.2s;
    width: 40px;
    height: 40px;
}
.send-btn:hover:not(:disabled) {
    transform: scale(1.1);
}

/* Dark Mode Overrides */
[data-bs-theme="dark"] .chat-background {
    background-color: #151521;
    background-image: radial-gradient(#2b2b40 1px, transparent 1px);
}
[data-bs-theme="dark"] .bot-bubble {
    background: #1e1e2d;
    color: #fff;
    border-color: #333;
}
[data-bs-theme="dark"] .bg-white {
    background-color: #1e1e2d !important;
}
[data-bs-theme="dark"] .text-dark {
    color: #fff !important;
}
[data-bs-theme="dark"] input.form-control {
    background-color: #2b2b40 !important;
    color: #fff;
}
</style>
