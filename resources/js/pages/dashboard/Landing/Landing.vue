<template>
    <div class="welcome-page">
        <!-- Background Animated -->
        <div class="welcome-background">
            <div class="gradient-overlay"></div>
            <div class="animated-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="welcome-content">
            
            <!-- Hero Section -->
            <section class="hero-section">
                <div class="container">
                    
                    <!-- Logo & Brand -->
                    <div class="brand-header animate-fade-in">
                        <img :src="logo" class="app-logo" alt="Logo" />
                        <h1 class="app-name">ChatApp</h1>
                    </div>

                    <!-- Hero Title -->
                    <div class="hero-text animate-slide-up" style="animation-delay: 0.2s">
                        <h1 class="hero-title">
                            Komunikasi Lebih Mudah,<br>
                            <span class="gradient-text">Lebih Cepat, Lebih Aman</span>
                        </h1>
                        <p class="hero-subtitle">
                            Terhubung dengan siapa saja, kapan saja, di mana saja dengan enkripsi end-to-end yang aman
                        </p>
                    </div>

                    <!-- CTA Buttons -->
                    <div class="cta-buttons animate-slide-up" style="animation-delay: 0.4s">
                        <router-link to="/sign-in" class="btn btn-primary">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                <polyline points="10 17 15 12 10 7"></polyline>
                                <line x1="15" y1="12" x2="3" y2="12"></line>
                            </svg>
                            Masuk Sekarang
                        </router-link>
                        <router-link to="/sign-up" class="btn btn-outline">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            Daftar Gratis
                        </router-link>
                    </div>

                    <!-- Hero Image/Illustration -->
                    <div class="hero-image animate-fade-in" style="animation-delay: 0.6s">
                        <div class="chat-preview">
                            <div class="chat-bubble bubble-1">
                                <div class="bubble-avatar">
                                    <div class="avatar-circle bg-purple"></div>
                                </div>
                                <div class="bubble-content">
                                    <div class="bubble-text">Halo! Selamat datang di ChatApp 👋</div>
                                    <div class="bubble-time">10:30</div>
                                </div>
                            </div>
                            <div class="chat-bubble bubble-2">
                                <div class="bubble-content">
                                    <div class="bubble-text">Terima kasih! Aplikasi ini luar biasa! 🎉</div>
                                    <div class="bubble-time">10:32</div>
                                </div>
                                <div class="bubble-avatar">
                                    <div class="avatar-circle bg-blue"></div>
                                </div>
                            </div>
                            <div class="chat-bubble bubble-3">
                                <div class="bubble-avatar">
                                    <div class="avatar-circle bg-green"></div>
                                </div>
                                <div class="bubble-content">
                                    <div class="bubble-text">Komunikasi jadi lebih mudah! ✨</div>
                                    <div class="bubble-time">10:35</div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Features Section -->
            <section class="features-section">
                <div class="container">
                    
                    <div class="section-header animate-fade-in">
                        <h2 class="section-title">Fitur Unggulan</h2>
                        <p class="section-subtitle">Semua yang Anda butuhkan untuk komunikasi yang efektif</p>
                    </div>

                    <div class="features-grid">
                        <!-- Feature 1 -->
                        <div class="feature-card animate-slide-up" style="animation-delay: 0.1s">
                            <div class="feature-icon icon-purple">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <h3 class="feature-title">Chat Real-Time</h3>
                            <p class="feature-description">Kirim dan terima pesan secara instan tanpa delay</p>
                        </div>

                        <!-- Feature 2 -->
                        <div class="feature-card animate-slide-up" style="animation-delay: 0.2s">
                            <div class="feature-icon icon-blue">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                </svg>
                            </div>
                            <h3 class="feature-title">Enkripsi End-to-End</h3>
                            <p class="feature-description">Keamanan tingkat tinggi untuk privasi Anda</p>
                        </div>

                        <!-- Feature 3 -->
                        <div class="feature-card animate-slide-up" style="animation-delay: 0.3s">
                            <div class="feature-icon icon-green">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                            </div>
                            <h3 class="feature-title">Chat Grup</h3>
                            <p class="feature-description">Buat grup hingga 1000 anggota</p>
                        </div>

                        <!-- Feature 4 -->
                        <div class="feature-card animate-slide-up" style="animation-delay: 0.4s">
                            <div class="feature-icon icon-orange">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="2" y="2" width="20" height="8" rx="2" ry="2"></rect>
                                    <rect x="2" y="14" width="20" height="8" rx="2" ry="2"></rect>
                                    <line x1="6" y1="6" x2="6.01" y2="6"></line>
                                    <line x1="6" y1="18" x2="6.01" y2="18"></line>
                                </svg>
                            </div>
                            <h3 class="feature-title">Multi-Platform</h3>
                            <p class="feature-description">Akses dari web, mobile, dan desktop</p>
                        </div>

                        <!-- Feature 5 -->
                        <div class="feature-card animate-slide-up" style="animation-delay: 0.5s">
                            <div class="feature-icon icon-pink">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                </svg>
                            </div>
                            <h3 class="feature-title">Voice & Video Call</h3>
                            <p class="feature-description">Panggilan suara dan video berkualitas HD</p>
                        </div>

                        <!-- Feature 6 -->
                        <div class="feature-card animate-slide-up" style="animation-delay: 0.6s">
                            <div class="feature-icon icon-cyan">
                                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path>
                                    <polyline points="13 2 13 9 20 9"></polyline>
                                </svg>
                            </div>
                            <h3 class="feature-title">Share Files</h3>
                            <p class="feature-description">Kirim dokumen, foto, dan video dengan mudah</p>
                        </div>
                    </div>

                </div>
            </section>

            <!-- Stats Section -->
            <section class="stats-section">
                <div class="container">
                    <div class="stats-grid">
                        <div class="stat-item animate-scale">
                            <div class="stat-number">1M+</div>
                            <div class="stat-label">Pengguna Aktif</div>
                        </div>
                        <div class="stat-item animate-scale" style="animation-delay: 0.1s">
                            <div class="stat-number">5M+</div>
                            <div class="stat-label">Pesan Terkirim</div>
                        </div>
                        <div class="stat-item animate-scale" style="animation-delay: 0.2s">
                            <div class="stat-number">99.9%</div>
                            <div class="stat-label">Uptime</div>
                        </div>
                        <div class="stat-item animate-scale" style="animation-delay: 0.3s">
                            <div class="stat-number">24/7</div>
                            <div class="stat-label">Support</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA Section -->
            <section class="cta-section">
                <div class="container">
                    <div class="cta-card animate-fade-in">
                        <h2 class="cta-title">Siap Memulai?</h2>
                        <p class="cta-text">Bergabunglah dengan jutaan pengguna yang telah mempercayai ChatApp</p>
                        <div class="cta-buttons">
                            <router-link to="/sign-up" class="btn btn-white">
                                Daftar Sekarang
                            </router-link>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Footer -->
            <footer class="welcome-footer">
                <div class="container">
                    <div class="footer-content">
                        <div class="footer-left">
                            <p>&copy; 2024 ChatApp. All rights reserved.</p>
                        </div>
                        <div class="footer-right">
                            <a href="#" class="footer-link">Privacy</a>
                            <a href="#" class="footer-link">Terms</a>
                            <a href="#" class="footer-link">Support</a>
                        </div>
                    </div>
                </div>
            </footer>

        </div>
    </div>
