<?php

namespace App\Http\Controllers\Api;

use App\Contracts\FixtureServiceInterface;
use App\Contracts\SimulationServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateFixtureRequest;
use App\Http\Resources\FixtureResource;
use App\Models\Fixture;
use Illuminate\Http\JsonResponse;

class FixtureController extends Controller
{
    public function __construct(
        private readonly FixtureServiceInterface $fixtureService,
        private readonly SimulationServiceInterface $simulationService,
    ) {}

    public function generate(): JsonResponse
    {
        if ($this->fixtureService->fixturesExist()) {
            return response()->json(['message' => 'Fixtures have already been generated.'], 409);
        }

        $this->fixtureService->generateFixtures();

        return response()->json(['message' => 'Fixtures generated successfully.'], 201);
    }

    public function reset(): JsonResponse
    {
        $this->fixtureService->resetFixtures();

        return response()->json(['message' => 'League has been reset.']);
    }

    public function update(UpdateFixtureRequest $request, Fixture $fixture): JsonResponse|FixtureResource
    {
        $currentWeek = (int) (
            Fixture::where('is_played', false)->min('week')
            ?? Fixture::max('week')
            ?? 0
        );

        if ($fixture->week > $currentWeek) {
            return response()->json([
                'message' => "Cannot edit a future fixture. Current week is {$currentWeek}.",
            ], 422);
        }

        $updated = $this->simulationService->editResult(
            $fixture,
            $request->validated('home_score'),
            $request->validated('away_score'),
        );

        return new FixtureResource($updated);
    }
}
