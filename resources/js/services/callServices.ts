import axios from "@/libs/axios";
import type { CallType } from "@/types/call";

// Generate Agora RTC token untuk backend
export const generateToken = async (channelName: string, uid: number) => {
    const response = await axios.post('/call/token', {
        channel_name: channelName,
        uid: uid
    });
    return response.data;
};

// Invite user untuk video / voice call
export const inviteCall = async (receiverId: number, type: CallType) => {
    const response = await axios.post('/call/invite', {
        callee_id: receiverId,
        call_type: type
    });
    return response.data;
};

// Menjawab panggilan masuk
export const answerCall = async (callId: number) => {
    const response = await axios.post('/call/answer', {
        call_id: callId
    });
    return response.data;
};

// Menolak panggilan masuk
export const rejectCall = async (callId: number) => {
    const response = await axios.post('/call/reject', {
        call_id: callId
    });
    return response.data;
};

// cancel panggilan
export const cancelCall = async (callId: number) => {
    const response = await axios.post('/call/cancel', {
        call_id: callId
    });
    return response.data;
};

// Mengakhiri panggilan
export const endCall = async (callId: number) => {
    const response = await axios.post('/call/end', {
        call_id: callId
    });
    return response.data;
};

// Mendapatkan histori panggilan
export const getCallHistory = async (page: number = 1) => {
    const response = await axios.get('/call/history', {
        params: { page }
    });
    return response.data;
};