import { apiFetch } from '../api';
import { showToast } from '../toast';

const SPINNER_HTML = '<span class="inline-block h-3 w-3 animate-spin rounded-full border-2 border-current border-t-transparent align-middle"></span>';

export async function render(container) {
    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <div class="min-w-0">
                    <p class="text-sm font-medium">Напоминания о фото-вопросах</p>
                    <p class="text-xs text-hint">
                        Каждый час с 10:00 до 18:00 ресторанам с привязанной группой Telegram
                        отправляется следующий фото-вопрос по очереди.
                    </p>
                </div>
                <button id="toggle" disabled
                    class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold bg-destructive/10 text-destructive">
                    ${SPINNER_HTML}
                </button>
            </div>
        </div>
    `;

    const toggle = container.querySelector('#toggle');

    let enabled = false;

    await load();

    toggle.addEventListener('click', async () => {
        toggle.disabled = true;
        toggle.innerHTML = SPINNER_HTML;

        try {
            const data = await apiFetch('/admin/cron-settings', {
                method: 'PATCH',
                body: { photo_question_reminders_enabled: !enabled },
            });
            enabled = data.photo_question_reminders_enabled;
            showToast(enabled ? 'Рассылка включена.' : 'Рассылка отключена.');
        } catch (error) {
            showToast(error.message, { type: 'error' });
        } finally {
            renderToggle();
            toggle.disabled = false;
        }
    });

    async function load() {
        try {
            const data = await apiFetch('/admin/cron-settings');
            enabled = data.photo_question_reminders_enabled;
            renderToggle();
            toggle.disabled = false;
        } catch (error) {
            showToast(error.message, { type: 'error' });
        }
    }

    function renderToggle() {
        toggle.textContent = enabled ? 'Включено' : 'Отключено';
        toggle.className = `shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${enabled ? 'bg-link/10 text-link' : 'bg-destructive/10 text-destructive'}`;
    }
}
