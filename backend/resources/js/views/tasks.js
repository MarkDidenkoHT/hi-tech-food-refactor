import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { showToast } from '../toast';
import { confirmDialog } from '../telegram';
import { getSelectedRestaurantId } from '../restaurant-context';

export async function render(container, ctx) {
    const restaurants = ctx.user.restaurants ?? [];

    if (restaurants.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Вам не назначен ресторан.</p>';

        return;
    }

    const restaurantId = getSelectedRestaurantId(restaurants);
    let status = 'open';

    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div class="flex gap-1 rounded-xl bg-surface p-1 shadow-sm">
                <button type="button" data-status="open"
                    class="tab-btn flex-1 rounded-lg px-3 py-1.5 text-sm font-medium">
                    Открытые
                </button>
                <button type="button" data-status="done"
                    class="tab-btn flex-1 rounded-lg px-3 py-1.5 text-sm font-medium">
                    Выполненные
                </button>
            </div>
            <form id="add-form" class="flex gap-2">
                <input type="text" name="description" placeholder="Новая задача" required autocomplete="off"
                    class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                <button type="submit"
                    class="shrink-0 rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                    Добавить
                </button>
            </form>
            <div id="list" class="flex flex-col gap-2"></div>
        </div>
    `;

    const list = container.querySelector('#list');
    const tabs = container.querySelectorAll('.tab-btn');
    const addForm = container.querySelector('#add-form');

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            status = tab.dataset.status;
            updateTabs();
            load();
        });
    });

    addForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const description = addForm.elements.description.value.trim();

        if (!description) {
            return;
        }

        try {
            await apiFetch('/tasks', {
                method: 'POST',
                body: { restaurant_id: restaurantId, description },
            });
            addForm.reset();

            if (status === 'open') {
                await load();
            }
        } catch (error) {
            showToast(error.message, { type: 'error' });
        }
    });

    function updateTabs() {
        tabs.forEach((tab) => {
            if (tab.dataset.status === status) {
                tab.classList.add('bg-button', 'text-button-text');
                tab.classList.remove('text-hint');
            } else {
                tab.classList.remove('bg-button', 'text-button-text');
                tab.classList.add('text-hint');
            }
        });
    }

    async function load() {
        list.innerHTML = loadingHtml();

        try {
            const { data: tasks } = await apiFetch(`/tasks?restaurant_id=${restaurantId}&status=${status}`);
            renderList(tasks);
        } catch (error) {
            list.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderList(tasks) {
        if (tasks.length === 0) {
            list.innerHTML = `<p class="text-sm text-hint">${status === 'open' ? 'Открытых задач нет.' : 'Выполненных задач нет.'}</p>`;

            return;
        }

        list.innerHTML = `
            ${status === 'open' ? `
                <button type="button" id="complete-all"
                    class="self-start rounded-xl border border-link px-4 py-2 text-sm font-semibold text-link">
                    Завершить все
                </button>
            ` : ''}
            ${tasks.map((task) => `
                <div class="flex items-start justify-between gap-3 rounded-2xl bg-surface p-4 shadow-sm" data-id="${task.id}">
                    <div class="min-w-0">
                        ${task.area ? `
                            <span class="mb-1 inline-block rounded-full bg-link/10 px-2 py-0.5 text-xs font-medium text-link">
                                ${escapeHtml(task.area)}
                            </span>
                        ` : ''}
                        <p class="text-sm">${escapeHtml(task.description)}</p>
                        <p class="text-xs text-hint">${escapeHtml(formatDate(task.created_at))}</p>
                    </div>
                    <button data-action="toggle"
                        class="shrink-0 rounded-xl px-3 py-1.5 text-xs font-semibold ${task.status === 'open' ? 'bg-link/10 text-link' : 'bg-hint/10 text-hint'}">
                        ${task.status === 'open' ? 'Готово' : 'Вернуть'}
                    </button>
                </div>
            `).join('')}
        `;

        list.querySelectorAll('[data-action="toggle"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.closest('[data-id]').dataset.id;
                const newStatus = status === 'open' ? 'done' : 'open';

                try {
                    await apiFetch(`/tasks/${id}`, { method: 'PATCH', body: { status: newStatus } });
                    await load();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });

        list.querySelector('#complete-all')?.addEventListener('click', async () => {
            if (!(await confirmDialog('Отметить все задачи как выполненные?'))) {
                return;
            }

            try {
                await Promise.all(tasks.map((task) => apiFetch(`/tasks/${task.id}`, {
                    method: 'PATCH',
                    body: { status: 'done' },
                })));
                await load();
            } catch (error) {
                showToast(error.message, { type: 'error' });
                await load();
            }
        });
    }

    function formatDate(value) {
        return new Date(value).toLocaleDateString();
    }

    updateTabs();
    await load();
}
