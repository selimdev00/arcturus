<script setup>
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { auth, logout } from './lib/auth';

const router = useRouter();

async function onLogout() {
    await logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="min-h-full flex flex-col">
        <header v-if="auth.user" class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
                <nav class="flex gap-4 text-sm font-medium">
                    <RouterLink to="/" class="text-slate-600 hover:text-slate-900" active-class="text-slate-900">Настройки</RouterLink>
                    <RouterLink to="/reviews" class="text-slate-600 hover:text-slate-900" active-class="text-slate-900">Отзывы</RouterLink>
                </nav>
                <button class="text-sm text-slate-500 hover:text-slate-900" @click="onLogout">Выйти</button>
            </div>
        </header>
        <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
            <RouterView />
        </main>
    </div>
</template>
