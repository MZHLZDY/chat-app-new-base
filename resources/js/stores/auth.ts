import { ref, computed } from "vue";
import { defineStore } from "pinia";
import ApiService from "@/core/services/ApiService";
import JwtService from "@/core/services/JwtService";

export interface User {
    id?: number;
    uuid?: string;
    name: string;
    email: string;
    phone?: string;
    photo?: string | null;
    profile_photo_url?: string;
    background_image_path?: string | null;
    password?: string;
    permission: Array<string>;
    role?: {
        id?: number;
        name: string;
        full_name: string;
        guard_name?: string;
    } | null;
    email_verified_at?: string | null;
}

export const useAuthStore = defineStore("auth", () => {
    const error = ref<null | string>(null);
    const user = ref<User>({
        name: '',
        email: '',
        permission: [],
        photo: null,
        profile_photo_url: '',
        role: null
    } as User);
    const isAuthenticated = ref(false);

    // Computed property untuk safe access
    const userPhotoUrl = computed(() => {
        if (!user.value || !user.value.name) {
            return 'https://ui-avatars.com/api/?name=User&color=7F9CF5&background=EBF4FF';
        }
        
        return user.value.profile_photo_url || 
               user.value.photo || 
               `https://ui-avatars.com/api/?name=${encodeURIComponent(user.value.name)}&color=7F9CF5&background=EBF4FF`;
    });

    const userName = computed(() => {
        return user.value?.name || 'User';
    });

    function setAuth(authUser: User, token = "") {
        isAuthenticated.value = true;
        
        // Ensure all required properties exist with safe defaults
        user.value = {
            ...authUser,
            photo: authUser.photo || null,
            profile_photo_url: authUser.profile_photo_url || 
                `https://ui-avatars.com/api/?name=${encodeURIComponent(authUser.name || 'User')}&color=7F9CF5&background=EBF4FF`,
            permission: authUser.permission || [],
            role: authUser.role || null,
            background_image_path: authUser.background_image_path || null,
        };
        
        error.value = null;

        if (token) {
            JwtService.saveToken(token);
        }
    }

    function purgeAuth() {
        isAuthenticated.value = false;
        user.value = {
            name: '',
            email: '',
            permission: [],
            photo: null,
            profile_photo_url: '',
            role: null
        } as User;
        error.value = null;
        JwtService.destroyToken();
    }

    async function login(credentials: { email: string; password: string }) {
        return ApiService.post("auth/login", credentials)
            .then(({ data }) => {
                // Handle response structure
                const userData = data.user || data.data?.user;
                const token = data.token || data.data?.token || data.data?.access_token;
                
                if (!userData) {
                    throw new Error('Invalid response: user data not found');
                }
                
                setAuth(userData, token);
                return data;
            })
            .catch(({ response }) => {
                error.value = response?.data?.message || 'Login failed';
                throw error.value;
            });
    }

    async function logout() {
        if (JwtService.getToken()) {
            ApiService.setHeader();
            try {
                await ApiService.delete("auth/logout");
            } catch (err) {
                console.error('Logout error:', err);
            } finally {
                purgeAuth();
            }
        } else {
            purgeAuth();
        }
    }

    async function register(credentials: any) {
        return ApiService.post("auth/register", credentials)
            .then(({ data }) => {
                // Registration might not return token immediately (email verification required)
                if (data.user && data.token) {
                    setAuth(data.user, data.token);
                }
                return data;
            })
            .catch(({ response }) => {
                error.value = response?.data?.message || 'Registration failed';
                throw error.value;
            });
    }

    async function forgotPassword(email: string) {
        return ApiService.post("auth/forgot_password", { email })
            .then(() => {
                error.value = null;
            })
            .catch(({ response }) => {
                error.value = response?.data?.message || 'Password reset failed';
                throw error.value;
            });
    }

    async function verifyAuth() {
        if (JwtService.getToken()) {
            ApiService.setHeader();
            try {
                const { data } = await ApiService.get("auth/me");
                
                // Handle different response structures
                const userData = data.user || data.data || data;
                
                if (userData && userData.email) {
                    setAuth(userData);
                } else {
                    purgeAuth();
                }
            } catch ({ response }: any) {
                error.value = response?.data?.message || 'Auth verification failed';
                purgeAuth();
            }
        } else {
            purgeAuth();
        }
    }

    return {
        error,
        user,
        isAuthenticated,
        userPhotoUrl,
        userName,
        login,
        logout,
        register,
        forgotPassword,
        verifyAuth,
        setAuth,
        purgeAuth,
    };
});