import { queryParams, type RouteQueryOptions, type RouteDefinition, type RouteFormDefinition } from './../../wayfinder'
/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
const Controllerb048a0b5d791fb5ca2f61779e8b831e6 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controllerb048a0b5d791fb5ca2f61779e8b831e6.url(options),
    method: 'get',
})

Controllerb048a0b5d791fb5ca2f61779e8b831e6.definition = {
    methods: ["get","head"],
    url: '/finance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
Controllerb048a0b5d791fb5ca2f61779e8b831e6.url = (options?: RouteQueryOptions) => {
    return Controllerb048a0b5d791fb5ca2f61779e8b831e6.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
Controllerb048a0b5d791fb5ca2f61779e8b831e6.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controllerb048a0b5d791fb5ca2f61779e8b831e6.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
Controllerb048a0b5d791fb5ca2f61779e8b831e6.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: Controllerb048a0b5d791fb5ca2f61779e8b831e6.url(options),
    method: 'head',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
const Controllerb048a0b5d791fb5ca2f61779e8b831e6Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controllerb048a0b5d791fb5ca2f61779e8b831e6.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
Controllerb048a0b5d791fb5ca2f61779e8b831e6Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controllerb048a0b5d791fb5ca2f61779e8b831e6.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/finance'
*/
Controllerb048a0b5d791fb5ca2f61779e8b831e6Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controllerb048a0b5d791fb5ca2f61779e8b831e6.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

Controllerb048a0b5d791fb5ca2f61779e8b831e6.form = Controllerb048a0b5d791fb5ca2f61779e8b831e6Form
/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
const Controller2e02fe2508d8fb39a7f2fb8f4f79b56b = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url(options),
    method: 'get',
})

Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.definition = {
    methods: ["get","head"],
    url: '/kebun/detail',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url = (options?: RouteQueryOptions) => {
    return Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url(options),
    method: 'head',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
const Controller2e02fe2508d8fb39a7f2fb8f4f79b56bForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
Controller2e02fe2508d8fb39a7f2fb8f4f79b56bForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun/detail'
*/
Controller2e02fe2508d8fb39a7f2fb8f4f79b56bForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

Controller2e02fe2508d8fb39a7f2fb8f4f79b56b.form = Controller2e02fe2508d8fb39a7f2fb8f4f79b56bForm
/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
const Controller8cb2b5d6234ebdf32eb72cb0c79c5b71 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url(options),
    method: 'get',
})

Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.definition = {
    methods: ["get","head"],
    url: '/kebun',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url = (options?: RouteQueryOptions) => {
    return Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url(options),
    method: 'head',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
const Controller8cb2b5d6234ebdf32eb72cb0c79c5b71Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
Controller8cb2b5d6234ebdf32eb72cb0c79c5b71Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/kebun'
*/
Controller8cb2b5d6234ebdf32eb72cb0c79c5b71Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

Controller8cb2b5d6234ebdf32eb72cb0c79c5b71.form = Controller8cb2b5d6234ebdf32eb72cb0c79c5b71Form
/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
const Controller58c971c77e0968848b2dcf0013ef50ef = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller58c971c77e0968848b2dcf0013ef50ef.url(options),
    method: 'get',
})

Controller58c971c77e0968848b2dcf0013ef50ef.definition = {
    methods: ["get","head"],
    url: '/aktivitas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
Controller58c971c77e0968848b2dcf0013ef50ef.url = (options?: RouteQueryOptions) => {
    return Controller58c971c77e0968848b2dcf0013ef50ef.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
Controller58c971c77e0968848b2dcf0013ef50ef.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller58c971c77e0968848b2dcf0013ef50ef.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
Controller58c971c77e0968848b2dcf0013ef50ef.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: Controller58c971c77e0968848b2dcf0013ef50ef.url(options),
    method: 'head',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
const Controller58c971c77e0968848b2dcf0013ef50efForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller58c971c77e0968848b2dcf0013ef50ef.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
Controller58c971c77e0968848b2dcf0013ef50efForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller58c971c77e0968848b2dcf0013ef50ef.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/aktivitas'
*/
Controller58c971c77e0968848b2dcf0013ef50efForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller58c971c77e0968848b2dcf0013ef50ef.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

Controller58c971c77e0968848b2dcf0013ef50ef.form = Controller58c971c77e0968848b2dcf0013ef50efForm
/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
const Controller00edbf1602507f5a0bcc065f9a50d23d = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller00edbf1602507f5a0bcc065f9a50d23d.url(options),
    method: 'get',
})

