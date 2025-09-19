# Repository Guidelines

## Project Structure & Module Organization
- `scripts/` contains the runtime pipeline: Python analyzers (`birdnet_analysis.py`, `server.py`), Bash maintenance jobs, and PHP endpoints.
- `scripts/utils/` holds shared Python helpers used by analysis, reporting, and notification flows.
- `homepage/` delivers the web UI (PHP views plus `static/` assets); `templates/` packages service, cron, and config stubs installed onto the Pi.
- `model/` stores bundled TFLite models and labels; `tests/` houses pytest suites; `docs/overview.png` gives the deployment overview.

## Build, Test, and Development Commands
- Set up a local env: ```python3 -m venv .venv && source .venv/bin/activate && pip install -r requirements.txt```
- Explore analyzer entry points: ```python3 scripts/birdnet_analysis.py --help``` before wiring new capture logic.
- Run automated checks: ```python3 -m pytest tests```; required prior to every pull request that touches Python.
- Full-device validation: ```./newinstaller.sh``` on a clean Raspberry Pi image mirrors production services.

## Coding Style & Naming Conventions
- Python follows PEP 8 with 4-space indents; keep module-level constants uppercase and prefer descriptive verbs (`sendAppriseNotifications`).
- Bash scripts should target `bash`, enable `set -euo pipefail` where practical, and quote `${var}` expansions when manipulating user data.
- PHP endpoints align with existing files in `scripts/`; reuse helpers instead of embedding SQL and keep 4-space indents.
- Static assets under `homepage/static/` follow the existing `*.min.css` / `*.js` naming—match that pattern for additions.

## Testing Guidelines
- Pytest is the canonical runner; add new `test_*.py` modules under `tests/` and isolate SQLite or filesystem usage with fixtures.
- Mock outbound integrations (Apprise, MQTT) as shown in `tests/test_apprise_notifications.py` to keep tests offline.
- Briefly document any manual checks (UI clicks, shell workflows) in the PR when automated coverage is impractical.

## Commit & Pull Request Guidelines
- Write focused commits with imperative, <72-character subjects (`fix: guard empty species labels`).
- Reference issues when available and list Pi model / OS versions used in manual tests.
- Pull requests should summarise configuration impacts, include relevant screenshots for `homepage/` changes, and confirm `pytest` output.

## Configuration Notes
- Runtime scripts read `/etc/birdnet/birdnet.conf`; mirror that file under `templates/` for fixtures instead of hard-coding paths.
- TFLite blobs in `model/` are large—coordinate replacements, verify licensing, and update any checksum logic in installer scripts.
