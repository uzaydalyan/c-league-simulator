# League Simulator

A Laravel REST API for simulating a round-robin football league. Generates fixtures, runs simulations using a Poisson score model, and produces championship predictions.

## Stack

- PHP 8.4 / Laravel 13
- PostgreSQL (production) / SQLite (local default)

## Local setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve
```

## Teams

Configured in [config/league.php](config/league.php). Add or remove entries to change team count (must be even, minimum 2).

## Typical workflow

```
POST /api/v1/fixtures/generate     # generate schedule
POST /api/v1/simulation/week/1     # simulate a week
GET  /api/v1/league/state          # standings, fixtures, predictions
PUT  /api/v1/fixtures/{id}         # edit a result (current/past weeks only)
POST /api/v1/fixtures/reset        # reset everything
```

Predictions appear in `league/state` once half the season has been played.

## Score model

Goals are Poisson-sampled. Each team's λ is weighted by its `power` rating with a **1.15× home advantage** and anchored to 2.7 average total goals per match (Knuth's algorithm).

## Tests

```bash
php artisan test
```

## API reference

See [API_DOCS.md](API_DOCS.md).
