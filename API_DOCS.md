# API Reference

Base URL: `/api/v1`

All responses are JSON. Errors use `{ "message": "..." }`.

---

## GET /api/v1/league/state

Returns the full league state in one call: standings, fixtures, predictions, and meta.

```json
{
  "data": {
    "teams": [
      {
        "id": 1,
        "name": "Manchester City",
        "played": 3,
        "won": 2,
        "drawn": 1,
        "lost": 0,
        "goals_for": 7,
        "goals_against": 2,
        "goal_difference": 5,
        "points": 7
      }
    ],
    "fixtures": [
      {
        "id": 1,
        "week": 1,
        "home_team": { "id": 1, "name": "Manchester City" },
        "away_team": { "id": 2, "name": "Liverpool" },
        "home_score": 2,
        "away_score": 1,
        "is_played": true
      }
    ],
    "predictions": {
      "available": true,
      "data": [
        { "team": { "id": 1, "name": "Manchester City" }, "percentage": 72.50 }
      ]
    },
    "meta": {
      "current_week": 3,
      "total_weeks": 6,
      "is_simulation_complete": false
    }
  }
}
```

`predictions.available` is `false` until more than half the season has been played. Percentages always sum to 100.

---

## POST /api/v1/fixtures/generate

Generates the full double round-robin schedule.

**201** `{ "message": "Fixtures generated successfully." }`
**409** `{ "message": "Fixtures have already been generated." }`

---

## POST /api/v1/fixtures/reset

Deletes all fixtures and resets all team stats to zero.

**200** `{ "message": "League has been reset." }`

---

## PUT /api/v1/fixtures/{id}

Edits a fixture result. Only current and past weeks can be edited. If the fixture was already played, standings are recalculated automatically.

**Body**
```json
{ "home_score": 3, "away_score": 1 }
```

**200** — updated fixture
```json
{
  "data": {
    "id": 5,
    "week": 2,
    "home_team": { "id": 1, "name": "Chelsea" },
    "away_team": { "id": 2, "name": "Arsenal" },
    "home_score": 3,
    "away_score": 1,
    "is_played": true
  }
}
```

**422** — future week or validation error
**404** — fixture not found

---

## POST /api/v1/simulation/week/{week}

Simulates all unplayed fixtures in the given week.

**200** `{ "message": "Week 2 simulated successfully.", "data": [ ...fixtures ] }`
**422** `{ "message": "No unplayed fixtures found for week 2." }`

---

## POST /api/v1/simulation/play-all

Simulates all remaining fixtures.

**200** `{ "message": "All fixtures simulated successfully.", "data": [ ...fixtures ] }`
**422** `{ "message": "All fixtures have already been played." }`
