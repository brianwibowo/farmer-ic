import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/messages'
 */
export const store = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/messages'
 */
store.url = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { current_team: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'slug' in args) {
            args = { current_team: args.slug }
        }
    
    if (Array.isArray(args)) {
        args = {
                    current_team: args[0],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "current_team",
        ])

    const parsedArgs = {
                        current_team: (typeof args?.current_team === 'object'
                ? args.current_team.slug
                : args?.current_team) ?? '$currentTeam',
                }

    return store.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/messages'
 */
store.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/messages'
 */
    const storeForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/messages'
 */
        storeForm.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const messages = {
    store: Object.assign(store, store),
}

export default messages