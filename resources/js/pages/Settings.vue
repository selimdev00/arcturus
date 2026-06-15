<script setup>
import { ref, onUnmounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import api from '../lib/api';

const router = useRouter();
const url = ref('');
const loading = ref(false);
const error = ref('');
const org = ref(null);
const cached = ref(false);
let poll = null;

const parsing = computed(() => org.value?.parseStatus === 'pending');

function stopPoll() {
    if (poll) { clearInterval(poll); poll = null; }
}

// Refresh the summary while the background full parse runs.
function startPoll(id) {
    stopPoll();
    poll = setInterval(async () => {
        try {
            const { data } = await api.get(`/organizations/${id}`);
            org.value = data.data;
            if (org.value?.parseStatus !== 'pending') stopPoll();
        } catch { stopPoll(); }
    }, 3000);
}

async function onSubmit() {
    error.value = '';
    cached.value = false;
    loading.value = true;
    try {
        const { data } = await api.post('/settings/source', { url: url.value });
        org.value = data.data;
        cached.value = data.cached === true;
        if (org.value?.parseStatus === 'pending') startPoll(org.value.id);
    } catch (e) {
        error.value = e?.response?.data?.message
            || (e?.response?.status === 422 ? 'Ссылка не похожа на карточку организации Яндекс.Карт' : 'Не удалось сохранить');
    } finally {
        loading.value = false;
    }
}

onUnmounted(stopPoll);
</script>

<template>
    <div>
        <h1 class="mb-1 text-xl font-semibold">Добавить организацию</h1>
        <p class="mb-6 text-sm text-slate-500">Вставьте ссылку на карточку организации в Яндекс.Картах.</p>

        <form class="space-y-3" @submit.prevent="onSubmit">
            <input v-model="url" type="url" required placeholder="https://yandex.ru/maps/org/.../12345/"
                class="w-full rounded-lg border border-slate-300 px-3 py-2 outline-none focus:border-slate-900" />
            <p v-if="error" class="text-sm text-red-600">{{ error }}</p>
            <button type="submit" :disabled="loading"
                class="rounded-lg bg-slate-900 px-4 py-2 font-medium text-white disabled:opacity-50">
                {{ loading ? 'Сохраняем и тянем данные…' : 'Сохранить' }}
            </button>
        </form>

        <div v-if="org" class="mt-8 rounded-xl border border-slate-200 bg-white p-5">
            <div class="flex items-baseline justify-between">
                <h2 class="font-medium">{{ org.name || 'Организация' }}</h2>
                <span class="text-2xl font-semibold">{{ org.averageRating ?? '—' }}</span>
            </div>
            <div class="mt-2 flex gap-6 text-sm text-slate-600">
                <span>{{ org.ratingsCount ?? '—' }} оценок</span>
                <span>{{ org.reviewsCount ?? '—' }} отзывов</span>
                <span>в базе: {{ org.reviewsStored ?? 0 }}</span>
            </div>
            <p class="mt-3 text-sm" :class="org.parseStatus === 'ok' ? 'text-emerald-600' : 'text-amber-600'">
                <span v-if="parsing">Идёт загрузка отзывов… <span class="text-slate-400">(обновляется автоматически)</span></span>
                <span v-else-if="cached">Загружено из кэша <span class="text-slate-400">(парсили ранее)</span></span>
                <span v-else>Статус парсинга: {{ org.parseStatusLabel || org.parseStatus }}</span>
            </p>
            <div class="mt-4 flex gap-4">
                <button class="text-sm font-medium text-slate-900 underline"
                    @click="router.push({ name: 'reviews', params: { id: org.id } })">
                    Перейти к отзывам →
                </button>
                <RouterLink to="/organizations" class="text-sm text-slate-500 underline">все организации</RouterLink>
            </div>
        </div>
    </div>
</template>
