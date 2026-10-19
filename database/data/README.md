# Monthly reading sheets

One CSV per purok per month, imported with `php artisan awas:import-readings`.
Start from `readings-TEMPLATE.csv`, or from the August 2026 files made from the Word sheet.

| Column | What to put |
|---|---|
| `purok` | Exactly as in the system: `Purok 2`, `Purok 3(Phase 2)`, `Purok 4(Phase 2)`, `Purok 4(Extension)`, `Purok 6`, `Purok 7`, `Purok 8` |
| `no` | List No. on the sheet (only used in messages) |
| `name` | As on the sheet: `SURNAME, FIRST NAME` |
| `meter` | Meter number |
| `present` | Present reading. Leave empty if the meter was not read |
| `previous` | Previous reading |
| `color` | `red` = set Inactive, `green` = paid in full in cash, empty = unpaid bill |
| `date_paid` | Date on the sheet, e.g. `9/21/26`. Empty = the bill's due date |

Edit in Excel and save as **CSV (Comma delimited)**.

## On the server

```
cd /var/www/awas && git pull
sudo -u www-data php artisan awas:import-readings database/data/readings-2026-08-purok2.csv --period=2026-08 --dry-run
sudo -u www-data php artisan awas:import-readings database/data/readings-2026-08-purok2.csv --period=2026-08 --dry-run | grep SKIP
sudo -u www-data php artisan awas:import-readings database/data/readings-2026-08-purok2.csv --period=2026-08
```

Dry run first, fix any SKIP rows, then run without `--dry-run`. Running a file twice is safe:
readings already saved, paid bills and inactive accounts are skipped.
