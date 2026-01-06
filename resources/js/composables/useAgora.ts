import { ref } from "vue";
import AgoraRTC, {
    type IAgoraRTCClient,
    type ICameraVideoTrack,
    type IMicrophoneAudioTrack,
    type IAgoraRTCRemoteUser
} from "agora-rtc-sdk-ng";
import { useCallStore } from "@/stores/callStore";

export const useAgora = () => {
    // State
    const client = ref<IAgoraRTCClient | null>(null);
    const localVideoTrack = ref<ICameraVideoTrack | null>(null);
    const localAudioTrack = ref<IMicrophoneAudioTrack | null>(null);
    const remoteUsers = ref<IAgoraRTCRemoteUser[]>([]);
    const isJoined = ref(false);
    
    // State baru untuk track status
    const isAudioEnabled = ref(true);
    const isVideoEnabled = ref(true);
    const remoteAudioTracks = ref(new Map());
    const remoteVideoTracks = ref(new Map());

    // Inisialisasi Klien Agora
    const initializeClient = () => {
        if (!client.value) {
            client.value = AgoraRTC.createClient({
                mode: 'rtc',
                codec: 'vp8'
            });
            setupEventListeners();
        }
    };

    // Setup event listeners
    const setupEventListeners = () => {
        if (!client.value) return;

        const callStore = useCallStore();

        // Remote user published
        client.value.on('user-published', async (user, mediaType) => {
            console.log('Remote user diterbitkan:', user.uid, mediaType);

            await client.value!.subscribe(user, mediaType);

            if (!remoteUsers.value.find(u => u.uid === user.uid)) {
                remoteUsers.value.push(user);
            }

            // Simpan tracks ke Map
            if (mediaType === 'video') {
                remoteVideoTracks.value.set(user.uid, user.videoTrack);
            }
            if (mediaType === 'audio') {
                remoteAudioTracks.value.set(user.uid, user.audioTrack);
            }

            callStore.addRemoteUser(user.uid as number);
        });

        // Remote user unpublished
        client.value.on('user-unpublished', (user, mediaType) => {
            console.log('Remote user belum diterbitkan:', user.uid, mediaType);
            
            // Hapus tracks dari Map
            if (mediaType === 'video') {
                remoteVideoTracks.value.delete(user.uid);
            }
            if (mediaType === 'audio') {
                remoteAudioTracks.value.delete(user.uid);
            }
        });

        // Remote user left
        client.value.on('user-left', (user) => {
            console.log('Remote user keluar:', user.uid);

            remoteUsers.value = remoteUsers.value.filter(u => u.uid !== user.uid);
            callStore.removeRemoteUser(user.uid as number);
        });
    };

    // Membuat dan menerbitkan track lokal
    const createAndPublishLocalTracks = async () => {
        try {
            localVideoTrack.value = await AgoraRTC.createCameraVideoTrack({
                encoderConfig: '480p_1',
            });

            localAudioTrack.value = await AgoraRTC.createMicrophoneAudioTrack();


            // @ts-ignore
            await client.value!.publish([
                localVideoTrack.value!,
                localAudioTrack.value!
            ]);

            console.log('Track lokal berhasil diterbitkan');
        } catch (error) {
            console.error('Gagal membuat dan menerbitkan track lokal:', error);
            throw error;
        }
    };

    // Join channel
    const joinChannel = async (
        channel: string,
        token: string,
        uid: number
    ) => {
        try {
            if (!client.value) {
                initializeClient();
            }

            const appId = import.meta.env.VITE_APP_AGORA_ID || '';

            await client.value!.join(appId, channel, token, uid);
            isJoined.value = true;

            await createAndPublishLocalTracks();

            console.log('Berhasil join channel:', channel);
        } catch (error) {
            console.error('Gagal join channel:', error);
            throw error;
        }
    };

    // Meninggalkan channel
    const leaveChannel = async () => {
        try {
            if (localVideoTrack.value) {
                localVideoTrack.value.close();
                localVideoTrack.value = null;
            }

            if (localAudioTrack.value) {
                localAudioTrack.value.close();
                localAudioTrack.value = null;
            }

            if (client.value && isJoined.value) {
                await client.value.leave();
                isJoined.value = false;
            }

            remoteUsers.value = [];
            remoteAudioTracks.value.clear();
            remoteVideoTracks.value.clear();

            const callStore = useCallStore();
            callStore.clearRemoteUsers();

            console.log('Berhasil meninggalkan channel');
        } catch (error) {
            console.error('Gagal meninggalkan channel:', error);
        }
    };

    // Toggle audio (Mute / Unmute)
    const toggleAudio = async (muted: boolean) => {
        if (localAudioTrack.value) {
            await localAudioTrack.value.setEnabled(!muted);
            isAudioEnabled.value = !muted;
        }
    };

    // Toggle video (Turn on / Turn off)
    const toggleVideo = async (muted: boolean) => {
        if (localVideoTrack.value) {
            await localVideoTrack.value.setEnabled(!muted);
            isVideoEnabled.value = !muted;
        }
    };

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
    };
};