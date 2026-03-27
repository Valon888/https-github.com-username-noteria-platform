# E-Noteria Advanced Scalability Implementation
# Read Replicas | Database Sharding | Kubernetes

## 1. READ REPLICAS SETUP (€5-10K)

### Architecture:
```
Primary (Write)
    ↓
├─ Replica 1 (Read)
├─ Replica 2 (Read)
├─ Replica 3 (Read)
└─ Replica 4 (Read)
```

### MySQL Configuration (Primary Server)

my.cnf:
```ini
[mysqld]
# Enable binary logging for replication
log_bin = mysql-bin
binlog_format = ROW
server_id = 1
binlog_do_db = noteria

# Buffer pool for performance
innodb_buffer_pool_size = 4G
innodb_log_file_size = 512M

# Max connections
max_connections = 1000
max_allowed_packet = 256M

# Replication user privileges
# CREATE USER 'repl'@'%' IDENTIFIED BY 'password';
# GRANT REPLICATION SLAVE ON *.* TO 'repl'@'%';
```

### Replica Server Configuration

my.cnf:
```ini
[mysqld]
server_id = 2  # Unique ID per replica
relay_log = mysql-relay-bin
read_only = ON  # Make it read-only
skip_slave_start = OFF

# Skip errors (optional)
slave_skip_errors = 1062
```

### PHP Configuration for Read/Write Splitting

```php
<?php
// Database connections
$pdo_write = new PDO('mysql:host=primary.rds.amazonaws.com;dbname=noteria', 'root', 'password');
$pdo_read = new PDO('mysql:host=replica-1.rds.amazonaws.com;dbname=noteria', 'root', 'password');

// SELECT queries use read replica
function getReservations($user_id) {
    global $pdo_read;
    $stmt = $pdo_read->prepare("SELECT * FROM reservations WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// INSERT/UPDATE/DELETE use primary
function createReservation($user_id, $zyra_id, $service, $date, $time) {
    global $pdo_write;
    $stmt = $pdo_write->prepare("INSERT INTO reservations (user_id, zyra_id, service, date, time) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$user_id, $zyra_id, $service, $date, $time]);
}
?>
```

---

## 2. DATABASE SHARDING (€10-20K)

### Sharding Strategy: By zyra_id (Office ID)

```sql
-- Create sharded tables (100 shards)
CREATE TABLE reservations_shard_0 LIKE reservations;
CREATE TABLE reservations_shard_1 LIKE reservations;
-- ... up to reservations_shard_99

-- Function to determine shard
-- shard_id = zyra_id % 100
```

### PHP Sharding Router

```php
<?php
class ShardingRouter {
    private $shards = []; // Array of PDO connections
    
    public function __construct() {
        // Connect to 10 shard servers
        for ($i = 0; $i < 10; $i++) {
            $this->shards[$i] = new PDO(
                "mysql:host=shard-$i.db.aws.com;dbname=noteria",
                'root', 'password'
            );
        }
    }
    
    private function getShardId($zyra_id) {
        return $zyra_id % 100;  // Consistent hashing
    }
    
    private function getConnection($zyra_id) {
        $shard_id = $this->getShardId($zyra_id);
        $server = intval($shard_id / 10);
        return $this->shards[$server];
    }
    
    public function insertReservation($user_id, $zyra_id, $service, $date, $time) {
        $shard_id = $this->getShardId($zyra_id);
        $table = "reservations_shard_" . str_pad($shard_id, 2, '0', STR_PAD_LEFT);
        $pdo = $this->getConnection($zyra_id);
        
        $stmt = $pdo->prepare("INSERT INTO $table (user_id, zyra_id, service, date, time, payment_status) 
                              VALUES (?, ?, ?, ?, ?, 'pending')");
        return $stmt->execute([$user_id, $zyra_id, $service, $date, $time]);
    }
    
    public function getReservationsByZyra($zyra_id) {
        $shard_id = $this->getShardId($zyra_id);
        $table = "reservations_shard_" . str_pad($shard_id, 2, '0', STR_PAD_LEFT);
        $pdo = $this->getConnection($zyra_id);
        
        $stmt = $pdo->prepare("SELECT * FROM $table WHERE zyra_id = ?");
        $stmt->execute([$zyra_id]);
        return $stmt->fetchAll();
    }
}

// Usage
$sharding = new ShardingRouter();
$sharding->insertReservation(1, 5, 'Vertetim', '2026-03-15', '10:00');
?>
```

---

## 3. KUBERNETES DEPLOYMENT (€20-50K)

### Docker Image Setup

Dockerfile:
```dockerfile
FROM php:8.2-fpm-alpine

# Install extensions
RUN apk add --no-cache \
    mysql-client \
    redis \
    libpq-dev \
    && docker-php-ext-install pdo pdo_mysql

# Copy application
COPY . /var/www/noteria
WORKDIR /var/www/noteria

# Set permissions
RUN chown -R www-data:www-data /var/www/noteria

EXPOSE 9000
CMD ["php-fpm"]
```

