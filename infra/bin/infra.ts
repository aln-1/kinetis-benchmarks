#!/usr/bin/env node
import 'source-map-support/register';
import * as cdk from 'aws-cdk-lib';
import { KinetisBenchmarksStack } from '../lib/kinetis-benchmarks-stack';

const app = new cdk.App();

new KinetisBenchmarksStack(app, 'KinetisBenchmarksStack', {
    env: {
        account: process.env.CDK_DEFAULT_ACCOUNT,
        region: process.env.CDK_DEFAULT_REGION ?? 'eu-west-1',
    },
});
