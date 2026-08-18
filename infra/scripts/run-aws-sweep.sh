#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"
source ./lib.sh
cd ../..

APP_INSTANCE=$(stack_output AppInstanceId)
APP_IP=$(stack_output AppPrivateIp)
DB_IP=$(stack_output DbPrivateIp)
CLIENT_INSTANCE=$(stack_output ClientInstanceId)

echo "App:    ${APP_INSTANCE} (${APP_IP})"
echo "DB:     $(stack_output DbInstanceId) (${DB_IP})"
echo "Client: ${CLIENT_INSTANCE}"

APP_CPUS=$(ssm_run "$APP_INSTANCE" "nproc" 20)
KINETIS_THREADS=$((APP_CPUS * 5 / 2))
echo "App vCPUs: ${APP_CPUS} (kinetis worker threads: ${KINETIS_THREADS})"

TARGETS=(${TARGETS:-kinetis laravel symfony codeigniter yii2 cakephp slim kinetis-fpm laravel-octane slim-frankenphp})
CONCURRENCY_LEVELS=(${CONCURRENCY_LEVELS:-16 32 64 128 256})
QUERY_COUNTS=(1 5 10 15 20)
QUERY_COUNT_CONCURRENCY=256
DURATION=15
WARMUP_DURATION=5
TIMESTAMP=$(date -u +%Y%m%dT%H%M%SZ)
RESULTS_DIR="results/${TIMESTAMP}-aws"
RAW_DIR="${RESULTS_DIR}/raw"
CSV="${RESULTS_DIR}/results.csv"
MD="${RESULTS_DIR}/results.md"

mkdir -p "$RAW_DIR"

banner() {
    cat <<'BANNER'
================================================================================
This run used three SEPARATE EC2 instances (app/db/client), not one
shared Docker host — the real machine isolation TechEmpower's own
methodology calls for. Numbers here are not directly comparable to a
same-host local run's numbers (see that run's own results/*/README.txt
for the disclosure it carries) and sit much closer to TechEmpower's own
published methodology, though still not on TechEmpower's own dedicated
bare-metal hardware.

Concurrency levels swept: 16/32/64/128/256 (override via
CONCURRENCY_LEVELS="16 32 64 128 256 512" — genuinely meaningful here,
unlike the local single-host run, since the client has its own real,
separate instance).

Query-count sweep (queries=1,5,10,15,20) held at a fixed concurrency of
256, per TechEmpower's own methodology split.
================================================================================
BANNER
}

banner | tee "${RESULTS_DIR}/README.txt"

echo "framework,test,level_type,level,requests_per_sec,latency_avg_ms,latency_stdev_ms,latency_max_ms,p50_ms,p75_ms,p90_ms,p99_ms" > "$CSV"

wait_for_app() {
    for _ in $(seq 1 60); do
        if ssm_run "$CLIENT_INSTANCE" "curl -sf --connect-timeout 2 --max-time 5 -o /dev/null http://${APP_IP}:8080/plaintext" 15 >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    echo "App on ${APP_IP}:8080 never became reachable from the client." >&2
    return 1
}

start_framework() {
    local name=$1
    ssm_run "$APP_INSTANCE" "docker stop kb-active >/dev/null 2>&1 || true; docker rm kb-active >/dev/null 2>&1 || true; docker run -d --name kb-active -p 8080:8080 --sysctl net.ipv4.ip_local_port_range='1024 65535' --sysctl net.ipv4.tcp_tw_reuse=1 -e DB_CONNECTION=mysql -e DB_HOST=${DB_IP} -e DB_PORT=3306 -e DB_NAME=tfbench -e DB_USER=tfbench -e DB_PASSWORD=tfbench -e KINETIS_WORKER_THREADS=${KINETIS_THREADS} -e SLIM_WORKER_THREADS=${KINETIS_THREADS} kb-${name}" 30 >/dev/null
    wait_for_app
}

stop_framework() {
    ssm_run "$APP_INSTANCE" "docker stop kb-active && docker rm kb-active" 30 >/dev/null
}

run_wrk() {
    local name=$1 test=$2 level_type=$3 level=$4 concurrency=$5 path=$6
    local threads out client_cpus=4
    threads=$(( concurrency < client_cpus ? concurrency : client_cpus ))

    local wrk_cmd="wrk -H 'Host: ${APP_IP}' -H 'Accept: application/json,text/html;q=0.9,application/xhtml+xml;q=0.9,application/xml;q=0.8,*/*;q=0.7' --timeout 8 -d ${WARMUP_DURATION} -c ${concurrency} -t ${threads} http://${APP_IP}:8080${path}"
    ssm_run "$CLIENT_INSTANCE" "$wrk_cmd" $((WARMUP_DURATION + 20)) >/dev/null 2>&1 || true

    wrk_cmd="wrk -H 'Host: ${APP_IP}' -H 'Accept: application/json,text/html;q=0.9,application/xhtml+xml;q=0.9,application/xml;q=0.8,*/*;q=0.7' --latency --timeout 8 -d ${DURATION} -c ${concurrency} -t ${threads} http://${APP_IP}:8080${path}"
    out=$(ssm_run "$CLIENT_INSTANCE" "$wrk_cmd" $((DURATION + 20)))

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

for name in "${TARGETS[@]}"; do
    echo "=== ${name} ==="
    start_framework "$name"

    for test in json db fortunes plaintext; do
        for level in "${CONCURRENCY_LEVELS[@]}"; do
            echo "[${name}] /${test} @ concurrency ${level}"
            run_wrk "$name" "$test" "concurrency" "$level" "$level" "/${test}"
        done
    done

    for test in queries updates; do
        for qcount in "${QUERY_COUNTS[@]}"; do
            echo "[${name}] /${test}?queries=${qcount} @ concurrency ${QUERY_COUNT_CONCURRENCY}"
            run_wrk "$name" "$test" "queries" "$qcount" "$QUERY_COUNT_CONCURRENCY" "/${test}?queries=${qcount}"
        done
    done

    stop_framework
done

{
    echo "# TechEmpower-methodology benchmark results (AWS, real machine isolation) — ${TIMESTAMP}"
    echo
    cat "${RESULTS_DIR}/README.txt"
    echo
    echo '```'
    column -s, -t "$CSV"
    echo '```'
} > "$MD"

echo "Done. Results: ${CSV} / ${MD}"
