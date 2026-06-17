import { MENU_ITEMS } from './menu-items';

const VIEW_OVERRIDES = {
    checklist: () => import('./views/checklist.js'),
    tasks: () => import('./views/tasks.js'),
    'photo-tasks': () => import('./views/photo-tasks.js'),
    stoplist: () => import('./views/stoplist.js'),
    calendar: () => import('./views/calendar.js'),
    feedback: () => import('./views/feedback.js'),
    archive: () => import('./views/archive.js'),
    mailings: () => import('./views/mailings.js'),
};

const placeholderRoutes = MENU_ITEMS
    .filter((item) => item.path !== 'admin')
    .map((item) => ({
        path: item.path,
        title: item.label,
        roles: item.roles,
        view: VIEW_OVERRIDES[item.path] ?? (() => import('./views/placeholder.js')),
    }));

/**
 * @type {Array<{path: string, title: string, roles?: string[], view: () => Promise<{render: Function}>}>}
 */
export const routes = [
    { path: '', title: 'Главная', view: () => import('./views/menu.js') },
    ...placeholderRoutes,
    { path: 'admin', title: 'Управление', roles: ['admin'], view: () => import('./views/admin/index.js') },
    { path: 'admin/restaurants', title: 'Рестораны', roles: ['admin'], view: () => import('./views/admin/restaurants.js') },
    { path: 'admin/invites', title: 'Приглашения', roles: ['admin'], view: () => import('./views/admin/invites.js') },
    { path: 'admin/users', title: 'Пользователи', roles: ['admin'], view: () => import('./views/admin/users.js') },
    { path: 'admin/cron', title: 'Автоматизация', roles: ['admin'], view: () => import('./views/admin/cron.js') },
];

export function findRoute(path) {
    return routes.find((route) => route.path === path) ?? routes[0];
}
