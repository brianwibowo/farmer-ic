import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\Teams\TeamMemberController::update
 * @see app/Http/Controllers/Teams/TeamMemberController.php:19
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
export const update = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

update.definition = {
    methods: ["patch"],
    url: '/settings/teams/{team?}/members/{user}',
} satisfies RouteDefinition<["patch"]>

/**
* @see \App\Http\Controllers\Teams\TeamMemberController::update
 * @see app/Http/Controllers/Teams/TeamMemberController.php:19
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
update.url = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    team: args[0],
                    user: args[1],
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
                                user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return update.definition.url
            .replace('{team?}', parsedArgs.team?.toString() ?? '')
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Teams\TeamMemberController::update
 * @see app/Http/Controllers/Teams/TeamMemberController.php:19
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
update.patch = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: update.url(args, options),
    method: 'patch',
})

    /**
* @see \App\Http\Controllers\Teams\TeamMemberController::update
 * @see app/Http/Controllers/Teams/TeamMemberController.php:19
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
    const updateForm = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: update.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'PATCH',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Teams\TeamMemberController::update
 * @see app/Http/Controllers/Teams/TeamMemberController.php:19
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
        updateForm.patch = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
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
* @see \App\Http\Controllers\Teams\TeamMemberController::destroy
 * @see app/Http/Controllers/Teams/TeamMemberController.php:38
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
export const destroy = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

destroy.definition = {
    methods: ["delete"],
    url: '/settings/teams/{team?}/members/{user}',
} satisfies RouteDefinition<["delete"]>

/**
* @see \App\Http\Controllers\Teams\TeamMemberController::destroy
 * @see app/Http/Controllers/Teams/TeamMemberController.php:38
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
destroy.url = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions) => {
    if (Array.isArray(args)) {
        args = {
                    team: args[0],
                    user: args[1],
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
                                user: typeof args.user === 'object'
                ? args.user.id
                : args.user,
                }

    return destroy.definition.url
            .replace('{team?}', parsedArgs.team?.toString() ?? '')
            .replace('{user}', parsedArgs.user.toString())
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\Teams\TeamMemberController::destroy
 * @see app/Http/Controllers/Teams/TeamMemberController.php:38
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
destroy.delete = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: destroy.url(args, options),
    method: 'delete',
})

    /**
* @see \App\Http\Controllers\Teams\TeamMemberController::destroy
 * @see app/Http/Controllers/Teams/TeamMemberController.php:38
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
    const destroyForm = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: destroy.url(args, {
                    [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                        _method: 'DELETE',
                        ...(options?.query ?? options?.mergeQuery ?? {}),
                    }
                }),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\Teams\TeamMemberController::destroy
 * @see app/Http/Controllers/Teams/TeamMemberController.php:38
 * @param team - Default: '$currentTeam'
 * @route '/settings/teams/{team?}/members/{user}'
 */
        destroyForm.delete = (args: { team?: string | { slug: string }, user: number | { id: number } } | [team: string | { slug: string }, user: number | { id: number } ], options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: destroy.url(args, {
                        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
                            _method: 'DELETE',
                            ...(options?.query ?? options?.mergeQuery ?? {}),
                        }
                    }),
            method: 'post',
        })
    
    destroy.form = destroyForm
const TeamMemberController = { update, destroy }

export default TeamMemberController