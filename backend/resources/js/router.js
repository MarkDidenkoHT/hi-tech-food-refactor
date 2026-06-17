import { findRoute } from './routes';
import { renderShell, parentPath } from './layout';
import { getTelegram } from './telegram';
import { logout } from './auth';
import { setSelectedRestaurantId } from './restaurant-context';

function currentPath() {
    return decodeURIComponent(location.hash.replace(/^#\/?/, ''));
}

/**
 * Start the hash-based router and render the initial route.
 *
 * @param {HTMLElement} root
 * @param {object} ctx
 */
export function startRouter(root, ctx) {
    const tg = getTelegram();

    if (tg?.BackButton) {
        tg.BackButton.onClick(() => {
            location.hash = `#/${parentPath(currentPath())}`;
        });
    }

    document.addEventListener('click', (event) => {
        const dropdown = root.querySelector('#nav-menu-dropdown');
        const button = root.querySelector('#nav-menu');

        if (!dropdown || dropdown.classList.contains('hidden')) {
            return;
        }

        if (!dropdown.contains(event.target) && !button.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });

    window.addEventListener('hashchange', () => renderRoute(root, ctx));
    renderRoute(root, ctx);
}

async function renderRoute(root, ctx) {
    const path = currentPath();
    const route = findRoute(path);

    if (route.roles && !route.roles.includes(ctx.user.role)) {
        location.hash = '#/';
        return;
    }

    root.innerHTML = renderShell(ctx, route, path);

    const tg = getTelegram();

    if (tg?.BackButton) {
        if (path === '') {
            tg.BackButton.hide();
        } else {
            tg.BackButton.show();
        }
    }

    const menuButton = root.querySelector('#nav-menu');
    const menuDropdown = root.querySelector('#nav-menu-dropdown');
    menuButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        menuDropdown.classList.toggle('hidden');
    });

    const backButton = root.querySelector('#nav-back');
    backButton?.addEventListener('click', () => {
        location.hash = `#/${parentPath(path)}`;
    });

    const logoutButton = root.querySelector('#nav-logout');
    logoutButton?.addEventListener('click', async () => {
        await logout();
        location.reload();
    });

    const restaurantSelect = root.querySelector('#restaurant-select');
    restaurantSelect?.addEventListener('change', () => {
        setSelectedRestaurantId(Number(restaurantSelect.value));
        renderRoute(root, ctx);
    });

    const view = root.querySelector('#view');
    const module = await route.view();
    await module.render(view, ctx, route);
}