</template>

<script lang="ts">
import { defineComponent, ref } from "vue";
import { getAssetPath } from "@/core/helpers/assets";

export default defineComponent({
    name: "WelcomePage",
    setup() {
        const logo = ref(getAssetPath("media/logos/default-dark.svg"));

        return {
            logo
        };
    },
});
</script>

<style lang="scss" scoped>
/* --- ANIMATIONS --- */
@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes slideUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes scaleIn {
    from {
        opacity: 0;
        transform: scale(0.95);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes float {
    0%, 100% {
        transform: translate(0, 0) rotate(0deg);
    }
    33% {
        transform: translate(30px, -30px) rotate(5deg);
    }
    66% {
        transform: translate(-20px, 20px) rotate(-5deg);
    }
}

@keyframes bubbleFloat {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.animate-fade-in {
    animation: fadeIn 1s ease-out forwards;
    opacity: 0;
}

.animate-slide-up {
    animation: slideUp 0.8s ease-out forwards;
    opacity: 0;
}

.animate-scale {
    animation: scaleIn 0.6s ease-out forwards;
    opacity: 0;
}

/* --- LAYOUT --- */
.welcome-page {
    position: relative;
    min-height: 100vh;
    overflow-x: hidden;
}

.welcome-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.gradient-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.1) 0%, transparent 60%);
}

