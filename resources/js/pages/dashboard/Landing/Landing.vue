<template>
    <div class="landing-page">
        <!-- Animated Background -->
        <div class="bg-animated">
            <div class="circle c1"></div>
            <div class="circle c2"></div>
            <div class="circle c3"></div>
        </div>

        <!-- Navbar -->
        <nav class="landing-nav animate-fade-in">
            <div class="nav-brand">
                <img :src="logo" class="nav-logo" alt="Logo" />
                <span class="nav-appname">{{ appName }}</span>
            </div>
            <div class="nav-actions">
                <router-link to="/sign-in" class="btn-nav-ghost">Masuk</router-link>
                <router-link to="/sign-up" class="btn-nav-solid">Daftar Gratis</router-link>
            </div>
        </nav>

        <!-- Hero -->
        <section class="hero">
            <div class="hero-inner">
                <!-- Badge -->
                <div class="hero-badge animate-slide-up">
                    <span class="badge-dot"></span>
                    Chat Real-time · Aman · Cepat
                </div>

                <h1 class="hero-title animate-slide-up" style="animation-delay:.1s">
                    Komunikasi Tanpa Batas,<br />
                    <span class="hero-title-accent">Kapan Saja & Di Mana Saja</span>
                </h1>

                <p class="hero-desc animate-slide-up" style="animation-delay:.2s">
                    Platform chat modern dengan pesan pribadi, grup, AI assistant,
                    dan manajemen tugas dalam satu aplikasi.
                </p>

                <div class="hero-cta animate-slide-up" style="animation-delay:.3s">
                    <router-link to="/sign-up" class="btn-primary-lg">
                        Mulai Sekarang
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </router-link>
                    <router-link to="/sign-in" class="btn-ghost-lg">Sudah punya akun?</router-link>
                </div>

                <!-- Mock chat bubble UI -->
                <div class="hero-mockup animate-slide-up" style="animation-delay:.4s">
                    <div class="mockup-card">
                        <!-- Header — sama dengan card-header di app -->
                        <div class="mockup-header">
                            <div class="mockup-avatar-wrap">
                                <div class="mockup-avatar"></div>
                                <span class="mockup-online-badge"></span>
                            </div>
                            <div class="mockup-contact-info">
                                <div class="mockup-name">Grup Tim Proyek</div>
                                <div class="mockup-status">Online</div>
                            </div>
                            <div class="mockup-header-actions">
                                <div class="mockup-action-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.14 1.2 2 2 0 012.11 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.09a16 16 0 006 6l.45-.45a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                                </div>
                                <div class="mockup-action-btn">
                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Body — bg #f9f9f9 -->
                        <div class="mockup-body">
                            <!-- Receiver bubble — putih, rounded-bottom-start-0 -->
                            <div class="bubble bubble-left">
                                <div class="bubble-avatar-sm">A</div>
                                <div class="bubble-recv">Hei, meeting jam berapa?</div>
                            </div>
                            <!-- Sender bubble — bg-primary, rounded-bottom-end-0 -->
                            <div class="bubble bubble-right">
                                <div class="bubble-send">Jam 2 siang ya! 📅</div>
                            </div>
                            <div class="bubble bubble-left">
                                <div class="bubble-avatar-sm">B</div>
                                <div class="bubble-recv">Oke siap, sudah kumasukkan ke todo list 👍</div>
                            </div>
                            <div class="bubble bubble-right">
                                <div class="bubble-send">Mantap! See you 🚀</div>
                            </div>
                        </div>

                        <!-- Footer — sama dengan card-footer -->
                        <div class="mockup-footer">
                            <div class="mockup-attach-btn">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg>
                            </div>
                            <div class="mockup-input-field">
                                <span class="mockup-typed">{{ typedText || '\u00A0' }}</span><span class="mockup-cursor" :class="{ 'cursor-hidden': !showCursor }">|</span>
                            </div>
                            <div class="mockup-send-btn">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="features-section">
            <div class="features-wrap">

                <!-- Section Header -->
                <div class="features-header animate-slide-up" style="animation-delay:.5s">
                    <div class="features-badge">✨ Fitur Unggulan</div>
                    <h2 class="features-title">Semua yang kamu butuhkan,<br/>dalam satu tempat</h2>
                    <p class="features-subtitle">Dirancang untuk produktivitas tim dan komunikasi yang lebih efektif</p>
                </div>

                <!-- Feature Cards Grid -->
                <div class="feat-grid animate-slide-up" style="animation-delay:.6s">

                    <!-- Real-time Chat -->
                    <div class="feat-card feat-chat">
                        <div class="feat-card-inner">
                            <div class="feat-icon-wrap feat-icon-blue">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
                            </div>
                            <h3 class="feat-title">Real-time Chat</h3>
                            <p class="feat-desc">Pesan terkirim instan dengan indikator "dibaca", balasan, dan berbagi file langsung di percakapan.</p>
                            <div class="feat-tags">
                                <span class="feat-tag">Pesan Pribadi</span>
                                <span class="feat-tag">Grup Chat</span>
                                <span class="feat-tag">Kirim File</span>
                            </div>
                            <!-- Mini illustration -->
                            <div class="feat-illustration chat-illustration">
                                <div class="fi-bubble fi-bubble-recv">Hei, gimana kabarnya? 👋</div>
                                <div class="fi-bubble fi-bubble-send">Baik! Lagi sibuk nih 😅</div>
                                <div class="fi-bubble fi-bubble-recv">Oke, chat nanti ya!</div>
                            </div>
                        </div>
                    </div>

                    <!-- Voice & Video Call -->
                    <div class="feat-card feat-call">
                        <div class="feat-card-inner">
                            <div class="feat-icon-wrap feat-icon-green">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2" ry="2"/></svg>
                            </div>
                            <h3 class="feat-title">Voice & Video Call</h3>
                            <p class="feat-desc">Panggilan suara dan video HD langsung dari aplikasi, baik satu-satu maupun grup call.</p>
                            <div class="feat-tags">
                                <span class="feat-tag">Voice Call</span>
                                <span class="feat-tag">Video Call</span>
                                <span class="feat-tag">Group Call</span>
                            </div>
                            <!-- Mini illustration -->
                            <div class="feat-illustration call-illustration">
                                <div class="fi-call-card">
                                    <div class="fi-call-avatar"></div>
                                    <div class="fi-call-info">
                                        <div class="fi-call-name">Reza</div>
                                        <div class="fi-call-status">00:42 ●</div>
                                    </div>
                                    <div class="fi-call-btns">
                                        <div class="fi-call-btn fi-btn-red">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1-9.4 0-17-7.6-17-17 0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.3 0 .7-.2 1L6.6 10.8z"/></svg>
                                        </div>
                                        <div class="fi-call-btn fi-btn-gray">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="1" y1="1" x2="23" y2="23"/><path d="M16.72 11.06A10.94 10.94 0 0119 12.55M5 5a10.94 10.94 0 0114.54 1.41M10.71 5.05A16 16 0 0122.56 9M1.42 9a15.91 15.91 0 014.7-2.88M8.53 16.11a6 6 0 016.95 0M12 20h.01"/></svg>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- AI Assistant -->
                    <div class="feat-card feat-ai">
                        <div class="feat-card-inner">
                            <div class="feat-icon-wrap feat-icon-purple">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v4M12 19v4M4.22 4.22l2.83 2.83M16.95 16.95l2.83 2.83M1 12h4M19 12h4M4.22 19.78l2.83-2.83M16.95 7.05l2.83-2.83"/></svg>
                            </div>
                            <h3 class="feat-title">AI Assistant</h3>
                            <p class="feat-desc">Tanya apa saja ke AI assistant yang cerdas — dari merangkum percakapan hingga membantu pekerjaan harian.</p>
                            <div class="feat-tags">
                                <span class="feat-tag">Tanya Jawab</span>
                                <span class="feat-tag">Ringkasan</span>
                                <span class="feat-tag">Produktivitas</span>
                            </div>
                            <!-- Mini illustration -->
                            <div class="feat-illustration ai-illustration">
                                <div class="fi-ai-row">
                                    <div class="fi-ai-avatar">AI</div>
                                    <div class="fi-ai-bubble">Halo! Ada yang bisa aku bantu? ✨</div>
                                </div>
                                <div class="fi-ai-row fi-ai-row-right">
                                    <div class="fi-user-bubble">Buatkan ringkasan rapat tadi</div>
                                </div>
                                <div class="fi-ai-row">
                                    <div class="fi-ai-avatar">AI</div>
                                    <div class="fi-ai-bubble fi-ai-typing">
                                        <span></span><span></span><span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Todo & Board -->
                    <div class="feat-card feat-todo">
                        <div class="feat-card-inner">
                            <div class="feat-icon-wrap feat-icon-orange">
                                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/></svg>
                            </div>
                            <h3 class="feat-title">Todo & Kanban Board</h3>
                            <p class="feat-desc">Kelola tugas tim dengan kanban board, assign anggota, set deadline, dan pantau progres langsung dari chat.</p>
                            <div class="feat-tags">
                                <span class="feat-tag">Kanban Board</span>
                                <span class="feat-tag">Assign Tugas</span>
                                <span class="feat-tag">Deadline</span>
                            </div>
                            <!-- Mini illustration -->
                            <div class="feat-illustration todo-illustration">
                                <div class="fi-kanban">
                                    <div class="fi-col">
                                        <div class="fi-col-head fi-col-todo">To Do</div>
                                        <div class="fi-task">Design mockup</div>
                                        <div class="fi-task">Review API</div>
                                    </div>
                                    <div class="fi-col">
                                        <div class="fi-col-head fi-col-progress">In Progress</div>
                                        <div class="fi-task fi-task-active">Frontend dev</div>
                                    </div>
                                    <div class="fi-col">
                                        <div class="fi-col-head fi-col-done">Done</div>
                                        <div class="fi-task fi-task-done">✓ Setup DB</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- Footer CTA -->
        <footer class="landing-footer animate-fade-in" style="animation-delay:.6s">
            <p>Sudah siap memulai?</p>
            <router-link to="/sign-up" class="btn-primary-lg">
                Buat Akun Gratis
            </router-link>
        </footer>
    </div>
