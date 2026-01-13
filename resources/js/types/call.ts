export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    photo?: string | null;
    profile_photo_url?: string;
}

export type CallType = 'video' | 'voice';

export type CallStatus = 'ringing' | 'ongoing' | 'ended' | 'rejected' | 'missed' | 'cancelled';

export interface Call {
    id: number;
    type: CallType;
    caller: User;
    receiver: User;
    status: CallStatus;
    token: string;
    channel: string;
    startedAt?: Date;
    endedAt?: Date;
}

// Interface untuk data call dari backend
export interface PersonalCall {
    id: number;
    caller_id: number;
    callee_id: number;
    channel_name: string;
    call_type: CallType;
    status: CallStatus;
    answered_at: string | null;
    ended_at: string | null;
    duration: number | null;
    ended_by: number | null;
    created_at: string;
    updated_at: string;
    caller?: User;
    callee?: User;
}

// Response dari API /call/invite
export interface InviteCallResponse {
    message: string;
    call_id: number;
    channel_name: string;
    token: string;
    call: PersonalCall;
}

// Response dari API /call/answer, /call/reject, /call/cancel, /call/end
export interface CallActionResponse {
    message: string;
    call: PersonalCall;
}

// Call event untuk histori
export interface CallEvent {
    id: number;
    call_id: number;
    user_id: number;
    event_type: 'invited' | 'answered' | 'rejected' | 'cancelled' | 'ended' | 'missed';
    created_at: string;
    updated_at: string;
}

// Response dari API /call/history
export interface CallHistoryResponse {
    current_page: number;
    data: PersonalCall[];
    total: number;
    per_page: number;
    last_page: number;
}