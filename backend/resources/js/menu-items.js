/**
 * Top-level navigation cards, filtered per-user by role.
 *
 * @type {Array<{path: string, label: string, icon: string, roles: string[]}>}
 */
export const MENU_ITEMS = [
    { path: 'checklist', label: 'Чек-лист', icon: '✅', roles: ['staff', 'manager', 'director', 'admin'] },
    { path: 'tasks', label: 'Задачи', icon: '📋', roles: ['staff', 'manager', 'director', 'admin'] },
    { path: 'photo-tasks', label: 'Фотозадания', icon: '📷', roles: ['staff', 'manager', 'director', 'admin'] },
    { path: 'mailings', label: 'Рассылки', icon: '📤', roles: ['admin'] },
    { path: 'feedback', label: 'Отзывы', icon: '💬', roles: ['staff', 'manager', 'director', 'admin'] },
    { path: 'calendar', label: 'Календарь', icon: '📅', roles: ['manager', 'director', 'admin'] },
    { path: 'stoplist', label: 'Стоп-лист', icon: '🚫', roles: ['manager', 'director', 'admin'] },
    { path: 'archive', label: 'Архив', icon: '🗂️', roles: ['manager', 'director', 'admin'] },
    { path: 'admin', label: 'Управление', icon: '⚙️', roles: ['admin'] },
];
