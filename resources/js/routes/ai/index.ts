import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition, applyUrlDefaults, validateParameters } from './../../wayfinder'
import conversations from './conversations'
import messages from './messages'
import conversationMessages from './conversation-messages'
import voice from './voice'
import knowledge from './knowledge'
/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
export const page = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: page.url(args, options),
    method: 'get',
})

page.definition = {
    methods: ["get","head"],
    url: '/{current_team?}/ai',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
page.url = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions) => {
    if (typeof args === 'string' || typeof args === 'number') {
        args = { current_team: args }
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
        current_team: args?.current_team ?? '$currentTeam',
    }

    return page.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
page.get = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: page.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
page.head = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: page.url(args, options),
    method: 'head',
})

/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
const pageForm = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: page.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
pageForm.get = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: page.url(args, options),
    method: 'get',
})

/**
* @see routes/web.php:70
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/ai'
*/
pageForm.head = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: page.url(args, {
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

page.form = pageForm

const ai = {
    page: Object.assign(page, page),
    conversations: Object.assign(conversations, conversations),
    messages: Object.assign(messages, messages),
    conversationMessages: Object.assign(conversationMessages, conversationMessages),
    voice: Object.assign(voice, voice),
    knowledge: Object.assign(knowledge, knowledge),
}

export default ai