import { apiFetch } from './api';

/**
 * In-memory caches that live for the duration of the SPA session (a page
 * reload clears them). All entries are keyed by restaurant id so switching
 * restaurants naturally uses a separate cache slot.
 */

const menuRequests = new Map(); // restaurantId -> Promise<string[]>
const stoplistData = new Map(); // restaurantId -> stoplist response

/**
 * Full menu item list for a restaurant. The menu rarely changes, so we fetch
 * it once per session and share the in-flight promise across callers. Filter
 * the result client-side instead of round-tripping per keystroke.
 *
 * @param {number} restaurantId
 * @returns {Promise<string[]>}
 */
export function getMenuItems(restaurantId) {
    if (!menuRequests.has(restaurantId)) {
        const request = apiFetch(`/stoplist/menu?restaurant_id=${restaurantId}`)
            .then(({ data }) => data)
            .catch((error) => {
                // Drop the failed promise so the next call retries.
                menuRequests.delete(restaurantId);

                throw error;
            });

        menuRequests.set(restaurantId, request);
    }

    return menuRequests.get(restaurantId);
}

/**
 * Forget the cached menu for a restaurant (call after editing menu items).
 *
 * @param {number} restaurantId
 */
export function invalidateMenuItems(restaurantId) {
    menuRequests.delete(restaurantId);
}

/**
 * Last-known stoplist response for a restaurant, or undefined if never loaded
 * this session. Use for an instant first paint while revalidating.
 *
 * @param {number} restaurantId
 */
export function readStoplist(restaurantId) {
    return stoplistData.get(restaurantId);
}

/**
 * Fetch the current stoplist and store it in the cache.
 *
 * @param {number} restaurantId
 */
export function fetchStoplist(restaurantId) {
    return apiFetch(`/stoplist?restaurant_id=${restaurantId}`).then((data) => {
        stoplistData.set(restaurantId, data);

        return data;
    });
}

/**
 * Forget the cached stoplist for a restaurant (call after a mutation).
 *
 * @param {number} restaurantId
 */
export function invalidateStoplist(restaurantId) {
    stoplistData.delete(restaurantId);
}
