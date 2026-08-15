# Hackview

Hackview is a PHP-first hackathon discovery dashboard for finding real opportunities by deadline, prize, type, platform, participant count, official links, and signup requirements.

The project is designed for source-attributed discovery. It does not generate placeholder events, invent prize amounts, or treat social posts and search results as proof that a hackathon is legitimate.

## Features

- Deadline-focused hackathon dashboard
- Search, filtering, and sorting by time remaining
- Prize, participant, type, location, and platform fields
- Official event and registration links
- Verification status and legitimacy notes
- Public RSS/Atom ingestion
- Configurable JSON endpoint ingestion
- Official X API discovery for relevant public posts
- Raw source preservation for auditability
- Separate unreviewed discovery candidates
- SQLite storage with PHP PDO
- Dark charcoal and deep-green responsive interface

## Requirements

- PHP 8.2 or newer
- SQLite extension
- SimpleXML extension for RSS/Atom feeds
- Internet access for configured source endpoints
- Composer is not required for the current core application

Check your PHP extensions with:

```bash
php -v
php -m
```

## Installation

From the project directory:

```bash
copy .env.example .env
php bin/migrate.php
```

On macOS/Linux, use:

```bash
cp .env.example .env
php bin/migrate.php
```

The local `.env` file contains credentials and is ignored by Git. Never commit it or paste real API keys into issues, pull requests, screenshots, or chat.

## Start the application

Recommended command:

```bash
php artisan serve
```

The default address is:

```text
http://127.0.0.1:8000
```

Use a different port when port 8000 is already occupied:

```bash
php artisan serve --port=8010
```

You can also use PHP directly:

```bash
php -S 127.0.0.1:8000 -t public public/index.php
```

Open the displayed address in your browser. The dashboard intentionally starts with an honest empty state until real records are ingested.

## Database commands

Run or re-run the database setup:

```bash
php bin/migrate.php
```

Check unreviewed records with the current verification command:

```bash
php bin/verify.php
```

Remove old raw ingestion database records:

```bash
php bin/prune.php
```

## RSS and Atom feeds

For a permitted public RSS or Atom feed:

```bash
php bin/ingest.php https://example.com/public-feed.xml
php bin/verify.php
```

Only connect feeds whose terms, rate limits, robots policy, and attribution requirements you have checked. Ingested events remain unreviewed until verification.

## X discovery

Hackview uses the official X API for automated public-post discovery. It does not use unofficial scrapers, browser extensions, account-cookie extraction, or bypass methods.

Add your own authorized token to `.env`:

```env
X_BEARER_TOKEN=your_token_here
```

Then run:

```bash
php bin/discover-x.php
```

Use a custom query when needed:

```bash
php bin/discover-x.php "(hackathon OR buildathon) (prize OR prizes) -is:retweet lang:en"
```

X posts are stored in `discovery_candidates`, not directly as hackathons. A post is only a lead. An official event page must be found and checked before it can become a verified listing.

### Sorsa X search

Sorsa's documented endpoint is a `POST` request to:

```text
https://api.sorsa.io/v3/search-tweets
```

The request uses the `ApiKey` header. Add your own Sorsa key to `.env`:

```env
SORSA_API_KEY=your_sorsa_key_here
SORSA_SEARCH_ENDPOINT_URL=https://api.sorsa.io/v3/search-tweets
SORSA_SEARCH_QUERY_FIELD=query
```

Search a keyword:

```bash
php bin/search-sorsa.php hackathon
php bin/search-sorsa.php "hackathon prizes"
```

The public documentation confirms the endpoint and header, but does not publish the JSON body field or response schema in the fetched specification. Hackview defaults to `{ "query": "..." }`; set `SORSA_SEARCH_QUERY_FIELD` to the exact field documented for your Sorsa account if different. Returned posts are stored as unreviewed candidates and never become verified hackathons automatically. Never put your Sorsa key in source code or share it in chat.

### Daily Sorsa batch

Configure several searches in `.env`:

```env
SORSA_BATCH_QUERIES=["hackathon","buildathon","hackathon prizes","hackathon applications","developer competition"]
```

Run the batch manually:

```bash
php artisan sorsa:batch
```

A completed batch is allowed only once per calendar date. To retry a failed or partial batch:

```bash
php artisan sorsa:batch --force
```

Each query is saved with its own raw response and audit record. Duplicate posts across queries are stored once, and existing candidate review status is preserved.

#### Windows Task Scheduler

The project includes a registration script that creates a daily task at **12:00 AM** without exposing `.env` secrets:

```powershell
Set-ExecutionPolicy -Scope Process Bypass
.\\bin\\register-sorsa-task.ps1 -PhpPath C:\\php\\php.exe
```

