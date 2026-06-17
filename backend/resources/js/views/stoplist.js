import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { showToast } from '../toast';
import { confirmDialog } from '../telegram';
import { getSelectedRestaurantId } from '../restaurant-context';
import {
    getMenuItems,
    invalidateMenuItems,
    readStoplist,
    fetchStoplist,
    invalidateStoplist,
} from '../data-cache';

const SECTIONS = [
    { key: 'kitchen', label: 'Кухня' },
    { key: 'bar', label: 'Бар' },
];

const STATUSES = [
    { key: 'stop', label: 'Стоп', resolveLabel: 'Вернуть', commentRequired: true },
    { key: 'limit', label: 'Лимит', resolveLabel: 'Вернуть', commentRequired: true },
    { key: 'play', label: 'Продаём', resolveLabel: 'Убрать', commentRequired: false },
];

export async function render(container, ctx) {
    const restaurants = ctx.user.restaurants ?? [];

    if (restaurants.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Вам не назначен ресторан.</p>';

        return;
    }

    const canManageMenu = ctx.user.role === 'admin' || ctx.user.role === 'director';
    const restaurantId = getSelectedRestaurantId(restaurants);

    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div id="send-container"></div>
            <div id="cards" class="flex flex-col gap-4"></div>
            <div id="menu-management" class="flex flex-col gap-4"></div>
        </div>
    `;

    const sendContainer = container.querySelector('#send-container');
    const cardsContainer = container.querySelector('#cards');
    const menuManagement = container.querySelector('#menu-management');

    // Shared full menu list for autocomplete and add-form validation across all
    // cards. Reads live, so refreshing it after a menu edit updates every card.
    let menuItems = [];

    async function refreshMenuItems() {
        try {
            menuItems = await getMenuItems(restaurantId);
        } catch {
            // ignore autocomplete errors
        }
    }

    container.addEventListener('click', (event) => {
        cardsContainer.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
            if (!dropdown.parentElement.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    });

    async function load() {
        const cached = readStoplist(restaurantId);

        if (cached) {
            await applyData(cached);
        } else {
            cardsContainer.innerHTML = loadingHtml();
            menuManagement.innerHTML = '';
            sendContainer.innerHTML = '';
        }

        try {
            const data = await fetchStoplist(restaurantId);
            await applyData(data);
        } catch (error) {
            if (cached) {
                showToast(error.message, { type: 'error' });
            } else {
                cardsContainer.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
            }
        }
    }

    async function applyData(data) {
        renderCards(data);
        renderSendButton(data);

        if (data.editable_menu && canManageMenu && menuManagement.children.length === 0) {
            await renderMenuManagement();
        }
    }

    function renderSendButton(data) {
        sendContainer.innerHTML = '';

        if (!data.telegram_configured) {
            return;
        }

        sendContainer.innerHTML = `
            <button type="button" id="send-stoplist"
                class="self-start rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                Отправить в группу
            </button>
        `;

        sendContainer.querySelector('#send-stoplist').addEventListener('click', async (event) => {
            const button = event.currentTarget;
            button.disabled = true;

            try {
                const result = await apiFetch('/stoplist/send', {
                    method: 'POST',
                    body: { restaurant_id: restaurantId },
                });
                showToast(result.message);
            } catch (error) {
                showToast(error.message, { type: 'error' });
            } finally {
                button.disabled = false;
            }
        });
    }

    function renderCards(data) {
        cardsContainer.innerHTML = SECTIONS.map((section) => STATUSES.map((status) => {
            const id = `${section.key}-${status.key}`;
            const entries = data[section.key]?.[status.key] ?? [];

            return `
                <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm" data-card="${id}">
                    <h3 class="text-sm font-semibold">${section.label} — ${status.label}</h3>
                    <div class="flex flex-col gap-2" data-list>
                        ${entries.length === 0 ? '<p class="text-sm text-hint">Нет позиций.</p>' : entries.map((entry) => `
                            <div class="flex items-start justify-between gap-3 rounded-xl bg-bg p-3" data-id="${entry.id}">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium">${escapeHtml(entry.item)}</p>
                                    ${entry.comment ? `<p class="text-sm italic text-hint">${escapeHtml(entry.comment)}</p>` : ''}
                                    <p class="text-xs text-hint">${escapeHtml(entry.created_by ?? '—')} · ${escapeHtml(formatDate(entry.created_at))}</p>
                                </div>
                                <button data-action="resolve" aria-label="${status.resolveLabel}"
                                    class="shrink-0 flex h-7 w-7 items-center justify-center rounded-full bg-hint/10 text-lg leading-none text-hint">
                                    &times;
                                </button>
                            </div>
                        `).join('')}
                    </div>
                    <form data-add-form class="flex flex-col gap-2">
                        <div class="relative">
                            <input type="text" name="item" placeholder="Позиция меню" required autocomplete="off"
                                class="w-full rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                            <div data-dropdown
                                class="absolute inset-x-0 top-full z-10 mt-1 hidden max-h-48 overflow-y-auto rounded-xl border border-separator bg-surface shadow-lg"></div>
                        </div>
                        <input type="text" name="comment" placeholder="${status.commentRequired ? 'Комментарий' : 'Комментарий (необязательно)'}" ${status.commentRequired ? 'required' : ''} autocomplete="off"
                            class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                        <button type="submit"
                            class="rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                            Добавить
                        </button>
                    </form>
                </div>
            `;
        })).flat().join('');

        SECTIONS.forEach((section) => {
            const activeItems = new Set(
                STATUSES.flatMap((status) => (data[section.key]?.[status.key] ?? []).map((entry) => entry.item.toLowerCase()))
            );

            STATUSES.forEach((status) => setupCard(section, status, activeItems));
        });
    }

    function setupCard(section, status, activeItems) {
        const id = `${section.key}-${status.key}`;
        const card = cardsContainer.querySelector(`[data-card="${id}"]`);
        const form = card.querySelector('[data-add-form]');
        const itemInput = form.elements.namedItem('item');
        const commentInput = form.elements.namedItem('comment');
        const dropdown = card.querySelector('[data-dropdown]');

        function renderDropdown() {
            const query = itemInput.value.trim().toLowerCase();

            const items = menuItems
                .filter((item) => !activeItems.has(item.toLowerCase()))
                .filter((item) => item.toLowerCase().includes(query))
                .slice(0, 20);

            if (items.length === 0) {
                dropdown.classList.add('hidden');
                dropdown.innerHTML = '';

                return;
            }

            dropdown.innerHTML = items.map((item) => `
                <button type="button" data-item="${escapeHtml(item)}"
                    class="block w-full px-3 py-2 text-left text-sm hover:bg-bg">
                    ${escapeHtml(item)}
                </button>
            `).join('');

            dropdown.querySelectorAll('[data-item]').forEach((button) => {
                button.addEventListener('click', () => {
                    itemInput.value = button.dataset.item;
                    dropdown.classList.add('hidden');
                });
            });

            dropdown.classList.remove('hidden');
        }

        itemInput.addEventListener('input', renderDropdown);
        itemInput.addEventListener('focus', renderDropdown);

        card.querySelectorAll('[data-action="resolve"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const entryId = button.closest('[data-id]').dataset.id;

                try {
                    await apiFetch(`/stoplist/${entryId}/resolve`, { method: 'PATCH' });
                    invalidateStoplist(restaurantId);
                    await load();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const item = itemInput.value.trim();
            const comment = commentInput.value.trim();

            if (!item || (status.commentRequired && !comment)) {
                return;
            }

            if (!menuItems.includes(item)) {
                showToast('Нужно выбрать позицию из меню.', { type: 'error' });

                return;
            }

            if (activeItems.has(item.toLowerCase())) {
                showToast('Эта позиция уже в стоп-листе.', { type: 'error' });

                return;
            }

            const body = { restaurant_id: restaurantId, section: section.key, status: status.key, item };

            if (comment) {
                body.comment = comment;
            }

            try {
                await apiFetch('/stoplist', {
                    method: 'POST',
                    body,
                });
                invalidateStoplist(restaurantId);
                await load();
            } catch (error) {
                showToast(error.message, { type: 'error' });
            }
        });
    }

    async function renderMenuManagement() {
        menuManagement.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <h3 class="text-sm font-semibold">Меню ресторана</h3>
                <div id="menu-list" class="flex flex-col gap-2"></div>
                <form id="menu-add-form" class="flex gap-2">
                    <input type="text" name="name" placeholder="Новая позиция" required autocomplete="off"
                        class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <button type="submit"
                        class="shrink-0 rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                        Добавить
                    </button>
                </form>
            </div>
        `;

        const menuList = menuManagement.querySelector('#menu-list');
        const menuAddForm = menuManagement.querySelector('#menu-add-form');

        menuAddForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            const name = menuAddForm.elements.name.value.trim();

            if (!name) {
                return;
            }

            try {
                await apiFetch('/menu-items', {
                    method: 'POST',
                    body: { restaurant_id: restaurantId, name },
                });
                menuAddForm.reset();
                invalidateMenuItems(restaurantId);
                refreshMenuItems();
                await loadMenuItems();
            } catch (error) {
                showToast(error.message, { type: 'error' });
            }
        });

        async function loadMenuItems() {
            menuList.innerHTML = loadingHtml();

            try {
                const { data: items } = await apiFetch(`/menu-items?restaurant_id=${restaurantId}`);
                renderMenuList(items);
            } catch (error) {
                menuList.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
            }
        }

        function renderMenuList(items) {
            if (items.length === 0) {
                menuList.innerHTML = '<p class="text-sm text-hint">Меню пока пусто.</p>';

                return;
            }

            menuList.innerHTML = items.map((item) => `
                <div class="flex items-start justify-between gap-3 rounded-xl bg-bg p-3" data-id="${item.id}">
                    <p class="min-w-0 flex-1 text-sm" data-text>${escapeHtml(item.name)}</p>
                    <div class="flex shrink-0 gap-2">
                        <button data-action="edit" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-link">
                            Изменить
                        </button>
                        <button data-action="delete" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-destructive">
                            Удалить
                        </button>
                    </div>
                </div>
            `).join('');

            menuList.querySelectorAll('[data-action="edit"]').forEach((button) => {
                button.addEventListener('click', () => startEdit(button.closest('[data-id]'), items));
            });

            menuList.querySelectorAll('[data-action="delete"]').forEach((button) => {
                button.addEventListener('click', async () => {
                    if (!(await confirmDialog('Удалить эту позицию меню?'))) {
                        return;
                    }

                    const id = button.closest('[data-id]').dataset.id;

                    try {
                        await apiFetch(`/menu-items/${id}`, { method: 'DELETE' });
                        invalidateMenuItems(restaurantId);
                        refreshMenuItems();
                        await loadMenuItems();
                    } catch (error) {
                        showToast(error.message, { type: 'error' });
                    }
                });
            });
        }

        function startEdit(row, items) {
            const id = row.dataset.id;
            const current = items.find((item) => String(item.id) === id);

            row.innerHTML = `
                <div class="flex w-full flex-col gap-2">
                    <input type="text" data-edit-text value="${escapeHtml(current.name)}"
                        class="w-full rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <div class="flex gap-2">
                        <button data-action="save"
                            class="flex-1 rounded-xl bg-button px-3 py-1.5 text-sm font-semibold text-button-text">
                            Сохранить
                        </button>
                        <button data-action="cancel"
                            class="flex-1 rounded-xl border border-separator px-3 py-1.5 text-sm font-medium">
                            Отмена
                        </button>
                    </div>
                </div>
            `;

            row.querySelector('[data-action="cancel"]').addEventListener('click', () => loadMenuItems());

            row.querySelector('[data-action="save"]').addEventListener('click', async () => {
                const name = row.querySelector('[data-edit-text]').value.trim();

                if (!name) {
                    return;
                }

                try {
                    await apiFetch(`/menu-items/${id}`, { method: 'PATCH', body: { name } });
                    invalidateMenuItems(restaurantId);
                    refreshMenuItems();
                    await loadMenuItems();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        }

        await loadMenuItems();
    }

    function formatDate(value) {
        return new Date(value).toLocaleDateString();
    }

    refreshMenuItems();
    await load();
}
