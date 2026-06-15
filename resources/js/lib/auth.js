import { reactive } from 'vue';
import api, { ensureCsrf } from './api';

export const auth = reactive({
    user: null,
    ready: false,
});

export async function fetchUser() {
    try {
        const { data } = await api.get('/me');
        auth.user = data;
    } catch {
        auth.user = null;
    } finally {
        auth.ready = true;
    }
}

export async function login(email, password) {
    await ensureCsrf();
    await api.post('/login', { email, password });
    await fetchUser();
}

export async function logout() {
    await api.post('/logout');
    auth.user = null;
}
