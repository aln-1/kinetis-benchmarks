#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")"
source ./lib.sh
cd ..

echo "=== cdk deploy ==="
DEPLOY_CMD=(npx cdk deploy --require-approval never)
[ -n "${AWS_PROFILE:-}" ] && DEPLOY_CMD+=(--profile "$AWS_PROFILE")
"${DEPLOY_CMD[@]}"
cd scripts

APP_INSTANCE=$(stack_output AppInstanceId)
DB_INSTANCE=$(stack_output DbInstanceId)
CLIENT_INSTANCE=$(stack_output ClientInstanceId)

echo
echo "App:    ${APP_INSTANCE}"
echo "DB:     ${DB_INSTANCE}"
echo "Client: ${CLIENT_INSTANCE}"
echo

wait_for_ssm() {
    local instance=$1
    for _ in $(seq 1 30); do
        if aws ssm describe-instance-information --region "$AWS_REGION" "${AWS_PROFILE_FLAG[@]}" \
            --filters "Key=InstanceIds,Values=${instance}" \
            --query 'InstanceInformationList[0].PingStatus' --output text 2>/dev/null | grep -q Online; then
            return 0
        fi
        sleep 5
    done
    echo "SSM agent on ${instance} never came online." >&2
    return 1
}

echo "=== Waiting for SSM agents ==="
wait_for_ssm "$APP_INSTANCE"
wait_for_ssm "$DB_INSTANCE"
wait_for_ssm "$CLIENT_INSTANCE"

echo "=== setup-db.sh (db instance, this can take a few minutes) ==="
ssm_run "$DB_INSTANCE" "$(cat setup-db.sh)" 600

echo "=== setup-client.sh (client instance) ==="
ssm_run "$CLIENT_INSTANCE" "$(cat setup-client.sh)" 120

echo "=== setup-app.sh (app instance — builds all 7 images, this is the slow one) ==="
ssm_run "$APP_INSTANCE" "$(cat setup-app.sh)" 900

echo
echo "Setup complete. Run ./run-aws-sweep.sh to start the benchmark sweep."
