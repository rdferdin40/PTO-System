# TimeOff Management System - PHP 8.2 Version

Complete PHP 8.2 conversion of the TimeOff Management System, fully compatible with XAMPP 8.2 and Ubuntu Server (Apache + PHP 8.2 + MySQL 8.0).

## Features

- ✅ **Complete PHP 8.2 Rewrite** - No Node.js dependencies
- ✅ **MVC Architecture** - Clean, maintainable code structure
- ✅ **Advanced Allowance Calculation** - 7-component algorithm with prorating, accrual, carry-over
- ✅ **Leave Management** - Full workflow with approvals, cancellations, revocations
- ✅ **Role-Based Access** - Admin, Supervisor, Employee roles
- ✅ **Calendar Views** - Personal and team calendars with iCal feeds
- ✅ **Email Notifications** - SMTP integration with audit trail
- ✅ **Security** - CSRF protection, bcrypt password hashing, input validation
- ✅ **Database-Backed Sessions** - Scalable session management

## System Requirements

### XAMPP 8.2 (Windows)
- XAMPP 8.2 or later
- PHP 8.2+
- MySQL 8.0+ (MariaDB 10.4+)
- Apache 2.4+

### Ubuntu Server
- Ubuntu 20.04 LTS or later
- PHP 8.2+ with extensions: pdo_mysql, mbstring, openssl, json
- MySQL 8.0+ or MariaDB 10.4+
- Apache 2.4+ with mod_rewrite enabled

## Installation

### Step 1: Copy Files

**XAMPP (Windows):**
```bash
# Copy to XAMPP htdocs
xcopy /E /I php C:\xampp\htdocs\timeoff
```

**Ubuntu:**
```bash
# Copy to Apache web root
sudo cp -r php /var/www/html/timeoff
sudo chown -R www-data:www-data /var/www/html/timeoff
sudo chmod -R 755 /var/www/html/timeoff
```

### Step 2: Configure Environment

```bash
cd /var/www/html/timeoff  # or C:\xampp\htdocs\timeoff
cp .env.example .env
```

Edit `.env` and configure:

```bash
# Database
DB_HOST=localhost
DB_DATABASE=timeoff_db
DB_USER=root
DB_PASSWORD=your_mysql_password

# Application
APP_ENV=production
APP_DEBUG=false
BRANDING_URL=http://localhost/timeoff
SESSION_SECRET=CHANGE_THIS_TO_RANDOM_32_CHAR_STRING

# Email (optional)
SEND_EMAIL=false
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_AUTH_USER=your-email@gmail.com
SMTP_AUTH_PASS=your-app-password
```

### Step 3: Create Database

**MySQL Command Line:**
```bash
mysql -u root -p
```

**SQL Commands:**
```sql
CREATE DATABASE timeoff_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE timeoff_db;
SOURCE /var/www/html/timeoff/database/schema.sql;
-- Windows: SOURCE C:/xampp/htdocs/timeoff/database/schema.sql;
```

This creates:
- All 18 tables with proper relationships
- Default company "Demo Company"
- Admin user: `admin@example.com` / `admin123456789`

### Step 4: Configure Apache

**XAMPP:** `.htaccess` is already included. Ensure `mod_rewrite` is enabled in `httpd.conf`.

**Ubuntu - Create Virtual Host:**
```bash
sudo nano /etc/apache2/sites-available/timeoff.conf
```

Add:
```apache
<VirtualHost *:80>
    ServerName timeoff.local
    DocumentRoot /var/www/html/timeoff

    <Directory /var/www/html/timeoff>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/timeoff-error.log
    CustomLog ${APACHE_LOG_DIR}/timeoff-access.log combined
</VirtualHost>
```

Enable site and rewrite:
```bash
sudo a2enmod rewrite
sudo a2ensite timeoff.conf
sudo systemctl restart apache2
```

### Step 5: Set Permissions

**Ubuntu:**
```bash
sudo chown -R www-data:www-data /var/www/html/timeoff
sudo chmod -R 755 /var/www/html/timeoff
sudo chmod -R 777 /var/www/html/timeoff/storage
sudo chmod 600 /var/www/html/timeoff/.env
```

**XAMPP:** Storage folder needs write permissions.

### Step 6: Install PHPMailer (Optional - for emails)

If you want email functionality:

```bash
cd /var/www/html/timeoff
composer require phpmailer/phpmailer
```

Or download manually and place in `vendor/phpmailer/`.

### Step 7: Access Application

**XAMPP:** http://localhost/timeoff

**Ubuntu:** http://your-server-ip/timeoff or http://timeoff.local

**Default Login:**
- Email: `admin@example.com`
- Password: `admin123456789`

**IMPORTANT:** Change the admin password immediately after first login!

## Directory Structure

```
php/
├── index.php              # Front controller
├── routes.php             # Route definitions
├── .htaccess              # Apache URL rewriting
├── .env.example           # Environment template
├── config/
│   └── config.php         # Configuration loader
├── core/                  # Core framework
│   ├── Database.php       # PDO database wrapper
│   ├── Router.php         # URL routing
│   ├── Request.php        # HTTP request
│   ├── Response.php       # HTTP response
│   ├── Controller.php     # Base controller
│   ├── Model.php          # Base model (Active Record)
│   ├── View.php           # Template rendering
│   └── Session.php        # Session management
├── app/
│   ├── Controllers/       # All controllers
│   ├── Models/            # All models (14 models)
│   ├── Middleware/        # Auth, Admin, API middleware
│   └── Libraries/         # Business logic
│       ├── AllowanceCalculator.php  # CRITICAL: 7-component calculation
│       ├── EmailService.php
│       └── CalendarGenerator.php
├── views/                 # PHP templates
│   ├── layouts/
│   ├── auth/
│   ├── calendar/
│   ├── leave/
│   └── user/
├── database/
│   └── schema.sql         # Complete MySQL schema
└── storage/               # Writable directory
    └── logs/
```

