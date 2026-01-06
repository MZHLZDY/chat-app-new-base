import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import type { User } from '@/types/call';

export const useAuthStore = defineStore('auth', () => {
    const user = ref<User | null>(null);
    const token = ref<string | null>(null);

    const isAuthenticated = computed(() => !!user.value && !!token.value);

    const setUser = (userData: User) => {
        user.value = userData;
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