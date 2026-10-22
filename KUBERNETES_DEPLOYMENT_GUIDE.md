# E-Noteria Kubernetes Deployment Guide

## Prerequisites

```bash
# 1. Install AWS CLI
brew install awscli  # macOS
# or
choco install awscliv2  # Windows

# 2. Install kubectl
curl -LO "https://dl.k8s.io/release/v1.28.0/bin/linux/amd64/kubectl"
chmod +x kubectl
sudo mv kubectl /usr/local/bin/

# 3. Install Helm
curl https://raw.githubusercontent.com/helm/helm/main/scripts/get-helm-3 | bash

# 4. Install eksctl (for EKS)
curl --silent --location "https://github.com/weaveworks/eksctl/releases/latest/download/eksctl_$(uname -s)_amd64.tar.gz" | tar xz -C /tmp
sudo mv /tmp/eksctl /usr/local/bin

# 5. Configure AWS credentials
aws configure
# Enter: AWS Access Key ID, Secret Access Key, Region (us-east-1), Output format (json)
```

## Step 1: Create AWS Infrastructure

```bash
# 1a. Create EKS Cluster
eksctl create cluster \
  --name noteria-production \
  --region us-east-1 \
  --node-type t3.xlarge \
  --nodes 20 \
  --managed \
  --with-oidc \
  --enable-ssm

# Wait 15-20 minutes for cluster to be ready...

# 1b. Update kubeconfig
aws eks update-kubeconfig --name noteria-production --region us-east-1

# Verify connection
kubectl get nodes

# 1c. Create RDS Primary Database
aws rds create-db-instance \
  --db-instance-identifier noteria-primary \
  --db-instance-class db.r5.xlarge \
  --engine mysql \
  --master-username admin \
  --master-user-password YourSecurePassword123! \
  --allocated-storage 100 \
  --backup-retention-period 30 \
  --enable-clouldwatch-logs-exports error,general

# 1d. Create RDS Read Replicas
aws rds create-db-instance-read-replica \
  --db-instance-identifier noteria-replica-1 \
  --source-db-instance-identifier noteria-primary

aws rds create-db-instance-read-replica \
  --db-instance-identifier noteria-replica-2 \
  --source-db-instance-identifier noteria-primary

# 1e. Create ElastiCache Redis Cluster
aws elasticache create-replication-group \
  --replication-group-description "Noteria Cache" \
  --engine redis \
  --engine-version 7.0 \
  --cache-node-type cache.r6g.xlarge \
  --num-cache-clusters 3 \
  --automatic-failover-enabled \
  --port 6379

# 1f. Create S3 bucket for file storage
aws s3 mb s3://noteria-production-files --region us-east-1
aws s3api put-bucket-versioning \
  --bucket noteria-production-files \
  --versioning-configuration Status=Enabled

# 1g. Create ECR Repository
aws ecr create-repository \
  --repository-name noteria \
  --region us-east-1 \
  --encryption-configuration encryptionType=AES

# Get ECR login token
aws ecr get-login-password --region us-east-1 | \
  docker login --username AWS --password-stdin \
  your-account-id.dkr.ecr.us-east-1.amazonaws.com
```

## Step 2: Build and Push Docker Image

```bash
# From your local machine

# 1. Build Docker image
docker build -t noteria:1.0.0 \
  --build-arg BUILD_DATE=$(date -u +'%Y-%m-%dT%H:%M:%SZ') \
  --build-arg VCS_REF=$(git rev-parse --short HEAD) \
  .

# 2. Tag for ECR
docker tag noteria:1.0.0 \
  your-account-id.dkr.ecr.us-east-1.amazonaws.com/noteria:1.0.0

docker tag noteria:1.0.0 \
  your-account-id.dkr.ecr.us-east-1.amazonaws.com/noteria:latest

# 3. Push to ECR
docker push your-account-id.dkr.ecr.us-east-1.amazonaws.com/noteria:1.0.0
docker push your-account-id.dkr.ecr.us-east-1.amazonaws.com/noteria:latest

# Verify
aws ecr describe-images --repository-name noteria --region us-east-1
```

## Step 3: Configure Kubernetes