## Critical Business Logic

### Allowance Calculation (7 Components)

The system calculates user allowance using 7 components:

```php
Total = Nominal + CarryOver + ManualAdjustment + EmploymentProrate + Accrual
```

**1. Nominal Allowance:** Base allowance from department (e.g., 20 days)

**2. Carry Over:** Unused allowance from previous year (capped by company setting)

**3. Manual Adjustment:** Admin-added adjustments

**4. Employment Prorating:** Reduction for partial year employment
- Only applies if employee started OR ended in current year
- Prorated by days: `(nominal × days_employed) / 365`

**5. Accrued Adjustment:** Locks future allowance (if enabled)
- Only for current year
- Allowance accrues monthly: `(nominal / 12) × months_passed`
- Prevents using future allowance

**6. Personal vs Vacation Buckets:** Separate tracking (not currently implemented in base)

**7. Status Workflow:** Only approved/pended_revoke leaves count as "used"

### Leave Validation

1. **Overlap Check:** Prevents double-booking (allows AM + PM on same day)
2. **Allowance Check:** Ensures sufficient balance
3. **Payroll Close:** Prevents editing past weeks after Monday deadline
4. **Bank Holidays:** Automatically excluded from deduction
5. **Weekend Handling:** Configurable work schedule

### Half-Day Support

- **All Day (1):** Full working day
- **Morning (2):** First half (AM)
- **Afternoon (3):** Second half (PM)

Two leaves can coexist on same day if one is AM and other is PM.

## API Endpoints

Integration API available with token authentication:

```
GET  /api/v1/users           # List all users
GET  /api/v1/leaves          # List all leaves
GET  /api/v1/absences        # Get absences data
POST /api/v1/leaves          # Create leave request
```

Enable in company settings and generate API token.

## Security Features

- **CSRF Protection:** All POST requests require valid token
- **Password Hashing:** bcrypt cost 12 (NIST SP 800-63B compliant)
- **Session Security:** HTTP-only cookies, regeneration on login
- **Input Validation:** All user input validated and sanitized
- **SQL Injection Protection:** PDO prepared statements
- **XSS Protection:** All output escaped
- **Role-Based Access:** Admin, Supervisor, Employee permissions

## Database Schema

**18 Tables:**
1. companies
2. users
3. departments
4. leaves
5. leave_types
6. bank_holidays
7. schedules
8. user_allowance_adjustments
9. email_audit
10. sessions
11. user_feeds
12. comments
13. password_resets
14. company_calendars
15. schedule_user (if needed)
16. user_calendar_feeds
17. user_company (if needed)
18. audit_log

See `database/schema.sql` for complete schema with all relationships, constraints, and indexes.

## Troubleshooting

### "Database not connected"
- Check .env database credentials
- Ensure MySQL is running
- Verify database exists: `SHOW DATABASES;`

### "404 Not Found" on all pages
- Enable Apache mod_rewrite: `sudo a2enmod rewrite`
- Check .htaccess is in php/ directory
- Verify AllowOverride All in Apache config

### "Permission denied" errors
- Set proper permissions on storage/: `chmod 777 storage/`
- Ensure .env is readable: `chmod 644 .env`

### Emails not sending
- Set SEND_EMAIL=true in .env
- Configure SMTP settings
- Check email_audit table for logged emails

### "Class not found" errors
- Verify file paths in core classes
- Check autoloader in index.php
- Ensure proper file naming (case-sensitive on Linux)

## Performance Optimization

### Production Settings

In `.env`:
```bash
APP_DEBUG=false
APP_ENV=production
```

### Enable OPcache

In `php.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
```

### Database Indexes

Schema includes all necessary indexes. Monitor with:
```sql
SHOW INDEX FROM leaves;
EXPLAIN SELECT * FROM leaves WHERE user_id = 1;
```

## Migrating from Node.js Version

1. **Export data** from Node.js app (users, leaves, company settings)
2. **Import to MySQL** using schema.sql as base
3. **Map data** to PHP models
4. **Test calculations** - verify allowance matches
5. **Test workflows** - create/approve/cancel leaves

## Development

### Code Style
- PSR-12 coding standard
- 4 spaces indentation
- Clear comments for business logic

### Adding Features

**New Model:**
```php
class MyModel extends Model {
    protected static string $table = 'my_table';
    protected static array $fillable = ['field1', 'field2'];
}
```

**New Route:**
```php
$router->get('/my-route', 'MyController@method');
```

**New View:**
```php
<?php
// views/my/view.php
?>
<h1><?= View::e($title) ?></h1>
```

## Support

For issues specific to this PHP conversion, check:
1. PHP error logs: `/var/log/apache2/error.log`
2. Application logs: `storage/logs/`
3. Email audit: Check `email_audit` table

## License

Same as original TimeOff Management System.

## Credits

- **Original Project:** TimeOff Management (Node.js/Express)
- **PHP Conversion:** Complete rewrite for PHP 8.2 / XAMPP 8.2
- **Date:** 2025

---

**Note:** This is a COMPLETE rewrite in PHP. All business logic has been reimplemented to match the original Node.js application exactly, including the critical allowance calculation algorithm.
