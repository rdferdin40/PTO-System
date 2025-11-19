#!/bin/bash
# Complete Installation from GitHub - LADC PTO System
# Run this on your Ubuntu server

set -e  # Exit on error

echo "=========================================="
echo "LADC PTO System - GitHub Installation"
echo "=========================================="
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;36m'
NC='\033[0m' # No Color

# Check if running as root
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}Please run as root or with sudo${NC}"
    exit 1
fi

echo -e "${BLUE}Step 1: Installing Git and required packages...${NC}"
apt-get update
apt-get install -y git apache2 mysql-server php8.2 php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip libapache2-mod-php8.2 unzip

# Fallback to PHP 8.1 if 8.2 not available
if ! php -v 2>/dev/null | grep -q "PHP 8"; then
    echo -e "${YELLOW}Installing PHP 8.1 as fallback...${NC}"
    apt-get install -y php8.1 php8.1-mysql php8.1-mbstring php8.1-xml \
        php8.1-curl php8.1-zip libapache2-mod-php8.1
fi

echo -e "${GREEN}✓ Packages installed${NC}"
echo ""

echo -e "${BLUE}Step 2: Cloning repository from GitHub...${NC}"
echo "Enter your GitHub repository URL:"
echo "Example: https://github.com/rdferdin40/PTO-System.git"
read -p "Repository URL: " REPO_URL

if [ -z "$REPO_URL" ]; then
    REPO_URL="https://github.com/rdferdin40/PTO-System.git"
    echo -e "${YELLOW}Using default: $REPO_URL${NC}"
fi

# Clone to temporary directory
TMP_DIR="/tmp/pto-install-$(date +%s)"
echo "Cloning to temporary directory..."
git clone "$REPO_URL" "$TMP_DIR"

# Check which branch to use
cd "$TMP_DIR"
echo ""
echo "Available branches:"
git branch -r
echo ""
echo "Enter branch name (default: claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ):"
read -p "Branch: " BRANCH_NAME

if [ -z "$BRANCH_NAME" ]; then
    BRANCH_NAME="claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ"
fi

echo "Checking out branch: $BRANCH_NAME"
git checkout "$BRANCH_NAME" || git checkout -b "$BRANCH_NAME" "origin/$BRANCH_NAME"

echo -e "${GREEN}✓ Repository cloned${NC}"
echo ""

echo -e "${BLUE}Step 3: Copying files to /var/www/html/pto...${NC}"
# Create target directory
mkdir -p /var/www/html/pto

# Copy PHP application files
if [ -d "$TMP_DIR/php" ]; then
    cp -r "$TMP_DIR/php/"* /var/www/html/pto/
    echo -e "${GREEN}✓ Files copied from php/ directory${NC}"
else
    echo -e "${RED}Error: php/ directory not found in repository${NC}"
    exit 1
fi

# Create required directories
mkdir -p /var/www/html/pto/storage/logs
mkdir -p /var/www/html/pto/public

echo -e "${GREEN}✓ Files copied to /var/www/html/pto${NC}"
echo ""

echo -e "${BLUE}Step 4: Setting up environment configuration...${NC}"
# Copy .env.example to .env
if [ -f "/var/www/html/pto/.env.example" ]; then
    cp /var/www/html/pto/.env.example /var/www/html/pto/.env

    # Generate session secret
    SESSION_SECRET=$(openssl rand -base64 32)

    # Update .env with your settings
    sed -i "s|BRANDING_URL=http://localhost/timeoff|BRANDING_URL=https://ladcportal.com/pto|g" /var/www/html/pto/.env
    sed -i "s|HEADER_TITLE=\"TimeOff Management\"|HEADER_TITLE=\"LADC PTO System\"|g" /var/www/html/pto/.env
    sed -i "s|SESSION_SECRET=CHANGE_THIS_TO_RANDOM_STRING_MIN_32_CHARS|SESSION_SECRET=$SESSION_SECRET|g" /var/www/html/pto/.env

    echo -e "${GREEN}✓ Environment file configured${NC}"
else
    echo -e "${RED}Warning: .env.example not found${NC}"
fi
echo ""

echo -e "${BLUE}Step 5: Enabling Apache modules...${NC}"
a2enmod rewrite
a2enmod headers
echo -e "${GREEN}✓ Apache modules enabled${NC}"
echo ""

echo -e "${BLUE}Step 6: Setting up database...${NC}"
echo "MySQL Configuration:"
echo "Enter MySQL root password (press Enter if no password):"
read -s MYSQL_ROOT_PASS

MYSQL_CMD="mysql"
if [ ! -z "$MYSQL_ROOT_PASS" ]; then
    MYSQL_CMD="mysql -p${MYSQL_ROOT_PASS}"
fi

# Create database
echo "Creating database 'timeoff_db'..."
$MYSQL_CMD -e "CREATE DATABASE IF NOT EXISTS timeoff_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || {
    echo -e "${YELLOW}Note: Database may already exist or password incorrect${NC}"
}

