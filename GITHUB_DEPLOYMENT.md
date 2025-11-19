# GitHub Deployment Guide - LADC PTO System

Complete guide to deploy from GitHub to Ubuntu server at ladcportal.com/pto

## 🚀 Quick Installation (Automated)

### On Your Ubuntu Server:

```bash
# 1. Clone repository
git clone https://github.com/rdferdin40/PTO-System.git
cd PTO-System

# 2. Checkout the PHP branch
git checkout claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ

# 3. Run automated installer
sudo bash INSTALL_FROM_GITHUB.sh
```

**Done!** The script handles everything automatically.

---

## 📋 Manual Installation (Step-by-Step)

### Prerequisites

- Ubuntu 20.04 or 22.04 LTS
- Root/sudo access
- Domain pointing to your server: ladcportal.com

### Step 1: Install Required Packages

```bash
# Update system
sudo apt-get update

# Install Git
sudo apt-get install -y git

# Install Apache
sudo apt-get install -y apache2

# Install MySQL
sudo apt-get install -y mysql-server

# Install PHP 8.2 and extensions
sudo apt-get install -y php8.2 php8.2-mysql php8.2-mbstring \
    php8.2-xml php8.2-curl php8.2-zip libapache2-mod-php8.2

# If PHP 8.2 unavailable, use 8.1
sudo apt-get install -y php8.1 php8.1-mysql php8.1-mbstring \
    php8.1-xml php8.1-curl php8.1-zip libapache2-mod-php8.1
```

### Step 2: Clone Repository from GitHub

```bash
# Clone your repository
git clone https://github.com/rdferdin40/PTO-System.git /tmp/pto-source

# Navigate to repository
cd /tmp/pto-source

# Checkout the PHP conversion branch
git checkout claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ

# Verify the php directory exists
ls -la php/
```

You should see:
```
php/
├── .env.example
├── .htaccess
├── README.md
├── app/
├── config/
├── core/
├── database/
├── index.php
├── routes.php
└── views/
```

### Step 3: Copy Files to Web Directory

```bash
# Create target directory
sudo mkdir -p /var/www/html/pto

# Copy PHP application
sudo cp -r /tmp/pto-source/php/* /var/www/html/pto/

# Create required directories
sudo mkdir -p /var/www/html/pto/storage/logs
sudo mkdir -p /var/www/html/pto/public

# Verify files copied
ls -la /var/www/html/pto/
```

### Step 4: Configure Environment

```bash
# Copy environment template
sudo cp /var/www/html/pto/.env.example /var/www/html/pto/.env

# Generate secure session secret
SESSION_SECRET=$(openssl rand -base64 32)

# Edit .env file
sudo nano /var/www/html/pto/.env
```

Update these values in `.env`:

```bash
# Application
APP_ENV=production
APP_DEBUG=false
HEADER_TITLE="LADC PTO System"
BRANDING_URL=https://ladcportal.com/pto

# Database
DB_HOST=localhost
DB_DATABASE=timeoff_db
DB_USER=root
DB_PASSWORD=YOUR_MYSQL_PASSWORD

# Security (paste generated secret)
SESSION_SECRET=YOUR_GENERATED_SECRET_HERE

# Email (configure later if needed)
SEND_EMAIL=false
```

### Step 5: Set Up Database

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Login to MySQL
sudo mysql -u root -p
```

Run these SQL commands:

```sql
-- Create database
CREATE DATABASE timeoff_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create dedicated user (recommended)
CREATE USER 'timeoff_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD';
GRANT ALL PRIVILEGES ON timeoff_db.* TO 'timeoff_user'@'localhost';
FLUSH PRIVILEGES;

-- Exit
EXIT;
```

If you created a dedicated user, update `.env`:
```bash
DB_USER=timeoff_user
DB_PASSWORD=YOUR_STRONG_PASSWORD
```

### Step 6: Import Database Schema

```bash
# Import schema (creates 18 tables + default data)
sudo mysql -u root -p timeoff_db < /var/www/html/pto/database/schema.sql

# Or if using dedicated user:
mysql -u timeoff_user -p timeoff_db < /var/www/html/pto/database/schema.sql

