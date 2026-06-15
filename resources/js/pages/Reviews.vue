<script setup>
import { ref, onMounted } from 'vue';
import api from '../lib/api';

const org = ref(null);
const reviews = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const reparsing = ref(false);

async function loadOrg() {
    try {
        const { data } = await api.get('/organization');
        org.value = data;
    } catch { org.value = null; }
}

async function loadPage(p) {
    error.value = '';
    loading.value = true;
    try {
        const { data } = await api.get('/organization/reviews', { params: { page: p } });
        reviews.value = data.data;
        page.value = data.meta.current_page;
        lastPage.value = data.meta.last_page;
        total.value = data.meta.total;
    } catch (e) {
        error.value = 'Не удалось загрузить отзывы';
    } finally {
        loading.value = false;
    }
}

async function reparse() {
    reparsing.value = true;
    try {
        await api.post('/organization/reparse');
        await loadOrg();
    } finally {
        reparsing.value = false;
    }
}

function fmtDate(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }); }
    catch { return iso; }
}

onMounted(async () => { await loadOrg(); await loadPage(1); });
</script>

<template>
    <div>
        <div v-if="org" class="mb-6 flex items-center justify-between rounded-xl border border-slate-200 bg-white p-5">
            <div>
                <h1 class="font-medium">{{ org.name || 'Организация' }}</h1>
                <div class="mt-1 flex gap-4 text-sm text-slate-600">
                    <span>{{ org.ratingsCount ?? '—' }} оценок</span>
                    <span>{{ org.reviewsCount ?? '—' }} отзывов</span>
                </div>
            </div>
            <div class="text-right">
                <div class="text-3xl font-semibold">{{ org.averageRating ?? '—' }}</div>
                <button class="mt-1 text-xs text-slate-500 underline disabled:opacity-50"
                    :disabled="reparsing" @click="reparse">
                    {{ reparsing ? 'обновляем…' : 'обновить' }}
                </button>
            </div>
        </div>

        <p v-if="org && org.parseStatus && org.parseStatus !== 'ok'" class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">
            {{ org.parseStatusLabel || org.parseStatus }}
        </p>

        <div v-if="loading" class="py-12 text-center text-slate-400">Загрузка…</div>
        <p v-else-if="error" class="py-12 text-center text-red-600">{{ error }}</p>
        <p v-else-if="!reviews.length" class="py-12 text-center text-slate-400">Отзывов пока нет</p>

        <ul v-else class="space-y-4">
            <li v-for="r in reviews" :key="r.id" class="rounded-xl border border-slate-200 bg-white p-4">
                <div class="flex items-center justify-between">
                    <span class="font-medium">{{ r.author }}</span>
                    <span class="text-sm text-amber-500">{{ '★'.repeat(r.rating) }}<span class="text-slate-200">{{ '★'.repeat(5 - r.rating) }}</span></span>
                </div>
                <div class="text-xs text-slate-400">{{ fmtDate(r.date) }}</div>
                <p v-if="r.text" class="mt-2 whitespace-pre-line text-sm text-slate-700">{{ r.text }}</p>
            </li>
        </ul>

        <div v-if="lastPage > 1" class="mt-6 flex items-center justify-center gap-3 text-sm">
            <button class="rounded-lg border border-slate-300 px-3 py-1 disabled:opacity-40"
                :disabled="page <= 1 || loading" @click="loadPage(page - 1)">Назад</button>
            <span class="text-slate-500">{{ page }} / {{ lastPage }}</span>
            <button class="rounded-lg border border-slate-300 px-3 py-1 disabled:opacity-40"
                :disabled="page >= lastPage || loading" @click="loadPage(page + 1)">Вперёд</button>
        </div>
    </div>
</template>