```bash
# 1. Create namespace
kubectl create namespace production

# 2. Create secrets
kubectl create secret generic noteria-secrets \
  --from-literal=DB_PRIMARY_HOST=noteria-primary.c9akciq32.us-east-1.rds.amazonaws.com \
  --from-literal=DB_REPLICA_HOST=noteria-replica-1.c9akciq32.us-east-1.rds.amazonaws.com \
  --from-literal=DB_USER=noteria_user \
  --from-literal=DB_PASSWORD=$(echo 'YourSecurePassword123!' | base64) \
  --from-literal=REDIS_HOST=noteria-redis.abcdef.ng.0001.use1.cache.amazonaws.com \
  --from-literal=REDIS_PASSWORD=$(echo 'YourRedisPassword!' | base64) \
  -n production

# 3. Apply Kubernetes manifests
kubectl apply -f k8s-deployment.yaml

# 4. Verify deployment
kubectl get pods -n production -w
kubectl get svc -n production
kubectl get hpa -n production

# Wait for pods to be Running...
```

## Step 4: Setup Ingress and TLS

```bash
# 1. Install Nginx Ingress Controller
helm repo add ingress-nginx https://kubernetes.github.io/ingress-nginx
helm repo update

helm install nginx-ingress ingress-nginx/ingress-nginx \
  --namespace ingress-nginx \
  --create-namespace \
  --values ingress-values.yaml

# 2. Install Cert-Manager for Let's Encrypt
helm repo add jetstack https://charts.jetstack.io
helm repo update

helm install cert-manager jetstack/cert-manager \
  --namespace cert-manager \
  --create-namespace \
  --set installCRDs=true

# 3. Create ClusterIssuer for Let's Encrypt
cat <<EOF | kubectl apply -f -
apiVersion: cert-manager.io/v1
kind: ClusterIssuer
metadata:
  name: letsencrypt-prod
spec:
  acme:
    server: https://acme-v02.api.letsencrypt.org/directory
    email: admin@noteria.kosove.gov.al
    privateKeySecretRef:
      name: letsencrypt-prod
    solvers:
    - http01:
        ingress:
          class: nginx
EOF

# 4. Update DNS records
# Point noteria.kosove.gov.al to LoadBalancer IP
EXTERNAL_IP=$(kubectl get svc nginx-ingress-ingress-nginx-controller \
  -n ingress-nginx -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')

echo "Update DNS A record to: $EXTERNAL_IP"
```

## Step 5: Database Initialization

```bash
# 1. Get RDS endpoint
RDS_ENDPOINT=$(aws rds describe-db-instances \
  --db-instance-identifier noteria-primary \
  --query 'DBInstances[0].Endpoint.Address' \
  --output text)

# 2. Create database and user
mysql -h $RDS_ENDPOINT -u admin -p << EOF
CREATE DATABASE noteria CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'noteria_user'@'%' IDENTIFIED BY 'YourSecurePassword123!';
GRANT ALL PRIVILEGES ON noteria.* TO 'noteria_user'@'%';
CREATE USER 'noteria_readonly'@'%' IDENTIFIED BY 'ReadOnlyPassword123!';
GRANT SELECT ON noteria.* TO 'noteria_readonly'@'%';
FLUSH PRIVILEGES;
EOF

# 3. Import schema
mysql -h $RDS_ENDPOINT -u noteria_user -p noteria < noteria.sql

# 4. Setup replication
mysql -h $RDS_ENDPOINT -u admin -p << EOF
SHOW MASTER STATUS;  -- Note the File and Position
EOF

# For each replica:
mysql -h noteria-replica-1.c9akciq32.us-east-1.rds.amazonaws.com -u admin -p << EOF
CHANGE MASTER TO
  MASTER_HOST='noteria-primary.c9akciq32.us-east-1.rds.amazonaws.com',
  MASTER_USER='repl',
  MASTER_PASSWORD='password',
  MASTER_LOG_FILE='mysql-bin.000001',
  MASTER_LOG_POS=123456;
START SLAVE;
SHOW SLAVE STATUS\G
EOF
```

## Step 6: Monitoring and Logging

