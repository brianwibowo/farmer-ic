import { queryParams, type RouteQueryOptions, type RouteDefinition, applyUrlDefaults, validateParameters } from './../wayfinder'
/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::login
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:47
* @route '/login'
*/
export const login = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

login.definition = {
    methods: ["get","head"],
    url: '/login',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::login
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:47
* @route '/login'
*/
login.url = (options?: RouteQueryOptions) => {
    return login.definition.url + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::login
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:47
* @route '/login'
*/
login.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: login.url(options),
    method: 'get',
})

/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::login
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:47
* @route '/login'
*/
login.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: login.url(options),
    method: 'head',
})

/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::logout
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:100
* @route '/logout'
*/
export const logout = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

logout.definition = {
    methods: ["post"],
    url: '/logout',
} satisfies RouteDefinition<["post"]>

/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::logout
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:100
* @route '/logout'
*/
logout.url = (options?: RouteQueryOptions) => {
    return logout.definition.url + queryParams(options)
}

/**
* @see \Laravel\Fortify\Http\Controllers\AuthenticatedSessionController::logout
* @see vendor/laravel/fortify/src/Http/Controllers/AuthenticatedSessionController.php:100
* @route '/logout'
*/
logout.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: logout.url(options),
    method: 'post',
})

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
export const home = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

home.definition = {
    methods: ["get","head"],
    url: '/',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
home.url = (options?: RouteQueryOptions) => {
    return home.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
home.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: home.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\HomeController::__invoke
* @see app/Http/Controllers/HomeController.php:11
* @route '/'
*/
home.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: home.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\FarmManagementController::kebun
* @see app/Http/Controllers/FarmManagementController.php:37
* @route '/kebun'
*/
export const kebun = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: kebun.url(options),
    method: 'get',
})

kebun.definition = {
    methods: ["get","head"],
    url: '/kebun',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\FarmManagementController::kebun
* @see app/Http/Controllers/FarmManagementController.php:37
* @route '/kebun'
*/
kebun.url = (options?: RouteQueryOptions) => {
    return kebun.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\FarmManagementController::kebun
* @see app/Http/Controllers/FarmManagementController.php:37
* @route '/kebun'
*/
kebun.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: kebun.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\FarmManagementController::kebun
* @see app/Http/Controllers/FarmManagementController.php:37
* @route '/kebun'
*/
kebun.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: kebun.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\Owner\OwnerController::aktivitas
* @see app/Http/Controllers/Owner/OwnerController.php:71
* @route '/aktivitas'
*/
export const aktivitas = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: aktivitas.url(options),
    method: 'get',
})

aktivitas.definition = {
    methods: ["get","head"],
    url: '/aktivitas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Owner\OwnerController::aktivitas
* @see app/Http/Controllers/Owner/OwnerController.php:71
* @route '/aktivitas'
*/
aktivitas.url = (options?: RouteQueryOptions) => {
    return aktivitas.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Owner\OwnerController::aktivitas
* @see app/Http/Controllers/Owner/OwnerController.php:71
* @route '/aktivitas'
*/
aktivitas.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: aktivitas.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Owner\OwnerController::aktivitas
* @see app/Http/Controllers/Owner/OwnerController.php:71
* @route '/aktivitas'
*/
aktivitas.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: aktivitas.url(options),
    method: 'head',
})

/**
* @see routes/web.php:74
* @route '/rincian-aktivitas'
*/
export const rincianAktivitas = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: rincianAktivitas.url(options),
    method: 'get',
})

rincianAktivitas.definition = {
    methods: ["get","head"],
    url: '/rincian-aktivitas',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:74
* @route '/rincian-aktivitas'
*/
rincianAktivitas.url = (options?: RouteQueryOptions) => {
    return rincianAktivitas.definition.url + queryParams(options)
}

/**
* @see routes/web.php:74
* @route '/rincian-aktivitas'
*/
rincianAktivitas.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: rincianAktivitas.url(options),
    method: 'get',
})

/**
* @see routes/web.php:74
* @route '/rincian-aktivitas'
*/
rincianAktivitas.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: rincianAktivitas.url(options),
    method: 'head',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
export const laporan = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: laporan.url(options),
    method: 'get',
})

