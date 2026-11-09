<?php

namespace App\Http\Controllers\Reference;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** Authenticated visual snapshots only; never reads operational domain records. */
class LegacyMobileReferenceController extends Controller
{
    public function __invoke(string $referencePage): Response
    {
        abort_unless(in_array($referencePage, [
            'Dashboard', 'Kebun', 'KebunDetail', 'KebunAktivitas', 'Aktivitas',
            'RincianAktivitas', 'Laporan', 'Finance', 'Index',
        ], true), 404);

        return Inertia::render('reference/'.$referencePage, [
            'reference' => [
                'sourceCommit' => '73f2555d91d0fb2d6acb7bc70813921edd479ea4',
                'readOnly' => true,
                'basePath' => '/referensi',
            ],
            'pendingInvitations' => [],
        ]);
    }
}
