Run the full Monolith unit test suite and report the results.

## Do this now

1. From the project root, run:

```bash
composer test
```

2. If tests fail, read the output, fix the failing code or tests, and re-run until `composer test` exits 0.

3. Reply with a short summary:
   - pass/fail
   - test count
   - any failures (file + reason)
   - coverage % if shown in output

## Notes

- `composer test` runs PHPUnit via `scripts/run-tests.php` and writes `var/test-status.json`.
- For style only: `composer lint` / `composer format`.
- Do not skip running the command — execute it in the terminal.
