#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"

DEFAULT_TARGETS=(
    "kinetis:8081"
    "laravel:8082"
    "symfony:8083"
    "codeigniter:8084"
    "yii2:8085"
    "cakephp:8086"
    "slim:8087"
    "kinetis-fpm:8088"
    "laravel-octane:8089"
    "slim-frankenphp:8090"
)

# Sweep a subset by naming targets, e.g.
#   TARGETS="kinetis:8081 slim-frankenphp:8090" ./run.sh
# Only the named targets are started, so the rest never compete for the
# same CPU package as whatever is under test.
read -r -a TARGETS <<< "${TARGETS:-${DEFAULT_TARGETS[*]}}"

CONCURRENCY_LEVELS=(${CONCURRENCY_LEVELS:-16 32 64 128 256})
QUERY_COUNTS=(1 5 10 15 20)
QUERY_COUNT_CONCURRENCY=256
DURATION=15
WARMUP_DURATION=5
TIMESTAMP=$(date -u +%Y%m%dT%H%M%SZ)
RESULTS_DIR="results/${TIMESTAMP}"
RAW_DIR="${RESULTS_DIR}/raw"
CSV="${RESULTS_DIR}/results.csv"
MD="${RESULTS_DIR}/results.md"

mkdir -p "$RAW_DIR"

banner() {
    cat <<'BANNER'
================================================================================
METHODOLOGY NOTE — read before trusting these numbers

This suite approximates TechEmpower's own methodology, which runs the
application, the database, and the wrk load-generator client on three
SEPARATE physical machines connected by a dedicated switch, specifically
so the load generator's own CPU/network usage never contends with the
app or database under test.

Here, all services run as containers on ONE Docker host, sharing the
same physical CPU package, kernel scheduler, loopback network stack, and
disk I/O. There is no real hardware isolation between "roles."

Consequently: these numbers are only meaningful as RELATIVE, same-machine
comparisons between the 10 targets below, run back to back under
identical conditions. They are NOT comparable to TechEmpower's own
published results, which run on dedicated hardware.

Concurrency levels swept: 16/32/64/128/256 (TechEmpower's own historical
set additionally includes 512; omitted here — at that level on shared
single-host hardware, the number increasingly reflects contention with
the wrk client's own CPU usage, not the framework under test). Override
via CONCURRENCY_LEVELS="16 32 64 128 256 512" if running this on
genuinely separate hardware.

Query-count sweep (queries=1,5,10,15,20) held at a FIXED concurrency of
256, per TechEmpower's own methodology split — a different sweep axis
than the other four test types, not a reduced version of the same one.
================================================================================
BANNER
}

banner | tee "${RESULTS_DIR}/README.txt"

echo "framework,test,level_type,level,requests_per_sec,latency_avg_ms,latency_stdev_ms,latency_max_ms,p50_ms,p75_ms,p90_ms,p99_ms" > "$CSV"

wait_for_target() {
    local port=$1
    for _ in $(seq 1 60); do
        if curl -sf --connect-timeout 2 --max-time 5 -o /dev/null "http://localhost:${port}/plaintext"; then
            return 0
        fi
        sleep 2
    done
    echo "Target on port ${port} never became reachable." >&2
    return 1
}

to_ms() {
    local raw=$1
    local value unit
    value=$(echo "$raw" | sed -E 's/[a-zA-Z]+$//')
    unit=$(echo "$raw" | sed -E 's/^[0-9.]+//')
    case "$unit" in
        us) awk "BEGIN { printf \"%.3f\", $value / 1000 }" ;;
        ms) awk "BEGIN { printf \"%.3f\", $value }" ;;
        s)  awk "BEGIN { printf \"%.3f\", $value * 1000 }" ;;
        *)  echo "$value" ;;
    esac
}

