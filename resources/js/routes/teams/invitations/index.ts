import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../wayfinder'
/**
* @see \App\Http\Controllers\Teams\TeamInvitationController::store
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:23
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations'
 */
export const store = (args?: { team?: string | { slug: string } } | [team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

store.definition = {
    methods: ["post"],
    url: '/settings/teams/{team?}/invitations',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\Teams\TeamInvitationController::store
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:23
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations'
 */
store.url = (args?: { team?: string | { slug: string } } | [team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { team: args }
    }

            if (typeof args === 'object' && !Array.isArray(args) && 'slug' in args) {
            args = { team: args.slug }
        }
    
    if (Array.isArray(args)) {
        args = {
                    team: args[0],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "team",
        ])

    const parsedArgs = {
                        team: (typeof args?.team === 'object'
                ? args.team.slug
                : args?.team) ?? '$currentTeam',
                }

    return store.definition.url
            .replace('{team?}', parsedArgs.team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Teams\TeamInvitationController::store
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:23
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations'
 */
store.post = (args?: { team?: string | { slug: string } } | [team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: store.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\Teams\TeamInvitationController::store
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:23
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations'
 */
    const storeForm = (args?: { team?: string | { slug: string } } | [team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: store.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Teams\TeamInvitationController::store
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:23
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations'
 */
        storeForm.post = (args?: { team?: string | { slug: string } } | [team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: store.url(args, options),
            method: 'post',
        })
    
    store.form = storeForm
/**
* @see \App\Http\Controllers\Teams\TeamInvitationController::destroy
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:45
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations/{invitation}'
 */
export const destroy = (args: { team?: string | { slug: string }, invitation: string | { code: string } } | [team: string | { slug: string }, invitation: string | { code: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/teams/{team?}/invitations/{invitation}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Teams\TeamInvitationController::destroy
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:45
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations/{invitation}'
 */
destroy.url = (args: { team?: string | { slug: string }, invitation: string | { code: string } } | [team: string | { slug: string }, invitation: string | { code: string } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    team: args[0],
                    invitation: args[1],
                }
    }

    args = applyUrlDefaults(args)

    validateParameters(args, [
            "team",
        ])

    const parsedArgs = {
                        team: (typeof args.team === 'object'
                ? args.team.slug
                : args.team) ?? '$currentTeam',
                                invitation: typeof args.invitation === 'object'
                ? args.invitation.code
                : args.invitation,
                }

    return destroy.definition.url
            .replace('{team?}', parsedArgs.team?.toString() ?? '')
            .replace('{invitation}', parsedArgs.invitation.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Teams\TeamInvitationController::destroy
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:45
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations/{invitation}'
 */
destroy.delete = (args: { team?: string | { slug: string }, invitation: string | { code: string } } | [team: string | { slug: string }, invitation: string | { code: string } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Teams\TeamInvitationController::destroy
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:45
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations/{invitation}'
 */
    const destroyForm = (args: { team?: string | { slug: string }, invitation: string | { code: string } } | [team: string | { slug: string }, invitation: string | { code: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Teams\TeamInvitationController::destroy
 * @see app/Http/Controllers/Teams/TeamInvitationController.php:45
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/invitations/{invitation}'
 */
        destroyForm.delete = (args: { team?: string | { slug: string }, invitation: string | { code: string } } | [team: string | { slug: string }, invitation: string | { code: string } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const invitations = {
    store: Object.assign(store, store),
destroy: Object.assign(destroy, destroy),
}

export default invitations