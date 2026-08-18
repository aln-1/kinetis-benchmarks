#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"
export AWS_REGION="${AWS_REGION:-eu-west-1}"
source ./lib.sh

TARGETS="${TARGETS:-kinetis}"
TEST_POINTS="${TEST_POINTS:-/queries?queries=20@256}"
DURATION="${DURATION:-15}"
WARMUP_DURATION="${WARMUP_DURATION:-5}"
THREADS=4

APP_INSTANCE=$(stack_output AppInstanceId)
APP_IP=$(stack_output AppPrivateIp)
DB_IP=$(stack_output DbPrivateIp)
CLIENT_INSTANCE=$(stack_output ClientInstanceId)

KINETIS_THREADS="${KINETIS_THREADS:-$(ssm_run "$APP_INSTANCE" "nproc" 20)}"
KINETIS_MAX_CONNECTIONS="${KINETIS_MAX_CONNECTIONS:-}"
EXTRA_ENV="${EXTRA_ENV:-}"

start_framework() {
    local name=$1
    local extra_env=""
    [ -n "$KINETIS_MAX_CONNECTIONS" ] && extra_env="-e DB_MAX_CONNECTIONS=${KINETIS_MAX_CONNECTIONS}"
    ssm_run "$APP_INSTANCE" "
        docker stop kb-active >/dev/null 2>&1 || true
        docker rm kb-active >/dev/null 2>&1 || true
        docker run -d --name kb-active -p 8080:8080 \
            --sysctl net.ipv4.ip_local_port_range='1024 65535' --sysctl net.ipv4.tcp_tw_reuse=1 \
            -e DB_CONNECTION=mysql -e DB_HOST=${DB_IP} -e DB_PORT=3306 \
            -e DB_NAME=tfbench -e DB_USER=tfbench -e DB_PASSWORD=tfbench \
            -e KINETIS_WORKER_THREADS=${KINETIS_THREADS} ${extra_env} ${EXTRA_ENV} \
            kb-${name} >/dev/null
        for i in \$(seq 1 60); do
            curl -sf --connect-timeout 1 --max-time 3 -o /dev/null http://127.0.0.1:8080/plaintext && exit 0
            sleep 1
        done
        echo 'APP NEVER BECAME READY' >&2
        docker logs kb-active --tail 50 >&2
        exit 1
    " 120 >/dev/null
}

for name in $TARGETS; do
    start_framework "$name"

    for point in $TEST_POINTS; do
        path="${point%@*}"
        conc="${point##*@}"
        out=$(ssm_run "$CLIENT_INSTANCE" "
            wrk -H 'Host: ${APP_IP}' --timeout 8 -d ${WARMUP_DURATION} -c ${conc} -t ${THREADS} 'http://${APP_IP}:8080${path}' >/dev/null 2>&1 || true
            wrk -H 'Host: ${APP_IP}' --latency --timeout 8 -d ${DURATION} -c ${conc} -t ${THREADS} 'http://${APP_IP}:8080${path}'
        " $((WARMUP_DURATION + DURATION + 30)))
        rps=$(echo "$out" | awk '/Requests\/sec/{print $2}')
        p50=$(echo "$out" | awk '/^ *50%/{print $2}')
        p99=$(echo "$out" | awk '/^ *99%/{print $2}')
        errors=$(echo "$out" | awk -F': ' '/Non-2xx/{print $2}')
        echo "${name} ${path}@c${conc}: ${rps} req/s p50=${p50} p99=${p99}${errors:+ NON-2XX=${errors}}"
    done
done

ssm_run "$APP_INSTANCE" "docker stop kb-active >/dev/null 2>&1 || true; docker rm kb-active >/dev/null 2>&1 || true" 30 >/dev/null