# Verify tables created
mysql -u root -p -e "USE timeoff_db; SHOW TABLES;"
```

You should see 18 tables including:
- users
- companies
- departments
- leaves
- leave_types
- bank_holidays
- schedules
- etc.

### Step 7: Set Permissions

```bash
# Set ownership to Apache user
sudo chown -R www-data:www-data /var/www/html/pto

# Set directory permissions
sudo chmod -R 755 /var/www/html/pto

# Make storage writable
sudo chmod -R 777 /var/www/html/pto/storage

# Protect .env file
sudo chmod 600 /var/www/html/pto/.env

# Verify permissions
ls -la /var/www/html/pto/
```

### Step 8: Configure Apache

```bash
# Enable required modules
sudo a2enmod rewrite
sudo a2enmod headers

# Create virtual host configuration
sudo nano /etc/apache2/sites-available/pto.conf
```

Add this configuration:

```apache
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

    ErrorLog ${APACHE_LOG_DIR}/pto-error.log
    CustomLog ${APACHE_LOG_DIR}/pto-access.log combined
</VirtualHost>
```

Enable site and restart Apache:

```bash
# Test configuration
sudo apache2ctl configtest

# Enable site
sudo a2ensite pto.conf

# Reload Apache
sudo systemctl reload apache2

# Check status
sudo systemctl status apache2
```

### Step 9: Verify Installation

Visit in your browser:
```
http://ladcportal.com/pto/verify.php
```

This verification script checks:
- ✅ PHP version (8.0+)
- ✅ Required extensions
- ✅ Database connection
- ✅ Tables imported
- ✅ Permissions
- ✅ Apache configuration

### Step 10: First Login

**URL:** http://ladcportal.com/pto

**Default Credentials:**
- Email: `admin@example.com`
- Password: `admin123456789`

**⚠️ IMMEDIATELY change this password after login!**

### Step 11: Cleanup

```bash
# Delete temporary files
sudo rm -rf /tmp/pto-source

# Delete verification script
sudo rm /var/www/html/pto/verify.php
```

---

## 🔒 Enable HTTPS (Strongly Recommended)

```bash
# Install Certbot
sudo apt-get install -y certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d ladcportal.com -d www.ladcportal.com

# Test auto-renewal
sudo certbot renew --dry-run

# Update .env after SSL is configured
sudo nano /var/www/html/pto/.env
# BRANDING_URL should already be: https://ladcportal.com/pto
```

Certbot will automatically:
- Obtain SSL certificate
- Configure Apache for HTTPS
- Set up auto-renewal

---

## 📧 Configure Email Notifications (Optional)

Edit `/var/www/html/pto/.env`:

```bash
SEND_EMAIL=true

# For Gmail
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_AUTH_REQUIRED=true
SMTP_AUTH_USER=your-email@gmail.com
SMTP_AUTH_PASS=your-app-password
SMTP_FROM=noreply@ladcportal.com
```

**Gmail Setup:**
1. Enable 2-factor authentication
2. Generate App Password at: https://myaccount.google.com/apppasswords
3. Use app password in `SMTP_AUTH_PASS`

Restart Apache after changes:
```bash
sudo systemctl restart apache2
```

---

## 🔄 Updating from GitHub

When you push updates to GitHub:

```bash
# On your server
cd /tmp
git clone https://github.com/rdferdin40/PTO-System.git
cd PTO-System
git checkout claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ

# Backup current installation
sudo cp -r /var/www/html/pto /var/www/html/pto.backup.$(date +%Y%m%d)