laporan.definition = {
    methods: ["get","head","post","put","patch","delete","options","query"],
    url: '/laporan',
} satisfies RouteDefinition<["get","head","post","put","patch","delete","options","query"]>

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.url = (options?: RouteQueryOptions) => {
    return laporan.definition.url + queryParams(options)
}

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: laporan.url(options),
    method: 'get',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: laporan.url(options),
    method: 'head',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.post = (options?: RouteQueryOptions): RouteDefinition<'post'> => ({
    url: laporan.url(options),
    method: 'post',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.put = (options?: RouteQueryOptions): RouteDefinition<'put'> => ({
    url: laporan.url(options),
    method: 'put',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.patch = (options?: RouteQueryOptions): RouteDefinition<'patch'> => ({
    url: laporan.url(options),
    method: 'patch',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.delete = (options?: RouteQueryOptions): RouteDefinition<'delete'> => ({
    url: laporan.url(options),
    method: 'delete',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.options = (options?: RouteQueryOptions): RouteDefinition<'options'> => ({
    url: laporan.url(options),
    method: 'options',
})

/**
* @see \Illuminate\Routing\RedirectController::__invoke
* @see vendor/laravel/framework/src/Illuminate/Routing/RedirectController.php:19
* @route '/laporan'
*/
laporan.query = (options?: RouteQueryOptions): RouteDefinition<'query'> => ({
    url: laporan.url(options),
    method: 'query',
})

/**
* @see \App\Http\Controllers\Finance\FinanceController::finance
* @see app/Http/Controllers/Finance/FinanceController.php:17
* @route '/finance'
*/
export const finance = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: finance.url(options),
    method: 'get',
})

finance.definition = {
    methods: ["get","head"],
    url: '/finance',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\Finance\FinanceController::finance
* @see app/Http/Controllers/Finance/FinanceController.php:17
* @route '/finance'
*/
finance.url = (options?: RouteQueryOptions) => {
    return finance.definition.url + queryParams(options)
}

/**
* @see \App\Http\Controllers\Finance\FinanceController::finance
* @see app/Http/Controllers/Finance/FinanceController.php:17
* @route '/finance'
*/
finance.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: finance.url(options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\Finance\FinanceController::finance
* @see app/Http/Controllers/Finance/FinanceController.php:17
* @route '/finance'
*/
finance.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: finance.url(options),
    method: 'head',
})

/**
* @see routes/web.php:148
* @route '/bypass'
*/
export const bypass = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bypass.url(options),
    method: 'get',
})

bypass.definition = {
    methods: ["get","head"],
    url: '/bypass',
} satisfies RouteDefinition<["get","head"]>

/**
* @see routes/web.php:148
* @route '/bypass'
*/
bypass.url = (options?: RouteQueryOptions) => {
    return bypass.definition.url + queryParams(options)
}

/**
* @see routes/web.php:148
* @route '/bypass'
*/
bypass.get = (options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: bypass.url(options),
    method: 'get',
})

/**
* @see routes/web.php:148
* @route '/bypass'
*/
bypass.head = (options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: bypass.url(options),
    method: 'head',
})

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:19
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/dashboard'
*/
export const dashboard = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(args, options),
    method: 'get',
})

dashboard.definition = {
    methods: ["get","head"],
    url: '/{current_team?}/dashboard',
} satisfies RouteDefinition<["get","head"]>

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:19
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/dashboard'
*/
dashboard.url = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions) => {
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

    return dashboard.definition.url
            .replace('{current_team?}', parsedArgs.current_team?.toString() ?? '')
            .replace(/\/+$/, '') + queryParams(options)
}

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:19
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/dashboard'
*/
dashboard.get = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'get'> => ({
    url: dashboard.url(args, options),
    method: 'get',
})

/**
* @see \App\Http\Controllers\DashboardController::__invoke
* @see app/Http/Controllers/DashboardController.php:19
* @param current_team - Default: '$currentTeam'
* @route '/{current_team?}/dashboard'
*/
dashboard.head = (args?: { current_team?: string | number } | [current_team: string | number ] | string | number, options?: RouteQueryOptions): RouteDefinition<'head'> => ({
    url: dashboard.url(args, options),
    method: 'head',
})

