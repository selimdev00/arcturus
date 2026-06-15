import axios from 'axios';

// Same-origin SPA: Sanctum cookie auth. Axios sends the XSRF-TOKEN cookie as the
// X-XSRF-TOKEN header automatically once /sanctum/csrf-cookie has been hit.
const api = axios.create({
    baseURL: '/api',
    withCredentials: true,
    withXSRFToken: true,
    headers: { Accept: 'application/json' },
});

let csrfReady = false;

export async function ensureCsrf() {
    if (csrfReady) return;
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
    csrfReady = true;
}

export default api;