</template>

<script lang="ts">
import { defineComponent, ref, onMounted, onUnmounted } from "vue";
import { getAssetPath } from "@/core/helpers/assets";

export default defineComponent({
    name: "LandingPage",
    setup() {
        const logo = ref(getAssetPath("media/logos/default-dark.svg"));
        const appName = ref("ChatApp");

        // Typewriter
        const typedText = ref("");
        const showCursor = ref(true);
        const phrases = [
            "Halo, ada yang bisa dibantu? 😊",
            "Meeting besok jam 9 ya!",
            "Sudah cek file yang aku kirim?",
            "Oke, nanti aku kabarin! 👍",
            "Jangan lupa deadline hari ini!",
        ];
        let phraseIndex = 0;
        let charIndex = 0;
        let isDeleting = false;
        let timer: ReturnType<typeof setTimeout> | null = null;

        const type = () => {
            const current = phrases[phraseIndex] ?? "";
            if (!isDeleting) {
                typedText.value = current.slice(0, charIndex + 1);
                charIndex++;
                if (charIndex === current.length) {
                    // Pause setelah selesai ketik
                    isDeleting = true;
                    timer = setTimeout(type, 1800);
                    return;
                }
                timer = setTimeout(type, 65);
            } else {
                typedText.value = current.slice(0, charIndex - 1);
                charIndex--;
                if (charIndex === 0) {
                    isDeleting = false;
                    phraseIndex = (phraseIndex + 1) % phrases.length;
                    timer = setTimeout(type, 400);
                    return;
                }
                timer = setTimeout(type, 30);
            }
        };

        // Cursor blink
        let cursorTimer: ReturnType<typeof setInterval> | null = null;

        onMounted(() => {
            timer = setTimeout(type, 1200);
            cursorTimer = setInterval(() => {
                showCursor.value = !showCursor.value;
            }, 530);
        });

        onUnmounted(() => {
            if (timer) clearTimeout(timer);
            if (cursorTimer) clearInterval(cursorTimer);
        });

        return { logo, appName, typedText, showCursor };
    },
});
</script>

