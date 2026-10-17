import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/messages'
*/
const stored9ea11ce0ed9b1a22a692f5b77006ae4 = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stored9ea11ce0ed9b1a22a692f5b77006ae4.url(args, options),
    method: 'post',
})

stored9ea11ce0ed9b1a22a692f5b77006ae4.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/messages'
*/
stored9ea11ce0ed9b1a22a692f5b77006ae4.url = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
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

    return stored9ea11ce0ed9b1a22a692f5b77006ae4.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/messages'
*/
stored9ea11ce0ed9b1a22a692f5b77006ae4.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: stored9ea11ce0ed9b1a22a692f5b77006ae4.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/messages'
*/
const stored9ea11ce0ed9b1a22a692f5b77006ae4Form = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: stored9ea11ce0ed9b1a22a692f5b77006ae4.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/messages'
*/
stored9ea11ce0ed9b1a22a692f5b77006ae4Form.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: stored9ea11ce0ed9b1a22a692f5b77006ae4.url(args, options),
    method: 'post',
})

stored9ea11ce0ed9b1a22a692f5b77006ae4.form = stored9ea11ce0ed9b1a22a692f5b77006ae4Form
/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/conversations/{conversation}/messages'
*/
const store297d73f8b26d2a49485ac523c54950f6 = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store297d73f8b26d2a49485ac523c54950f6.url(args, options),
    method: 'post',
})

store297d73f8b26d2a49485ac523c54950f6.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/conversations/{conversation}/messages',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/conversations/{conversation}/messages'
*/
store297d73f8b26d2a49485ac523c54950f6.url = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
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

    return store297d73f8b26d2a49485ac523c54950f6.definition.url
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
store297d73f8b26d2a49485ac523c54950f6.post = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store297d73f8b26d2a49485ac523c54950f6.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/conversations/{conversation}/messages'
*/
const store297d73f8b26d2a49485ac523c54950f6Form = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store297d73f8b26d2a49485ac523c54950f6.url(args, options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\AI\AiMessageController::store
* @see app/Http/Controllers/AI/AiMessageController.php:22
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai/conversations/{conversation}/messages'
*/
store297d73f8b26d2a49485ac523c54950f6Form.post = (args: { current_team?: string | { slug: string }, conversation: string | number | { id: string | number } } | [current_team: string | { slug: string }, conversation: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
    action: store297d73f8b26d2a49485ac523c54950f6.url(args, options),
    method: 'post',
})

store297d73f8b26d2a49485ac523c54950f6.form = store297d73f8b26d2a49485ac523c54950f6Form

/**
* Multiple routes resolve to \App\Http\Controllers\AI\AiMessageController::store, so this export is a
* dictionary keyed by URI rather than a callable, with the verbs prefixed where two routes share a URI.
* Call a specific route with `store['<key>'](...)`, or import the route by name from your
* generated `routes/` directory.
*/
export const store = {
    '/{current_team?}/ai/messages': stored9ea11ce0ed9b1a22a692f5b77006ae4,
    '/{current_team?}/ai/conversations/{conversation}/messages': store297d73f8b26d2a49485ac523c54950f6,
}

const AiMessageController = { store }

export default AiMessageController