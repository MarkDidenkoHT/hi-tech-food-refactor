import { apiFetch } from '../../api';
import { loadingHtml } from '../../dom';
import { showToast } from '../../toast';

export async function render(container) {
    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <div id="list" class="flex flex-col gap-3"></div>
        </div>
    `;

    const list = container.querySelector('#list');

    async function load() {
        list.innerHTML = loadingHtml();

        try {
            const data = await apiFetch('/admin/cron-settings');
            renderToggle(data);
        } catch (error) {
            list.innerHTML = `<p class="text-sm text-destructive">${error.message}</p>`;
        }
    }

    function renderToggle(data) {
        list.innerHTML = `
            <div class="flex items-center justify-between gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <div class="min-w-0">
                    <p class="text-sm font-medium">Напоминания о фотозаданиях</p>
                    <p class="text-xs text-hint">Ежечасная рассылка следующего фотозадания в группы ресторанов.</p>
                </div>
                <button type="button" id="toggle" role="switch"
                    aria-checked="${data.photo_question_reminders_enabled}"
                    class="relative h-6 w-11 shrink-0 rounded-full transition ${data.photo_question_reminders_enabled ? 'bg-button' : 'bg-hint/30'}">
                    <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white transition-all ${data.photo_question_reminders_enabled ? 'left-[22px]' : 'left-0.5'}"></span>
                </button>
            </div>
        `;

        list.querySelector('#toggle').addEventListener('click', async (event) => {
            const button = event.currentTarget;
            const next = button.getAttribute('aria-checked') !== 'true';
            button.disabled = true;

            try {
                const result = await apiFetch('/admin/cron-settings', {
                    method: 'PATCH',
                    body: { photo_question_reminders_enabled: next },
                });
                renderToggle(result);
                showToast(next ? 'Напоминания включены.' : 'Напоминания выключены.');
            } catch (error) {
                showToast(error.message, { type: 'error' });
                button.disabled = false;
            }
        });
    }

    await load();
}
