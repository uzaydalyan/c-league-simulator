<?php

namespace App\Contracts;

interface FixtureServiceInterface
{
    public function generateFixtures(): void;

    public function resetFixtures(): void;

    public function fixturesExist(): bool;
}
