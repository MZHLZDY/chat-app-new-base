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
    <div class="ai-chat-wrapper overflow-hidden" style="height: calc(100vh - 170px);">
        <div class="card ai-chat-card">
            <!-- ── Header — sama dengan private chat ── -->
            <div
                class="card-header d-flex align-items-center p-3 border-bottom"
                style="min-height: 70px"
            >
                <div class="d-flex align-items-center flex-grow-1">
                    <button
                        @click="router.go(-1)"
                        class="btn btn-icon btn-sm text-gray-500 me-2 d-lg-none"
                    >
                        <i class="fas fa-arrow-left fs-5"></i>
                    </button>
                    <div
                        class="symbol symbol-40px symbol-circle me-3 position-relative"
                    >
                        <img :src="botProfile.avatar" alt="AI" />
                        <span
                            class="position-absolute bottom-0 end-0 bg-success border border-white rounded-circle"
                            style="width: 10px; height: 10px"
                        ></span>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="fw-bold text-gray-800 fs-6">{{
                            botProfile.name
                        }}</span>
                        <span class="fs-8 text-success fw-bold">Online</span>
                    </div>
                </div>
            </div>

            <!-- ── Chat Body — bg #f9f9f9 sama persis ── -->
            <div
                ref="chatBodyRef"
                @scroll="handleScroll"
                class="card-body chat-body-custom p-4 overflow-auto position-relative"
            >
                <!-- Loading -->
                <div
                    v-if="isLoading"
                    class="d-flex justify-content-center align-items-center h-100"
                >
                    <div
                        class="spinner-border text-primary"
                        role="status"
                    ></div>
                </div>

                <!-- Empty -->
                <div
                    v-else-if="messages.length === 0"
                    class="d-flex flex-column justify-content-center align-items-center h-100 text-center"
                >
                    <div class="ai-empty-icon mb-3">
                        <i
                            class="fas fa-robot"
                            style="
                                font-size: 2.5rem;
                                color: #667eea;
                                opacity: 0.4;
                            "
                        ></i>
                    </div>
                    <p class="fw-bold text-gray-500 mb-1">
                        Mulai percakapan dengan AI!
                    </p>
                    <p class="text-muted fs-7">
                        Tanya apa saja, aku siap membantu 🤖
                    </p>
                </div>

                <!-- Messages -->
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
                            <!-- Bot avatar -->
                            <div
                                v-if="msg.sender_type === 'bot'"
                                class="symbol symbol-30px symbol-circle me-2 align-self-end mb-1 flex-shrink-0"
                            >
                                <img
                                    :src="botProfile.avatar"
                                    class="rounded-circle"
                                />
                            </div>

                            <!-- Bubble -->
                            <div
                                class="p-3 rounded shadow-sm position-relative"
                                :class="
                                    msg.sender_type === 'user'
                                        ? 'bg-primary text-white rounded-bottom-end-0'
                                        : 'receiver-bubble rounded-bottom-start-0'
                                "
                                style="max-width: 320px; min-width: 80px"
                            >
                                <div
                                    style="
                                        white-space: pre-wrap;
                                        font-size: 0.9rem;
                                        line-height: 1.5;
                                    "
                                >
                                    {{ msg.message }}
                                </div>
                                <div
                                    class="d-flex align-items-center justify-content-end mt-1"
                                    style="font-size: 0.7rem; opacity: 0.75"
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

                    <!-- Typing indicator -->
                    <div v-if="isSending" class="d-flex align-items-center">
                        <div
                            class="symbol symbol-30px symbol-circle me-2 flex-shrink-0"
                        >
                            <img
                                :src="botProfile.avatar"
                                class="rounded-circle"
                            />
                        </div>
                        <div
                            class="typing-indicator receiver-bubble rounded p-3 shadow-sm"
                        >
                            <span></span><span></span><span></span>
                        </div>
                    </div>

                    <div ref="bottomRef" style="height: 1px"></div>
                </div>

                <!-- Scroll to bottom button -->
                <Transition name="fade">
                    <button
                        v-if="showScrollButton"
                        @click="scrollToBottom('smooth')"
                        class="btn btn-primary btn-icon shadow position-sticky start-50 translate-middle-x rounded-circle"
                        style="
                            bottom: 16px;
                            z-index: 5;
                            margin-top: -50px;
                            width: 36px;
                            height: 36px;
                        "
                    >
                        <i class="fas fa-arrow-down fs-7"></i>
                    </button>
                </Transition>
            </div>

            <!-- ── Footer — sama dengan private chat ── -->
            <div class="card-footer pt-4 pb-4" style="min-height: 80px">
                <div class="d-flex align-items-center">
                    <input
                        v-model="newMessage"
                        @keyup.enter="sendMessage"
                        type="text"
                        class="form-control form-control-solid me-3"
                        placeholder="Ketik pesan..."
                        :disabled="isSending"
                    />
                    <button
                        class="btn btn-primary btn-icon"
                        @click="sendMessage"
                        :disabled="!newMessage.trim() || isSending"
                    >
                        <i
                            v-if="!isSending"
                            class="fas fa-paper-plane fs-5"
                        ></i>
                        <span
                            v-else
                            class="spinner-border spinner-border-sm"
                        ></span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.ai-chat-wrapper {
    display: flex;
    justify-content: center;
    width: 100%;
    height: 100%;
}

.ai-chat-card {
    width: 100%;
    max-width: 900px;
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.chat-body-custom {
    flex: 1;
    overflow-y: auto;
    background-color: #f9f9f9;
    scroll-behavior: smooth;
}

.receiver-bubble {
    background-color: #ffffff;
    color: #3f4254;
}

.typing-indicator {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    min-width: 52px;
}
.typing-indicator span {
    width: 7px;
    height: 7px;
    background-color: #a1a5b7;
    border-radius: 50%;
    animation: typing 1.4s infinite ease-in-out both;
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
        transform: scale(0.6);
        opacity: 0.4;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

.message-fade-enter-active,
.message-fade-leave-active {
    transition: all 0.25s ease;
}
.message-fade-enter-from,
.message-fade-leave-to {
    opacity: 0;
    transform: translateY(8px);
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.chat-body-custom::-webkit-scrollbar {
    width: 5px;
}
.chat-body-custom::-webkit-scrollbar-track {
    background: transparent;
}
.chat-body-custom::-webkit-scrollbar-thumb {
    background: rgba(0, 0, 0, 0.1);
    border-radius: 10px;
}
.chat-body-custom::-webkit-scrollbar-thumb:hover {
    background: rgba(0, 0, 0, 0.18);
}

[data-bs-theme="dark"] .chat-body-custom {
    background-color: #151521 !important;
}
[data-bs-theme="dark"] .card-header,
[data-bs-theme="dark"] .card-footer {
    background-color: #1e1e2d !important;
    border-bottom: 1px solid #2b2b40 !important;
    border-top: 1px solid #2b2b40 !important;
}
[data-bs-theme="dark"] .receiver-bubble {
    background-color: #2b2b40 !important;
    color: #ffffff !important;
}
[data-bs-theme="dark"] .form-control-solid {
    background-color: #1b1b29 !important;
    border-color: #2b2b40 !important;
    color: #ffffff !important;
}
[data-bs-theme="dark"] .text-gray-800 {
    color: #ffffff !important;
}
[data-bs-theme="dark"] .text-gray-500 {
    color: #7e8299 !important;
}
</style>
