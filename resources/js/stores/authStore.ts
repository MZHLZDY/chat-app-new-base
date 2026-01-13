import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/types/call';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(null);

    const isAuthenticated = computed(() => !!user.value && !!token.value);

    const setUser = (userData: any) => {
        user.value = {
            id: userData.id,
            name: userData.name,
            email: userData.email,
            avatar: userData.avatar || userData.photo || userData.profile_photo_url || undefined,
            photo: userData.photo || undefined,
            profile_photo_url: userData.profile_photo_url || undefined,
        };
        console.log('✅ authStore.user set:', user.value);
    };

    const setToken = (authToken: string) => {
        token.value = authToken;
    };

    const clearAuth = () => {
        user.value = null;
        token.value = null;
    };

    return {
        user,
        token,
        isAuthenticated,
        setUser,
        setToken,
        clearAuth,
    };
});