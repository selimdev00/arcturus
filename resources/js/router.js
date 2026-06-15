import { createRouter, createWebHistory } from 'vue-router';
import { auth, fetchUser } from './lib/auth';

import Login from './pages/Login.vue';
import Dashboard from './pages/Dashboard.vue';
import Reviews from './pages/Reviews.vue';

const routes = [
    { path: '/login', name: 'login', component: Login, meta: { guest: true } },
    { path: '/', name: 'dashboard', component: Dashboard, meta: { auth: true } },
    { path: '/organizations/:id', name: 'reviews', component: Reviews, props: true, meta: { auth: true } },
    { path: '/organizations', redirect: '/' },
    { path: '/reviews', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    if (!auth.ready) await fetchUser();
    if (to.meta.auth && !auth.user) return { name: 'login' };
    if (to.meta.guest && auth.user) return { name: 'dashboard' };
    return true;
});

export default router;
