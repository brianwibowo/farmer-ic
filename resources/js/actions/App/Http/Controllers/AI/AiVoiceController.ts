import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../../../../wayfinder'
/**
* @see \App\Http\Controllers\AI\AiVoiceController::transcribe
 * @see app/Http/Controllers/AI/AiVoiceController.php:19
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/transcribe'
 */
export const transcribe = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transcribe.url(args, options),
    method: 'post',
})

transcribe.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/voice/transcribe',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiVoiceController::transcribe
 * @see app/Http/Controllers/AI/AiVoiceController.php:19
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/transcribe'
 */
transcribe.url = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
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

    return transcribe.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiVoiceController::transcribe
 * @see app/Http/Controllers/AI/AiVoiceController.php:19
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/transcribe'
 */
transcribe.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: transcribe.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiVoiceController::transcribe
 * @see app/Http/Controllers/AI/AiVoiceController.php:19
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/transcribe'
 */
    const transcribeForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: transcribe.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiVoiceController::transcribe
 * @see app/Http/Controllers/AI/AiVoiceController.php:19
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/transcribe'
 */
        transcribeForm.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: transcribe.url(args, options),
            method: 'post',
        })
    
    transcribe.form = transcribeForm
/**
* @see \App\Http\Controllers\AI\AiVoiceController::synthesize
 * @see app/Http/Controllers/AI/AiVoiceController.php:109
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/synthesize'
 */
export const synthesize = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: synthesize.url(args, options),
    method: 'post',
})

synthesize.definition = {
    methods: ["post"],
    url: '/{current_team?}/ai/voice/synthesize',
} satisfies RouteDefinition<["post"]>

/**
* @see \App\Http\Controllers\AI\AiVoiceController::synthesize
 * @see app/Http/Controllers/AI/AiVoiceController.php:109
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/synthesize'
 */
synthesize.url = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions) => {
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

    return synthesize.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\AI\AiVoiceController::synthesize
 * @see app/Http/Controllers/AI/AiVoiceController.php:109
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/synthesize'
 */
synthesize.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: synthesize.url(args, options),
    method: 'post',
})

    /**
* @see \App\Http\Controllers\AI\AiVoiceController::synthesize
 * @see app/Http/Controllers/AI/AiVoiceController.php:109
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/synthesize'
 */
    const synthesizeForm = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
        action: synthesize.url(args, options),
        method: 'post',
    })

            /**
* @see \App\Http\Controllers\AI\AiVoiceController::synthesize
 * @see app/Http/Controllers/AI/AiVoiceController.php:109
 * @param current_team - Default: '$currentTeam'
 * @route '/{current_team?}/ai/voice/synthesize'
 */
        synthesizeForm.post = (args?: { current_team?: string | { slug: string } } | [current_team: string | { slug: string } ] | string | { slug: string }, options?: RouteQueryOptions): RouteFormDefinition<'post'> => ({
            action: synthesize.url(args, options),
            method: 'post',
        })
    
    synthesize.form = synthesizeForm
const AiVoiceController = { transcribe, synthesize }

export default AiVoiceController