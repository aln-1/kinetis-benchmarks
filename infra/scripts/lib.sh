#!/usr/bin/env bash

AWS_REGION=${AWS_REGION:-eu-west-1}
AWS_PROFILE_FLAG=()
[ -n "${AWS_PROFILE:-}" ] && AWS_PROFILE_FLAG=(--profile "$AWS_PROFILE")
STACK_NAME=${STACK_NAME:-KinetisBenchmarksStack}

stack_output() {
    aws cloudformation describe-stacks --stack-name "$STACK_NAME" --region "$AWS_REGION" "${AWS_PROFILE_FLAG[@]}" \
        --query "Stacks[0].Outputs[?OutputKey=='$1'].OutputValue" --output text
}

ssm_run() {
    local instance=$1 command=$2 timeout=${3:-60}
    local cmd_id status elapsed=0 input_file

    input_file=$(mktemp)
    SSM_INSTANCE="$instance" python3 -c '
import json, os, sys
content = sys.stdin.read()
doc = {
    "InstanceIds": [os.environ["SSM_INSTANCE"]],
    "DocumentName": "AWS-RunShellScript",
    "Parameters": {"commands": [content]},
}
json.dump(doc, open(sys.argv[1], "w"))
' "$input_file" <<< "$command"

    cmd_id=$(aws ssm send-command --cli-input-json "file://${input_file}" \
        --region "$AWS_REGION" "${AWS_PROFILE_FLAG[@]}" --query 'Command.CommandId' --output text)
    rm -f "$input_file"

    while [ "$elapsed" -lt "$timeout" ]; do
        status=$(aws ssm get-command-invocation --command-id "$cmd_id" --instance-id "$instance" \
            --region "$AWS_REGION" "${AWS_PROFILE_FLAG[@]}" --query 'Status' --output text 2>/dev/null || echo "Pending")
        case "$status" in
            Success)
                aws ssm get-command-invocation --command-id "$cmd_id" --instance-id "$instance" \
                    --region "$AWS_REGION" "${AWS_PROFILE_FLAG[@]}" --query 'StandardOutputContent' --output text
                return 0
                ;;
            Failed|Cancelled|TimedOut)
                echo "SSM command failed on ${instance} (status ${status})" >&2
                aws ssm get-command-invocation --command-id "$cmd_id" --instance-id "$instance" \
                    --region "$AWS_REGION" "${AWS_PROFILE_FLAG[@]}" --query 'StandardErrorContent' --output text >&2
                return 1
                ;;
        esac
        sleep 2
        elapsed=$((elapsed + 2))
    done

    echo "SSM command on ${instance} timed out after ${timeout}s" >&2
    return 1
}

to_ms() {
    local raw=$1 value unit
    value=$(echo "$raw" | sed -E 's/[a-zA-Z]+$//')
    unit=$(echo "$raw" | sed -E 's/^[0-9.]+//')
    case "$unit" in
        us) awk "BEGIN { printf \"%.3f\", $value / 1000 }" ;;
        ms) awk "BEGIN { printf \"%.3f\", $value }" ;;
        s)  awk "BEGIN { printf \"%.3f\", $value * 1000 }" ;;
        *)  echo "$value" ;;
    esac
}
