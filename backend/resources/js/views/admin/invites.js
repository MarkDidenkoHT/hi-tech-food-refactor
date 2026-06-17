import { apiFetch } from '../../api';
import { escapeHtml, loadingHtml } from '../../dom';
import { showToast } from '../../toast';
import { confirmDialog } from '../../telegram';

const ROLES = ['staff', 'manager'];

const ROLE_LABELS = {
    staff: 'Сотрудник',
    manager: 'Менеджер',
    director: 'Директор',
    admin: 'Админ',
};

const STATUS_STYLES = {
    pending: 'bg-link/10 text-link',
    used: 'bg-hint/10 text-hint',
    expired: 'bg-destructive/10 text-destructive',
};

const STATUS_LABELS = {
    pending: 'ожидает',
    used: 'использовано',
    expired: 'истекло',
};

export async function render(container) {
    container.innerHTML = `
        <div class="flex flex-col gap-4 p-4">
            <form id="create-form" class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <p class="text-sm font-medium">Новое приглашение</p>
                <label class="flex flex-col gap-1 text-sm">
                    Ресторан
                    <select name="restaurant_id" class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                        <option value="">— Нет —</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Роль
                    <select name="role" class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                        ${ROLES.map((role) => `<option value="${role}">${escapeHtml(ROLE_LABELS[role] ?? role)}</option>`).join('')}
                    </select>
                </label>
                <label class="flex flex-col gap-1 text-sm">
                    Истекает <span class="text-hint">(необязательно)</span>
                    <input type="datetime-local" name="expires_at"
                        class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                </label>
                <button type="submit" class="rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                    Создать приглашение
                </button>
            </form>
            <div id="list" class="flex flex-col gap-2"></div>
        </div>
    `;

    const form = container.querySelector('#create-form');
    const restaurantSelect = form.elements.restaurant_id;
    const list = container.querySelector('#list');

    const { data: restaurants } = await apiFetch('/admin/restaurants');
    restaurantSelect.insertAdjacentHTML(
        'beforeend',
        restaurants.map((r) => `<option value="${r.id}">${escapeHtml(r.name)}</option>`).join('')
    );

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const body = {
            role: form.elements.role.value,
            restaurant_id: restaurantSelect.value ? Number(restaurantSelect.value) : null,
        };

        const expiresAt = form.elements.expires_at.value;

        if (expiresAt) {
            body.expires_at = new Date(expiresAt).toISOString();
        }

        try {
            await apiFetch('/admin/invites', { method: 'POST', body });
            form.reset();
            showToast('Приглашение создано.');
            await load();
        } catch (error) {
            showToast(error.message, { type: 'error' });
        }
    });

    async function load() {
        list.innerHTML = loadingHtml();

        try {
            const { data: invites } = await apiFetch('/admin/invites');
            renderList(invites);
        } catch (error) {
            list.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    function renderList(invites) {
        if (invites.length === 0) {
            list.innerHTML = '<p class="text-sm text-hint">Приглашений пока нет.</p>';

            return;
        }

        list.innerHTML = invites.map((invite) => `
            <div class="flex flex-col gap-2 rounded-2xl bg-surface p-4 shadow-sm" data-id="${invite.id}">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            ${escapeHtml(ROLE_LABELS[invite.role] ?? invite.role)}${invite.restaurant?.name ? ` &middot; ${escapeHtml(invite.restaurant.name)}` : ''}
                        </p>
                        <p class="truncate text-xs text-hint">
                            Создано ${escapeHtml(formatDate(invite.created_at))}
                            ${invite.used_by ? ` &middot; использовано ${escapeHtml(invite.used_by)}` : ''}
                            ${invite.expires_at ? ` &middot; истекает ${escapeHtml(formatDate(invite.expires_at))}` : ''}
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${STATUS_STYLES[invite.status] ?? ''}">
                        ${escapeHtml(STATUS_LABELS[invite.status] ?? invite.status)}
                    </span>
                </div>
                ${invite.status === 'pending' && invite.link ? `
                    <div class="flex items-center gap-2">
                        <input readonly value="${escapeHtml(invite.link)}"
                            class="flex-1 truncate rounded-xl border border-separator bg-bg px-3 py-2 text-xs">
                        <button data-action="copy" data-link="${escapeHtml(invite.link)}"
                            class="shrink-0 rounded-xl border border-separator px-3 py-2 text-xs font-semibold">
                            Скопировать
                        </button>
                        <button data-action="revoke"
                            class="shrink-0 rounded-xl px-3 py-2 text-xs font-semibold text-destructive">
                            Отозвать
                        </button>
                    </div>
                ` : ''}
            </div>
        `).join('');

        list.querySelectorAll('[data-action="copy"]').forEach((button) => {
            button.addEventListener('click', async () => {
                try {
                    await navigator.clipboard.writeText(button.dataset.link);
                    showToast('Ссылка скопирована.');
                } catch {
                    showToast('Не удалось скопировать ссылку.', { type: 'error' });
                }
            });
        });

        list.querySelectorAll('[data-action="revoke"]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!(await confirmDialog('Отозвать это приглашение?'))) {
                    return;
                }

                const id = button.closest('[data-id]').dataset.id;

                try {
                    await apiFetch(`/admin/invites/${id}`, { method: 'DELETE' });
                    showToast('Приглашение отозвано.');
                    await load();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });
    }

    await load();
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString();
}
