---
name: run-jobfind
description: Build, run, seed, and smoke-test the JobFind PHP/MySQL app. Use when asked to start, launch, run, screenshot, or verify JobFind locally; or when you need a known-good URL to hit while developing.
---

JobFind is a server-rendered PHP 8.2 / MySQL 8 app that runs as a three-container Docker stack (Apache+PHP, MySQL, phpMyAdmin). There is no SPA — every page is a `.php` file under `public/`. The agent driver is **curl** wrapped by `smoke.sh`; it brings the stack up, seeds test accounts + a sample employer/candidate/job, and asserts the homepage, the login flow, an authenticated page, the public job listing/detail, and phpMyAdmin all respond as expected.

Paths below are relative to the repo root (`<unit>/`).

## Prerequisites

- Docker Desktop with Compose v2 (verified: Docker 29.1.3, Compose v5.0.1).
- A POSIX shell with `curl` and `mysql` client *not* needed locally — both run inside containers.
- On Windows: Git Bash. The driver uses `cygpath` to translate `/tmp/...` into a path Windows-native curl can write to. No extra packages.

## Build & run

```bash
docker compose up -d --build
```

The first build takes ~2 minutes (installs `gd`, `mysqli`, `zip` PHP extensions and `a2enmod rewrite`). Subsequent starts are instant. Compose maps:

- `:8080` → app (Apache, DocumentRoot `public/`)
- `:8081` → phpMyAdmin (root / `root_pass`)
- `:3307` → MySQL (`jobfind` / `jobfind_pass`, db `jobfinder`)

The schema in `db/schema.sql` is mounted into MySQL's `docker-entrypoint-initdb.d/`, so the first DB boot auto-applies it. Migrations in `db/migrations/` are mounted alongside.

## Run (agent path)

```bash
bash .claude/skills/run-jobfind/smoke.sh
```

The driver is idempotent — re-run any time. It:

1. `docker compose up -d` and waits for `GET /` to return 200 (≤30s).
2. Seeds `user@test.com`, `employer@test.com`, `admin@test.com` (all `123456`) via `create_test_accounts.php`, then `db/seed.php` to add an employer profile, candidate profile, and a sample job (`Example PHP Developer` @ `Example Company`, id=1).
3. Asserts:
   - Homepage renders with `<title>JobFind…</title>` and links to `view.php?id=1`.
   - `/account/login.php` renders.
   - POST to `/account/login.php` sets a `PHPSESSID` cookie.
   - `/candidate/profile.php` with that cookie shows `Test User` (proves session sticks despite the warning printed during login — see Gotchas).
   - `/job/share/` lists jobs; `/job/share/view.php?id=1` shows the seeded job.
   - phpMyAdmin at `:8081` returns 200.
   - `users` table has the 3 seeded test accounts.

Exit code 0 means every assertion passed. Output ends with `Summary: N passed, M failed.`.

Override defaults with env vars: `JOBFIND_BASE=http://host:port JOBFIND_PMA=http://host:port`. Cookies and HTML dumps land in `$TMPDIR/jobfind-driver/` (on Git Bash, translated via `cygpath -m`).

### Test accounts (after seeding)

| Email | Password | Role |
| --- | --- | --- |
| `user@test.com` | `123456` | candidate (role_id 3) |
| `employer@test.com` | `123456` | employer (role_id 2) |
| `admin@test.com` | `123456` | admin (role_id 1) |
| `candidate@local` | `candidate123` | candidate, with a filled profile + sample job to apply to |
| `employer@local` | `employer123` | employer, owns the seeded `Example PHP Developer` job |
| `admin@local` | `admin123` | admin |

### Hand-driving with curl

```bash
# Login (cookie jar must be a Windows-native path on Git Bash — see smoke.sh)
JAR=$(cygpath -m /tmp 2>/dev/null || echo /tmp)/jobfind.jar
curl -sS -c "$JAR" -b "$JAR" \
  --data-urlencode email=user@test.com \
  --data-urlencode password=123456 \
  http://localhost:8080/account/login.php -o /dev/null
# Hit any authenticated page:
curl -sS -b "$JAR" http://localhost:8080/candidate/profile.php | grep '<title>'
```

## Run (human path)

```bash
docker compose up -d --build
docker compose exec -T app php /var/www/html/create_test_accounts.php >/dev/null
docker compose exec -T app php /var/www/html/db/seed.php >/dev/null
```

Then open in a browser:

- `http://localhost:8080/` — landing page
- `http://localhost:8080/account/login.php` — log in as one of the test accounts above
- `http://localhost:8080/job/share/` — public job feed
- `http://localhost:8080/employer/admin/` — employer back-office (log in as `employer@test.com` first)
- `http://localhost:8081/` — phpMyAdmin (`root` / `root_pass`)

## Direct DB access

