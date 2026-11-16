// CreateFromRecord actions (AdminController::resourceProps): the create form
// of the module with fields taken from a record, e.g. ?parent_id=12.
export function recordActionHref(routeName, action, item) {
    const query = Object.fromEntries(
        Object.entries(action.fill || {}).map(([field, attribute]) => [field, item[attribute]])
    )

    return route(`${routeName}.create`, query)
}