<style scoped>
/* ── Animations ─────────────────────────────────────────────────────────── */
@keyframes fadeIn {
    from { opacity: 0; }
    to   { opacity: 1; }
}
@keyframes slideUp {
    from { opacity: 0; transform: translateY(28px); }
    to   { opacity: 1; transform: translateY(0); }
}
@keyframes float {
    0%, 100% { transform: translate(0, 0); }
    33%       { transform: translate(30px, -30px); }
    66%       { transform: translate(-20px, 20px); }
}
.animate-fade-in { animation: fadeIn 0.8s ease-out forwards; opacity: 0; }
.animate-slide-up { animation: slideUp 0.8s ease-out forwards; opacity: 0; }

/* ── Page ───────────────────────────────────────────────────────────────── */
.landing-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    flex-direction: column;
    position: relative;
    overflow: hidden;
    font-family: inherit;
}

/* ── Background circles ─────────────────────────────────────────────────── */
.bg-animated {
    position: fixed;
    inset: 0;
    z-index: 0;
    pointer-events: none;
}
.circle {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.07);
    animation: float 20s ease-in-out infinite;
}
.c1 { width: 380px; height: 380px; top: -120px; left: -120px; }
.c2 { width: 500px; height: 500px; top: 40%; right: -180px; animation-delay: -6s; animation-duration: 26s; }
.c3 { width: 280px; height: 280px; bottom: -80px; left: 38%; animation-delay: -12s; animation-duration: 32s; }

