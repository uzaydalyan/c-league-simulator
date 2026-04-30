<?php

namespace App\Http\Controllers\Api;

use App\Contracts\SimulationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\FixtureResource;
use Illuminate\Http\JsonResponse;

class SimulationController extends Controller
{
    public function __construct(
        private readonly SimulationServiceInterface $simulationService,
    ) {}

    public function playWeek(int $week): JsonResponse
    {
        try {
            $fixtures = $this->simulationService->playWeek($week);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => "Week {$week} simulated successfully.",
            'data'    => FixtureResource::collection($fixtures),
        ]);
    }

    public function playAll(): JsonResponse
    {
        try {
            $fixtures = $this->simulationService->playAll();
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'All fixtures simulated successfully.',
            'data'    => FixtureResource::collection($fixtures),
        ]);
    }
}
