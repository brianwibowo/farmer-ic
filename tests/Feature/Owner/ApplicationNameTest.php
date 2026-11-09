<?php

namespace Tests\Feature\Owner;

use Illuminate\Support\Env;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ApplicationNameTest extends TestCase
{
    public function test_app_name_defaults_when_the_environment_variable_is_missing(): void
    {
        $environment = Env::getRepository();
        $original = $environment->get('APP_NAME');
        $environment->clear('APP_NAME');

        try {
            $configuration = require config_path('app.php');
            $this->assertSame('Bumi Hijauan', $configuration['name']);
        } finally {
            if ($original !== null) {
                $environment->set('APP_NAME', $original);
            }
        }
    }

    public function test_configured_name_reaches_inertia_and_the_initial_html(): void
    {
        config(['app.name' => 'Kebun & Nusantara']);

        $response = $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('name', 'Kebun & Nusantara'))
            ->assertSee('<meta name="application-name" content="Kebun &amp; Nusantara">', false)
            ->assertSee('Memuat aplikasi Kebun &amp; Nusantara', false);

        preg_match('/<title[^>]*>(.*?)<\/title>/s', $response->getContent(), $title);
        $this->assertStringContainsString(
            'Kebun & Nusantara',
            html_entity_decode($title[1] ?? ''),
        );
    }

    public function test_startup_and_email_views_escape_the_configured_name(): void
    {
        config(['app.name' => '<Kebun & Nusantara>']);

        foreach ([
            view('components.startup-screen')->render(),
            view('vendor.mail.html.header', ['url' => 'https://example.test'])->render(),
        ] as $html) {
            $this->assertStringContainsString('&lt;Kebun &amp; Nusantara&gt;', $html);
            $this->assertStringNotContainsString('<Kebun & Nusantara>', $html);
        }
    }
}
