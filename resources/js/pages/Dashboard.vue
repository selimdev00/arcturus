<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../lib/api';
import Stars from '../components/Stars.vue';

const router = useRouter();
const orgs = ref([]);
const loadingList = ref(true);

const url = ref('');
const submitting = ref(false);
const error = ref('');
const justAdded = ref(null);
const cached = ref(false);

let poll = null;
function stopPoll() { if (poll) { clearInterval(poll); poll = null; } }

const stats = computed(() => {
    const n = orgs.value.length;
    const stored = orgs.value.reduce((s, o) => s + (o.reviewsStored || 0), 0);
    const rated = orgs.value.filter((o) => o.averageRating != null);
    const avg = rated.length ? rated.reduce((s, o) => s + o.averageRating, 0) / rated.length : null;
    return { n, stored, avg: avg != null ? avg.toFixed(1) : '—' };
});
const anyParsing = computed(() => orgs.value.some((o) => o.parseStatus === 'pending'));

async function loadList() {
    try {
        const { data } = await api.get('/organizations');
        orgs.value = data.data;
        if (anyParsing.value) { if (!poll) poll = setInterval(loadList, 3000); }
        else stopPoll();
    } catch { orgs.value = []; }
    finally { loadingList.value = false; }
}

async function onSubmit() {
    error.value = '';
    cached.value = false;
    submitting.value = true;
    try {
        const { data } = await api.post('/settings/source', { url: url.value });
        justAdded.value = data.data;
        cached.value = data.cached === true;
        url.value = '';
        await loadList();
        if (!poll && data.data?.parseStatus === 'pending') poll = setInterval(loadList, 3000);
    } catch (e) {
        error.value = e?.response?.data?.message
            || (e?.response?.status === 422 ? 'Ссылка не похожа на карточку организации Яндекс.Карт' : 'Не удалось сохранить');
    } finally {
        submitting.value = false;
    }
}

function open(o) { router.push({ name: 'reviews', params: { id: o.id } }); }
function fmtDate(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); }
    catch { return ''; }
}

onMounted(loadList);
onUnmounted(stopPoll);
</script>

<template>
    <div class="space-y-10">
        <!-- Add -->
        <section>
            <h1 class="text-[1.6rem] font-bold tracking-tight">Отзывы Яндекс.Карт</h1>
            <p class="mt-1 text-sm text-[var(--text-muted)]">
                Вставьте ссылку на карточку организации — соберём рейтинг и все доступные отзывы.
            </p>
            <form class="mt-5 flex flex-col gap-2 sm:flex-row" @submit.prevent="onSubmit">
                <input v-model="url" type="url" required placeholder="https://yandex.ru/maps/org/.../12345/"
                    class="flex-1 rounded-lg border border-[var(--border-strong)] bg-[var(--surface-raised)] px-3.5 py-2.5 text-sm outline-none transition-colors placeholder:text-[oklch(0.55_0.012_70_/_0.7)] focus:border-[var(--accent)]" />
                <button type="submit" :disabled="submitting"
                    class="rounded-lg bg-[var(--ink)] px-5 py-2.5 text-sm font-medium text-white transition-opacity disabled:opacity-50">
                    {{ submitting ? 'Собираем…' : 'Собрать отзывы' }}
                </button>
            </form>
            <p v-if="error" class="mt-2 text-sm text-[var(--danger)]">{{ error }}</p>
            <p v-else-if="cached && justAdded" class="mt-2 text-sm text-[var(--text-muted)]">
                «{{ justAdded.name }}» уже в базе — показали из кэша.
            </p>
        </section>

        <!-- Stats strip -->
        <section v-if="orgs.length" class="flex flex-wrap items-end gap-x-10 gap-y-4 border-y border-[var(--border)] py-5">
            <div>
                <div class="text-2xl font-semibold tnum">{{ stats.n }}</div>
                <div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">организаций</div>
            </div>
            <div>
                <div class="text-2xl font-semibold tnum">{{ stats.stored.toLocaleString('ru-RU') }}</div>
                <div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">отзывов в базе</div>
            </div>
            <div class="flex items-end gap-2">
                <div>
                    <div class="text-2xl font-semibold tnum">{{ stats.avg }}</div>
                    <div class="text-xs uppercase tracking-wide text-[var(--text-muted)]">средний рейтинг</div>
                </div>
            </div>
            <div v-if="anyParsing" class="flex items-center gap-2 text-sm text-[var(--accent-ink)]">
                <svg viewBox="0 0 24 24" class="h-4 w-4 pulse-star" aria-hidden="true"><path d="M12 3c.4 3.6 2.4 5.6 6 6-3.6.4-5.6 2.4-6 6-.4-3.6-2.4-5.6-6-6 3.6-.4 5.6-2.4 6-6Z" fill="var(--accent)" /></svg>
                идёт сбор…
            </div>
        </section>

        <!-- Latest organizations -->
        <section>
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-[var(--text-muted)]">Организации</h2>

            <p v-if="loadingList" class="py-10 text-center text-[var(--text-muted)]">Загрузка…</p>
            <p v-else-if="!orgs.length" class="rounded-xl border border-dashed border-[var(--border-strong)] py-10 text-center text-[var(--text-muted)]">
                Пока пусто. Вставьте ссылку выше, чтобы добавить первую.
            </p>

            <ul v-else class="space-y-2.5">
                <li v-for="(o, i) in orgs" :key="o.id" class="rise" :style="{ animationDelay: i * 40 + 'ms' }">
                    <button
                        class="group flex w-full items-center justify-between gap-4 rounded-xl border border-[var(--border)] bg-[var(--surface-raised)] p-4 text-left transition-all hover:border-[var(--border-strong)] hover:shadow-[0_1px_12px_oklch(0.74_0.15_65_/_0.08)]"
                        @click="open(o)">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <Stars :value="Math.round(o.averageRating || 0)" />
                                <h3 class="truncate font-medium">{{ o.name || 'Организация' }}</h3>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-[0.8rem] text-[var(--text-muted)]">
                                <span class="tnum">{{ (o.ratingsCount ?? 0).toLocaleString('ru-RU') }} оценок</span>
                                <span class="tnum">{{ (o.reviewsCount ?? 0).toLocaleString('ru-RU') }} отзывов</span>
                                <span class="tnum">в базе {{ o.reviewsStored ?? 0 }}</span>
                                <span v-if="o.parsedAt">обновлено {{ fmtDate(o.parsedAt) }}</span>
                            </div>
                            <p v-if="o.parseStatus === 'pending'" class="mt-1 text-[0.78rem] text-[var(--accent-ink)]">идёт сбор отзывов…</p>
                            <p v-else-if="o.parseStatus !== 'ok'" class="mt-1 text-[0.78rem] text-[var(--danger)]">{{ o.parseStatusLabel }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            <span class="text-xl font-semibold tnum">{{ o.averageRating ?? '—' }}</span>
                            <span class="text-[var(--text-muted)] transition-transform group-hover:translate-x-0.5">→</span>
                        </div>
                    </button>
                </li>
            </ul>
        </section>
    </div>
</template>
