<?php

namespace App\Http\Controllers;

use App\Models\Pillar;
use App\Models\Program;
use Illuminate\View\View;

class SuperAdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $activeProgramQuery = Program::where('is_archived', false)
            ->whereIn('status', Program::PUBLIC_STATUSES);

        return view('superadmin.dashboard', [
            'stats' => [
                'program_pending' => (clone $activeProgramQuery)
                    ->whereIn('status', [
                        'pending_fase1',
                        'pending_fase2',
                        'menunggu_review_proposal',
                        'menunggu_review_survei',
                        'menunggu_review_laporan_akhir',
                    ])
                    ->count(),
                'program_approved' => (clone $activeProgramQuery)
                    ->whereIn('status', ['completed', 'selesai'])
                    ->count(),
            ],
            'pillars' => Pillar::orderBy('id')->get(),
            'pillarCounts' => (clone $activeProgramQuery)
                ->selectRaw('pillar_id, count(*) total')
                ->groupBy('pillar_id')
                ->pluck('total', 'pillar_id'),
        ]);
    }
}
