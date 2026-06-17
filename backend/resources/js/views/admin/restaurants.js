import { apiFetch } from '../../api';
import { escapeHtml, loadingHtml } from '../../dom';
import { showToast } from '../../toast';

export async function render(container) {
    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <form id="create-form" class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-sm">
                <label class="text-sm font-medium" for="restaurant-name">Новый ресторан</label>
                <div class="flex gap-2">
                    <input id="restaurant-name" name="name" type="text" required autocomplete="off"
                        placeholder="Название ресторана"
                        class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <button type="submit"
                        class="shrink-0 rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                        Добавить
                    </button>
                </div>
            </form>
            <div id="list" class="flex flex-col gap-2"></div>
        </div>
    `;

    const form = container.querySelector('#create-form');
    const list = container.querySelector('#list');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const name = form.elements.name.value.trim();

        if (!name) {
            return;
        }

        try {
            await apiFetch('/admin/restaurants', { method: 'POST', body: { name } });
            form.reset();
            showToast('Ресторан создан.');
            await load();
        } catch (error) {
            showToast(error.message, { type: 'error' });
        }
    });

    async function load() {
        list.innerHTML = loadingHtml();

        try {
            const { data: restaurants } = await apiFetch('/admin/restaurants');
            renderList(restaurants);
        } catch (error) {
            list.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderList(restaurants) {
        if (restaurants.length === 0) {
            list.innerHTML = '<p class="text-sm text-hint">Ресторанов пока нет.</p>';

            return;
        }

        list.innerHTML = restaurants.map((restaurant) => `
            <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm" data-id="${restaurant.id}">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">${escapeHtml(restaurant.name)}</p>
                        <p class="truncate text-xs text-hint">${escapeHtml(restaurant.slug)}</p>
                    </div>
                    <button data-action="toggle"
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${restaurant.is_active ? 'bg-link/10 text-link' : 'bg-destructive/10 text-destructive'}">
                        ${restaurant.is_active ? 'Активен' : 'Неактивен'}
                    </button>
                </div>
                <div class="flex gap-2">
                    <input data-field="telegram_group_chat_id" type="number" inputmode="numeric" autocomplete="off"
                        placeholder="ID группы Telegram" value="${restaurant.telegram_group_chat_id ?? ''}"
                        class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <button data-action="save-chat-id"
                        class="shrink-0 rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                        Сохранить
                    </button>
                </div>
            </div>
        `).join('');

        list.querySelectorAll('[data-action="toggle"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.closest('[data-id]').dataset.id;
                const restaurant = restaurants.find((r) => String(r.id) === id);

                try {
                    await apiFetch(`/admin/restaurants/${id}`, {
                        method: 'PUT',
                        body: { is_active: !restaurant.is_active },
                    });
                    await load();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });

        list.querySelectorAll('[data-action="save-chat-id"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const card = button.closest('[data-id]');
                const id = card.dataset.id;
                const input = card.querySelector('[data-field="telegram_group_chat_id"]');
                const value = input.value.trim();

                try {
                    await apiFetch(`/admin/restaurants/${id}`, {
                        method: 'PUT',
                        body: { telegram_group_chat_id: value === '' ? null : Number(value) },
                    });
                    showToast('ID группы сохранён.');
                    await load();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });
    }

    await load();
}
