import { ref, markRaw, shallowRef } from "vue";
import AgoraRTC, {
    type IAgoraRTCClient,
    type ICameraVideoTrack,
    type IMicrophoneAudioTrack,
    type IAgoraRTCRemoteUser
} from "agora-rtc-sdk-ng";
import { useCallStore } from "@/stores/callStore";
import { useAuthStore } from "@/stores/authStore";

// State
const client = shallowRef<IAgoraRTCClient | null>(null);
const localVideoTrack = ref<ICameraVideoTrack | null>(null);
const localAudioTrack = ref<IMicrophoneAudioTrack | null>(null);
const remoteUsers = ref<IAgoraRTCRemoteUser[]>([]);
const isJoined = ref(false);
    
// State baru untuk track status
const isAudioEnabled = ref(true);
const isVideoEnabled = ref(true);
const remoteAudioTracks = ref(new Map());
const remoteVideoTracks = ref(new Map());
const hasMultipleCameras = ref(false);

export const useAgora = () => {
    // Inisialisasi Klien Agora
    const initializeClient = () => {
        if (!client.value) {
            client.value = markRaw(AgoraRTC.createClient({
                mode: 'rtc',
                codec: 'vp8'
            }));

            // Log level untuk debugging
            AgoraRTC.setLogLevel(0); // 0: debug, 1: info, 2: warning, 3: error, 4: none

            if (typeof window !== 'undefined') {
                (window as any).AgoraRTC = AgoraRTC;
                (window as any).__agoraClient = client.value;
                (window as any).__remoteVideoTracks = remoteVideoTracks;
                (window as any).__remoteAudioTracks = remoteAudioTracks;
                (window as any).__remoteUsers = remoteUsers;
            }
            
            setupEventListeners();
        }
    };

    // Setup event listeners
    const setupEventListeners = () => {
        if (!client.value) return;

        const callStore = useCallStore();
        const authStore = useAuthStore();

        // Remote user published
        client.value.on('user-published', async (user, mediaType) => {
            console.log('📡 [user-published] Remote user:', user.uid, 'mediaType:', mediaType);

            const currentUserId = authStore.user?.id;

            console.log('Debug Subscribe:');
            console.log('📦 Remote UID:', user.uid, '(type:', typeof user.uid, ')');
            console.log('📦 Current User ID:', currentUserId, '(type:', typeof currentUserId, ')');

            // Validasi UID (skip kalau remote UID sama dengan current user)
            if (String(user.uid) === String(currentUserId)) {
                console.warn('⚠️ Mengabaikan publish UID dari diri sendiri');
                return;
            }

            // Subscribe langsung tanpa retry
            try {
                console.log(`🔔 Subscribe ke remote user ${user.uid} untuk ${mediaType}`);
                
                await client.value!.subscribe(user, mediaType);
                console.log(`✅ Berhasil subscribe ${mediaType} dari user ${user.uid}`);
                
                // Simpan track ke Map
                if (mediaType === 'video') {
                    const videoTrack = user.videoTrack;
                    if (videoTrack) {
                        remoteVideoTracks.value.set(String(user.uid), markRaw(videoTrack));
                        console.log('📹 Remote video track disimpan');
                    } else {
                        console.warn('⚠️ Video track tidak ditemukan');
                    }
                } else if (mediaType === 'audio') {
                    const audioTrack = user.audioTrack;
                    if (audioTrack) {
                        remoteAudioTracks.value.set(String(user.uid), markRaw(audioTrack));
                        audioTrack.play();
                        console.log('🎤 Remote audio track disimpan dan playing');
                    } else {
                        console.warn('⚠️ Audio track tidak ditemukan');
                    }
                }

                // Update state
                const safeUser = markRaw(user);
                const existingUserIndex = remoteUsers.value.findIndex(u => String(u.uid) === String(user.uid));

                if (existingUserIndex !== -1) {
                    console.log('🔄 Update existing user tracks');
                    remoteUsers.value[existingUserIndex] = safeUser;
                } else {
                    console.log('➕ Menambahkan remote user baru ke array');
                    remoteUsers.value.push(safeUser);
                }

                callStore.addRemoteUser(user.uid as number);
                
            } catch (error: any) {
                console.error(`❌ Gagal subscribe ${mediaType} dari user ${user.uid}:`, error.message);
                console.error(`❌ Subscribe error:`, error);
                console.error('Error code:', error.code);
            }
        });

        // Remote user unpublished
        client.value.on('user-unpublished', (user, mediaType) => {
            console.log('Remote user belum diterbitkan:', user.uid, mediaType);
            
            // Hapus tracks dari Map
            if (mediaType === 'video') {
                remoteVideoTracks.value.delete(String(user.uid));
            }
            if (mediaType === 'audio') {
                remoteAudioTracks.value.delete(String(user.uid));
            }
        });

        // Remote user left
        client.value.on('user-left', (user) => {
            console.log('Remote user keluar:', user.uid);

            remoteUsers.value = remoteUsers.value.filter(u => String(u.uid) !== String(user.uid));
            callStore.removeRemoteUser(user.uid as number);
        });
    };

    // Membuat dan menerbitkan track lokal
    const createAndPublishLocalTracks = async () => {
        console.log('🎬 Membuat track lokal...');

        const tracksToPublish: any[] = [];

        // Video track
        try {
            console.log('📹 Membuat video track (Resolusi 480p)');
            localVideoTrack.value = markRaw(await AgoraRTC.createCameraVideoTrack({
                encoderConfig: '480p_1',
            }));
            console.log('✅ Video track lokal dibuat dengan resolusi 480p:', localVideoTrack.value);
            tracksToPublish.push(localVideoTrack.value);

            const cameras = await AgoraRTC.getCameras();
            hasMultipleCameras.value = cameras.length > 1;
        } catch (error: any) {
            console.error('❌ Gagal membuat video track lokal:');
            console.error('❌ Error name:', error.name);
            console.error('❌ Error message:', error.message);
            

            // Fallback resolusi otomatis
            try {
                console.log('🔄 Fallback: Reoslusi otomatis...');
                localVideoTrack.value = markRaw(await AgoraRTC.createCameraVideoTrack());
                console.log('✅ Resolusi otomatis video track berhasil');
                tracksToPublish.push(localVideoTrack.value);

                const cameras = await AgoraRTC.getCameras();
                hasMultipleCameras.value = cameras.length > 1;
            } catch (fallbackError: any) {
                console.error('❌ Resolusi otomatis gagal:', fallbackError.message);

                // Alert user friendly
                if (error.name === 'NotAllowedError') {
                    alert('❌ Akses kamera ditolak! Pastikan akses kamera diizinkan di browser Anda.');
                } else if (error.name === 'NotFoundError') {
                    alert('❌ Kamera mikrofon tidak ditemukan!');
                } else {
                    alert(`❌ Gagal mengakses kamera: ${error.message}`);
                }

                console.warn('⚠️ Melanjutkan tanpa video');
            }
        }

        // Audio track
        try {
            console.log('🎤 Membuat audio track...');

            // List semua audio input devices
            const devices = await AgoraRTC.getDevices();
            const audioDevices = devices.filter(d => d.kind === 'audioinput');

            console.log('🎤 Device audio tersedia:', audioDevices.length);
            audioDevices.forEach((devices, index) => {
                console.log(`   ${index + 1}. ${devices.label || 'Unknown Device'} (${devices.deviceId})`);
            })

            localAudioTrack.value = markRaw(await AgoraRTC.createMicrophoneAudioTrack({
                AEC: true,  // Acoustic Echo Cancellation
                ANS: true,  // Audio Noise Suppression
                AGC: true,  // Automatic Gain Control
            }));
            
            console.log('✅ Audio track lokal dibuat:', localAudioTrack.value);
            console.log('📦 Device info:', localAudioTrack.value.getMediaStreamTrack().label);
            console.log('📦 Device ID:', localAudioTrack.value.getMediaStreamTrack().getSettings().deviceId);

            tracksToPublish.push(localAudioTrack.value);
        } catch (error: any) {
            console.error('❌ Gagal membuat audio track lokal:', error.name, error.message);
            if (error.name === 'NotAllowedError') {
                alert('❌ Akses mikrofon ditolak! Pastikan akses mikrofon diizinkan di browser Anda.');
            } else if (error.name === 'NotFoundError') {
                alert('❌ Mikrofon tidak ditemukan! Pastikan mikrofon terhubung.');
            } else if (error.name === 'NotReadableError') {
                alert('❌ Mikrofon sedang digunakan aplikasi lain atau ada error hardware.');
            } else {
                alert(`❌ Gagal mengakses mikrofon: ${error.message}`);
            }
        }

        // Publish hanya track yang berhasil dibuat
        if (tracksToPublish.length === 0) {
            throw new Error('Tidak ada track lokal yang berhasil dibuat');
        }

        console.log(`📤 Menerbitkan ${tracksToPublish.length} track lokal...`);
        await client.value!.publish(tracksToPublish);
        console.log('✅ Track lokal berhasil diterbitkan');
        console.log('📹 Video:', localVideoTrack.value ? '✅ 480p' : '❌ Gagal');
        console.log('🎤 Audio:', localAudioTrack.value ? '✅' : '❌ Gagal');
    };

    // Join channel
    const joinChannel = async (
        channel: string,
        token: string,
        uid: number
    ) => {
        const callStore = useCallStore();
        try {
            if (!client.value) {
                initializeClient();
            }

            const appId = import.meta.env.VITE_AGORA_APP_ID;
            const uidNumber =typeof uid === 'string' ? parseInt(uid) : uid;

            console.log('📦 Agora App Id:', appId);
            console.log('📦 Channel:', channel);
            console.log('📦 UID:', uid, '(type:', typeof uid, ')');
            console.log('📦 Token:', token ? token.substring(0, 20) + '...' : 'missing');
            console.log('📦 Is Joined:', isJoined.value);
            console.log('📦 hasJoinedAgora:', callStore.hasJoinedAgora);

            if (!appId) {
                console.error('❌ Agora App ID tidak ditemukan di env!');
                throw new Error('Agora App ID tidak dikonfigurasi');
            }

            // Validasi UID
            if (isNaN(uidNumber) || uidNumber <= 0) {
                console.error('❌ UID tidak valid:', uid);
                throw new Error('UID harus lebih besar dari 0');
            }

            // Cek aapakah sudah join channel
            if (isJoined.value || callStore.hasJoinedAgora) {
                console.warn('⚠️ Sudah berganung ke channel, leave dulu sebelum join lagi');
                if (!localVideoTrack.value || !localAudioTrack.value) {
                    console.warn('⚠️ Track lokal belum dibuat, membuat dan menerbitkan track lokal...');
                    await createAndPublishLocalTracks();
                } else {
                    const publishedTracks = client.value?.localTracks || [];
                    if (publishedTracks.length === 0) {
                        console.warn('⚠️ Track ada tapi belum diterbitkan');

                        const tracksToPublish: any[] = [];
                        if (localVideoTrack.value) tracksToPublish.push(localVideoTrack.value);
                        if (localAudioTrack.value) tracksToPublish.push(localAudioTrack.value);

                        if (tracksToPublish.length > 0) {
                            await client.value!.publish(tracksToPublish);
                            console.log(`✅ ${tracksToPublish.length} Track berhasil diterbitkan`);
                        }
                    } else {
                        console.log('✅ Track sudah ada dan diterbitkan sebelumnya');
                    }
                }

                console.log('✅ Track teverifikasi dan diterbitkan');
                return;
            }

            console.log('🚪 Bergabung ke channel Agora...');
            await client.value!.join(appId, channel, token, uidNumber);
            isJoined.value = true;
            callStore.setHasJoinedAgora(true);
            console.log('✅ Berhasil bergabung ke channel sebagai UID:', uid);

            console.log('🎬 Membuat dan menerbitkan track lokal...');
            await createAndPublishLocalTracks();
            console.log('✅ Track lokal dibuat dan diterbitkan');

            console.log('Berhasil join channel:', channel);
        } catch (error: any) {
            console.error('❌ Gagal join channel:', error);
            console.error('❌ Error code:', error.code);
            console.error('❌ Error message:', error.message);

            // Handle Invalid Operation error
            if (error.code === 'INVALID_OPERATION') {
                console.warn('⚠️ Client sudah join, set flag hasJoinedAgora = true');
                isJoined.value = true;
                callStore.setHasJoinedAgora(true);
                return;
            }

            // Handle UID conflict
            if (error.code === 'UID_CONFLICT') {
                console.error('❌ Konflik UID: UID sudah dipakai user lain!');
                console.error('📦 UID yang konflik:', uid);
                console.error('📦 Channel:', channel);
            }

            isJoined.value = false;
            throw error;
        }
    };

    // Meninggalkan channel
    const leaveChannel = async () => {
        console.log('👋 useAgora: leaveChannel dipanggil');
        const callStore = useCallStore();

        try {
            // Stop dan tutup track lokal dulu
            if (localVideoTrack.value) {
                console.log('📹 Menutup track video lokal');
                localVideoTrack.value.stop();
                localVideoTrack.value.close();
                localVideoTrack.value = null;
            }

            if (localAudioTrack.value) {
                console.log('🎤 Menutup track audio lokal');
                localAudioTrack.value.stop();
                localAudioTrack.value.close();
                localAudioTrack.value = null;
                console.log('✅ Track audio lokal ditutup');
            }

            // Unpublish kalau masih published
            if (client.value && client.value.localTracks.length > 0) {
                console.log('📤 Unpublish track lokal dari channel...');
                await client.value.unpublish();
                console.log('✅ Track lokal berhasil di-unpublish');
            }

            // Meninggalkan channel
            if (client.value && isJoined.value) {
                console.log('🚪 Meninggalkan channel Agora...');
                await client.value.leave();
                isJoined.value = false;
                callStore.setHasJoinedAgora(false);
                console.log('✅ Berhasil meninggalkan channel Agora');
            }

            // Membersihkan remote users dan tracks
            remoteUsers.value = [];
            remoteAudioTracks.value.clear();
            remoteVideoTracks.value.clear();
            console.log('🧹 Membersihkan remote users dan tracks');

            // Reset state
            isAudioEnabled.value = true;
            isVideoEnabled.value = true;
            console.log('✅ Reset status audio dan video lokal');

            console.log('✅ useAgora: Channel ditinggalkan dan state dibersihkan');

            callStore.clearRemoteUsers();

        } catch (error) {
            console.error('Gagal meninggalkan channel:', error);
            isJoined.value = false;

            // Cleanup secara paksa
            localAudioTrack.value = null;
            localVideoTrack.value = null;
            remoteUsers.value = [];
            remoteAudioTracks.value.clear();
            remoteVideoTracks.value.clear();
            isJoined.value = false;
            isAudioEnabled.value = false;
            isVideoEnabled.value = false;
            callStore.setHasJoinedAgora(false);
        }
    };

    // Toggle audio (Mute / Unmute)
    const toggleAudio = async () => {
        if (localAudioTrack.value) {
            const newState = !isAudioEnabled.value;
            await localAudioTrack.value.setEnabled(newState);
            isAudioEnabled.value = newState;
        }
    };

    // Toggle video (Turn on / Turn off)
    const toggleVideo = async () => {
        if (localVideoTrack.value) {
            const newState = !isVideoEnabled.value;
            await localVideoTrack.value.setEnabled(newState);
            isVideoEnabled.value = newState;
        }
    };

    let currentVideoDeviceIndex = 0;

    // Switch kamera depan belakang
    const switchCamera = async () => {
        if (!localVideoTrack.value) return;

        try {
            // Ambil SEMUA device kamera yang ada
            const cameras = await AgoraRTC.getCameras();
            if (cameras.length <= 1) {
                console.warn('⚠️ Hanya ada satu kamera, tidak bisa switch');
                return;
            }

            // Dapatkan ID kamera yang sedang dipakai SEKARANG
            const currentTrack = localVideoTrack.value.getMediaStreamTrack();
            const currentDeviceId = currentTrack.getSettings().deviceId;

            // Cari index kamera yang sedang dipakai di dalam array 'cameras'
            let currentIndex = cameras.findIndex(c => c.deviceId === currentDeviceId);
            
            // Kalau karena alasan tertentu nggak ketemu, anggap aja index 0 (kamera pertama)
            if (currentIndex === -1) currentIndex = 0;

            // Putar ke index kamera berikutnya (kalau udh mentok belakang, balik ke 0)
            const nextIndex = (currentIndex + 1) % cameras.length;
            const nextCamera = cameras[nextIndex];

            if (!nextCamera || !nextCamera.deviceId) {
                console.error("❌ Kamera selanjutnya gagal dibaca.");
                return;
            }

            // Mulai proses ganti source kamera
            await localVideoTrack.value.setDevice(nextCamera.deviceId);
            console.log('🔄 Kamera berhasil diganti ke:', nextCamera.label || 'Kamera tanpa nama');
            
        } catch (error) {
            console.error('❌ Gagal switch kamera:', error);
        }
    }

    return {
        // State
        client,
        localVideoTrack,
        localAudioTrack,
        remoteUsers,
        isJoined,
        isAudioEnabled,
        isVideoEnabled,
        remoteAudioTracks,
        remoteVideoTracks,

        // Actions
        joinChannel,
        leaveChannel,
        toggleAudio,
        toggleVideo,
        switchCamera,
        hasMultipleCameras,
    };
};