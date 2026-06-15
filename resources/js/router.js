import { createRouter, createWebHistory } from 'vue-router';
import { auth, fetchUser } from './lib/auth';

import Login from './pages/Login.vue';
import Settings from './pages/Settings.vue';
import Reviews from './pages/Reviews.vue';

const routes = [
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/', name: 'settings', component: Settings, meta: { auth: true } },
    { path: '/reviews', name: 'reviews', component: Reviews, meta: { auth: true } },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    if (!auth.ready) await fetchUser();
    if (to.meta.auth && !auth.user) return { name: 'login' };
    if (to.meta.guest && auth.user) return { name: 'settings' };
    return true;
});

export default router;