/* ── Navbar ─────────────────────────────────────────────────────────────── */
.landing-nav {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.25rem 2.5rem;
}
.nav-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}
.nav-logo {
    height: 36px;
    filter: brightness(0) invert(1);
}
.nav-appname {
    font-size: 1.2rem;
    font-weight: 700;
    color: white;
    letter-spacing: -0.01em;
}
.nav-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}
.btn-nav-ghost {
    padding: 8px 20px;
    border-radius: 10px;
    border: 1.5px solid rgba(255, 255, 255, 0.5);
    color: white;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s;
    backdrop-filter: blur(6px);
}
.btn-nav-ghost:hover {
    background: rgba(255, 255, 255, 0.15);
    border-color: white;
}
.btn-nav-solid {
    padding: 8px 20px;
    border-radius: 10px;
    background: white;
    color: #667eea;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.15);
}
.btn-nav-solid:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
}

/* ── Hero ───────────────────────────────────────────────────────────────── */
.hero {
    position: relative;
    z-index: 1;
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 2.5rem 3rem;
}
.hero-inner {
    max-width: 700px;
    width: 100%;
    text-align: center;
}
.hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    font-size: 0.82rem;
    font-weight: 600;
    padding: 6px 16px;
    border-radius: 20px;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(8px);
    letter-spacing: 0.03em;
}
.badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4ade80;
    box-shadow: 0 0 6px #4ade80;
    flex-shrink: 0;
}
.hero-title {
    font-size: clamp(1.9rem, 4vw, 3rem);
    font-weight: 800;
    color: white;
    line-height: 1.2;
    margin: 0 0 1.25rem;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}
.hero-title-accent {
    background: linear-gradient(90deg, #fff 0%, rgba(255,255,255,0.75) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}
.hero-desc {
    font-size: 1.05rem;
    color: rgba(255, 255, 255, 0.88);
    line-height: 1.7;
    margin: 0 auto 2rem;
    max-width: 520px;
}
.hero-cta {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 2.5rem;
}
.btn-primary-lg {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: white;
    color: #667eea;
    font-size: 0.95rem;
    font-weight: 700;
    padding: 12px 28px;
    border-radius: 12px;
    text-decoration: none;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
    transition: all 0.2s;
}
.btn-primary-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.25);
    color: #5a67d8;
}
.btn-ghost-lg {
    color: rgba(255, 255, 255, 0.85);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    transition: color 0.2s;
    border-bottom: 1px solid rgba(255, 255, 255, 0.4);
    padding-bottom: 2px;
}
.btn-ghost-lg:hover { color: white; border-bottom-color: white; }

/* ── Mockup Chat Card — replica of real app ─────────────────────────────── */
.hero-mockup { display: flex; justify-content: center; }
.mockup-card {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 360px;
    box-shadow: 0 24px 64px rgba(0, 0, 0, 0.22);
    overflow: hidden;
}

/* Header — matches card-header in app */
.mockup-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: #ffffff;
    border-bottom: 1px solid #eff2f5;
    min-height: 60px;
}
.mockup-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}
.mockup-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
}
.mockup-online-badge {
    position: absolute;
    bottom: 1px;
    right: 1px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #50cd89;
    border: 2px solid white;
}
.mockup-contact-info { flex: 1; min-width: 0; }
.mockup-name {
    font-size: 0.88rem;
    font-weight: 700;
    color: #181c32;
    line-height: 1.3;
}
.mockup-status {
    font-size: 0.72rem;
    color: #50cd89;
    font-weight: 600;
}
.mockup-header-actions {
    display: flex;
    gap: 4px;
    flex-shrink: 0;
}
.mockup-action-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a1a5b7;
    cursor: default;
}

/* Chat Body — matches chat-body-custom bg */
.mockup-body {
    padding: 14px 14px 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #f9f9f9;
}

/* Bubbles */
.bubble { display: flex; align-items: flex-end; gap: 7px; }
.bubble-right { flex-direction: row-reverse; }

