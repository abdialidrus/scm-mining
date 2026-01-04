/**
 * Bootstrap axios configuration for API requests
 */
import axios from 'axios';

// Configure axios to include credentials (cookies) with requests
axios.defaults.withCredentials = true;

// Set default headers
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['Accept'] = 'application/json';

// Handle CSRF token from meta tag
const token = document.head.querySelector<HTMLMetaElement>(
    'meta[name="csrf-token"]',
);
if (token) {
    axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
}

export default axios;
