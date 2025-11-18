# TimeOff Management System - Ubuntu Deployment Guide

## Important Notice

**This is a Node.js application, NOT a PHP application.**

Despite the task name mentioning "XAMPP 8.2", this application runs on **Node.js** (not PHP). This guide will help you deploy it on Ubuntu Server (18.04+/20.04+/22.04+) in `/var/www/html` with Apache as a reverse proxy.

## Table of Contents

1. [System Requirements](#system-requirements)
2. [Pre-Installation Setup](#pre-installation-setup)
3. [Database Setup](#database-setup)
4. [Application Installation](#application-installation)
5. [Apache Configuration](#apache-configuration)
6. [Systemd Service Setup](#systemd-service-setup)
7. [SSL Certificate Setup](#ssl-certificate-setup)
8. [First-Time Application Setup](#first-time-application-setup)
9. [Troubleshooting](#troubleshooting)
10. [Maintenance](#maintenance)

---

## System Requirements

- **Ubuntu Server**: 18.04 LTS, 20.04 LTS, 22.04 LTS, or newer
- **Node.js**: Version 16.x or newer (18.x LTS recommended)
- **PostgreSQL**: 12+ (recommended) OR MySQL 8.0+
- **Apache**: 2.4+
- **Memory**: Minimum 1GB RAM (2GB+ recommended)
- **Disk Space**: At least 2GB free space

---

## Pre-Installation Setup

### 1. Update System Packages

```bash
sudo apt update
sudo apt upgrade -y
```

### 2. Install Node.js 18.x LTS

```bash
# Install Node.js 18.x from NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs

# Verify installation
node --version  # Should show v18.x.x
npm --version   # Should show 9.x.x or newer
```

### 3. Install Apache Web Server

```bash
sudo apt install -y apache2

# Enable required Apache modules
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite ssl

# Verify Apache is running
sudo systemctl status apache2
```

### 4. Install Build Essentials

```bash
# Required for building native Node.js modules
sudo apt install -y build-essential python3
```

---

## Database Setup

### Option A: PostgreSQL (Recommended)

#### Install PostgreSQL

```bash
sudo apt install -y postgresql postgresql-contrib

# Start and enable PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql
```

#### Create Database and User

```bash
# Switch to postgres user
sudo -u postgres psql

# In PostgreSQL prompt, run:
CREATE DATABASE timeoff_db;
CREATE USER timeoff_user WITH ENCRYPTED PASSWORD 'YOUR_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON DATABASE timeoff_db TO timeoff_user;

# For PostgreSQL 15+, also grant schema privileges
\c timeoff_db
GRANT ALL ON SCHEMA public TO timeoff_user;

# Exit PostgreSQL
\q
```

#### Configure PostgreSQL for Local Connections

Edit `/etc/postgresql/*/main/pg_hba.conf` and ensure this line exists:

```
local   timeoff_db    timeoff_user                    md5
host    timeoff_db    timeoff_user    127.0.0.1/32    md5
```

Restart PostgreSQL:

```bash
sudo systemctl restart postgresql
```

### Option B: MySQL (Alternative)

#### Install MySQL

```bash
sudo apt install -y mysql-server

# Secure MySQL installation
sudo mysql_secure_installation
```

#### Create Database and User

```bash
sudo mysql

# In MySQL prompt, run:
CREATE DATABASE timeoff_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'timeoff_user'@'localhost' IDENTIFIED BY 'YOUR_STRONG_PASSWORD_HERE';
GRANT ALL PRIVILEGES ON timeoff_db.* TO 'timeoff_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## Application Installation

### 1. Create Directory and Set Permissions

```bash
# Create directory if it doesn't exist
sudo mkdir -p /var/www/html/PTO-System

# Set proper ownership
sudo chown -R www-data:www-data /var/www/html/PTO-System
```

### 2. Clone/Copy Application Files

```bash
# If using git (recommended)
cd /var/www/html
sudo -u www-data git clone https://github.com/timeoff-management/timeoff-management-application.git PTO-System

# OR if copying from local files
sudo cp -r /path/to/PTO-System/* /var/www/html/PTO-System/
sudo chown -R www-data:www-data /var/www/html/PTO-System
```

### 3. Install Node.js Dependencies

```bash
cd /var/www/html/PTO-System

# Install production dependencies
sudo -u www-data npm ci --only=production

# OR if you need dev dependencies for building
sudo -u www-data npm install

# Build static assets
sudo -u www-data npm run build
```

### 4. Configure Environment Variables

```bash
# Copy production environment template
sudo cp /var/www/html/PTO-System/deployment/.env.production /var/www/html/PTO-System/.env

# Edit the .env file with your settings
sudo nano /var/www/html/PTO-System/.env
```

**IMPORTANT: Update these values in `.env`:**

- `BRANDING_URL` - Your domain name (e.g., `https://timeoff.yourcompany.com`)
- `SESSION_SECRET` - Generate with: `openssl rand -base64 32`
- `DATABASE_URL` - Your database connection string
- `DB_PASSWORD` - Your database password
- `SMTP_*` variables - Your email server settings
- `OPTION_ALLOW_NEW_REGISTRATIONS` - Set to `false` after initial setup

```bash
# Set secure permissions on .env file
sudo chmod 600 /var/www/html/PTO-System/.env
sudo chown www-data:www-data /var/www/html/PTO-System/.env
```

### 5. Run Database Migrations

```bash
cd /var/www/html/PTO-System

# Run Prisma migrations
sudo -u www-data npx prisma migrate deploy

# OR if using npm start script (includes migrations)
sudo -u www-data npm start
# Press Ctrl+C after it starts successfully
```

---

## Apache Configuration

### 1. Install Apache Virtual Host Configuration

```bash
# Copy the Apache configuration
sudo cp /var/www/html/PTO-System/deployment/apache/timeoff.conf /etc/apache2/sites-available/

# Edit the configuration
sudo nano /etc/apache2/sites-available/timeoff.conf
```

**Update these values in the Apache config:**

- `ServerName` - Your domain name (e.g., `timeoff.yourcompany.com`)
- `ServerAlias` - Additional domain names (e.g., `www.timeoff.yourcompany.com`)
- `ServerAdmin` - Your email address
- SSL certificate paths (if using SSL)

### 2. Enable the Site

```bash
# Disable default site (optional)
sudo a2dissite 000-default.conf

# Enable TimeOff site
sudo a2ensite timeoff.conf

# Test Apache configuration
sudo apache2ctl configtest

# If test passes, reload Apache
sudo systemctl reload apache2
```

---

## Systemd Service Setup

### 1. Install Systemd Service

```bash
# Copy the service file
sudo cp /var/www/html/PTO-System/deployment/systemd/timeoff.service /etc/systemd/system/

# Edit if needed (e.g., different Node.js path)
sudo nano /etc/systemd/system/timeoff.service
```

**Check Node.js path:**

```bash
which node
# Update ExecStart in timeoff.service if the path differs
```

### 2. Enable and Start the Service

```bash
# Reload systemd to recognize new service
sudo systemctl daemon-reload

# Enable service to start on boot
sudo systemctl enable timeoff.service

# Start the service
sudo systemctl start timeoff.service

# Check service status
sudo systemctl status timeoff.service
```

### 3. View Application Logs

```bash
# View real-time logs
sudo journalctl -u timeoff.service -f

# View recent logs
sudo journalctl -u timeoff.service -n 100

# View logs with timestamps
sudo journalctl -u timeoff.service --since "1 hour ago"
```

---

## SSL Certificate Setup

### Option A: Let's Encrypt (Free, Recommended)

```bash
# Install Certbot
sudo apt install -y certbot python3-certbot-apache

# Obtain SSL certificate
sudo certbot --apache -d timeoff.yourcompany.com -d www.timeoff.yourcompany.com

# Follow the prompts
# Certbot will automatically configure Apache for HTTPS

# Test auto-renewal
sudo certbot renew --dry-run
```

### Option B: Manual SSL Certificate

If you have your own SSL certificate:

```bash
# Copy certificate files
sudo cp your-domain.crt /etc/ssl/certs/
sudo cp your-domain.key /etc/ssl/private/
sudo cp your-domain-chain.crt /etc/ssl/certs/  # If you have a chain file

# Set proper permissions
sudo chmod 644 /etc/ssl/certs/your-domain.crt
sudo chmod 600 /etc/ssl/private/your-domain.key

# Update Apache configuration
sudo nano /etc/apache2/sites-available/timeoff.conf
# Update SSLCertificateFile and SSLCertificateKeyFile paths

# Reload Apache
sudo systemctl reload apache2
```

---

## First-Time Application Setup

### 1. Access the Application

Open your browser and navigate to:
- `http://your-domain.com` (if not using SSL)
- `https://your-domain.com` (if using SSL)

### 2. Create First Admin Account

1. If `OPTION_ALLOW_NEW_REGISTRATIONS=true`, click "Register"
2. Fill in the registration form
3. The first user becomes the company administrator
4. After creating the admin account, **disable registrations**:

```bash
sudo nano /var/www/html/PTO-System/.env
# Change: OPTION_ALLOW_NEW_REGISTRATIONS=false
sudo systemctl restart timeoff.service
```

### 3. Configure Company Settings

1. Log in as admin
2. Go to Settings → Company Details
3. Configure:
   - Company name
   - Country and timezone
   - Leave types and allowances
   - Departments
   - Public holidays

---

## Troubleshooting

### Application Won't Start

```bash
# Check service status
sudo systemctl status timeoff.service

# View detailed logs
sudo journalctl -u timeoff.service -n 50

# Common issues:
# 1. Database connection failed - check DATABASE_URL in .env
# 2. Port already in use - check if another app uses port 3000
# 3. Permission denied - check file ownership (should be www-data)
```

### Database Connection Errors

```bash
# Test PostgreSQL connection
sudo -u postgres psql -d timeoff_db -U timeoff_user -h localhost

# Check PostgreSQL is running
sudo systemctl status postgresql

# View PostgreSQL logs
sudo tail -f /var/log/postgresql/postgresql-*-main.log
```

### Apache Proxy Not Working

```bash
# Check if proxy modules are enabled
sudo apache2ctl -M | grep proxy

# Should show:
# proxy_module (shared)
# proxy_http_module (shared)

# If not enabled:
sudo a2enmod proxy proxy_http
sudo systemctl restart apache2
```

### Permission Errors

```bash
# Fix file ownership
sudo chown -R www-data:www-data /var/www/html/PTO-System

# Fix directory permissions
sudo find /var/www/html/PTO-System -type d -exec chmod 755 {} \;

# Fix file permissions
sudo find /var/www/html/PTO-System -type f -exec chmod 644 {} \;

# .env should be readable only by www-data
sudo chmod 600 /var/www/html/PTO-System/.env
```

### Email Not Sending

```bash
# Test SMTP connection
telnet your-smtp-server.com 587

# Check application logs for email errors
sudo journalctl -u timeoff.service | grep -i email

# Verify SMTP settings in .env
sudo nano /var/www/html/PTO-System/.env
```

---

## Maintenance

### Updating the Application

```bash
# Stop the service
sudo systemctl stop timeoff.service

# Backup the database
sudo -u postgres pg_dump timeoff_db > /tmp/timeoff_backup_$(date +%Y%m%d).sql

# Pull latest code (if using git)
cd /var/www/html/PTO-System
sudo -u www-data git pull

# Install dependencies
sudo -u www-data npm ci --only=production

# Run migrations
sudo -u www-data npx prisma migrate deploy

# Rebuild assets
sudo -u www-data npm run build

# Start the service
sudo systemctl start timeoff.service

# Check status
sudo systemctl status timeoff.service
```

### Database Backups

#### PostgreSQL Backup

```bash
# Create backup script
sudo nano /usr/local/bin/backup-timeoff-db.sh
```

Add this content:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/timeoff"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p $BACKUP_DIR
sudo -u postgres pg_dump timeoff_db | gzip > $BACKUP_DIR/timeoff_db_$DATE.sql.gz
# Keep only last 7 days of backups
find $BACKUP_DIR -name "timeoff_db_*.sql.gz" -mtime +7 -delete
```

```bash
# Make executable
sudo chmod +x /usr/local/bin/backup-timeoff-db.sh

# Add to cron (daily at 2 AM)
sudo crontab -e
# Add this line:
0 2 * * * /usr/local/bin/backup-timeoff-db.sh
```

### Monitoring

```bash
# Check service health
sudo systemctl is-active timeoff.service

# Monitor resource usage
htop
# Look for node process

# Check disk space
df -h

# Check memory usage
free -h

# Monitor logs in real-time
sudo journalctl -u timeoff.service -f
```

### Restarting Services

```bash
# Restart TimeOff application
sudo systemctl restart timeoff.service

# Restart Apache
sudo systemctl restart apache2

# Restart PostgreSQL
sudo systemctl restart postgresql

# Restart all (after system updates)
sudo systemctl restart timeoff.service apache2
```

---

## Security Checklist

- [ ] Strong `SESSION_SECRET` generated
- [ ] Strong database password set
- [ ] `.env` file has permissions 600 and owned by www-data
- [ ] `OPTION_ALLOW_NEW_REGISTRATIONS` set to false after initial setup
- [ ] SSL certificate installed and configured
- [ ] Firewall configured (UFW recommended)
  ```bash
  sudo ufw allow 80/tcp
  sudo ufw allow 443/tcp
  sudo ufw allow 22/tcp
  sudo ufw enable
  ```
- [ ] Database only accessible from localhost (unless using remote DB)
- [ ] Regular backups configured
- [ ] System packages kept up to date
- [ ] Application kept up to date

---

## Support and Resources

- **Official Documentation**: https://github.com/timeoff-management/timeoff-management-application
- **System Logs**: `sudo journalctl -u timeoff.service`
- **Apache Logs**: `/var/log/apache2/timeoff-*.log`
- **PostgreSQL Logs**: `/var/log/postgresql/`

---

## Quick Reference Commands

```bash
# Service management
sudo systemctl start timeoff.service
sudo systemctl stop timeoff.service
sudo systemctl restart timeoff.service
sudo systemctl status timeoff.service

# View logs
sudo journalctl -u timeoff.service -f

# Apache management
sudo systemctl restart apache2
sudo apache2ctl configtest

# Database access
sudo -u postgres psql -d timeoff_db

# Update application
cd /var/www/html/PTO-System
sudo systemctl stop timeoff.service
sudo -u www-data git pull
sudo -u www-data npm ci --only=production
sudo -u www-data npm run build
sudo systemctl start timeoff.service
```

---

**End of Deployment Guide**