.bubble-avatar-sm {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

/* Receiver — white, rounded-bottom-start-0, shadow-sm */
.bubble-recv {
    background: #ffffff;
    color: #3f4254;
    border-radius: 8px 8px 8px 0;
    padding: 9px 12px;
    font-size: 0.8rem;
    line-height: 1.45;
    max-width: 200px;
    box-shadow: 0 1px 4px rgba(0,0,0,0.07);
}

/* Sender — bg-primary (#667eea), rounded-bottom-end-0 */
.bubble-send {
    background: #667eea;
    color: white;
    border-radius: 8px 8px 0 8px;
    padding: 9px 12px;
    font-size: 0.8rem;
    line-height: 1.45;
    max-width: 200px;
    box-shadow: 0 2px 8px rgba(102,126,234,0.35);
}

/* Footer — matches card-footer in app */
.mockup-footer {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: #ffffff;
    border-top: 1px solid #eff2f5;
}
.mockup-attach-btn {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a1a5b7;
    flex-shrink: 0;
    cursor: default;
}
.mockup-input-field {
    flex: 1;
    background: #f5f8fa;
    border-radius: 8px;
    padding: 8px 12px;
    display: flex;
    align-items: center;
}
.mockup-typed {
    font-size: 0.78rem;
    color: #3f4254;
}
.mockup-cursor {
    font-size: 0.85rem;
    color: #667eea;
    font-weight: 300;
    margin-left: 1px;
    transition: opacity 0.1s;
}
.mockup-cursor.cursor-hidden {
    opacity: 0;
}
.mockup-send-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #667eea;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 3px 10px rgba(102,126,234,0.4);
}

/* ── Features Section ───────────────────────────────────────────────────── */
.features-section {
    position: relative;
    z-index: 1;
    padding: 0 2rem 4rem;
}
.features-wrap {
    max-width: 960px;
    margin: 0 auto;
}
.features-header {
    text-align: center;
    margin-bottom: 2.5rem;
}
.features-badge {
    display: inline-block;
    background: rgba(255,255,255,0.15);
    border: 1px solid rgba(255,255,255,0.3);
    color: white;
    font-size: 0.78rem;
    font-weight: 600;
    padding: 5px 14px;
    border-radius: 20px;
    margin-bottom: 1rem;
    backdrop-filter: blur(8px);
}
.features-title {
    font-size: clamp(1.5rem, 3vw, 2.1rem);
    font-weight: 800;
    color: white;
    margin: 0 0 0.75rem;
    line-height: 1.25;
}
.features-subtitle {
    color: rgba(255,255,255,0.75);
    font-size: 0.95rem;
    margin: 0;
}

