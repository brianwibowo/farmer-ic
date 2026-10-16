import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
export const index = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})

index.definition = {
    methods: ["get","head"],
    url: '/{current_team?}/ai/knowledge',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
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
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
index.get = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: index.url(args, options),
    method: 'get',
})
/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
index.head = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: index.url(args, options),
    method: 'head',
})

    /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
    const indexForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
        action: index.url(args, options),
        method: 'get',
    })

            /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
        indexForm.get = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
            action: index.url(args, options),
            method: 'get',
        })
            /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::index
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:15
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
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
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::store
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:28
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
export const store = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/knowledge',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::store
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:28
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
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
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::store
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:28
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
store.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::store
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:28
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
    const storeForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::store
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:28
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge'
 */
        storeForm.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::update
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:46
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}'
 */
export const update = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/{current_team?}/ai/knowledge/{document}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::update
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:46
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}'
 */
update.url = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    current_team: args[0],
                    document: args[1],
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
                                document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return update.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::update
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:46
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}'
 */
update.patch = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::update
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:46
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}'
 */
    const updateForm = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::update
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:46
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}'
 */
        updateForm.patch = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: update.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'PATCH',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    update.form = updateForm
/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::publish
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:66
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/publish'
 */
export const publish = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

publish.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/knowledge/{document}/publish',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::publish
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:66
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/publish'
 */
publish.url = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    current_team: args[0],
                    document: args[1],
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
                                document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return publish.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::publish
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:66
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/publish'
 */
publish.post = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: publish.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::publish
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:66
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/publish'
 */
    const publishForm = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: publish.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::publish
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:66
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/publish'
 */
        publishForm.post = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: publish.url(args, options),
            method: 'post',
        })
    
    publish.form = publishForm
/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::archive
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:80
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/archive'
 */
export const archive = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: archive.url(args, options),
    method: 'post',
})

archive.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/knowledge/{document}/archive',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::archive
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:80
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/archive'
 */
archive.url = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    current_team: args[0],
                    document: args[1],
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
                                document: typeof args.document === 'object'
                ? args.document.id
                : args.document,
                }

    return archive.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace('{document}', parsedArgs.document.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::archive
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:80
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/archive'
 */
archive.post = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: archive.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::archive
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:80
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/archive'
 */
    const archiveForm = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: archive.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiKnowledgeDocumentController::archive
 * @see app/Http/Controllers/AI/AiKnowledgeDocumentController.php:80
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/knowledge/{document}/archive'
 */
        archiveForm.post = (args: { current_team?: string | { slug: string }, document: string | number | { id: string | number } } | [current_team: string | { slug: string }, document: string | number | { id: string | number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: archive.url(args, options),
            method: 'post',
        })
    
    archive.form = archiveForm
const knowledge = {
    index: Object.assign(index, index),
store: Object.assign(store, store),
update: Object.assign(update, update),
publish: Object.assign(publish, publish),
archive: Object.assign(archive, archive),
}

export default knowledge