Controller00edbf1602507f5a0bcc065f9a50d23d.definition = {
    methods: ["get","head"],
    url: '/rincian-aktivitas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
Controller00edbf1602507f5a0bcc065f9a50d23d.url = (options?: RouteQueryOptions) => {
    return Controller00edbf1602507f5a0bcc065f9a50d23d.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
Controller00edbf1602507f5a0bcc065f9a50d23d.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controller00edbf1602507f5a0bcc065f9a50d23d.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
Controller00edbf1602507f5a0bcc065f9a50d23d.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: Controller00edbf1602507f5a0bcc065f9a50d23d.url(options),
    method: 'head',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
const Controller00edbf1602507f5a0bcc065f9a50d23dForm = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller00edbf1602507f5a0bcc065f9a50d23d.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
Controller00edbf1602507f5a0bcc065f9a50d23dForm.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller00edbf1602507f5a0bcc065f9a50d23d.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/rincian-aktivitas'
*/
Controller00edbf1602507f5a0bcc065f9a50d23dForm.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controller00edbf1602507f5a0bcc065f9a50d23d.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

Controller00edbf1602507f5a0bcc065f9a50d23d.form = Controller00edbf1602507f5a0bcc065f9a50d23dForm
/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
const Controllere19ee86e9cf603ce1a59a1ec5d21dec5 = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url(options),
    method: 'get',
})

Controllere19ee86e9cf603ce1a59a1ec5d21dec5.definition = {
    methods: ["get","head"],
    url: '/settings/appearance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url = (options?: RouteQueryOptions) => {
    return Controllere19ee86e9cf603ce1a59a1ec5d21dec5.definition.url + queryParams(options)
}

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
Controllere19ee86e9cf603ce1a59a1ec5d21dec5.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
Controllere19ee86e9cf603ce1a59a1ec5d21dec5.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url(options),
    method: 'head',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
const Controllere19ee86e9cf603ce1a59a1ec5d21dec5Form = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
Controllere19ee86e9cf603ce1a59a1ec5d21dec5Form.get = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url(options),
    method: 'get',
})

/**
* @see \Inertia\Controller::__invoke
* @see vendor/inertiajs/inertia-laravel/src/Controller.php:13
* @route '/settings/appearance'
*/
Controllere19ee86e9cf603ce1a59a1ec5d21dec5Form.head = (options?: RouteQueryOptions): RouteFormDefinition<'get'> => ({
    action: Controllere19ee86e9cf603ce1a59a1ec5d21dec5.url({
        [options?.mergeQuery ? 'mergeQuery' : 'query']: {
            _method: 'HEAD',
            ...(options?.query ?? options?.mergeQuery ?? {}),
        }
    }),
    method: 'get',
})

Controllere19ee86e9cf603ce1a59a1ec5d21dec5.form = Controllere19ee86e9cf603ce1a59a1ec5d21dec5Form

/**
* Multiple routes resolve to \Inertia\Controller::Controller, so this export is a
* dictionary keyed by URI rather than a callable, with the verbs prefixed where two routes share a URI.
* Call a specific route with `Controller['<key>'](...)`, or import the route by name from your
* generated `routes/` directory.
*/
const Controller = {
    '/finance': Controllerb048a0b5d791fb5ca2f61779e8b831e6,
    '/kebun/detail': Controller2e02fe2508d8fb39a7f2fb8f4f79b56b,
    '/kebun': Controller8cb2b5d6234ebdf32eb72cb0c79c5b71,
    '/aktivitas': Controller58c971c77e0968848b2dcf0013ef50ef,
    '/rincian-aktivitas': Controller00edbf1602507f5a0bcc065f9a50d23d,
    '/settings/appearance': Controllere19ee86e9cf603ce1a59a1ec5d21dec5,
}

export default Controller