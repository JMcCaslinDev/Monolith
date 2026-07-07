/** @param {string[]} categoryIds */
export function initOpenCategories(categoryIds) {
  /** @type {Record<string, boolean>} */
  const open = {};
  for (const id of categoryIds) {
    open[id] = true;
  }
  return open;
}

/** @param {Record<string, boolean>} openCategories @param {string} id */
export function toggleCategoryState(openCategories, id) {
  return { ...openCategories, [id]: !openCategories[id] };
}

/** @param {Record<string, boolean>} openCategories @param {string} id */
export function isCategoryOpen(openCategories, id) {
  return !!openCategories[id];
}

/** @param {{ scrollTop?: number } | null | undefined} panel */
export function scrollPanelToTop(panel) {
  if (panel) {
    panel.scrollTop = 0;
  }
}
