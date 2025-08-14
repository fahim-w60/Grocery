/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Add the CSRF token to all axios requests
const token = document.head.querySelector('meta[name="csrf-token"]');
if (token) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
} else {
    console.error('CSRF token not found');
}

// Add authorization header if user is authenticated
const userMeta = document.head.querySelector('meta[name="user-id"]');
const authToken = localStorage.getItem('auth_token');

if (authToken) {
    window.axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
}

// Initialize WebSocket connection when needed
const initWebSocket = () => {
    if (authToken) {
        import('./services/socketService').then(({ default: socketService }) => {
            socketService.connect(authToken);
            
            // Store socket service in window for easy access
            window.socketService = socketService;
            
            // Listen for new messages
            socketService.on('message', (message) => {
                // You can dispatch a custom event or handle the message here
                const event = new CustomEvent('new-message', { detail: message });
                window.dispatchEvent(event);
            });
            
            // Listen for typing indicators
            socketService.on('typing', (data) => {
                const event = new CustomEvent('user-typing', { detail: data });
                window.dispatchEvent(event);
            });
            
            // Listen for user online/offline status
            socketService.on('user-online', (data) => {
                const event = new CustomEvent('user-online', { detail: data });
                window.dispatchEvent(event);
            });
            
            socketService.on('user-offline', (data) => {
                const event = new CustomEvent('user-offline', { detail: data });
                window.dispatchEvent(event);
            });
        }).catch(error => {
            console.error('Error initializing WebSocket:', error);
        });
    }
};

// Initialize WebSocket when the DOM is fully loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWebSocket);
} else {
    initWebSocket();
}