# Update files (preserve .env)
sudo cp /var/www/html/pto/.env /tmp/.env.backup
sudo cp -r php/* /var/www/html/pto/
sudo cp /tmp/.env.backup /var/www/html/pto/.env

# Set permissions
sudo chown -R www-data:www-data /var/www/html/pto
sudo chmod -R 755 /var/www/html/pto
sudo chmod -R 777 /var/www/html/pto/storage

# Cleanup
cd ~
sudo rm -rf /tmp/PTO-System
```

---

## 🐛 Troubleshooting

### Can't clone repository - Authentication required

If repository is private:

```bash
# Use personal access token
git clone https://YOUR_TOKEN@github.com/rdferdin40/PTO-System.git

# Or set up SSH keys
ssh-keygen -t ed25519 -C "your-email@example.com"
cat ~/.ssh/id_ed25519.pub  # Add to GitHub Settings → SSH Keys
git clone git@github.com:rdferdin40/PTO-System.git
```

### Wrong branch checked out

```bash
# List all branches
git branch -a

# Switch to correct branch
git checkout claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ
```

### Database import fails

```bash
# Check if database exists
mysql -u root -p -e "SHOW DATABASES;"

# Drop and recreate if needed
mysql -u root -p -e "DROP DATABASE IF EXISTS timeoff_db;"
mysql -u root -p -e "CREATE DATABASE timeoff_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Import again
mysql -u root -p timeoff_db < /var/www/html/pto/database/schema.sql
```

### Permission errors

```bash
# Reset all permissions
sudo chown -R www-data:www-data /var/www/html/pto
sudo chmod -R 755 /var/www/html/pto
sudo chmod -R 777 /var/www/html/pto/storage
sudo chmod 600 /var/www/html/pto/.env

# Check Apache user
ps aux | grep apache2
```

### 404 errors on all pages

```bash
# Verify mod_rewrite is enabled
sudo apache2ctl -M | grep rewrite

# Enable if not
sudo a2enmod rewrite
sudo systemctl restart apache2

# Check .htaccess exists
ls -la /var/www/html/pto/.htaccess

# Verify AllowOverride
sudo grep -A5 "Directory /var/www/html/pto" /etc/apache2/sites-available/pto.conf
```

### Database connection fails

```bash
# Test MySQL connection
mysql -u root -p -e "SELECT 1;"

# Test from PHP
php -r "new PDO('mysql:host=localhost;dbname=timeoff_db', 'root', 'password');"

# Check credentials in .env
cat /var/www/html/pto/.env | grep DB_
```

---

## 📊 Monitoring

### Real-time Logs

```bash
# Apache error log
sudo tail -f /var/log/apache2/pto-error.log

# Apache access log
sudo tail -f /var/log/apache2/pto-access.log

# Application logs
sudo tail -f /var/www/html/pto/storage/logs/*.log
```

### System Status

```bash
# Check Apache
sudo systemctl status apache2

# Check MySQL
sudo systemctl status mysql

# Check disk space
df -h

# Check memory
free -h
```

---

## 💾 Backup Strategy

### Database Backup

```bash
# Create backup script
sudo nano /usr/local/bin/backup-pto-db.sh
```

Add:
```bash
#!/bin/bash
mysqldump -u timeoff_user -p'PASSWORD' timeoff_db | gzip > /var/backups/pto/db_$(date +%Y%m%d_%H%M%S).sql.gz
find /var/backups/pto -name "*.sql.gz" -mtime +7 -delete
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-pto-db.sh

# Add to crontab (daily at 2 AM)
sudo crontab -e
0 2 * * * /usr/local/bin/backup-pto-db.sh
```

### Files Backup

```bash
# Backup application files
sudo tar -czf /var/backups/pto/files_$(date +%Y%m%d).tar.gz /var/www/html/pto
```

---

## ✅ Post-Deployment Checklist

- [ ] Repository cloned successfully
- [ ] Files copied to /var/www/html/pto
- [ ] .env configured with correct settings
- [ ] Database created and schema imported
- [ ] Apache virtual host configured
- [ ] mod_rewrite enabled
- [ ] Permissions set correctly
- [ ] verify.php shows all checks passing
- [ ] Can login with default credentials
- [ ] Admin password changed
- [ ] verify.php deleted
- [ ] HTTPS/SSL configured
- [ ] Email configured (if needed)
- [ ] Backups configured
- [ ] Firewall rules set

---

## 🎯 Your Repository Details

- **Repository:** https://github.com/rdferdin40/PTO-System
- **Branch:** claude/xampp-8.2-conversion-01YKsQHqbyBchJZzgS38H3KZ
- **Application Path:** php/
- **Deployment:** /var/www/html/pto
- **URL:** https://ladcportal.com/pto

---

## 📞 Support

**Check Logs:**
- Application: `/var/www/html/pto/storage/logs/`
- Apache: `/var/log/apache2/pto-error.log`
- MySQL: `sudo journalctl -u mysql`

**Test Database:**
```bash
mysql -u timeoff_user -p timeoff_db -e "SELECT COUNT(*) FROM users;"
```

**Verify Apache Config:**
```bash
sudo apache2ctl -t
```

---

**Deployment Date:** 2025-11-19
**Version:** PHP 8.2
**Status:** Production Ready