run_wrk() {
    local name=$1 test=$2 level_type=$3 level=$4 concurrency=$5 host=$6 port=$7 path=$8
    local threads out client_cpus=2
    threads=$(( concurrency < client_cpus ? concurrency : client_cpus ))

    docker compose --profile benchmark run --rm wrk \
        -H "Host: ${host}" \
        -H 'Accept: application/json,text/html;q=0.9,application/xhtml+xml;q=0.9,application/xml;q=0.8,*/*;q=0.7' \
        --timeout 8 -d "${WARMUP_DURATION}" -c "$concurrency" -t "$threads" \
        "http://${host}:${port}${path}" >/dev/null 2>&1 || true

    out=$(docker compose --profile benchmark run --rm wrk \
        -H "Host: ${host}" \
        -H 'Accept: application/json,text/html;q=0.9,application/xhtml+xml;q=0.9,application/xml;q=0.8,*/*;q=0.7' \
        --latency --timeout 8 -d "${DURATION}" -c "$concurrency" -t "$threads" \
        "http://${host}:${port}${path}")

    echo "$out" > "${RAW_DIR}/${name}_${test}_${level_type}${level}.txt"

    local rps lat_avg lat_stdev lat_max p50 p75 p90 p99
    rps=$(echo "$out" | grep 'Requests/sec:' | awk '{print $2}')
    lat_avg=$(echo "$out" | grep -m1 '^\s*Latency' | awk '{print $2}')
    lat_stdev=$(echo "$out" | grep -m1 '^\s*Latency' | awk '{print $3}')
    lat_max=$(echo "$out" | grep -m1 '^\s*Latency' | awk '{print $4}')
    p50=$(echo "$out" | grep -m1 -E '^ *50%' | awk '{print $2}')
    p75=$(echo "$out" | grep -m1 -E '^ *75%' | awk '{print $2}')
    p90=$(echo "$out" | grep -m1 -E '^ *90%' | awk '{print $2}')
    p99=$(echo "$out" | grep -m1 -E '^ *99%' | awk '{print $2}')

    echo "${name},${test},${level_type},${level},${rps:-0},$(to_ms "${lat_avg:-0ms}"),$(to_ms "${lat_stdev:-0ms}"),$(to_ms "${lat_max:-0ms}"),$(to_ms "${p50:-0ms}"),$(to_ms "${p75:-0ms}"),$(to_ms "${p90:-0ms}"),$(to_ms "${p99:-0ms}")" >> "$CSV"
}

echo "Bringing up the stack..."
TARGET_NAMES=()
for target in "${TARGETS[@]}"; do
    TARGET_NAMES+=("${target%%:*}")
done
docker compose up --build -d mysql migrate "${TARGET_NAMES[@]}"

for target in "${TARGETS[@]}"; do
    name=${target%%:*}
    port=${target##*:}
    echo "Waiting for ${name} (port ${port})..."
    wait_for_target "$port"
done

for target in "${TARGETS[@]}"; do
    name=${target%%:*}
    port=${target##*:}

    for test in json db fortunes plaintext; do
        for level in "${CONCURRENCY_LEVELS[@]}"; do
            echo "[${name}] /${test} @ concurrency ${level}"
            run_wrk "$name" "$test" "concurrency" "$level" "$level" "$name" "8080" "/${test}"
        done
    done

    for test in queries updates; do
        for qcount in "${QUERY_COUNTS[@]}"; do
            echo "[${name}] /${test}?queries=${qcount} @ concurrency ${QUERY_COUNT_CONCURRENCY}"
            run_wrk "$name" "$test" "queries" "$qcount" "$QUERY_COUNT_CONCURRENCY" "$name" "8080" "/${test}?queries=${qcount}"
        done
    done
done

docker compose down

{
    echo "# TechEmpower-methodology benchmark results — ${TIMESTAMP}"
    echo
    cat "${RESULTS_DIR}/README.txt"
    echo
    echo '```'
    column -s, -t "$CSV"
    echo '```'
} > "$MD"

echo "Done. Results: ${CSV} / ${MD}"