# Prompt for database user creation
echo ""
echo "Do you want to create a dedicated database user? (recommended) [y/N]"
read CREATE_USER

if [[ "$CREATE_USER" =~ ^[Yy]$ ]]; then
    echo "Enter username for database (default: timeoff_user):"
    read DB_USER
    DB_USER=${DB_USER:-timeoff_user}

    echo "Enter password for database user:"
    read -s DB_PASS

    $MYSQL_CMD -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';" 2>/dev/null
    $MYSQL_CMD -e "GRANT ALL PRIVILEGES ON timeoff_db.* TO '${DB_USER}'@'localhost';" 2>/dev/null
    $MYSQL_CMD -e "FLUSH PRIVILEGES;" 2>/dev/null

    # Update .env
    sed -i "s/DB_USER=root/DB_USER=${DB_USER}/" /var/www/html/pto/.env
    sed -i "s/DB_PASSWORD=/DB_PASSWORD=${DB_PASS}/" /var/www/html/pto/.env

    echo -e "${GREEN}✓ Database user created${NC}"
fi

# Import schema
echo "Importing database schema..."
if [ -f "/var/www/html/pto/database/schema.sql" ]; then
    if [ -z "$MYSQL_ROOT_PASS" ]; then
        mysql timeoff_db < /var/www/html/pto/database/schema.sql 2>/dev/null
    else
        mysql -p${MYSQL_ROOT_PASS} timeoff_db < /var/www/html/pto/database/schema.sql 2>/dev/null
    fi
    echo -e "${GREEN}✓ Database schema imported${NC}"
else
    echo -e "${RED}Warning: schema.sql not found${NC}"
fi
echo ""

echo -e "${BLUE}Step 7: Setting permissions...${NC}"
chown -R www-data:www-data /var/www/html/pto
chmod -R 755 /var/www/html/pto
chmod -R 777 /var/www/html/pto/storage
chmod 600 /var/www/html/pto/.env
echo -e "${GREEN}✓ Permissions set${NC}"
echo ""

echo -e "${BLUE}Step 8: Configuring Apache Virtual Host...${NC}"
cat > /etc/apache2/sites-available/pto.conf << 'EOF'
# LADC PTO System Virtual Host
<VirtualHost *:80>
    ServerName ladcportal.com
    ServerAlias www.ladcportal.com

    # Subdirectory installation
    Alias /pto /var/www/html/pto

    <Directory /var/www/html/pto>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted

        # Security Headers
        Header set X-Content-Type-Options "nosniff"
        Header set X-Frame-Options "SAMEORIGIN"
        Header set X-XSS-Protection "1; mode=block"

        # PHP Settings
        php_value upload_max_filesize 10M
        php_value post_max_size 10M
        php_value memory_limit 256M
        php_value max_execution_time 300
    </Directory>

    # Protect sensitive files
    <FilesMatch "^\.env$">
        Require all denied
    </FilesMatch>

    <DirectoryMatch "/(config|storage|database)/">
        Require all denied
    </DirectoryMatch>

    # Logging
    ErrorLog ${APACHE_LOG_DIR}/pto-error.log
    CustomLog ${APACHE_LOG_DIR}/pto-access.log combined
</VirtualHost>
EOF

a2ensite pto.conf
systemctl reload apache2
echo -e "${GREEN}✓ Apache configured${NC}"
echo ""

# Cleanup
echo -e "${BLUE}Step 9: Cleaning up...${NC}"
rm -rf "$TMP_DIR"
echo -e "${GREEN}✓ Temporary files removed${NC}"
echo ""

echo -e "${GREEN}=========================================="
echo "Installation Complete!"
echo "==========================================${NC}"
echo ""
echo -e "${YELLOW}IMPORTANT INFORMATION:${NC}"
echo ""
echo "🌐 Access your application at:"
echo "   http://ladcportal.com/pto"
echo "   or"
echo "   http://$(hostname -I | awk '{print $1}')/pto"
echo ""
echo "🔑 Default login credentials:"
echo "   Email:    admin@example.com"
echo "   Password: admin123456789"
echo ""
echo -e "${RED}⚠️  CRITICAL: Change admin password immediately after login!${NC}"
echo ""
echo "📝 Next steps:"
echo "   1. Visit http://ladcportal.com/pto/verify.php to verify installation"
echo "   2. Login and change admin password"
echo "   3. Delete verify.php: rm /var/www/html/pto/verify.php"
echo "   4. Configure company settings"
echo "   5. Add users and departments"
echo ""
echo "🔒 Enable HTTPS (recommended):"
echo "   sudo apt-get install certbot python3-certbot-apache"
echo "   sudo certbot --apache -d ladcportal.com"
echo ""
echo "📂 Configuration file: /var/www/html/pto/.env"
echo "📊 Logs: /var/www/html/pto/storage/logs/"
echo "🐛 Apache logs: /var/log/apache2/pto-error.log"
echo ""
