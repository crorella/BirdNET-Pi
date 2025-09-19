> Processing Pipeline

  - scripts/birdnet_recording.sh:10 and scripts/install_services.sh:62 currently hard‑code a single StreamData sink; extend them (and any new upload handler) so incoming probe audio lands under RECS_DIR/<probe>/StreamData with filenames that encode the
  probe ID.
  - scripts/utils/helpers.py:62 (ParseFileName) and scripts/utils/helpers.py:103 (get_wav_files) need to recognise the new folder layout, extract the probe identifier from either the directory or filename, and expose it so downstream code can tag
  detections.
  - scripts/birdnet_analysis.py:28 watches only the root StreamData; teach it to watch each probe directory (or recurse) and to pass the parsed probe information through to reporting.

  Database & Notifications

  - Add a Probe column (and possibly a lookup table) via scripts/createdb.sh:5 and migrate existing installs; mirror the schema change anywhere the table is created in tests (tests/test_apprise_notifications.py:20).
  - Carry the probe through write paths in scripts/utils/reporting.py:74 (clip naming), scripts/utils/reporting.py:96 (write_to_db), scripts/utils/reporting.py:134 (JSON export), and scripts/utils/reporting.py:153/189 for third‑party integrations so
  detections store the source.
  - Update query helpers such as scripts/utils/notifications.py:128 and summary builders in scripts/utils/reporting.py:118 to include probe filters when counting daily/weekly occurrences.

  Web UI & Dashboards

  - Core query layer scripts/common.php:60 (fetch helpers) should accept an optional probe filter and surface available probes to the UI.
  - Every UI view that reads from detections—scripts/todays_detections.php:16, scripts/history.php:24, scripts/stats.php:12, scripts/weekly_report.php:30, scripts/play.php:24, scripts/spectrogram.php:19, and scripts/overview.php—needs dropdowns/toggles
  for “All vs specific probe” and to include the new column in displays.
  - Streamlit dashboard scripts/plotly_streamlit.py:46 must load the new column, provide sidebar filters, and propagate probe context through plots.

  Configuration & Ops

  - Introduce configuration for known probes (names, credentials, upload endpoints) in scripts/install_config.sh:70, expose it in the settings UI (scripts/config.php, scripts/advanced.php) and ensure helpers like scripts/service_controls.php:52 report
  backlog per probe.
  - Any tooling that assumes a single StreamData directory—scripts/update_birdnet_snippets.sh:84, scripts/uninstall.sh:23, scripts/spectrogram.sh:7, backup/restore scripts like scripts/backup_data.sh:18—must loop over probe directories.
  - If probes push audio remotely, add an authenticated upload/ingest endpoint (likely under a new module in scripts/ plus frontend glue in homepage/views.php:29).

  Validation & Docs

  - Extend the pytest suite (or add new cases) to cover probe‑aware inserts/queries once implemented.
  - Document the new workflow in AGENTS.md and user‑facing docs (README.md) so operators know how to register probes and filter results.
