export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
}

export type CallType = 'video' | 'voice';

export type CallStatus = 'ringing' | 'ongoing' | 'ended' | 'rejected' | 'missed';

export interface Call {
    id: string;
    type: CallType;
    caller: User;
    receiver: User;
    status: CallStatus;
    token: string;
    channel: string;
    startedAt?: Date;
    endedAt?: Date;
}