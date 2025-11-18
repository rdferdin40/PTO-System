# Deployment Files for Ubuntu Server

This directory contains all the necessary configuration files and documentation for deploying the TimeOff Management System on Ubuntu Server.

## Important Notice

**This is a Node.js application, NOT a PHP application.**

Despite references to "XAMPP 8.2", this application requires Node.js, not PHP. All deployment files have been configured for a Node.js environment on Ubuntu Server.

## Contents

### Configuration Files

1. **apache/timeoff.conf** - Apache virtual host configuration with reverse proxy
   - Proxies requests from Apache (port 80/443) to Node.js (port 3000)
   - Includes HTTPS configuration
   - Security headers and caching

2. **systemd/timeoff.service** - Systemd service file
   - Runs the Node.js application as a background service
   - Automatic restart on failure
   - Logging to systemd journal

3. **.env.production** - Production environment template
   - Database configuration
   - Email settings
   - Security settings
   - All configuration options with explanations

### Documentation

4. **UBUNTU_DEPLOYMENT.md** - Complete deployment guide
   - Step-by-step installation instructions
   - Database setup (PostgreSQL/MySQL)
   - Apache configuration
   - SSL certificate setup
   - Troubleshooting guide
   - Maintenance procedures

## Quick Start

1. **Read the deployment guide first**: `UBUNTU_DEPLOYMENT.md`

2. **System Requirements**:
   - Ubuntu 18.04+ / 20.04+ / 22.04+
   - Node.js 16.x or newer (18.x recommended)
   - PostgreSQL 12+ or MySQL 8.0+
   - Apache 2.4+

3. **Installation Summary**:
   ```bash
   # Install Node.js 18.x
   curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
   sudo apt install -y nodejs

   # Install Apache
   sudo apt install -y apache2
   sudo a2enmod proxy proxy_http headers rewrite ssl

   # Install PostgreSQL
   sudo apt install -y postgresql

   # Copy application to /var/www/html/PTO-System
   # Configure environment (.env)
   # Install Apache config
   # Install systemd service
   # Start services
   ```

4. **Deploy to /var/www/html**:
   - Application files go in: `/var/www/html/PTO-System`
   - Apache config goes in: `/etc/apache2/sites-available/timeoff.conf`
   - Systemd service goes in: `/etc/systemd/system/timeoff.service`
   - Environment file goes in: `/var/www/html/PTO-System/.env`

## Architecture

```
User Browser
     ↓
Apache (Port 80/443) - HTTPS, Security Headers
     ↓
Reverse Proxy
     ↓
Node.js Application (Port 3000) - Express.js
     ↓
PostgreSQL/MySQL Database
```

## Key Changes from Original Code

All code has been updated for Node.js 16+ and modern dependencies:

1. **Fixed deprecated Buffer constructor**
   - Changed `new Buffer()` to `Buffer.from()` in `lib/route/feed.js`

2. **Updated Sequelize operators**
   - Changed `$or`, `$ne`, `$lt`, `$gte`, `$lte`, `$in` to `Op.or`, `Op.ne`, etc.
   - Updated in: `lib/route/feed.js`, `lib/route/audit.js`, `lib/model/db/company.js`

These changes ensure compatibility with:
- Node.js 16.x, 18.x, and newer
- Sequelize 6.x
- Modern Ubuntu LTS versions

## Support

For detailed instructions, troubleshooting, and maintenance procedures, see `UBUNTU_DEPLOYMENT.md`.

## Files Summary

| File | Purpose | Destination |
|------|---------|-------------|
| `apache/timeoff.conf` | Apache reverse proxy config | `/etc/apache2/sites-available/` |
| `systemd/timeoff.service` | Systemd service definition | `/etc/systemd/system/` |
| `.env.production` | Production environment template | `/var/www/html/PTO-System/.env` (copy and edit) |
| `UBUNTU_DEPLOYMENT.md` | Complete deployment guide | Documentation |

## License

This deployment configuration is provided as-is for the TimeOff Management System.
Original application license applies.
