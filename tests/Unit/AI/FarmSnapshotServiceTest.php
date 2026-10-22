<?php

namespace Tests\Unit\AI;

use App\AI\Services\FarmImageRenderer;
use App\AI\Services\FarmSnapshotService;
use Tests\TestCase;

class FarmSnapshotServiceTest extends TestCase
{
    public function test_named_garden_resolves_from_the_canonical_demo_data(): void
    {
        $snapshot = (new FarmSnapshotService)->resolve(
            'Buatkan saya infografis tentang kebun tani lestari dengan data detail kebunnya juga',
        );

        $this->assertIsArray($snapshot);
        $this->assertSame('kebun-002', $snapshot['id']);
        $this->assertSame('Kebun Tani Lestari', $snapshot['name']);
        $this->assertSame('Parakan', $snapshot['district']);
        $this->assertSame('Temanggung', $snapshot['city']);
        $this->assertSame(180, $snapshot['used_hectare']);
        $this->assertSame(750, $snapshot['available_hectare']);
        $this->assertSame('Jagung', $snapshot['seed_type']);
        $this->assertSame('Hibrida BISI-18', $snapshot['seed_type_detail']);
        $this->assertSame('Sehat', $snapshot['health_status']);
        $this->assertSame(65, data_get($snapshot, 'insight.growth'));
    }

    public function test_unmatched_name_is_not_fuzzily_assigned_to_another_garden(): void
    {
        $this->assertNull((new FarmSnapshotService)->resolve(
            'Buatkan gambar untuk Kebun Tani Lestarii',
        ));
    }

    public function test_renderer_contains_only_canonical_garden_values(): void
    {
        $snapshot = (new FarmSnapshotService)->resolve('gambar kebun tani lestari');
        $this->assertIsArray($snapshot);

        $svg = (new FarmImageRenderer)->render($snapshot);

        $this->assertStringContainsString('Kebun Tani Lestari', $svg);
        $this->assertStringContainsString('Parakan, Temanggung', $svg);
        $this->assertStringContainsString('Jl. Parakan-Ngadirejo No. 27', $svg);
        $this->assertStringContainsString('180 ha', $svg);
        $this->assertStringContainsString('750 ha', $svg);
        $this->assertStringContainsString('Jagung Hibrida BISI-18', $svg);
        $this->assertStringContainsString('Sehat', $svg);
        $this->assertStringNotContainsString('2 hektar', $svg);
        $this->assertStringNotContainsString('Sukamaju', $svg);
    }
}
