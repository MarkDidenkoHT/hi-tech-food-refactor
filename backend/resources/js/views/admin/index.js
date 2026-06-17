const ITEMS = [
    { path: 'admin/restaurants', label: 'Рестораны', icon: '🏠' },
    { path: 'admin/invites', label: 'Приглашения', icon: '🔗' },
    { path: 'admin/users', label: 'Пользователи', icon: '👥' },
    { path: 'admin/cron', label: 'Автоматизация', icon: '⏰' },
];

export function render(container) {
    container.innerHTML = `
        <div class="grid grid-cols-2 gap-3 p-4">
            ${ITEMS.map((item) => `
                <a href="#/${item.path}"
                    class="flex flex-col items-center justify-center gap-2 rounded-2xl bg-surface p-6 text-center shadow-sm transition active:scale-95">
                    <span class="text-3xl">${item.icon}</span>
                    <span class="text-sm font-medium">${item.label}</span>
                </a>
            `).join('')}
        </div>
    `;
}
