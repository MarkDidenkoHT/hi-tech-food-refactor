import '../css/app.css';
import { initTelegram } from './telegram';
import { login } from './auth';
import { ApiError } from './api';
import { startRouter } from './router';
import { renderLoading, renderNotRegistered, renderError } from './views/boot';

async function bootstrap() {
    const tg = initTelegram();
    const root = document.getElementById('app');

    root.innerHTML = renderLoading();

    try {
        const user = await login();
        startRouter(root, { user, tg });
    } catch (error) {
        if (error instanceof ApiError && error.status === 403) {
            root.innerHTML = renderNotRegistered(error.message);
        } else {
            root.innerHTML = renderError(error.message ?? 'Неизвестная ошибка.');
        }
    }
}

bootstrap();
