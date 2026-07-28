# Fill-in rotation operations

Rotation is intentionally disabled by default. It enforces the mechanical safeguards configured for restricted-language Scripture content: no more than 500 distinct active verses and no complete Bible book. Passing the audit is not a legal determination; confirm translation rights and permitted use before enabling it.

1. Curate ordered rows in `FillInRotationSets` and `FillInRotationSetQuestions`. Only non-deleted Bible fill-ins in the global question bank are eligible. Verse ranges are expanded from `StartVerseID` through `EndVerseID` during audit. Rotation state is initialized automatically for each enabled year/language pair.
2. Audit production without changing active questions:

   ```sh
   php bin/rotate-fill-ins.php --dry-run
   ```

3. After a rights review and a successful audit, set `ENABLE_FILL_IN_ROTATION=true` in the environment or private configuration.
   `--force` cannot bypass this gate for a live rotation.
4. Run one live rotation manually and verify quiz availability, `CurrentFillInChapters`, `FillInRotationState`, and `FillInRotationAudit`.
5. Configure the host in `America/New_York` and schedule Sunday at 00:05:

   ```cron
   CRON_TZ=America/New_York
   5 0 * * 0 cd /path/to/pbe && /usr/bin/php bin/rotate-fill-ins.php >> var/log/fill-in-rotation.log 2>&1
   ```

Do not import or expose a larger inactive restricted-language pool while the feature gate is disabled. Incomplete quiz attempts can be cleaned independently with `php bin/purge-incomplete-attempts.php --days=30`.
