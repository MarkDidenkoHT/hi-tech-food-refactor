const STORAGE_KEY = 'restaurant_id';

/**
 * Get the currently selected restaurant id, falling back to the first
 * restaurant the user belongs to if nothing valid is stored.
 *
 * @param {Array<{id: number}>} restaurants
 * @returns {number|null}
 */
export function getSelectedRestaurantId(restaurants) {
    const stored = Number(localStorage.getItem(STORAGE_KEY));

    if (restaurants.some((restaurant) => restaurant.id === stored)) {
        return stored;
    }

    return restaurants[0]?.id ?? null;
}

/**
 * Persist the selected restaurant id so it carries over between views.
 *
 * @param {number} id
 */
export function setSelectedRestaurantId(id) {
    localStorage.setItem(STORAGE_KEY, String(id));
}
