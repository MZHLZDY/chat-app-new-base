import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
    wsHost: import.meta.env.VITE_PUSHER_HOST ? import.meta.env.VITE_PUSHER_HOST : `ws-${import.meta.env.VITE_PUSHER_APP_CLUSTER}.pusher.com`,
    wsPort: import.meta.env.VITE_PUSHER_PORT ?? 80,
    wssPort: import.meta.env.VITE_PUSHER_PORT ?? 443,
    forceTLS: true,
    enabledTransports: ['ws', 'wss'],

    authorizer: (channel, options) => {
        return {
            authorize: (socketId, callback) => {
                axios.post('http://localhost:8000/broadcasting/auth', { 
                    socket_id: socketId,
                    channel_name: channel.name
                }, {
                    withCredentials: true 
                })
                .then(response => {
                    callback(false, response.data);
                })
                .catch(error => {
                    console.error("Auth Error:", error);
                    callback(true, error);
                });
            }
        };
    },
});