```bash
# psql-style shell against the running container:
docker compose exec -T db mysql -ujobfind -pjobfind_pass jobfinder

# One-off query (printed without headers):
docker compose exec -T db mysql -ujobfind -pjobfind_pass -BN jobfinder \
  -e "SELECT id, email, role_id FROM users;"
```

## Gotchas

- **Don't reintroduce trailing `?>` + whitespace** in any file under `app/models/`, `app/controllers/`, `app/services/`, `app/helpers/`, or `config/`. Those files are `require_once`d before `header()` / `session_start()` calls; anything after `?>` (even a single newline) is output, and PHP then refuses to send headers. As of 2026-05-30 the include files all end with `}` and no closing tag — PHP best practice for pure-PHP files. Symptoms if it regresses: `Warning: Cannot modify header information - headers already sent by (output started at /var/www/html/app/models/X.php:NN)` on every page, dashboard truncating to ~380 bytes of warnings, login redirect failing.
- **Don't reintroduce a leading newline (or BOM) before `<?php`** in entry-point files like `public/dashboard.php`. Same root cause as above, but local to that one file. If you paste content with a leading blank line, the entire page breaks the same way.
- **The Google sign-in button hits `BASE_URL/google_login.php`**, which redirects to Google using the real credentials in `config/google_oauth.php` (committed to the repo as of 2026-05-31). The redirect URI is hard-coded to `http://localhost:8080/google_callback.php` and **must match exactly** what's registered in Google Cloud Console under Authorized redirect URIs — Google rejects the OAuth round-trip with `redirect_uri_mismatch` otherwise. If you change ports or hostnames, update both places.
- **Use `BASE_URL` and `ADMIN_URL` for in-app links, never `/JobFind/public/...`.** `config/config.php` defines `BASE_URL = ''` in Docker (DocumentRoot is `public/`) and `BASE_URL = '/JobFind/public'` in XAMPP. The same file defines `ADMIN_URL` (`/admin` vs `/JobFind/admin`). Pre-2026-05-30 the auth pages had `/JobFind/public/...` hardcoded everywhere, which 404'd under Docker.
- **MySQL port is `3307` on the host**, not 3306 — `:3307→3306` mapping in `docker-compose.yml`. If a host MySQL is already on 3306 the compose stack still starts; just don't connect to `localhost:3306`.
- **Git Bash + Windows curl can't write to `/tmp/...`.** Curl is `/mingw64/bin/curl` (built for `x86_64-w64-mingw32` with Schannel) and treats `/tmp` as a literal Windows path under the current drive, not the MSYS mount. `smoke.sh` runs `cygpath -m` to translate; if you write your own curl calls on Git Bash, pass `--cookie-jar "$(cygpath -m /tmp/jar)"` or use `$LOCALAPPDATA/Temp/...`.
- **`MSYS_NO_PATHCONV=1` is needed on Git Bash** to stop URL paths like `http://localhost:8080/account/login.php` from being rewritten to `http://localhost:8080C:/Program Files/Git/account/login.php`. `smoke.sh` sets it at the top.
- **`db/seed.php` is best-effort idempotent** — it uses `findByEmail` for users and `stripos($title, 'Example')` for the sample job. Renaming "Example PHP Developer" will make seed re-add it on the next run.
- **The repo is not a git checkout.** There's no `.git/` directory at `C:/Users/Admin/FindJob/` even though the harness reports `Is a git repository: true`. Skip `git status` — it errors.

## Troubleshooting

| Symptom | Cause / fix |
| --- | --- |
| `smoke.sh` prints `client returned ERROR on write of N bytes` and `no PHPSESSID after login` | You're on Git Bash and `cygpath` wasn't found, so curl is trying to write to a Windows path that doesn't exist. Install Git for Windows ≥ 2.30, or set `TMPDIR=$LOCALAPPDATA/Temp` before running. |
| `app status after wait: HTTP 000` | Apache hasn't bound `:8080` yet. Check `docker compose logs app`; the first build pulls `php:8.2-apache` + apt packages and can take ~2 min. |
| `/account/login.php` POSTs return the login form again, no warning | The seeded password didn't match. Re-run `docker compose exec -T app php /var/www/html/create_test_accounts.php`; passwords use `password_hash()` so old rows with a different hash won't verify. |
| phpMyAdmin "mysqli::real_connect(): (HY000/2002): Connection refused" | The `db` container failed its healthcheck. `docker compose logs db` will show why — most often a port conflict on `3307`. |
| Sample job `?id=1` 404s | `db/seed.php` never ran or the `jobs` table was wiped. Re-run `docker compose exec -T app php /var/www/html/db/seed.php`. |
| `mysql -BN ... | tr -d '\r\n '` returns empty | Older Compose `exec` swallows the query's stdout when `-T` is omitted under Windows. The driver passes `-T`; if you copy a one-off command, keep it. |