.animated-shapes {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    overflow: hidden;
}

.shape {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.08);
    animation: float 20s ease-in-out infinite;
}

.shape-1 {
    width: 400px;
    height: 400px;
    top: -100px;
    left: -100px;
}

.shape-2 {
    width: 500px;
    height: 500px;
    top: 40%;
    right: -150px;
    animation-delay: -5s;
    animation-duration: 25s;
}

.shape-3 {
    width: 300px;
    height: 300px;
    bottom: 20%;
    left: 10%;
    animation-delay: -10s;
    animation-duration: 30s;
}

.shape-4 {
    width: 250px;
    height: 250px;
    top: 60%;
    left: -80px;
    animation-delay: -15s;
    animation-duration: 22s;
}

.welcome-content {
    position: relative;
    z-index: 1;
}

.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 2rem;
}

/* --- HERO SECTION --- */
.hero-section {
    min-height: 100vh;
    display: flex;
    align-items: center;
    padding: 4rem 0;
    text-align: center;
}

.brand-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 3rem;
}

.app-logo {
    height: 60px;
    filter: brightness(0) invert(1);
}

.app-name {
    font-size: 2rem;
    font-weight: 800;
    color: white;
    margin: 0;
}

.hero-text {
    margin-bottom: 3rem;
}

.hero-title {
    font-size: 3.5rem;
    font-weight: 900;
    color: white;
    margin-bottom: 1.5rem;
    line-height: 1.2;
}

