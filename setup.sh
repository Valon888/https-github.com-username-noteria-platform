#!/bin/bash

# ============================================
# E-Noteria CloudFlare Full Deployment Script
# ============================================
# This script automates the entire setup process
# Run on your local development machine

set -e

echo "========================================="
echo "E-Noteria CloudFlare Full Setup"
echo "========================================="
echo ""

# ============================================
# STEP 1: Check Prerequisites
# ============================================
echo "✓ Checking prerequisites..."

if ! command -v node &> /dev/null; then
    echo "❌ Node.js not found. Install from: https://nodejs.org/"
    exit 1
fi

if ! command -v npm &> /dev/null; then
    echo "❌ npm not found. Install Node.js first."
    exit 1
fi

if ! command -v git &> /dev/null; then
    echo "❌ Git not found. Install from: https://git-scm.com/"
    exit 1
fi

echo "✓ Node.js: $(node --version)"
echo "✓ npm: $(npm --version)"
echo "✓ Git: $(git --version)"
echo ""

# ============================================
# STEP 2: Install Wrangler
# ============================================
echo "📦 Installing Wrangler CLI..."
npm install -g wrangler 2>/dev/null || echo "⚠️  Wrangler might already be installed"
echo "✓ Wrangler: $(wrangler --version 2>/dev/null || echo 'installing...')"
echo ""

# ============================================
# STEP 3: Install Project Dependencies
# ============================================
echo "📦 Installing project dependencies..."
npm install
echo "✓ Dependencies installed"
echo ""

# ============================================
# STEP 4: Create .gitignore
# ============================================
echo "📝 Creating .gitignore..."
cat > .gitignore << 'EOF'
node_modules/
.env
.env.local
.wrangler/
dist/
build/
.DS_Store
*.log
*.swp
*.swo
~*
.vscode/.history/
.idea/
*.pem
*.key
wrangler.toml.local
EOF
echo "✓ .gitignore created"
echo ""

# ============================================
# STEP 5: Initialize Git (if not already)
# ============================================
echo "📁 Initializing Git repository..."
if [ ! -d .git ]; then
    git init
    echo "✓ Git repository initialized"
else
    echo "✓ Git repository already exists"
fi

git add .
git commit -m "E-Noteria CloudFlare deployment setup" 2>/dev/null || echo "✓ Changes staged"
echo ""

# ============================================
# STEP 6: Create Environment Configuration
# ============================================
echo "⚙️  Creating configuration files..."

# Create .env.example
cat > .env.example << 'EOF'
# CloudFlare Configuration
CLOUDFLARE_ACCOUNT_ID=your-account-id
CLOUDFLARE_ZONE_ID=your-zone-id
CLOUDFLARE_API_TOKEN=your-api-token

# Backend Server
BACKEND_URL=https://api.noteria.kosove.gov.al
BACKEND_PORT=443

# Environment
NODE_ENV=production
LOG_LEVEL=warn
EOF
echo "✓ .env.example created"
echo ""

# ============================================
# STEP 7: Display Next Steps
# ============================================
echo "========================================="
echo "✅ LOCAL SETUP COMPLETE"
echo "========================================="
echo ""
echo "📋 NEXT STEPS:"
echo ""
echo "1️⃣  LOGIN TO CLOUDFLARE:"
echo "   wrangler login"
echo ""
echo "2️⃣  GET YOUR CLOUDFLARE CREDENTIALS:"
echo "   - Visit: https://dash.cloudflare.com/"
echo "   - Find Account ID (bottom right)"
echo "   - Select your domain → Copy Zone ID"
echo "   - API Tokens → Create Token"
echo ""
echo "3️⃣  ADD DOMAIN TO CLOUDFLARE:"
echo "   - https://dash.cloudflare.com/sign-up"
echo "   - Add domain: noteria.kosove.gov.al"
echo "   - Update nameservers at registrar:"
echo "     cecilia.ns.cloudflare.com"
echo "     neil.ns.cloudflare.com"
echo ""
echo "4️⃣  UPDATE wrangler.toml:"
cat > wrangler-template.txt << 'EOF'
# Fill these in:
account_id = "YOUR_ACCOUNT_ID"
zone_id = "YOUR_ZONE_ID"

[env.production.vars]
BACKEND_URL = "https://your-backend-server-ip"
EOF
cat wrangler-template.txt
rm wrangler-template.txt
echo ""
echo "5️⃣  INITIALIZE GITHUB REPO:"
echo "   git remote add origin https://github.com/YOUR_USERNAME/noteria.git"
echo "   git push -u origin main"
echo ""
echo "6️⃣  DEPLOY TO CLOUDFLARE:"
echo "   wrangler publish --env production"
echo ""
echo "7️⃣  TEST DEPLOYMENT:"
echo "   curl https://api.noteria.kosove.gov.al/health"
echo ""
echo "========================================="
echo "📖 Read CLOUDFLARE_DEPLOYMENT_STEPS.md"
echo "========================================="
echo ""
