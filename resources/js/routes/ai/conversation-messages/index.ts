import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}/messages'
 */
export const store = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/conversations/{conversation}/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}/messages'
 */
store.url = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    current_team: args[0],
                    conversation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "current_team",
        ])

    const parsedArgs = {
                        current_team: (typeof args.current_team === 'object'
                ? args.current_team.slug
                : args.current_team) ?? '$currentTeam',
                                conversation: typeof args.conversation === 'object'
                ? args.conversation.id
                : args.conversation,
                }

    return store.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace('{conversation}', parsedArgs.conversation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}/messages'
 */
store.post = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}/messages'
 */
    const storeForm = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiMessageController::store
 * @see app/Http/Controllers/AI/AiMessageController.php:22
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}/messages'
 */
        storeForm.post = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
const conversationMessages = {
    store: Object.assign(store, store),
}

export default conversationMessages