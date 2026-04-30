<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PredictionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChampionshipPredictionResource;
use App\Http\Resources\FixtureResource;
use App\Http\Resources\TeamResource;
use App\Models\Fixture;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class LeagueStateController extends Controller
{
    public function __construct(
        private readonly PredictionServiceInterface $predictionService,
    ) {}

    public function index(): JsonResponse
    {
        $teams    = $this->loadTeams();
        $fixtures = $this->loadFixtures();
        $meta     = $this->buildMeta();

        return response()->json([
            'data' => [
                'teams'       => TeamResource::collection($teams)->resolve(),
                'fixtures'    => FixtureResource::collection($fixtures)->resolve(),
                'predictions' => $this->buildPredictions($meta['total_weeks'], $meta['remaining_weeks']),
                'meta'        => [
                    'current_week'          => $meta['current_week'],
                    'is_simulation_complete' => $meta['is_simulation_complete'],
                    'total_weeks'           => $meta['total_weeks'],
                ],
            ],
        ]);
    }

    private function loadTeams()
    {
        return Team::orderByDesc('points')
            ->orderByDesc(DB::raw('goals_for - goals_against'))
            ->orderByDesc('goals_for')
            ->get();
    }

    private function loadFixtures()
    {
        return Fixture::with(['homeTeam', 'awayTeam'])
            ->orderBy('week')
            ->orderBy('id')
            ->get();
    }

    private function buildMeta(): array
    {
        $totalWeeks     = (int) Fixture::max('week');
        $remainingWeeks = Fixture::where('is_played', false)
            ->distinct()
            ->pluck('week')
            ->count();
        $currentWeek    = (int) Fixture::where('is_played', true)->max('week');

        return [
            'total_weeks'           => $totalWeeks,
            'remaining_weeks'       => $remainingWeeks,
            'current_week'          => $currentWeek,
            'is_simulation_complete' => $totalWeeks > 0 && $remainingWeeks === 0,
        ];
    }

    private function buildPredictions(int $totalWeeks, int $remainingWeeks): array
    {
        if ($totalWeeks === 0 || $remainingWeeks > intdiv($totalWeeks, 2)) {
            return ['available' => false, 'data' => []];
        }

        $predictions = $this->predictionService->predict();

        return [
            'available' => true,
            'data'      => ChampionshipPredictionResource::collection($predictions)->resolve(),
        ];
    }
}
