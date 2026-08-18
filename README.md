# TechEmpower-methodology benchmark: Kinetis vs. 6 other PHP frameworks (plus Kinetis on PHP-FPM, Laravel on Octane, and Slim on FrankenPHP)

**TL;DR** — looking for the results? They're here:
[docs.kinetis.dev/benchmarks.html](https://docs.kinetis.dev/benchmarks.html).
This repository is the methodology and code behind them.

Compares [Kinetis](https://github.com/kinetis-dev/kinetis) against
Laravel, Symfony, CodeIgniter, Yii2, CakePHP, and Slim, implementing the
standard [TechEmpower Framework
Benchmarks](https://www.techempower.com/benchmarks) (TFB) test types
against identical schema and data — same 6 routes, same MySQL dataset,
same clamp/escaping rules, one implementation per framework, each using
that framework's own idiomatic minimal routing/DB/templating layer.

**Two frameworks appear twice, on two runtimes each.** `kinetis`
(FrankenPHP worker mode, the architecturally-intended way to deploy it)
and `kinetis-fpm` (the identical, unmodified application served under
classic nginx + PHP-FPM instead, same as every other framework here).
`slim` and `slim-frankenphp` are the same pairing for Slim.

That second pair is a control rather than a competitor: Slim is a
minimalistic "framework", nothing more than a request router and a PSR-7
implementation (`slim/slim` with `slim/psr7`), so putting it on the same
worker runtime Kinetis uses is what separates what a framework costs
from what the runtime provides. Together the four isolate Kinetis's own
request-handling cost (kinetis-fpm vs. the other 6, same runtime model),
what the persistent-worker runtime adds (kinetis vs. kinetis-fpm), and
what a near-zero-overhead framework reaches on that same runtime
(kinetis vs. slim-frankenphp).

Two ways to run it, covered in full below:

- **Local** (`docker compose` + `run.sh`) — fast, zero AWS cost, but all
  12 containers share one Docker host. Good for iterating on the
  framework implementations themselves.
- **AWS** (`infra/`) — three separate EC2 instances (app/db/client),
  matching TFB's own real machine-isolation practice. This is the
  methodologically meaningful run; the local one is not.

## Repository layout

```
schema/seed.sql       Shared MySQL schema + seed data (world/fortune tables)
docker-compose.yml     Local: 10 apps + mysql + migrate + a profile-gated wrk client
run.sh                 Local: sweep harness + CSV/MD report
kinetis/               Kinetis on FrankenPHP (composer.json pulls kinetis/* from Packagist)
kinetis-fpm/           Docker/nginx/php-fpm setup only — reuses kinetis/'s own source unmodified
laravel-octane/        Docker/Octane-on-FrankenPHP setup only — reuses laravel/'s own source unmodified
laravel/               One full implementation of the six routes per framework,
symfony/               each using that framework's own idiomatic minimal
codeigniter/           routing/DB/templating layer
yii2/
cakephp/
slim/
slim-frankenphp/       Docker/FrankenPHP-worker setup only — reuses slim/'s own source unmodified
infra/                 AWS CDK stack (TypeScript) + deployment/sweep scripts
  lib/kinetis-benchmarks-stack.ts   The 3-instance stack definition
  scripts/                          Every script the AWS path runs — see below
  README.md                         CDK-specific one-time setup / deploy / destroy
results/               Written by run.sh / run-aws-sweep.sh (gitignored)
```

## The six test types

Every framework implements the same six routes, against the same
`world`/`fortune` MySQL tables (`schema/seed.sql` — 10,000 `world` rows,
12 `fortune` rows including a raw `<script>` tag and a bare `&`, to
verify each framework's template escaping is actually correct, not just
fast):

| Route | What it does |
|---|---|
| `GET /json` | `{"message":"Hello, World!"}`, fresh object every request. |
| `GET /db` | One random `id` (1–10000), fetch that `world` row, return as JSON. |
| `GET /queries?queries=N` | N independent single-row fetches (own query each — never `WHERE id IN (...)`). `N` clamped to 1–500; missing/invalid defaults to 1, never an error. |
| `GET /fortunes` | Every `fortune` row plus one in-memory-only extra, sorted by message, rendered as an HTML table with the message column escaped by the template engine. |
| `GET /updates?queries=N` | Same N-row fetch as `/queries`, each row's `randomNumber` reassigned and persisted with its own `UPDATE` (never batched). Returns the N updated rows. |
| `GET /plaintext` | Raw `Hello, World!`, `Content-Type: text/plain`. |

---

## Configuration and fairness

Read this before trusting any of the numbers — it exists specifically
so nothing here can be dismissed as "the numbers were tuned to make one
framework win." Every setting below applies identically wherever it
applies; where a framework has its own production mechanism, that
mechanism is used rather than a lowest-common-denominator substitute.

**Uniform PHP runtime, all 10 targets:** `php.ini-production` active,
opcache with identical production tuning (`validate_timestamps=0`,
192M, tracing JIT with a 64M buffer), nginx access logging off on
every PHP-FPM target. Debug/development mode is off in every framework
(`APP_ENV=prod`, `YII_DEBUG=false`, `CI_ENVIRONMENT=production`,
CakePHP `debug=false`, Laravel `APP_DEBUG=false`).

**Each framework's own AOT/pre-warming, enabled:** Symfony
`cache:warmup` at image build; Laravel `config:cache`/`route:cache`/
`view:cache` at container start (config caching bakes env values, and
DB env arrives at run time); CodeIgniter `spark optimize`; Slim's
route cache file; Yii2's DB schema cache; Kinetis `kinetis build`
(compiled discovery caches, used by both `kinetis` and `kinetis-fpm`).

**Worker sizing:**

- PHP-FPM pool, all 7 nginx+PHP-FPM services: `pm = static`,
  `pm.max_children = 128`. The stock image default of 5 measures pool
  starvation, not framework overhead; 128 (~16x the app instance's 8
  vCPUs) covers every concurrency level tested (up to 256) for a
  DB-round-trip-bound workload.
- FrankenPHP worker threads (`kinetis` and `slim-frankenphp`): 2.5x the
  host's vCPUs — 20 on the 8-vCPU AWS app instance, passed as
  `KINETIS_WORKER_THREADS` and `SLIM_WORKER_THREADS` by both
  harnesses. Both worker-mode targets get the same count, which is what
  makes them comparable to each other. Each worker thread
  handles exactly one HTTP request at a time
  (`frankenphp_handle_request()` blocks until the response is fully
  sent), so cross-request concurrency is bounded by thread count, the
  same as PHP-FPM's worker count. Kinetis's Fiber/Revolt
  `concurrently()` provides real parallelism *within* one request's
  work (fetching N rows at once for `/queries`) — it does not let one
  thread serve multiple separate connections the way an
  event-loop-based server does. This is exactly what the `kinetis` /
  `kinetis-fpm` split isolates.
- Laravel Octane workers: 128, not 20 (`OCTANE_WORKERS`). Octane's
  request model is blocking, so it draws concurrency from worker count
  the same way a PHP-FPM pool does; sizing it like the two non-blocking
  worker targets starves it. 128 matches the PHP-FPM pool above, which
  is the comparison that treats it fairly.
- Kinetis DB pool: 12 connections per worker thread (each thread
  builds its own pool), keeping the total —
  `threads x maxConnections = 240` — comfortably under MySQL's
  `max_connections` while leaving fan-out width for the
  `/queries`/`/updates` routes.

**Infrastructure headroom** (so the rig never binds before the
framework under test does):

- MySQL `max_connections = 1000` — 128 per-request connections per
  FPM target plus Kinetis's pooled 240 need more than the stock 300.
- MySQL `max_connect_errors = 1000000` — MySQL blocks a client host
  outright after too many failed connection attempts, and a one-off
  bad deploy must not permanently wedge a run.
- `net.ipv4.ip_local_port_range` widened and `tcp_tw_reuse` enabled
  per app container — sustained 2000+ req/s of short-lived outbound
  DB connections can exhaust the default ~28k ephemeral port range
  before any CPU/DB/pool limit is reached, and a container's own
  network namespace means host-level sysctls don't apply.

---

## Running it locally

```sh
# Bring the whole stack up (10 apps + mysql + migrate), without the
# load-generator client:
docker compose up --build -d

# Smoke-test one framework by hand, e.g. Kinetis on its published port:
curl http://localhost:8081/json
curl http://localhost:8081/fortunes   # check the <script> tag is escaped

# Run the full concurrency/query-count sweep across all 10 targets and
# write a combined results table:
./run.sh

# Stop containers between runs, keeping the seeded database and
# installed vendor/ intact (never pass -v — that would force a full
# reseed/reinstall on the next run):
docker compose down
```

Published ports: `8081` Kinetis (FrankenPHP), `8082` Laravel, `8083`
Symfony, `8084` CodeIgniter, `8085` Yii2, `8086` CakePHP, `8087` Slim,
`8088` Kinetis (nginx + PHP-FPM), `8089` Laravel (Octane on FrankenPHP),
`8090` Slim (FrankenPHP worker mode).

`run.sh` sweeps concurrency levels `16, 32, 64, 128, 256` across
`/json`, `/db`, `/fortunes`, `/plaintext` for every framework, then
sweeps `queries=1,5,10,15,20` at a fixed concurrency of `256` across
`/queries`/`/updates` — TechEmpower's own two-different-sweep-axis
split, not one sweep collapsed into the other. Results land under
`results/<timestamp>/results.csv` (raw per-run `wrk` output alongside
it under `raw/`) and the same table rendered as `results.md`.

### Local methodology — read before trusting these numbers

TechEmpower runs the application, the database, and the `wrk`
load-generator client on three **separate physical machines** connected
by a dedicated switch, specifically so the load generator's own
CPU/network usage never contends with the app or database under test.

Locally, all 12 containers (10 apps, 1 database, 1 client) run on **one
Docker host**, sharing the same physical CPU package, kernel scheduler,
loopback network stack, and disk I/O — there is no real hardware
isolation between roles, only per-container CPU/memory limits (`cpus:
"2"`, `memory: 2g` on each service) as a partial approximation.

**Consequently: local results are only meaningful as relative,
same-machine comparisons between the 10 targets, run back to back
under identical conditions on the same hardware. They are not
comparable to TechEmpower's own published results, and are not directly
comparable to the AWS run's numbers either (different hardware, and the
AWS run has genuine machine isolation the local run doesn't).**

---

## Running it on AWS — real machine isolation

`infra/` provisions three separate EC2 instances via CDK — **app**
(Docker, one framework's container running at a time), **db** (native
MySQL, not RDS), **client** (native `wrk`) — so the load generator, the
database, and the framework under test never share CPU/scheduler time,
closely matching TFB's own real practice (short of TFB's own dedicated
physical hardware and switch).

### Prerequisites

- AWS credentials with permission to create VPC-scoped resources (EC2
  instances, security groups, IAM role/instance profile) in the target
  account/region. No key pair is created or needed — every instance is
  managed exclusively via SSM Session Manager, no inbound SSH anywhere.
- Node.js (for the CDK CLI) and the AWS CLI, both on the machine running
  these scripts (not on the EC2 instances themselves).
- `jq` is not required — plain `aws ... --query ... --output text` is
  used throughout.

### One-time setup

```sh
cd infra
npm install
npx cdk bootstrap          # once per AWS account+region
```

### Full run — the easy path

```sh
cd infra/scripts

# Provisions the 3 instances (npx cdk deploy), then installs/configures
# MySQL on db, Docker + builds all 10 images on app, and wrk on client —
# via SSM, no SSH. Idempotent; safe to re-run.
./deploy-and-setup.sh

# Runs the full concurrency/query-count sweep against the real,
# separate instances and writes results/<timestamp>-aws/{results.csv,results.md}.
./run-aws-sweep.sh

# Tear everything down once you're done — removes the 3 instances,
# their security groups, and the shared IAM role. The looked-up default
# VPC itself is never touched.
cd ..
npx cdk destroy
```

Set `AWS_PROFILE=<profile>` and/or `AWS_REGION=<region>` (default
`eu-west-1`) in the environment before any of the above if you're not
using the default profile/region. `STACK_NAME` (default
`KinetisBenchmarksStack`) only needs setting if you changed it in
`infra/bin/infra.ts`.

### What each script does

All under `infra/scripts/`:

| Script | Runs where | What it does |
|---|---|---|
| `lib.sh` | sourced, not run directly | Shared `stack_output()` (reads a CloudFormation output), `ssm_run()` (sends a command via SSM, polls for completion, returns stdout or fails loudly with stderr), `to_ms()` (wrk duration-string normalizer). |
| `setup-db.sh` | on the **db** instance, via SSM | Installs MySQL if missing, clones/pulls this repo, opens MySQL to the app instance's security group, seeds `schema/seed.sql`. Idempotent. |
| `setup-app.sh` | on the **app** instance, via SSM | Installs Docker if missing, clones/pulls this repo, builds all 10 `kb-<name>` images in parallel. Idempotent. |
| `setup-client.sh` | on the **client** instance, via SSM | Installs `wrk` natively (no Docker on the client, to keep its own resource footprint as close to bare metal as possible). |
| `deploy-and-setup.sh` | your machine | `cdk deploy`, waits for each instance's SSM agent to register, then runs the three scripts above in the right order. |
| `run-aws-sweep.sh` | your machine, driving app+client via SSM | For each of the 10 targets: starts its container on the app instance (`docker run -p 8080:8080 ...`), waits for it to answer, runs the same concurrency/query-count wrk sweep as local `run.sh` (via SSM on the client instance, targeting the app instance's private IP directly — no port published to the internet), stops the container, moves to the next framework. Writes `results/<timestamp>-aws/`. |

### Manual walkthrough — every command by hand

Useful for debugging a failed step, or if you'd rather not run the
wrapper scripts at all. Every one of the commands below is exactly what
the corresponding script above automates.

**1. Provision the stack**

```sh
cd infra
npx cdk deploy --require-approval never
```

Read the outputs (or re-fetch them any time):

```sh
STACK=KinetisBenchmarksStack
REGION=eu-west-1

aws cloudformation describe-stacks --stack-name "$STACK" --region "$REGION" \
    --query 'Stacks[0].Outputs' --output table
```

You need `AppInstanceId`, `AppPrivateIp`, `DbInstanceId`, `DbPrivateIp`,
`ClientInstanceId` for everything below.

**2. Confirm SSM is ready on each instance**

```sh
aws ssm describe-instance-information --region "$REGION" \
    --filters "Key=InstanceIds,Values=<instance-id>" \
    --query 'InstanceInformationList[0].PingStatus' --output text
```

Should print `Online`. Takes ~30–60s after the instance first becomes
`running`.

**3. Run a command on an instance via SSM**

Every setup step below is really this same two-call pattern — send,
then poll for the result:

```sh
CMD_ID=$(aws ssm send-command \
    --instance-ids <instance-id> \
    --document-name AWS-RunShellScript \
    --parameters 'commands=["echo hello"]' \
    --region "$REGION" --query 'Command.CommandId' --output text)

# Poll until Status is Success/Failed/Cancelled/TimedOut:
aws ssm get-command-invocation \
    --command-id "$CMD_ID" --instance-id <instance-id> \
    --region "$REGION" --query 'Status' --output text

# Once Success, read the output:
aws ssm get-command-invocation \
    --command-id "$CMD_ID" --instance-id <instance-id> \
    --region "$REGION" --query 'StandardOutputContent' --output text
```

To run a whole script this way, pass its contents as one string in the
`commands` array — the easiest way to build that JSON-escaped array
correctly by hand is:

```sh
COMMAND=$(cat infra/scripts/setup-db.sh)
aws ssm send-command \
    --instance-ids <db-instance-id> \
    --document-name AWS-RunShellScript \
    --parameters "commands=[$(python3 -c 'import json,sys; print(json.dumps(sys.stdin.read()))' <<< "$COMMAND")]" \
    --region "$REGION" --query 'Command.CommandId' --output text
```

**4. Set up each instance**

Run `setup-db.sh`'s contents on the db instance, `setup-app.sh`'s on the
app instance, `setup-client.sh`'s on the client instance, each via the
pattern above. `setup-app.sh` takes the longest (builds all 10 Docker
images) — expect several minutes.

**5. Start one framework on the app instance**

```sh
NAME=kinetis   # or laravel / symfony / codeigniter / yii2 / cakephp / slim / kinetis-fpm / laravel-octane / slim-frankenphp
DB_IP=<DbPrivateIp>

aws ssm send-command --instance-ids <app-instance-id> --document-name AWS-RunShellScript \
    --parameters "commands=[\"docker run -d --name kb-active -p 8080:8080 -e DB_CONNECTION=mysql -e DB_HOST=${DB_IP} -e DB_PORT=3306 -e DB_NAME=tfbench -e DB_USER=tfbench -e DB_PASSWORD=tfbench -e KINETIS_WORKER_THREADS=20 kb-${NAME}\"]" \
    --region "$REGION"
```

**6. Confirm it's answering, from the client instance**

```sh
APP_IP=<AppPrivateIp>
aws ssm send-command --instance-ids <client-instance-id> --document-name AWS-RunShellScript \
    --parameters "commands=[\"curl -sf http://${APP_IP}:8080/plaintext\"]" \
    --region "$REGION"
# then poll/read the invocation as in step 3
```

**7. Run wrk against it, from the client instance**

```sh
aws ssm send-command --instance-ids <client-instance-id> --document-name AWS-RunShellScript \
    --parameters "commands=[\"wrk -H 'Host: ${APP_IP}' --latency --timeout 8 -d 15 -c 64 -t 4 http://${APP_IP}:8080/json\"]" \
    --region "$REGION"
# poll/read the invocation — StandardOutputContent is wrk's normal report
```

Repeat for every route/concurrency-level/query-count combination you
want — `run-aws-sweep.sh` is exactly this loop, with the concurrency
levels `16 32 64 128 256` for `/json`, `/db`, `/fortunes`, `/plaintext`,
and query counts `1 5 10 15 20` at a fixed concurrency of `256` for
`/queries`/`/updates`, parsed into a CSV.

**8. Stop the current framework before starting the next one**

```sh
aws ssm send-command --instance-ids <app-instance-id> --document-name AWS-RunShellScript \
    --parameters '{"commands":["docker stop kb-active && docker rm kb-active"]}' \
    --region "$REGION"
```

**9. Tear down**

```sh
cd infra
npx cdk destroy
```

### AWS cost

Real on-demand pricing in `eu-west-1` (fetched via the AWS Pricing API,
not estimated): `c6i.2xlarge` (app, db) is $0.3648/hr each,
`c6i.xlarge` (client) is $0.1824/hr — **$0.912/hr total** for all
three instances running. A full cycle (deploy → setup → sweep →
destroy) typically takes 60–90 minutes end to end, most of it the sweep
itself, so budget roughly **$0.90–$1.40 per full run** — negligible EBS
storage and no data-transfer cost beyond the free tier, since nothing
crosses an AZ or leaves the VPC. Nothing is left running after
`cdk destroy`; there's no idle cost between runs.

### What's deliberately not in the AWS stack

- **No key pair, no inbound SSH anywhere** — every instance is managed
  exclusively via SSM Session Manager. The IAM role attached to all
  three instances carries only `AmazonSSMManagedInstanceCore`.
- **No RDS** — the db instance runs MySQL natively, matching
  TechEmpower's own real practice of a dedicated, non-virtualized
  database box rather than a managed service with an extra network hop.
- **No load balancer, no auto-scaling** — this is a fixed, three-node
  benchmark rig, not a production service.
- **No public port on the app instance** — its security group only
  accepts `:8080` from the client instance's own security group, never
  from the internet.

---

## What's deliberately not attempted here (either environment)

- No Postgres variant — only MySQL 8.4. This suite measures each
  framework's request/serialization/DB-round-trip overhead, not
  MySQL-vs-Postgres performance.
- The perf sweep itself never runs in CI — shared, noisy CI runner
  hardware can't produce a reproducible requests/sec number; running it
  there would only manufacture flaky, meaningless "regressions."
- No `queries`/`updates` batching (`WHERE id IN (...)`, a bulk
  `UPDATE`) — TechEmpower's own rule is one query per row, deliberately
  measuring per-round-trip overhead, not what a hand-optimized query
  could achieve.
