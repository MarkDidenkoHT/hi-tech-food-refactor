import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { showToast } from '../toast';
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

    async function load() {
        content.innerHTML = loadingHtml();

        try {
            const data = await apiFetch(`/checklist?restaurant_id=${restaurantId}`);
            renderContent(data);
        } catch (error) {
            content.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderContent(data) {
        if (data.submission) {
            renderSummary(data);
        } else {
            renderForm(data);
        }
    }

    function renderSummary(data) {
        const { submission } = data;
        const time = new Date(submission.submitted_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const issues = submission.answers.filter((answer) => answer.status === 'not_ok' || answer.comment);

        content.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <p class="text-sm font-medium">
                    Чек-лист заполнен сегодня в ${escapeHtml(time)} — ${escapeHtml(submission.submitted_by)}
                </p>
                ${issues.length > 0 ? `
                    <div class="flex flex-col gap-2">
                        ${issues.map((answer) => `
                            <div class="rounded-xl bg-bg p-3 text-sm">
                                <p class="font-medium text-destructive">${escapeHtml(answer.question)}</p>
                                ${answer.comment ? `<p class="mt-1 text-xs text-hint">${escapeHtml(answer.comment)}</p>` : ''}
                            </div>
                        `).join('')}
                    </div>
                ` : ''}
                <button id="refill" type="button"
                    class="rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                    Заполнить заново
                </button>
            </div>
        `;

        content.querySelector('#refill').addEventListener('click', () => renderForm(data));
    }

    function renderForm(data) {
        content.innerHTML = `
            <form id="checklist-form" class="flex flex-col gap-4">
                <button type="button" id="mark-all-ok"
                    class="rounded-xl border border-link px-4 py-2 text-sm font-semibold text-link">
                    Все ОК
                </button>
                ${data.areas.map((area) => `
                    <div class="flex flex-col gap-2">
                        <h2 class="text-sm font-semibold">${escapeHtml(area.area)}</h2>
                        ${area.questions.map((question) => `
                            <div data-question-row data-question-id="${question.id}"
                                class="flex flex-col gap-2 rounded-2xl bg-surface p-3 shadow-sm transition">
                                <p class="text-sm">${escapeHtml(question.question)}</p>
                                <div class="flex gap-2">
                                    <button type="button" data-status="ok"
                                        class="status-btn flex-1 rounded-xl border border-separator px-3 py-1.5 text-sm font-medium">
                                        ОК
                                    </button>
                                    <button type="button" data-status="not_ok"
                                        class="status-btn flex-1 rounded-xl border border-separator px-3 py-1.5 text-sm font-medium">
                                        Не ОК
                                    </button>
                                </div>
                                <input type="text" data-comment placeholder="Комментарий (необязательно)"
                                    class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                            </div>
                        `).join('')}
                    </div>
                `).join('')}
                <button type="submit" class="rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                    Отправить
                </button>
            </form>
        `;

        const form = content.querySelector('#checklist-form');

        form.querySelectorAll('.status-btn').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-question-row]');

                row.querySelectorAll('.status-btn').forEach((b) => {
                    b.classList.remove('bg-link', 'bg-destructive', 'text-white', 'border-link', 'border-destructive');
                });

                row.dataset.status = button.dataset.status;
                row.classList.remove('ring-2', 'ring-destructive');

                if (button.dataset.status === 'ok') {
                    button.classList.add('bg-link', 'text-white', 'border-link');
                } else {
                    button.classList.add('bg-destructive', 'text-white', 'border-destructive');
                }
            });
        });

        form.querySelector('#mark-all-ok').addEventListener('click', () => {
            form.querySelectorAll('[data-question-row] .status-btn[data-status="ok"]').forEach((button) => {
                button.click();
            });

            form.querySelector('button[type="submit"]').scrollIntoView({ behavior: 'smooth', block: 'end' });
        });

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const rows = Array.from(form.querySelectorAll('[data-question-row]'));
            const unanswered = rows.filter((row) => !row.dataset.status);

            rows.forEach((row) => row.classList.remove('ring-2', 'ring-destructive'));

            if (unanswered.length > 0) {
                unanswered.forEach((row) => row.classList.add('ring-2', 'ring-destructive'));
                unanswered[0].scrollIntoView({ behavior: 'smooth', block: 'center' });

                return;
            }

            const answers = rows.map((row) => ({
                checklist_question_id: Number(row.dataset.questionId),
                status: row.dataset.status,
                comment: row.querySelector('[data-comment]').value.trim() || null,
            }));

            try {
                const result = await apiFetch('/checklist', {
                    method: 'POST',
                    body: { restaurant_id: restaurantId, answers },
                });

                showToast(result.tasks_created > 0
                    ? `Чек-лист отправлен. Новых задач: ${result.tasks_created}`
                    : 'Чек-лист отправлен.');

                renderContent(result);
            } catch (error) {
                showToast(error.message, { type: 'error' });
            }
        });
    }

    await load();
}
