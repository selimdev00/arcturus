<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../lib/auth';

const router = useRouter();
const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function onSubmit() {
    error.value = '';
    loading.value = true;
    try {
        await login(email.value, password.value);
        router.push({ name: 'settings' });
    } catch (e) {
        error.value = e?.response?.status === 422 || e?.response?.status === 401
            ? 'Неверный логин или пароль'
            : 'Не удалось войти, попробуйте ещё раз';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto mt-12 max-w-sm">
        <h1 class="mb-6 text-xl font-semibold">Вход</h1>
        <form class="space-y-4" @submit.prevent="onSubmit">
            <div>
                <label class="mb-1 block text-sm text-slate-600">Email</label>
                <input v-model="email" type="email" required autocomplete="username"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900" />
            </div>
            <div>
                <label class="mb-1 block text-sm text-slate-600">Пароль</label>
                <input v-model="password" type="password" required autocomplete="current-password"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900" />
            </div>
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button type="submit" :disabled="loading"
                class="w-full rounded-lg bg-slate-900 py-2 font-medium text-white disabled:opacity-50">
                {{ loading ? 'Входим…' : 'Войти' }}
            </button>
        </form>
    </div>
</template>