/* Cards grid — 2x2 */
.feat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
.feat-card {
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.18);
    border-radius: 20px;
    backdrop-filter: blur(12px);
    overflow: hidden;
    transition: transform 0.25s, background 0.25s;
    cursor: default;
}
.feat-card:hover {
    transform: translateY(-4px);
    background: rgba(255,255,255,0.16);
}
.feat-card-inner {
    padding: 24px 22px 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.feat-icon-wrap {
    width: 50px;
    height: 50px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
    margin-bottom: 2px;
}
.feat-icon-blue   { background: rgba(102,126,234,0.5); }
.feat-icon-green  { background: rgba(80,205,137,0.4); }
.feat-icon-purple { background: rgba(163,105,234,0.45); }
.feat-icon-orange { background: rgba(255,159,67,0.45); }

.feat-title {
    font-size: 1rem;
    font-weight: 700;
    color: white;
    margin: 0;
}
.feat-desc {
    font-size: 0.82rem;
    color: rgba(255,255,255,0.78);
    line-height: 1.6;
    margin: 0;
}
.feat-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.feat-tag {
    font-size: 0.7rem;
    font-weight: 600;
    color: rgba(255,255,255,0.85);
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    padding: 3px 10px;
    border-radius: 20px;
}

/* ── Feature Illustrations ──────────────────────────────────────────────── */
.feat-illustration {
    margin-top: 6px;
    border-radius: 12px;
    background: rgba(0,0,0,0.15);
    padding: 12px;
    backdrop-filter: blur(6px);
}

/* Chat illustration */
.chat-illustration { display: flex; flex-direction: column; gap: 6px; }
.fi-bubble { font-size: 0.72rem; padding: 7px 10px; border-radius: 10px; max-width: 80%; line-height: 1.4; }
.fi-bubble-recv { background: rgba(255,255,255,0.9); color: #3f4254; border-radius: 10px 10px 10px 2px; align-self: flex-start; }
.fi-bubble-send { background: #667eea; color: white; border-radius: 10px 10px 2px 10px; align-self: flex-end; }

/* Call illustration */
/* .call-illustration */
.fi-call-card {
    background: rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 10px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}
.fi-call-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #50cd89, #1bc5bd);
    flex-shrink: 0;
}
.fi-call-info { flex: 1; }
.fi-call-name { font-size: 0.78rem; font-weight: 700; color: white; }
.fi-call-status { font-size: 0.68rem; color: #50cd89; font-weight: 600; }
.fi-call-btns { display: flex; gap: 6px; }
.fi-call-btn {
    width: 30px; height: 30px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: white;
}
.fi-btn-red { background: #f64e60; }
.fi-btn-gray { background: rgba(255,255,255,0.2); }

/* AI illustration */
.ai-illustration { display: flex; flex-direction: column; gap: 7px; }
.fi-ai-row { display: flex; align-items: flex-end; gap: 6px; }
.fi-ai-row-right { flex-direction: row-reverse; }
.fi-ai-avatar {
    width: 24px; height: 24px;
    border-radius: 50%;
    background: linear-gradient(135deg, #a369ea, #667eea);
    color: white; font-size: 0.55rem; font-weight: 700;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.fi-ai-bubble {
    background: rgba(255,255,255,0.9);
    color: #3f4254;
    font-size: 0.7rem;
    padding: 6px 9px;
    border-radius: 8px 8px 8px 2px;
    line-height: 1.4;
    max-width: 85%;
}
.fi-user-bubble {
    background: #667eea;
    color: white;
    font-size: 0.7rem;
    padding: 6px 9px;
    border-radius: 8px 8px 2px 8px;
    max-width: 85%;
}
/* AI typing dots */
.fi-ai-typing {
    display: flex;
    align-items: center;
    gap: 3px;
    padding: 9px 12px;
}
.fi-ai-typing span {
    width: 5px; height: 5px;
    border-radius: 50%;
    background: #667eea;
    animation: aiDot 1.2s ease-in-out infinite;
}
.fi-ai-typing span:nth-child(2) { animation-delay: 0.2s; }
.fi-ai-typing span:nth-child(3) { animation-delay: 0.4s; }
@keyframes aiDot {
    0%, 80%, 100% { transform: scale(0.7); opacity: 0.4; }
    40%            { transform: scale(1);   opacity: 1; }
}

/* Kanban illustration */
/* .todo-illustration */
.fi-kanban {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6px;
}
.fi-col { display: flex; flex-direction: column; gap: 4px; }
.fi-col-head {
    font-size: 0.62rem;
    font-weight: 700;
    padding: 3px 6px;
    border-radius: 6px;
    text-align: center;
    margin-bottom: 2px;
}
.fi-col-todo     { background: rgba(255,255,255,0.15); color: white; }
.fi-col-progress { background: rgba(102,126,234,0.4);  color: white; }
.fi-col-done     { background: rgba(80,205,137,0.3);   color: #d1fae5; }
.fi-task {
    background: rgba(255,255,255,0.9);
    color: #3f4254;
    font-size: 0.62rem;
    padding: 5px 7px;
    border-radius: 6px;
    line-height: 1.3;
}
.fi-task-active { background: rgba(102,126,234,0.85); color: white; }
.fi-task-done   { background: rgba(80,205,137,0.25); color: #d1fae5; }

/* ── Footer ─────────────────────────────────────────────────────────────── */
.landing-footer {
    position: relative;
    z-index: 1;
    text-align: center;
    padding: 1.5rem 2.5rem 2.5rem;
}
.landing-footer p {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.95rem;
    margin-bottom: 1rem;
}

/* ── Responsive ─────────────────────────────────────────────────────────── */
@media (max-width: 768px) {
    .landing-nav { padding: 1rem 1.25rem; }
    .nav-appname { font-size: 1rem; }
    .hero { padding: 1.5rem 1.25rem 2rem; }
    .features-section { padding: 0 1.25rem 2.5rem; }
    .feat-grid { grid-template-columns: 1fr; }
    .landing-footer { padding: 1rem 1.25rem 2rem; }
}
@media (max-width: 480px) {
    .hero-cta { flex-direction: column; }
    .btn-nav-ghost { display: none; }
}
</style>