.gradient-text {
    background: linear-gradient(90deg, #ffd89b 0%, #19547b 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.25rem;
    color: rgba(255, 255, 255, 0.9);
    max-width: 600px;
    margin: 0 auto;
    line-height: 1.6;
}

.cta-buttons {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
    margin-bottom: 4rem;
}

.btn {
    padding: 1rem 2rem;
    border-radius: 12px;
    font-size: 1rem;
    font-weight: 700;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
}

.btn-primary {
    background: white;
    color: #667eea;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);

    &:hover {
        transform: translateY(-3px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3);
    }
}

.btn-outline {
    background: transparent;
    color: white;
    border: 2px solid white;

    &:hover {
        background: white;
        color: #667eea;
        transform: translateY(-3px);
    }
}

.btn-white {
    background: white;
    color: #667eea;

    &:hover {
        transform: translateY(-3px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }
}

/* --- CHAT PREVIEW --- */
.hero-image {
    max-width: 600px;
    margin: 0 auto;
}

.chat-preview {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    padding: 2rem;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.chat-bubble {
    display: flex;
    gap: 0.75rem;
    animation: bubbleFloat 3s ease-in-out infinite;

    &.bubble-2 {
        flex-direction: row-reverse;
        animation-delay: -1s;
    }

    &.bubble-3 {
        animation-delay: -2s;
    }
}

.bubble-avatar {
    flex-shrink: 0;
}

.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    
    &.bg-purple {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    &.bg-blue {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    &.bg-green {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
}

.bubble-content {
    background: white;
    border-radius: 16px;
    padding: 0.85rem 1.25rem;
    max-width: 70%;
}

.chat-bubble.bubble-2 .bubble-content {
    background: #667eea;
    color: white;
}

.bubble-text {
    font-size: 0.95rem;
    margin-bottom: 0.25rem;
}

.bubble-time {
    font-size: 0.75rem;
    opacity: 0.7;
    text-align: right;
}

/* --- FEATURES SECTION --- */
.features-section {
    padding: 6rem 0;
    background: white;
}

.section-header {
    text-align: center;
    margin-bottom: 4rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 800;
    color: #1a202c;
    margin-bottom: 1rem;
}

.section-subtitle {
    font-size: 1.1rem;
    color: #718096;
}

.features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
}

.feature-card {
    background: white;
    padding: 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;

    &:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
    }
}

.feature-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    
    &.icon-purple {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
    
    &.icon-blue {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }
    
    &.icon-green {
        background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }
    
    &.icon-orange {
        background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }
    
    &.icon-pink {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }
    
    &.icon-cyan {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    svg {
        color: white;
    }
}

.feature-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #1a202c;
    margin-bottom: 0.75rem;
}

.feature-description {
    font-size: 0.95rem;
    color: #718096;
    line-height: 1.6;
}

/* --- STATS SECTION --- */
.stats-section {
    padding: 4rem 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 2rem;
    text-align: center;
}

.stat-item {
    color: white;
}

.stat-number {
    font-size: 3rem;
    font-weight: 900;
    margin-bottom: 0.5rem;
}

.stat-label {
    font-size: 1rem;
    opacity: 0.9;
}

/* --- CTA SECTION --- */
.cta-section {
    padding: 6rem 0;
    background: white;
}

.cta-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 24px;
    padding: 4rem 2rem;
    text-align: center;
    color: white;
}

.cta-title {
    font-size: 2.5rem;
    font-weight: 800;
    margin-bottom: 1rem;
}

.cta-text {
    font-size: 1.1rem;
    opacity: 0.95;
    margin-bottom: 2rem;
}

/* --- FOOTER --- */
.welcome-footer {
    padding: 2rem 0;
    background: white;
    border-top: 1px solid #e2e8f0;
}

.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: #718096;
    font-size: 0.9rem;
}

.footer-right {
    display: flex;
    gap: 2rem;
}

.footer-link {
    color: #718096;
    text-decoration: none;
    transition: color 0.2s;

    &:hover {
        color: #667eea;
    }
}

/* --- RESPONSIVE --- */
@media (max-width: 992px) {
    .hero-title {
        font-size: 2.5rem;
    }

    .hero-subtitle {
        font-size: 1.1rem;
    }

    .section-title {
        font-size: 2rem;
    }

    .features-grid {
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .container {
        padding: 0 1.5rem;
    }

    .hero-title {
        font-size: 2rem;
    }

    .hero-subtitle {
        font-size: 1rem;
    }

    .cta-buttons {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }

    .chat-preview {
        padding: 1.5rem;
    }

    .features-grid {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }

    .stat-number {
        font-size: 2.5rem;
    }

    .cta-card {
        padding: 3rem 1.5rem;
    }

    .cta-title {
        font-size: 2rem;
    }

    .footer-content {
        flex-direction: column;
        gap: 1rem;
        text-align: center;
    }

    .footer-right {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 576px) {
    .brand-header {
        flex-direction: column;
        gap: 0.5rem;
    }

    .app-logo {
        height: 50px;
    }

    .app-name {
        font-size: 1.5rem;
    }

    .hero-title {
        font-size: 1.75rem;
    }

    .section-title {
        font-size: 1.75rem;
    }

    .shape-1,
    .shape-2,
    .shape-3,
    .shape-4 {
        width: 200px;
        height: 200px;
    }
}
</style>