Build:
```bash
docker build -t noteria:1.0 .
docker tag noteria:1.0 your-ecr.dkr.ecr.us-east-1.amazonaws.com/noteria:1.0
docker push your-ecr.dkr.ecr.us-east-1.amazonaws.com/noteria:1.0
```

### Kubernetes Deployment

k8s/deployment.yaml:
```yaml
apiVersion: apps/v1
kind: Deployment
metadata:
  name: noteria-api
  namespace: production
spec:
  replicas: 10  # Scale to 10 pods
  selector:
    matchLabels:
      app: noteria-api
  template:
    metadata:
      labels:
        app: noteria-api
    spec:
      containers:
      - name: noteria
        image: your-ecr.dkr.ecr.us-east-1.amazonaws.com/noteria:1.0
        ports:
        - containerPort: 9000
        env:
        - name: DB_HOST
          value: "primary.rds.aws.com"
        - name: REDIS_HOST
          value: "redis-cluster.aws.com"
        - name: ENVIRONMENT
          value: "production"
        resources:
          requests:
            memory: "512Mi"
            cpu: "500m"
          limits:
            memory: "2Gi"
            cpu: "2000m"
        livenessProbe:
          httpGet:
            path: /health
            port: 9000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /ready
            port: 9000
          initialDelaySeconds: 10
          periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: noteria-api-service
spec:
  type: LoadBalancer
  selector:
    app: noteria-api
  ports:
  - port: 80
    targetPort: 9000
    protocol: TCP
---
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: noteria-hpa
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: noteria-api
  minReplicas: 10
  maxReplicas: 500
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
  - type: Resource
    resource:
      name: memory
      target:
        type: Utilization
        averageUtilization: 80
```

### Health Check Endpoints

health.php:
```php
<?php
header('Content-Type: application/json');

// Simple liveness check
if ($_SERVER['REQUEST_URI'] === '/health') {
    echo json_encode(['status' => 'ok']);
    exit();
}

// Detailed readiness check
if ($_SERVER['REQUEST_URI'] === '/ready') {
    try {
        require 'confidb.php';
        $pdo->query('SELECT 1');
        echo json_encode(['status' => 'ready', 'database' => 'ok']);
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode(['status' => 'not_ready', 'error' => $e->getMessage()]);
    }
    exit();
}
?>
```

---

## 4. NGINX LOAD BALANCER CONFIG

nginx.conf:
```nginx
upstream noteria_backend {
    least_conn;  # Load balancing algorithm
    server api1.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    server api2.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    server api3.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    server api4.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
    server api5.internal:9000 weight=1 max_fails=3 fail_timeout=30s;
}

server {
    listen 80;
    server_name noteria.kosove.gov.al;

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|js|css|svg|woff|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }

    # PHP request routing
    location ~ \.php$ {
        proxy_pass http://noteria_backend;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Timeouts
        proxy_connect_timeout 5s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }

    # WebSocket support (video calls)
    location /ws {
        proxy_pass http://noteria_backend;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 86400;
    }
}
```

---

## 5. COST BREAKDOWN

| Component | Quarterly Cost |
|-----------|----------------|
| **Read Replicas** | €2,000-5,000 |
| **Sharding Infrastructure** | €3,000-8,000 |
| **Kubernetes Cluster** | €4,000-10,000 |
| **Load Balancer** | €500-1,000 |
| **Monitoring (Datadog)** | €1,000-2,000 |
| **Storage (S3, RDS)** | €500-1,500 |
| **TOTAL (Q)** | **€11,000-27,500** |
| **YEARLY** | **€44,000-110,000** |

---

## 6. DEPLOYMENT STEPS

### Step 1: Prepare Infrastructure (Week 1-2)
```bash
# Create AWS RDS Read Replicas
aws rds create-db-instance-read-replica \
  --db-instance-identifier noteria-replica-1 \
  --source-db-instance-identifier noteria-primary

# Create Kubernetes cluster
eksctl create cluster --name noteria-prod --region us-east-1 --nodes 20
```

### Step 2: Setup Sharding (Week 2-3)
```bash
# Create shard databases
for i in {0..99}; do
  mysql -h shard-$((i % 10)).db.aws.com -u root -p < create_shard_$i.sql
done
```

### Step 3: Deploy to Kubernetes (Week 3-4)
```bash
# Build Docker image
docker build -t noteria:1.0 .

# Push to registry
docker push your-registry/noteria:1.0

# Deploy with Helm
helm install noteria ./helm-chart -f values-prod.yaml
```

---

## Expected Performance Gains

| Metric | Before | After |
|--------|--------|-------|
| Concurrent Users | 1,000 | 100,000 |
| Daily Active Users | 10,000 | 500,000 |
| Query Response | 500ms | 50ms |
| Database throughput | 100 qps | 10,000 qps |
| Availability | 99% | 99.99% |

---

Timeline: 4-6 weeks
Difficulty: Advanced
Team: 3-5 engineers

Dëshironi t'ia fillojmë implementacinit?
