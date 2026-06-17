import { apiFetch } from '../api';
import { escapeHtml, loadingHtml } from '../dom';
import { showToast } from '../toast';
import { confirmDialog } from '../telegram';
import { getSelectedRestaurantId } from '../restaurant-context';

const MONTH_NAMES = [
    'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
    'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь',
];

const DAY_NAMES = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

const NO_TYPE_LABEL = 'Без типа';
const NO_TYPE_COLOR = '#9ca3af';

// Fixed event types — kept in sync with the App\Enums\EventType PHP enum.
const EVENT_TYPES = [
    { slug: 'banquet', name: 'Банкет', color: '#e74c3c' },
    { slug: 'reserve', name: 'Резерв', color: '#27ae60' },
];

export async function render(container, ctx) {
    const restaurants = ctx.user.restaurants ?? [];

    if (restaurants.length === 0) {
        container.innerHTML = '<p class="p-4 text-sm text-hint">Вам не назначен ресторан.</p>';

        return;
    }

    const restaurantId = getSelectedRestaurantId(restaurants);

    let viewDate = startOfMonth(new Date());
    let events = [];
    let activeFilters = new Set();
    let selectedDay = null;

    container.innerHTML = `
        <div class="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
            <div id="cal-filters" class="flex flex-wrap gap-2"></div>
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1.7fr)_minmax(0,1fr)] lg:items-start">
                <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                    <div class="flex items-center justify-between">
                        <button type="button" id="cal-prev"
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-bg text-lg leading-none">‹</button>
                        <h2 id="cal-title" class="text-sm font-semibold"></h2>
                        <button type="button" id="cal-next"
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-bg text-lg leading-none">›</button>
                    </div>
                    <div id="cal-grid"></div>
                </div>
                <div id="cal-day" class="lg:sticky lg:top-20"></div>
            </div>
        </div>
    `;

    const filtersEl = container.querySelector('#cal-filters');
    const titleEl = container.querySelector('#cal-title');
    const gridEl = container.querySelector('#cal-grid');
    const dayEl = container.querySelector('#cal-day');

    container.querySelector('#cal-prev').addEventListener('click', () => {
        viewDate = addMonths(viewDate, -1);
        selectedDay = null;
        renderGrid();
        renderDayPanel();
    });

    container.querySelector('#cal-next').addEventListener('click', () => {
        viewDate = addMonths(viewDate, 1);
        selectedDay = null;
        renderGrid();
        renderDayPanel();
    });

    async function load() {
        gridEl.innerHTML = loadingHtml();

        try {
            const eventsRes = await apiFetch(`/events?restaurant_id=${restaurantId}`);

            events = eventsRes.data;
            activeFilters = new Set(buildFilters().map((filter) => filter.name));

            if (selectedDay === null) {
                const today = toDateKey(new Date());

                if (today.startsWith(`${viewDate.getFullYear()}-${pad(viewDate.getMonth() + 1)}`)) {
                    selectedDay = today;
                }
            }

            renderFilters();
            renderGrid();
            renderDayPanel();
        } catch (error) {
            gridEl.innerHTML = `<p class="text-sm text-destructive">${escapeHtml(error.message)}</p>`;
        }
    }

    /**
     * Distinct {name, color} pairs present across the current events, used both
     * for the filter chips and the colored day dots.
     */
    function buildFilters() {
        const seen = new Map();

        events.forEach((event) => {
            const name = event.type_name ?? NO_TYPE_LABEL;

            if (!seen.has(name)) {
                seen.set(name, { name, color: event.type_color ?? NO_TYPE_COLOR });
            }
        });

        return [...seen.values()];
    }

    function renderFilters() {
        const filters = buildFilters();

        if (filters.length === 0) {
            filtersEl.innerHTML = '';

            return;
        }

        filtersEl.innerHTML = filters.map((filter) => {
            const active = activeFilters.has(filter.name);

            return `
                <button type="button" data-filter="${escapeHtml(filter.name)}"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium ${active ? 'bg-bg' : 'opacity-40'}">
                    <span class="h-2.5 w-2.5 rounded-full" style="background:${escapeHtml(filter.color)}"></span>
                    ${escapeHtml(filter.name)}
                </button>
            `;
        }).join('');

        filtersEl.querySelectorAll('[data-filter]').forEach((button) => {
            button.addEventListener('click', () => {
                const name = button.dataset.filter;

                if (activeFilters.has(name)) {
                    activeFilters.delete(name);
                } else {
                    activeFilters.add(name);
                }

                renderFilters();
                renderGrid();

                if (selectedDay) {
                    renderDay(selectedDay);
                }
            });
        });
    }

    function visibleEventsForDay(dateKey) {
        return events
            .filter((event) => event.event_date === dateKey)
            .filter((event) => activeFilters.has(event.type_name ?? NO_TYPE_LABEL))
            .sort((a, b) => (a.event_time ?? '').localeCompare(b.event_time ?? ''));
    }

    function renderGrid() {
        titleEl.textContent = `${MONTH_NAMES[viewDate.getMonth()]} ${viewDate.getFullYear()}`;

        const year = viewDate.getFullYear();
        const month = viewDate.getMonth();
        const firstWeekday = (new Date(year, month, 1).getDay() + 6) % 7; // Monday = 0
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayKey = toDateKey(new Date());

        const cells = [];

        for (let i = 0; i < firstWeekday; i++) {
            cells.push('<div></div>');
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const key = `${year}-${pad(month + 1)}-${pad(day)}`;
            const dayEvents = visibleEventsForDay(key);
            const isToday = key === todayKey;
            const isSelected = key === selectedDay;

            // Mobile: compact colored dots. Desktop: chips with the event title.
            const dots = dayEvents.slice(0, 4).map((event) => `
                <span class="h-1.5 w-1.5 rounded-full" style="background:${escapeHtml(event.type_color ?? NO_TYPE_COLOR)}"></span>
            `).join('');

            const chips = dayEvents.slice(0, 3).map((event) => `
                <span class="flex w-full items-center gap-1 truncate rounded px-1 py-0.5 text-left text-xs"
                    style="background:${escapeHtml(event.type_color ?? NO_TYPE_COLOR)}1a">
                    <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background:${escapeHtml(event.type_color ?? NO_TYPE_COLOR)}"></span>
                    <span class="truncate">${event.event_time ? `${escapeHtml(event.event_time)} ` : ''}${escapeHtml(event.title)}</span>
                </span>
            `).join('');

            const overflow = dayEvents.length > 3
                ? `<span class="px-1 text-left text-xs text-hint">+${dayEvents.length - 3}</span>`
                : '';

            cells.push(`
                <button type="button" data-day="${key}"
                    class="flex aspect-square flex-col items-center gap-1 rounded-lg p-1 text-sm sm:aspect-auto sm:min-h-24 sm:items-stretch
                        ${isSelected ? 'bg-link/10 ring-1 ring-link' : 'hover:bg-bg'} ${isToday ? 'font-bold text-link' : ''}">
                    <span class="sm:px-1">${day}</span>
                    <span class="flex flex-wrap justify-center gap-0.5 sm:hidden">${dots}</span>
                    <span class="hidden min-w-0 flex-col gap-0.5 sm:flex">${chips}${overflow}</span>
                </button>
            `);
        }

        gridEl.innerHTML = `
            <div class="grid grid-cols-7 gap-1">
                ${DAY_NAMES.map((name) => `<div class="py-1 text-center text-xs text-hint">${name}</div>`).join('')}
                ${cells.join('')}
            </div>
        `;

        gridEl.querySelectorAll('[data-day]').forEach((button) => {
            button.addEventListener('click', () => {
                selectedDay = button.dataset.day;
                renderGrid();
                renderDay(selectedDay);
            });
        });
    }

    function renderDayPanel() {
        if (selectedDay) {
            renderDay(selectedDay);

            return;
        }

        dayEl.innerHTML = `
            <div class="flex items-center justify-center rounded-2xl bg-surface p-8 text-sm text-hint shadow-sm">
                Выберите день
            </div>
        `;
    }

    function renderDay(dateKey) {
        const dayEvents = visibleEventsForDay(dateKey);

        dayEl.innerHTML = `
            <div class="flex flex-col gap-3 rounded-2xl bg-surface p-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold">${escapeHtml(formatHumanDate(dateKey))}</h3>
                    <button type="button" id="cal-add"
                        class="rounded-xl bg-button px-3 py-1.5 text-xs font-semibold text-button-text">
                        Добавить
                    </button>
                </div>
                <div class="flex flex-col gap-2">
                    ${dayEvents.length === 0
                        ? '<p class="text-sm text-hint">Событий нет.</p>'
                        : dayEvents.map((event) => eventCard(event)).join('')}
                </div>
                <div id="cal-form"></div>
            </div>
        `;

        dayEl.querySelector('#cal-add').addEventListener('click', () => renderForm(dateKey, null));

        dayEl.querySelectorAll('[data-edit]').forEach((button) => {
            button.addEventListener('click', () => {
                const event = events.find((item) => String(item.id) === button.dataset.edit);

                if (event) {
                    renderForm(dateKey, event);
                }
            });
        });

        dayEl.querySelectorAll('[data-delete]').forEach((button) => {
            button.addEventListener('click', async () => {
                if (!(await confirmDialog('Удалить это событие?'))) {
                    return;
                }

                try {
                    await apiFetch(`/events/${button.dataset.delete}`, { method: 'DELETE' });
                    await reload(dateKey);
                } catch (error) {
                    showToast(error.message, { type: 'error' });
                }
            });
        });
    }

    function eventCard(event) {
        const time = event.event_time ? `${escapeHtml(event.event_time)} · ` : '';

        return `
            <div class="flex items-start justify-between gap-3 rounded-xl bg-bg p-3">
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:${escapeHtml(event.type_color ?? NO_TYPE_COLOR)}"></span>
                        <p class="truncate text-sm font-medium">${escapeHtml(event.title)}</p>
                    </div>
                    <p class="text-xs text-hint">${time}${escapeHtml(event.type_name ?? NO_TYPE_LABEL)}${event.guests ? ` · ${event.guests} гост.` : ''}</p>
                    ${event.contact ? `<p class="text-xs text-hint">${escapeHtml(event.contact)}</p>` : ''}
                    ${event.notes ? `<p class="whitespace-pre-line text-xs italic text-hint">${escapeHtml(event.notes)}</p>` : ''}
                </div>
                ${event.editable ? `
                    <div class="flex shrink-0 gap-2">
                        <button type="button" data-edit="${escapeHtml(event.id)}" class="text-xs font-semibold text-link">Изм.</button>
                        <button type="button" data-delete="${escapeHtml(event.id)}" class="text-xs font-semibold text-destructive">Удал.</button>
                    </div>
                ` : ''}
            </div>
        `;
    }

    function renderForm(dateKey, event) {
        const formEl = dayEl.querySelector('#cal-form');
        const isEdit = event !== null;

        formEl.innerHTML = `
            <form class="flex flex-col gap-2 border-t border-separator pt-3">
                <input type="text" name="title" placeholder="Название" required autocomplete="off"
                    value="${isEdit ? escapeHtml(event.title) : ''}"
                    class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                <div class="flex gap-2">
                    <input type="date" name="event_date" required value="${isEdit ? escapeHtml(event.event_date) : dateKey}"
                        class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <input type="time" name="event_time" value="${isEdit && event.event_time ? escapeHtml(event.event_time) : ''}"
                        class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                </div>
                <select name="event_type" class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <option value="">${NO_TYPE_LABEL}</option>
                    ${EVENT_TYPES.map((type) => `
                        <option value="${type.slug}" ${isEdit && event.event_type === type.slug ? 'selected' : ''}>
                            ${escapeHtml(type.name)}
                        </option>
                    `).join('')}
                </select>
                <div class="flex gap-2">
                    <input type="number" name="guests" min="0" placeholder="Гостей" value="${isEdit && event.guests ? event.guests : ''}"
                        class="w-24 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                    <input type="text" name="contact" placeholder="Контакт" autocomplete="off"
                        value="${isEdit && event.contact ? escapeHtml(event.contact) : ''}"
                        class="flex-1 rounded-xl border border-separator bg-bg px-3 py-2 text-sm">
                </div>
                <textarea name="notes" placeholder="Заметки" rows="2"
                    class="rounded-xl border border-separator bg-bg px-3 py-2 text-sm">${isEdit && event.notes ? escapeHtml(event.notes) : ''}</textarea>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 rounded-xl bg-button px-4 py-2 text-sm font-semibold text-button-text">
                        ${isEdit ? 'Сохранить' : 'Добавить'}
                    </button>
                    <button type="button" data-cancel class="rounded-xl border border-separator px-4 py-2 text-sm font-medium">
                        Отмена
                    </button>
                </div>
            </form>
        `;

        const form = formEl.querySelector('form');

        form.querySelector('[data-cancel]').addEventListener('click', () => {
            formEl.innerHTML = '';
        });

        form.addEventListener('submit', async (submitEvent) => {
            submitEvent.preventDefault();

            const body = {
                restaurant_id: restaurantId,
                title: form.elements.title.value.trim(),
                event_date: form.elements.event_date.value,
                event_time: form.elements.event_time.value || null,
                event_type: form.elements.event_type.value || null,
                guests: form.elements.guests.value ? Number(form.elements.guests.value) : 0,
                contact: form.elements.contact.value.trim() || null,
                notes: form.elements.notes.value.trim() || null,
            };

            if (!body.title || !body.event_date) {
                return;
            }

            try {
                if (isEdit) {
                    await apiFetch(`/events/${event.id}`, { method: 'PATCH', body });
                } else {
                    await apiFetch('/events', { method: 'POST', body });
                }

                await reload(body.event_date);
            } catch (error) {
                showToast(error.message, { type: 'error' });
            }
        });
    }

    // Reload data from the server and keep the day panel open if one was selected.
    async function reload(dayToKeep) {
        await load();

        if (dayToKeep) {
            selectedDay = dayToKeep;
            renderGrid();
            renderDay(dayToKeep);
        }
    }

    function startOfMonth(date) {
        return new Date(date.getFullYear(), date.getMonth(), 1);
    }

    function addMonths(date, delta) {
        return new Date(date.getFullYear(), date.getMonth() + delta, 1);
    }

    function toDateKey(date) {
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function pad(value) {
        return String(value).padStart(2, '0');
    }

    function formatHumanDate(key) {
        const [year, month, day] = key.split('-').map(Number);

        return `${day} ${MONTH_NAMES[month - 1].toLowerCase()} ${year}`;
    }

    await load();
}
