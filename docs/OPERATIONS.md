# Operations and cutover

## Prerequisites and configuration

PHP 8.4+, Composer 2, `ext-mongodb` 2.4+, BCMath, and the standard Laravel PHP extensions are required. `composer.lock` fixes the tested dependency versions. All application collections use MongoDB. Financial writes require a replica set or sharded cluster; a standalone MongoDB server is insufficient. Local development uses one replica-set member; production should use an authenticated redundant replica set/Atlas with TLS and backups.

Use `.env.example` and populate secrets through the deployment's secret manager. Never copy the Node `.env` into source control. Map `MONGO_URL` to `MONGODB_URI` and explicitly set `MONGODB_DATABASE`; preserve the original database and collection names at cutover. Inspect and configure `MONGODB_WITHDRAWAL_COLLECTION` if the actual Mongoose collection name differs. Preserve legacy JWT `SECRET` as `JWT_SECRET` when at least 32 bytes long; Laravel `APP_KEY` is independent and must be kept stable to decrypt queued outbox messages.

Set `APP_DEBUG=false`, SMTP transport and sender details, actual frontend links, allowed origins, provider URLs/secrets, and the webhook signature mode. The default `MAIL_MAILER=log` is for local development only. Use SMTP in production; do not log activation/reset links in a shared environment.

Use `CACHE_STORE=mongodb` for shared throttling and scheduler locks across web/worker processes. File sessions and synchronous Laravel queue configuration do not depend on SQL; actual asynchronous mail runs through the MongoDB `outbox`. No Redis, SQL, or Node runtime is required.

## Local start

The existing workspace has a local-only `.env` and an ignored, machine-specific MongoDB extension loaded by `./scripts/php`. Use that wrapper in place of `php` here when the system PHP does not load the extension. Its local replica set uses port 27028; it must be running before database commands or tests. On a fresh machine install the extension normally or use Docker.

For a fresh installation with PHP/MongoDB installed:

```sh
cp .env.example .env
composer install
php artisan key:generate
# Fill JWT_SECRET with a cryptographically random secret of at least 32 bytes.
# Configure MONGODB_URI/database and frontend/provider settings in .env.
php artisan fundraiser:preflight
php artisan fundraiser:indexes --apply
php artisan serve
```

In another terminal, run `php artisan schedule:work`. The application listens at `http://localhost:8000`, with `/up` for liveness and `/ready` for MongoDB readiness. No provider credentials are needed for application boot, state listings, or tests; provider endpoints report 503 until configured.

Docker alternative (Docker daemon must be running):

```sh
cp .env.example .env
# Generate APP_KEY and JWT_SECRET, and configure mail/frontend values.
docker compose build
docker compose up -d
docker compose exec app php artisan fundraiser:indexes --apply
```

The compose stack provides PHP-FPM, Nginx, a scheduler, and an isolated MongoDB replica set. It binds the web port to localhost and does not expose unauthenticated MongoDB to the host. It deliberately uses `nabaku_local`, not production data. It is a development topology; deploy the same PHP image with managed production secrets, authenticated MongoDB, TLS ingress, and proper monitoring.

## Heroku

The `Procfile` runs the API with Heroku's PHP buildpack (`web`, Apache serving `public/`) and the email outbox scheduler (`worker`, `schedule:work`). `composer.json` requires PHP 8.4 and `ext-mongodb`, which the buildpack installs. Set the `.env.example` keys as config vars (`heroku config:set`), use the existing Atlas URI as `MONGODB_URI`, set `CACHE_STORE=mongodb`, and scale the worker with `heroku ps:scale worker=1`, or activation and reset emails are never sent. `public/.user.ini` raises PHP's upload limits to match `docker/php.ini`. Follow the cutover steps below before pointing the web app or Flutterwave webhooks at it.

## Commands

| Command | Behavior |
| --- | --- |
| `fundraiser:preflight` | Read-only collection counts/index counts, replica-set check, proposed-unique-index duplicate checks |
| `fundraiser:audit-data` | Read-only BSON type, orphan reference, and money-value checks without personal-data output |
| `fundraiser:indexes` | Preview additive indexes |
| `fundraiser:indexes --apply` | Create indexes; fails on duplicates or conflicting definitions; never drops data/indexes |
| `fundraiser:admin email --phone=... --name=...` | Prompt secretly for a new password; never changes an existing account |
| `fundraiser:outbox --limit=100` | Lease and deliver pending emails; retry transient delivery failures up to ten attempts |
| `fundraiser:reconcile` | Compare wallet/project totals and reservations; return failure on discrepancy or unreserved legacy pending requests |
| `fundraiser:payment reference` | Verify provider status without financial mutation |
| `fundraiser:payment reference --apply` | Apply the verified result through the same idempotent settlement as webhooks |

Inspect `outbox` records with `status=failed` and `checkoutState=unknown` transactions as part of operations. SMTP delivery is **at least once**: a crash after acceptance but before saving `sent` can resend a message. The stable Message-ID helps tracing but does not guarantee mailbox deduplication. Money is credited only once independently of mail delivery. Do not delete unsent encrypted payloads or rotate `APP_KEY` without handling outstanding messages.

