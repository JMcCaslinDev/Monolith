import * as aws from "@pulumi/aws";
import * as pulumi from "@pulumi/pulumi";

const config = new pulumi.Config("monolith");
const domain = config.require("domain");
const dbName = config.require("dbName");
const dbUsername = config.require("dbUsername");
const dbPassword = config.requireSecret("dbPassword");
const imageTag = config.get("imageTag") ?? "latest";

const vpc = new aws.ec2.Vpc("monolith-vpc", {
  cidrBlock: "10.0.0.0/16",
  enableDnsHostnames: true,
  enableDnsSupport: true,
  tags: { Name: "monolith-vpc" },
});

const azs = aws.getAvailabilityZonesOutput({ state: "available" });
const publicSubnet = new aws.ec2.Subnet("monolith-public-a", {
  vpcId: vpc.id,
  cidrBlock: "10.0.1.0/24",
  availabilityZone: azs.names[0],
  mapPublicIpOnLaunch: true,
  tags: { Name: "monolith-public-a" },
});
const privateSubnet = new aws.ec2.Subnet("monolith-private-a", {
  vpcId: vpc.id,
  cidrBlock: "10.0.2.0/24",
  availabilityZone: azs.names[0],
  tags: { Name: "monolith-private-a" },
});

const igw = new aws.ec2.InternetGateway("monolith-igw", { vpcId: vpc.id });
const publicRt = new aws.ec2.RouteTable("monolith-public-rt", { vpcId: vpc.id });
new aws.ec2.Route("monolith-public-route", {
  routeTableId: publicRt.id,
  destinationCidrBlock: "0.0.0.0/0",
  gatewayId: igw.id,
});
new aws.ec2.RouteTableAssociation("monolith-public-rta", {
  subnetId: publicSubnet.id,
  routeTableId: publicRt.id,
});

const dbSg = new aws.ec2.SecurityGroup("monolith-db-sg", {
  vpcId: vpc.id,
  description: "MariaDB — ECS only",
  ingress: [],
  egress: [{ protocol: "-1", fromPort: 0, toPort: 0, cidrBlocks: ["0.0.0.0/0"] }],
});

const appSg = new aws.ec2.SecurityGroup("monolith-app-sg", {
  vpcId: vpc.id,
  description: "ECS tasks",
  ingress: [{ protocol: "tcp", fromPort: 80, toPort: 80, cidrBlocks: ["0.0.0.0/0"] }],
  egress: [{ protocol: "-1", fromPort: 0, toPort: 0, cidrBlocks: ["0.0.0.0/0"] }],
});

new aws.ec2.SecurityGroupRule("db-from-app", {
  type: "ingress",
  securityGroupId: dbSg.id,
  sourceSecurityGroupId: appSg.id,
  protocol: "tcp",
  fromPort: 3306,
  toPort: 3306,
});

const dbSubnetGroup = new aws.rds.SubnetGroup("monolith-db-subnets", {
  subnetIds: [privateSubnet.id, publicSubnet.id],
});

const db = new aws.rds.Instance("monolith-db", {
  engine: "mariadb",
  engineVersion: "11.4",
  instanceClass: "db.t4g.micro",
  allocatedStorage: 20,
  dbName,
  username: dbUsername,
  password: dbPassword,
  vpcSecurityGroupIds: [dbSg.id],
  dbSubnetGroupName: dbSubnetGroup.name,
  skipFinalSnapshot: true,
  publiclyAccessible: false,
});

const repo = new aws.ecr.Repository("monolith", { forceDelete: true });
const cluster = new aws.ecs.Cluster("monolith");

const alb = new aws.lb.LoadBalancer("monolith-alb", {
  loadBalancerType: "application",
  securityGroups: [appSg.id],
  subnets: [publicSubnet.id],
});

const tg = new aws.lb.TargetGroup("monolith-tg", {
  port: 80,
  protocol: "HTTP",
  targetType: "ip",
  vpcId: vpc.id,
  healthCheck: { path: "/health" },
});

new aws.lb.Listener("monolith-http", {
  loadBalancerArn: alb.arn,
  port: 80,
  protocol: "HTTP",
  defaultActions: [{ type: "forward", targetGroupArn: tg.arn }],
});

const logGroup = new aws.cloudwatch.LogGroup("monolith-logs", {
  retentionInDays: 14,
});

const executionRole = new aws.iam.Role("monolith-exec-role", {
  assumeRolePolicy: aws.iam.assumeRolePolicyForPrincipal({ Service: "ecs-tasks.amazonaws.com" }),
});
new aws.iam.RolePolicyAttachment("monolith-exec-policy", {
  role: executionRole.name,
  policyArn: "arn:aws:iam::aws:policy/service-role/AmazonECSTaskExecutionRolePolicy",
});

const taskRole = new aws.iam.Role("monolith-task-role", {
  assumeRolePolicy: aws.iam.assumeRolePolicyForPrincipal({ Service: "ecs-tasks.amazonaws.com" }),
});

const image = pulumi.interpolate`${repo.repositoryUrl}:${imageTag}`;

const taskDef = new aws.ecs.TaskDefinition("monolith-task", {
  family: "monolith",
  cpu: "256",
  memory: "512",
  networkMode: "awsvpc",
  requiresCompatibilities: ["FARGATE"],
  executionRoleArn: executionRole.arn,
  taskRoleArn: taskRole.arn,
  containerDefinitions: pulumi.all([image, db.endpoint, dbName, dbUsername, dbPassword]).apply(
    ([img, host, name, user, pass]) =>
      JSON.stringify([
        {
          name: "app",
          image: img,
          essential: true,
          portMappings: [{ containerPort: 80, protocol: "tcp" }],
          environment: [
            { name: "APP_ENV", value: "production" },
            { name: "APP_DEBUG", value: "false" },
            { name: "APP_URL", value: `https://${domain}` },
            { name: "DB_HOST", value: host },
            { name: "DB_PORT", value: "3306" },
            { name: "DB_DATABASE", value: name },
            { name: "DB_USERNAME", value: user },
            { name: "DB_PASSWORD", value: pass },
          ],
          logConfiguration: {
            logDriver: "awslogs",
            options: {
              "awslogs-group": logGroup.name,
              "awslogs-region": aws.config.region,
              "awslogs-stream-prefix": "app",
            },
          },
        },
      ])
  ),
});

const service = new aws.ecs.Service("monolith-svc", {
  cluster: cluster.arn,
  taskDefinition: taskDef.arn,
  desiredCount: 1,
  launchType: "FARGATE",
  networkConfiguration: {
    subnets: [publicSubnet.id],
    securityGroups: [appSg.id],
    assignPublicIp: true,
  },
  loadBalancers: [
    {
      targetGroupArn: tg.arn,
      containerName: "app",
      containerPort: 80,
    },
  ],
});

export const vpcId = vpc.id;
export const dbEndpoint = db.endpoint;
export const ecrRepositoryUrl = repo.repositoryUrl;
export const albDnsName = alb.dnsName;
export const ecsClusterName = cluster.name;
export const ecsServiceName = service.name;
export const domainConfigured = domain;