By default, the script uses the project directory as the working directory, prevents overlapping runs, and gives each run a two-hour limit. Remove the task with:

```powershell
.\\bin\\register-sorsa-task.ps1 -Remove
```

The equivalent manual task settings are:

- **Program:** the absolute path to `php.exe`, such as `C:\\php\\php.exe`
- **Arguments:** `artisan sorsa:batch`
- **Start in:** `C:\\Users\\USER\\hackviewer`
- Make sure the task account can read `.env`, write `database/app.sqlite`, and write `storage/raw`.
- Do not put the Sorsa key in the task arguments.

Test the same command from Command Prompt first:

```cmd
cd /d C:\Users\USER\hackviewer
php artisan sorsa:batch
```

#### Linux/macOS cron

Run at midnight using an absolute PHP path:

```cron
0 0 * * * cd /path/to/hackviewer && /usr/bin/php artisan sorsa:batch >> storage/logs/sorsa-batch.log 2>&1
```

The batch uses the application timezone for its calendar date, saves successful query results when another query fails, and refuses to repeat a completed date.

## Supported platform registry

Hackview recognizes these platforms:

- Devpost
- DoraHacks
- Major League Hacking
- HackerEarth
- Kaggle
- HackQuest
- Unstop

Recognition does not mean an undocumented API or scraper is enabled. Each platform remains `manual-only` until you configure an official or explicitly permitted HTTPS RSS, Atom, or JSON endpoint.

View source status:

```bash
php bin/discover.php --list-sources
```

Configure an endpoint in `.env`:

```env
DEVPOST_ENDPOINT_URL=https://your-permitted-endpoint.example/events.json
DORAHACKS_ENDPOINT_URL=
MLH_ENDPOINT_URL=
HACKEREARTH_ENDPOINT_URL=
KAGGLE_ENDPOINT_URL=
HACKQUEST_ENDPOINT_URL=
UNSTOP_ENDPOINT_URL=
```

Run one configured source:

```bash
php bin/discover.php --source=devpost
```

Run every configured source:

```bash
php bin/discover.php
```

The JSON adapter accepts a top-level array or common collections such as `data`, `items`, `events`, or `results`. Each event should provide:

- `title`
- `official_url`

Optional fields include:

- `id` or `slug`
- `description`
- `organizer`
- `start_date`
- `end_date`
- `registration_deadline`
- `prize`
- `participants`
- `location`
- `registration_url`
- `rules_url`

Missing information remains empty or “Not reported”; it is never guessed.

## Data and verification policy

Hackview separates three kinds of information:

1. **Official source data** — event details from an authorized platform endpoint or official event page.
2. **Discovery candidates** — X posts and search results that may point to an event.
3. **Verification evidence** — checks used before presenting an event as verified.

Every event should retain source attribution, an official URL, retrieval timing, and a verification state. Current verification begins with HTTPS URL validation; stronger checks for official domains, page availability, organizers, rules, prizes, and freshness should be added before treating a listing as trusted.

The system must not:

- Add dummy or placeholder hackathons
- Invent dates, prizes, participant counts, or organizers
- Present an X post as proof of legitimacy
- Guess undocumented platform API URLs
- Bypass authentication or access controls
- Use unofficial scraping as a production source
- Commit `.env` files or API credentials

## Project structure

```text
app/
  Controllers/       Dashboard and detail controllers
  Repositories/      SQLite queries
  Services/          Ingestion and countdown services
  Sources/           RSS, JSON, X, and source registry adapters
  Views/             Server-rendered HTML
bin/
  migrate.php        Create the SQLite schema
  ingest.php         Ingest a permitted RSS/Atom feed
  discover.php       Run configured platform sources
  discover-x.php     Discover public X posts
  verify.php         Run verification checks
  register-sorsa-task.ps1  Register/remove midnight Sorsa task
  prune.php          Remove old raw database records
public/
  index.php          Web front controller
  assets/            CSS and JavaScript
storage/
  raw/               Local raw source payloads, ignored by Git
database/
  migrations/        SQL schema
  app.sqlite         Local database, ignored by Git
artisan              PHP development-server launcher
.env                 Local secrets and configuration, ignored by Git
```

## Troubleshooting

If the application reports that SQLite is unavailable, enable the PHP SQLite extension and restart the command.

If port 8000 is busy:

```bash
php artisan serve --port=8010
```

If X discovery says `Set X_BEARER_TOKEN in .env`, add a valid token obtained from your own authorized X developer account.

If a platform shows `manual-only`, leave its endpoint blank until you have a documented, permitted endpoint. This status is safer than silently scraping or guessing.

## License and source terms

Before connecting an external source, review its terms of service, API documentation, rate limits, robots policy, authentication rules, and attribution requirements. Hackview is source-attributed software and should only process data you are authorized to collect and use.
