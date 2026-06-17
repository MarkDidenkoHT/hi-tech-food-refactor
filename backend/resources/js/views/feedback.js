import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { getSelectedRestaurantId } from '../restaurant-context';

export async function render(container, ctx) {
    const restaurants = ctx.user.restaurants ?? [];

    if (restaurants.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Вам не назначен ресторан.</p>';

        return;
    }

    const restaurantId = getSelectedRestaurantId(restaurants);

    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div id="content"></div>
        </div>
    `;

    const content = container.querySelector('#content');

    let feedbacks = [];
    let orderReviews = [];
    const collapsed = { order_reviews: true };

    async function load() {
        content.innerHTML = loadingHtml();

        try {
            const data = await apiFetch(`/feedback?restaurant_id=${restaurantId}`);

            if (!data.available) {
                content.innerHTML = '<p class="text-sm text-hint">Для этого ресторана отзывы недоступны.</p>';

                return;
            }

            feedbacks = data.feedbacks;
            orderReviews = data.order_reviews;
            renderContent();
        } catch (error) {
            content.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderContent() {
        content.innerHTML = `
            <div class="flex flex-col gap-4">
                <div class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-sm sm:flex-row">
                    <input type="text" id="filter-text" placeholder="Поиск по тексту" autocomplete="off"
                        class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <select id="filter-rating" class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                        <option value="">Любой рейтинг</option>
                        <option value="5">★★★★★ и выше</option>
                        <option value="4">★★★★ и выше</option>
                        <option value="3">★★★ и выше</option>
                        <option value="2">★★ и выше</option>
                        <option value="1">★ и выше</option>
                    </select>
                </div>
                <div class="flex flex-col gap-3">
                    <button type="button" data-toggle="feedbacks"
                        class="flex items-center justify-between rounded-2xl bg-surface p-4 text-sm font-semibold shadow-sm">
                        <span>Отзывы</span>
                        <span data-arrow="feedbacks">▾</span>
                    </button>
                    <div data-wrapper="feedbacks">
                        <div data-section="feedbacks" class="flex flex-col gap-2"></div>
                    </div>
                </div>
                <div class="flex flex-col gap-3">
                    <button type="button" data-toggle="order_reviews"
                        class="flex items-center justify-between rounded-2xl bg-surface p-4 text-sm font-semibold shadow-sm">
                        <span>Отзывы о заказах</span>
                        <span data-arrow="order_reviews">▸</span>
                    </button>
                    <div data-wrapper="order_reviews" class="hidden">
                        <div data-section="order_reviews" class="flex flex-col gap-2"></div>
                    </div>
                </div>
            </div>
        `;

        const filterText = content.querySelector('#filter-text');
        const filterRating = content.querySelector('#filter-rating');

        content.querySelectorAll('[data-toggle]').forEach((button) => {
            button.addEventListener('click', () => {
                const key = button.dataset.toggle;
                collapsed[key] = !collapsed[key];
                updateCollapsed();
            });
        });

        filterText.addEventListener('input', renderLists);
        filterRating.addEventListener('change', renderLists);

        function updateCollapsed() {
            Object.entries(collapsed).forEach(([key, isCollapsed]) => {
                content.querySelector(`[data-wrapper="${key}"]`).classList.toggle('hidden', isCollapsed);
                content.querySelector(`[data-arrow="${key}"]`).textContent = isCollapsed ? '▸' : '▾';
            });
        }

        function renderLists() {
            const text = filterText.value.trim().toLowerCase();
            const rating = Number(filterRating.value) || 0;

            const filter = (items) => items.filter((item) => {
                if (rating > 0 && (!item.rating || item.rating < rating)) {
                    return false;
                }

                if (text) {
                    const haystack = [item.message, item.review, item.name, item.email]
                        .filter(Boolean)
                        .join(' ')
                        .toLowerCase();

                    if (!haystack.includes(text)) {
                        return false;
                    }
                }

                return true;
            });

            renderSection('feedbacks', filter(feedbacks));
            renderSection('order_reviews', filter(orderReviews));
        }

        function renderSection(key, items) {
            const section = content.querySelector(`[data-section="${key}"]`);

            if (items.length === 0) {
                section.innerHTML = '<p class="rounded-2xl bg-surface p-4 text-sm text-hint shadow-sm">Нет данных.</p>';

                return;
            }

            section.innerHTML = items.map((item) => renderItem(item)).join('');
        }

        updateCollapsed();
        renderLists();
    }

    function renderItem(item) {
        const rows = [
            ['Дата', formatDate(item.date)],
            ['Имя', item.name || '—'],
            ['Email', item.email || '—'],
            ['Телефон', item.phone || '—'],
        ];

        if (item.waiter) {
            rows.push(['Официант', item.waiter]);
        }

        if (item.message) {
            rows.push(['Сообщение', item.message]);
        }

        if (item.review) {
            rows.push(['Отзыв', item.review]);
        }

        rows.push(['Рейтинг', displayRating(item.rating)]);

        if (item.website) {
            rows.push(['Сайт', item.website]);
        }

        return `
            <div class="flex flex-col gap-1 rounded-2xl bg-surface p-4 shadow-sm">
                ${rows.map(([label, value]) => `
                    <div class="flex items-start justify-between gap-3 text-sm">
                        <span class="shrink-0 text-hint">${escapeHtml(label)}</span>
                        <span class="text-right">${escapeHtml(String(value))}</span>
                    </div>
                `).join('')}
            </div>
        `;
    }

    function formatDate(value) {
        if (!value) {
            return '—';
        }

        const date = new Date(String(value).replace(' ', 'T'));

        if (Number.isNaN(date.getTime())) {
            return value;
        }

        return date.toLocaleString('ru-RU');
    }

    function displayRating(rating) {
        if (rating === null || rating === undefined) {
            return '—';
        }

        const stars = Math.min(5, Math.max(0, Math.round(rating)));

        return `${'★'.repeat(stars)}${'☆'.repeat(5 - stars)} (${rating})`;
    }

    await load();
}
