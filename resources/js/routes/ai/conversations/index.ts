import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
export const index = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/{current_team?}/ai/conversations',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
index.url = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
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

    return index.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
index.get = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
index.head = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
    const indexForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
        indexForm.get = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AI\AiConversationController::index
 * @see app/Http/Controllers/AI/AiConversationController.php:16
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
        indexForm.head = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    index.form = indexForm
/**
* @see \App\Http\Controllers\AI\AiConversationController::store
 * @see app/Http/Controllers/AI/AiConversationController.php:29
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
export const store = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/conversations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiConversationController::store
 * @see app/Http/Controllers/AI/AiConversationController.php:29
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
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
* @see \App\Http\Controllers\AI\AiConversationController::store
 * @see app/Http/Controllers/AI/AiConversationController.php:29
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
store.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiConversationController::store
 * @see app/Http/Controllers/AI/AiConversationController.php:29
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
    const storeForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiConversationController::store
 * @see app/Http/Controllers/AI/AiConversationController.php:29
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations'
 */
        storeForm.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
export const show = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})

show.definition = {
    methods: ["get","head"],
    url: '/{current_team?}/ai/conversations/{conversation}',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
show.url = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return show.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace('{conversation}', parsedArgs.conversation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
show.get = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: show.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
show.head = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: show.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
    const showForm = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: show.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
        showForm.get = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AI\AiConversationController::show
 * @see app/Http/Controllers/AI/AiConversationController.php:40
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/conversations/{conversation}'
 */
        showForm.head = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: show.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'HEAD',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'get',
        })
    
    show.form = showForm
const conversations = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
show: Object.assign(show, show),
}

export default conversations