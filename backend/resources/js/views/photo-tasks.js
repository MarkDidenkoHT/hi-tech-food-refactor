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

    const canEdit = ctx.user.role === 'admin' || ctx.user.role === 'director';
    const restaurantId = getSelectedRestaurantId(restaurants);

    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div id="list" class="flex flex-col gap-2"></div>
            ${canEdit ? `
                <form id="add-form" class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-sm">
                    <label class="text-sm font-medium" for="new-question">Новое фотозадание</label>
                    <div class="flex gap-2">
                        <input id="new-question" name="question" type="text" required autocomplete="off"
                            class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                        <button type="submit"
                            class="shrink-0 rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                            Добавить
                        </button>
                    </div>
                </form>
            ` : ''}
        </div>
    `;

    const list = container.querySelector('#list');
    const addForm = container.querySelector('#add-form');

    addForm?.addEventListener('submit', async (event) => {
        event.preventDefault();

        const question = addForm.elements.question.value.trim();

        if (!question) {
            return;
        }

        try {
            await apiFetch('/photo-questions', {
                method: 'POST',
                body: { restaurant_id: restaurantId, question },
            });
            addForm.reset();
            await load();
        } catch (error) {
            showToast(error.message, { type: 'error' });
        }
    });

    async function load() {
        list.innerHTML = loadingHtml();

        try {
            const { data: questions } = await apiFetch(`/photo-questions?restaurant_id=${restaurantId}`);
            renderList(questions);
        } catch (error) {
            list.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderList(questions) {
        if (questions.length === 0) {
            list.innerHTML = '<p class="text-sm text-hint">Фотозаданий пока нет.</p>';

            return;
        }

        list.innerHTML = questions.map((question) => `
            <div class="flex items-start justify-between gap-3 rounded-2xl bg-surface p-4 shadow-sm" data-id="${question.id}">
                <p class="min-w-0 flex-1 text-sm" data-text>${escapeHtml(question.question)}</p>
                ${canEdit ? `
                    <div class="flex shrink-0 gap-2">
                        <button data-action="edit" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-link">
                            Изменить
                        </button>
                        <button data-action="delete" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-destructive">
                            Удалить
                        </button>
                    </div>
                ` : ''}
            </div>
        `).join('');

        if (!canEdit) {
            return;
        }

        list.querySelectorAll('[data-action="edit"]').forEach((button) => {
            button.addEventListener('click', () => startEdit(button.closest('[data-id]'), questions));
        });

        list.querySelectorAll('[data-action="delete"]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!(await confirmDialog('Удалить это фотозадание?'))) {
                    return;
                }

                const id = button.closest('[data-id]').dataset.id;

                try {
                    await apiFetch(`/photo-questions/${id}`, { method: 'DELETE' });
                    await load();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });
    }

    function startEdit(card, questions) {
        const id = card.dataset.id;
        const current = questions.find((q) => String(q.id) === id);

        card.innerHTML = `
            <div class="flex w-full flex-col gap-2">
                <textarea data-edit-text rows="3"
                    class="w-full rounded-xl border border-separator bg-bg px-3 py-2 text-sm">${escapeHtml(current.question)}</textarea>
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

        card.querySelector('[data-action="cancel"]').addEventListener('click', () => load());

        card.querySelector('[data-action="save"]').addEventListener('click', async () => {
            const text = card.querySelector('[data-edit-text]').value.trim();

            if (!text) {
                return;
            }

            try {
                await apiFetch(`/photo-questions/${id}`, { method: 'PATCH', body: { question: text } });
                await load();
            } catch (error) {
                showToast(error.message, { type: 'error' });
            }
        });
    }

    await load();
}