```bash
# 1. Install Prometheus for metrics
helm repo add prometheus-community https://prometheus-community.github.io/helm-charts
helm repo update

helm install prometheus prometheus-community/kube-prometheus-stack \
  -n monitoring \
  --create-namespace

# 2. Install ELK Stack for logging
helm repo add elastic https://helm.elastic.co
helm repo update

helm install elasticsearch elastic/elasticsearch \
  -n logging \
  --create-namespace

helm install kibana elastic/kibana \
  -n logging

# 3. Setup CloudWatch logging
kubectl apply -f - <<EOF
apiVersion: v1
kind: ConfigMap
metadata:
  name: cwagent-config
  namespace: amazon-cloudwatch
data:
  cwagent.json: |
    {
      "agent": {
        "metrics_collection_interval": 60
      },
      "logs": {
        "logs_collected": {
          "files": {
            "collect_list": [
              {
                "file_path": "/var/www/noteria/logs/*.log",
                "log_group_name": "/aws/eks/noteria",
                "log_stream_name": "noteria-api"
              }
            ]
          }
        }
      }
    }
EOF
```

## Step 7: Verification

```bash
# 1. Check cluster health
kubectl get nodes
kubectl get pods -n production
kubectl get svc -n production

# 2. Check application logs
kubectl logs -f deployment/noteria-api -n production

# 3. Check metrics
kubectl top nodes
kubectl top pods -n production

# 4. Test application
curl https://noteria.kosove.gov.al/health
curl https://noteria.kosove.gov.al/ready

# 5. Monitor autoscaling
kubectl get hpa -n production -w
```

## Step 8: Scaling Configuration (Post-Deployment)

```bash
# Update HPA for different scenarios
kubectl patch hpa noteria-api-hpa -n production --type='json' \
  -p='[{"op": "replace", "path": "/spec/minReplicas", "value":50}]'

kubectl patch hpa noteria-api-hpa -n production --type='json' \
  -p='[{"op": "replace", "path": "/spec/maxReplicas", "value":1000}]'

# Monitor scaling
watch kubectl get hpa -n production
```

## Troubleshooting

```bash
# 1. Pod won't start
kubectl describe pod <pod-name> -n production
kubectl logs <pod-name> -n production

# 2. Database connectivity
kubectl exec -it <pod-name> -n production -- \
  mysql -h $RDS_ENDPOINT -u noteria_user -p -e "SELECT 1;"

# 3. Check resource limits
kubectl top nodes
kubectl top pods -n production

# 4. View recent events
kubectl get events -n production --sort-by='.lastTimestamp'

# 5. Debug with shell
kubectl exec -it deployment/noteria-api -n production -- /bin/sh
```

## Cost Optimization

```bash
# 1. Use Spot Instances (70% cheaper)
eksctl create nodegroup \
  --cluster=noteria-production \
  --name=spot-group \
  --spot \
  --instance-types=t3.xlarge,t3.2xlarge \
  --nodes=15 \
  --nodes-min=10 \
  --nodes-max=50

# 2. Enable cluster autoscaler
helm repo add autoscaler https://kubernetes.github.io/autoscaler
helm install cluster-autoscaler autoscaler/cluster-autoscaler \
  --namespace kube-system \
  --set autoDiscovery.clusterName=noteria-production

# 3. Monitor costs
aws ce get-cost-and-usage \
  --time-period Start=2026-03-01,End=2026-03-02 \
  --granularity DAILY \
  --metrics "UnblendedCost"
```

## Estimated Costs (Monthly)

```
EKS Cluster (20-50 nodes)        €3,000-8,000
RDS MySQL (db.r5.xlarge + replicas) €2,000-4,000
ElastiCache Redis                €800-1,500
S3 Storage                       €500-1,000
Data Transfer                    €1,000-2,000
Monitoring & Logging             €500-1,000
Load Balancers                   €300-500
--------
TOTAL MONTHLY:                   €8,100-18,000
TOTAL YEARLY:                    €97,200-216,000
```

## Success Indicators

✅ All pods running and healthy
✅ Requests completing in <200ms
✅ Auto-scaling working (CPU/Memory)
✅ Database replication synchronized
✅ HTTPS working with valid certificate
✅ Application logs flowing to Kibana
✅ Metrics visible in Prometheus
✅ Handling 100,000+ requests/day

Deployment status: READY TO SCALE!