No Monnify payout is initiated by an admin status change. The Node source implements manual withdrawal completion, and this conversion retains it. The administrator should mark completed only after verifying the external/manual payout. Bank details are snapshotted when a new withdrawal is requested. A completion debits the booked balance and releases its reservation atomically; cancellation only releases the reservation.

Stored currency units remain NGN major-unit numbers. Financial arithmetic uses integer kobo/BCMath before writing compatible numbers. Existing `ledger_balance` is preserved, not reinterpreted as reservations. Audit any pre-existing floating-point drift, additional wallet credits, or balance adjustments; reconciliation does not automatically repair or assume the source's totals were correct.

## Tests and evidence

```sh
composer check-platform-reqs
vendor/bin/pint --test
vendor/bin/phpunit --testdox
composer audit
```

Integration tests require a disposable replica-set database named `nabaku_test`; they clear its collections before each test and refuse non-test database names. Default test URI is `mongodb://127.0.0.1:27028/?replicaSet=nabaku-test`. Override `MONGODB_URI` to another isolated test replica set. Do not point tests at production. CI starts its own replica set. Concurrent tests start two fresh PHP processes; `TEST_PHP_EXTENSION` can specify a custom local extension file if it is not loaded by php.ini.

The checked-in Node fixture capture uses a separate synthetic `nabaku_baseline_test` database, disables dotenv/provider calls, and stubs only the unused undeclared `shortid` import. To recapture with the old project's dependencies installed, run `node scripts/capture-node-baseline.cjs ../fundraiser-api`. This is an optional migration-development tool, not a production dependency. `NodeContractTest` compares the 13 captured responses, accounting explicitly for corrected counts, public redaction and additional wallet/profile fields. Route and feature tests cover all mounted route handlers, but real provider and React staging verification remain deployment gates.

Latest local evidence: **34 tests, 320 assertions passed**. Composer strict validation, platform checks, formatting, dependency audit, and route caching passed. A synthetic backup restored eight collections/four documents with identical BSON and passing preflight, data audit, and reconciliation. This does not verify production data. Docker configuration validates, but Docker builds and hosted CI have not run.

## Backup, rollout and rollback

1. Discover actual MongoDB topology/version, database/collection names, indexes, BSON types, null/missing values, orphan references, and balances. Run preflight/reconciliation against a restored staging copy first. Resolve differences before creating unique indexes; never drop a live index to make an install pass.
2. Freeze writes for a consistent dump, or use your managed cluster's consistent snapshot workflow. With MongoDB Database Tools installed, `scripts/backup-restore.sh tools-config.yml source_db source_restore_test archive.gz` dumps and restores to a distinct disposable target. The protected tools config contains the URI/credentials; the helper never drops the target or overwrites an archive. Verify counts, IDs, indexes, reference types, and reconciliation after restore. Do not copy production personal data into an unsecured development environment.
3. Complete real sandbox checkout, duplicate webhook, account verification, Monnify balance, Cloudinary, SMTP, and React flows. Confirm frontend route paths, CORS origins, manager policies, and intentional changes in [API.md](API.md).
4. Resolve every outstanding/unknown payment and existing withdrawal. Legacy requests without reservations block a clean reconciliation; complete/cancel them during the write freeze or establish a reviewed reservation backfill. Historical requests without saved bank details need manual verification; never infer a payout destination from an unreviewed current account.
5. Deploy Laravel to staging, then production with no public write traffic initially. Use `/up`, `/ready`, route/config cache, preflight, and reconciliation checks. Run `php artisan config:cache` and `php artisan route:cache` on the deployed artifact with its final environment.
6. At cutover, pause all API mutations, drain Node writes, stop old workers, and pause/drain webhook processing at the ingress. Take the final backup, apply tested additive indexes, check counts/balances, switch API and webhook routing to Laravel, then resume one authoritative writer. Do not have both runtimes process payments against the same database.
7. Monitor 5xx/401 rates, provider timeouts, webhook retries, wallet/project reconciliation, outbox failures, and pending withdrawals. Use request IDs and payment references; do not log secrets or request payloads.
8. Roll back on unexplained balance differences, sustained error rates, or broken login/checkout flows. Pause writes, drain Laravel handlers/outbox, reconcile every post-cutover event, and verify Node can read the current documents. Pending Laravel reservations must be resolved before Node resumes financial writes because Node does not enforce them. Reroute only when financial state is consistent. Restoring the old snapshot alone would lose new donations and is not an acceptable rollback.
9. Keep the Node deployment for the observation period agreed with the operator. Retire it only after React and provider flows pass and no API/worker/scheduled task needs it. Archive, rather than delete, this migration evidence.

This repository does not include the React project, production host configuration, provider sandbox credentials, or a sanitized production snapshot. Production backup/restore, frontend acceptance, live sandbox validation, deployment, traffic switching, and retirement must be recorded when those environments are available; local test success does not establish those outcomes.
