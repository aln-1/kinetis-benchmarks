import * as cdk from 'aws-cdk-lib';
import * as ec2 from 'aws-cdk-lib/aws-ec2';
import * as iam from 'aws-cdk-lib/aws-iam';
import { Construct } from 'constructs';

export class KinetisBenchmarksStack extends cdk.Stack {
    public readonly appInstance: ec2.Instance;
    public readonly dbInstance: ec2.Instance;
    public readonly clientInstance: ec2.Instance;

    constructor(scope: Construct, id: string, props?: cdk.StackProps) {
        super(scope, id, props);

        const vpc = ec2.Vpc.fromLookup(this, 'DefaultVpc', { isDefault: true });

        const subnetSelection: ec2.SubnetSelection = {
            subnetType: ec2.SubnetType.PUBLIC,
            availabilityZones: [cdk.Stack.of(this).availabilityZones[0]],
        };

        const clientSg = new ec2.SecurityGroup(this, 'ClientSecurityGroup', {
            vpc,
            description: 'kinetis-benchmarks: wrk load-generator client',
            allowAllOutbound: true,
        });

        const appSg = new ec2.SecurityGroup(this, 'AppSecurityGroup', {
            vpc,
            description: 'kinetis-benchmarks: application server under test',
            allowAllOutbound: true,
        });

        const dbSg = new ec2.SecurityGroup(this, 'DbSecurityGroup', {
            vpc,
            description: 'kinetis-benchmarks: MySQL database server',
            allowAllOutbound: true,
        });

        appSg.addIngressRule(clientSg, ec2.Port.tcp(8080), 'wrk client to app under test');
        dbSg.addIngressRule(appSg, ec2.Port.tcp(3306), 'app to MySQL');

        const role = new iam.Role(this, 'InstanceRole', {
            assumedBy: new iam.ServicePrincipal('ec2.amazonaws.com'),
            managedPolicies: [
                iam.ManagedPolicy.fromAwsManagedPolicyName('AmazonSSMManagedInstanceCore'),
            ],
        });

        const ubuntu2404 = ec2.MachineImage.fromSsmParameter(
            '/aws/service/canonical/ubuntu/server/24.04/stable/current/amd64/hvm/ebs-gp3/ami-id',
            { os: ec2.OperatingSystemType.LINUX },
        );

        this.appInstance = new ec2.Instance(this, 'AppInstance', {
            vpc,
            vpcSubnets: subnetSelection,
            instanceType: ec2.InstanceType.of(ec2.InstanceClass.C6I, ec2.InstanceSize.XLARGE2),
            machineImage: ubuntu2404,
            securityGroup: appSg,
            role,
            blockDevices: [{
                deviceName: '/dev/sda1',
                volume: ec2.BlockDeviceVolume.ebs(40, { volumeType: ec2.EbsDeviceVolumeType.GP3 }),
            }],
        });

        this.dbInstance = new ec2.Instance(this, 'DbInstance', {
            vpc,
            vpcSubnets: subnetSelection,
            instanceType: ec2.InstanceType.of(ec2.InstanceClass.C6I, ec2.InstanceSize.XLARGE2),
            machineImage: ubuntu2404,
            securityGroup: dbSg,
            role,
            blockDevices: [{
                deviceName: '/dev/sda1',
                volume: ec2.BlockDeviceVolume.ebs(20, { volumeType: ec2.EbsDeviceVolumeType.GP3 }),
            }],
        });

        this.clientInstance = new ec2.Instance(this, 'ClientInstance', {
            vpc,
            vpcSubnets: subnetSelection,
            instanceType: ec2.InstanceType.of(ec2.InstanceClass.C6I, ec2.InstanceSize.XLARGE),
            machineImage: ubuntu2404,
            securityGroup: clientSg,
            role,
        });

        cdk.Tags.of(this).add('Project', 'kinetis-benchmarks');

        new cdk.CfnOutput(this, 'AppInstanceId', { value: this.appInstance.instanceId });
        new cdk.CfnOutput(this, 'AppPrivateIp', { value: this.appInstance.instancePrivateIp });
        new cdk.CfnOutput(this, 'DbInstanceId', { value: this.dbInstance.instanceId });
        new cdk.CfnOutput(this, 'DbPrivateIp', { value: this.dbInstance.instancePrivateIp });
        new cdk.CfnOutput(this, 'ClientInstanceId', { value: this.clientInstance.instanceId });
        new cdk.CfnOutput(this, 'ClientPrivateIp', { value: this.clientInstance.instancePrivateIp });
    }
}
