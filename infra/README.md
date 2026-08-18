# kinetis-benchmarks infrastructure (AWS CDK)

Provisions the 3-EC2-instance layout used to run the benchmark suite
with real machine isolation — a dedicated **app** server (runs Docker,
one framework's container at a time), **db** server (native MySQL, not
RDS), and **client** server (runs `wrk`) — so the load generator, the
database, and the framework under test never share CPU/scheduler time
the way running everything on one Docker host does.

## One-time setup

```sh
npm install

# Only needed once per AWS account+region — creates the S3 bucket/IAM
# roles CDK itself uses to publish deployment assets.
npx cdk bootstrap
```

## Deploy

```sh
npx cdk deploy
```

Outputs the three instances' IDs and private IPs — used to target them
via `aws ssm send-command` for the actual deployment/test steps (git
clone, `docker compose build`, running the sweep).

`cdk.context.json` is committed, not gitignored — it pins the exact
default-VPC/subnet IDs this stack resolved to, so every future deploy
targets the same network layout instead of re-resolving (and
potentially drifting) each time.

## Destroy

```sh
npx cdk destroy
```

Removes exactly what this stack created — the 3 instances, their
security groups, and the shared IAM role/instance profile. The default
VPC itself is only ever looked up, never modified, so it's untouched.

## What's deliberately not in this stack

- **No key pair, no inbound SSH anywhere** — every instance is managed
  exclusively via SSM Session Manager, matching how every other AWS
  interaction in this project has worked. The IAM role attached to all
  three instances carries only `AmazonSSMManagedInstanceCore`.
- **No RDS** — the db instance runs MySQL natively, matching
  TechEmpower's own real practice of a dedicated, non-virtualized
  database box rather than a managed service with an extra network hop.
- **No load balancer, no auto-scaling** — this is a fixed, three-node
  benchmark rig, not a production service.
