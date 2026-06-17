import { apiFetch } from '../../api';
import { escapeHtml, loadingHtml } from '../../dom';
import { showToast } from '../../toast';
import { confirmDialog } from '../../telegram';

const ROLES = [
    { value: 'staff', label: 'Сотрудник' },
    { value: 'manager', label: 'Менеджер' },
    { value: 'director', label: 'Директор' },
    { value: 'admin', label: 'Админ' },
];

export async function render(container, ctx) {
    container.innerHTML = `<div id="list" class="flex flex-col gap-2 p-4">${loadingHtml()}</div>`;

    const list = container.querySelector('#list');

    const [usersResponse, restaurantsResponse] = await Promise.all([
        apiFetch('/admin/users'),
        apiFetch('/admin/restaurants'),
    ]);

    let users = usersResponse.data;
    const restaurants = restaurantsResponse.data;

    renderList();

    async function refresh() {
        users = (await apiFetch('/admin/users')).data;
        renderList();
    }

    function renderList() {
        if (users.length === 0) {
            list.innerHTML = '<p class="text-sm text-hint">Пользователей пока нет.</p>';

            return;
        }

        list.innerHTML = users.map((user) => `
            <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm" data-id="${user.id}">
                <div class="flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">
                            ${escapeHtml(`${user.first_name} ${user.last_name ?? ''}`.trim())}
                        </p>
                        <p class="truncate text-xs text-hint">
                            ${user.username ? `@${escapeHtml(user.username)} &middot; ` : ''}${escapeHtml(user.telegram_id)}
                        </p>
                    </div>
                    <button data-action="toggle-active"
                        class="shrink-0 rounded-full px-3 py-1 text-xs font-semibold ${user.is_active ? 'bg-link/10 text-link' : 'bg-destructive/10 text-destructive'}">
                        ${user.is_active ? 'Активен' : 'Неактивен'}
                    </button>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <select data-field="role" class="rounded-xl border border-separator bg-bg px-2 py-1.5 text-xs">
                        ${ROLES.map((role) => `<option value="${role.value}" ${role.value === user.role ? 'selected' : ''}>${escapeHtml(role.label)}</option>`).join('')}
                    </select>
                    <select data-field="restaurant_ids" multiple
                        class="min-w-[9rem] rounded-xl border border-separator bg-bg px-2 py-1.5 text-xs">
                        ${restaurants.map((r) => `<option value="${r.id}" ${user.restaurants.some((ur) => ur.id === r.id) ? 'selected' : ''}>${escapeHtml(r.name)}</option>`).join('')}
                    </select>
                    ${user.id === ctx.user.id ? '' : `
                        <button data-action="delete"
                            class="ml-auto shrink-0 rounded-xl px-3 py-1.5 text-xs font-semibold text-destructive">
                            Удалить
                        </button>
                    `}
                </div>
            </div>
        `).join('');

        list.querySelectorAll('[data-action="toggle-active"]').forEach((button) => {
            button.addEventListener('click', async () => {
                const id = button.closest('[data-id]').dataset.id;
                const user = users.find((u) => String(u.id) === id);

                try {
                    await apiFetch(`/admin/users/${id}`, { method: 'PUT', body: { is_active: !user.is_active } });
                    await refresh();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });

        list.querySelectorAll('[data-field="role"]').forEach((select) => {
            select.addEventListener('change', async () => {
                const id = select.closest('[data-id]').dataset.id;

                try {
                    await apiFetch(`/admin/users/${id}`, { method: 'PUT', body: { role: select.value } });
                    showToast('Роль обновлена.');
                    await refresh();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });

        list.querySelectorAll('[data-field="restaurant_ids"]').forEach((select) => {
            select.addEventListener('change', async () => {
                const id = select.closest('[data-id]').dataset.id;
                const restaurant_ids = Array.from(select.selectedOptions).map((option) => Number(option.value));

                try {
                    await apiFetch(`/admin/users/${id}`, { method: 'PUT', body: { restaurant_ids } });
                    showToast('Рестораны обновлены.');
                    await refresh();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });

        list.querySelectorAll('[data-action="delete"]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!(await confirmDialog('Удалить этого пользователя?'))) {
                    return;
                }

                const id = button.closest('[data-id]').dataset.id;

                try {
                    await apiFetch(`/admin/users/${id}`, { method: 'DELETE' });
                    showToast('Пользователь удалён.');
                    await refresh();
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });
    }
}
