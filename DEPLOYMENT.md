# Lumen CRM - Deployment Guide

## 📋 Pre-Deployment Checklist

### Server Requirements
- [x] PHP 8.1+ with required extensions
- [x] MySQL 8.0+ or MariaDB 10.3+
- [x] Nginx or Apache web server
- [x] Composer installed
- [x] SSL Certificate ready
- [x] Domain name configured

### PHP Extensions Required
```bash
php -m | grep -E 'pdo|mysql|mbstring|xml|bcmath|json|openssl|tokenizer|curl'
```

Required extensions:
- PDO
- pdo_mysql
- mbstring
- xml
- bcmath
- json
- openssl
- tokenizer
- curl
- fileinfo
- ctype

---

## 🚀 Deployment Steps

### 1. Clone Repository

```bash
cd /var/www
git clone <repository-url> lumen-crm-backend
cd lumen-crm-backend
```

### 2. Install Dependencies

```bash
composer install --optimize-autoloader --no-dev
```

### 3. Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Edit environment variables
nano .env
```

**Production .env Configuration:**
```env
APP_NAME="Lumen CRM"
APP_ENV=production
APP_KEY=base64:GENERATE_THIS_KEY
APP_DEBUG=false
APP_URL=https://api.yourdomain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lumen_crm_production
DB_USERNAME=your_db_user
DB_PASSWORD=your_secure_password

# Session
SESSION_DRIVER=database
SESSION_LIFETIME=120

# Cache
CACHE_STORE=redis

# Queue
QUEUE_CONNECTION=redis

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourdomain.com
MAIL_PORT=587
MAIL_USERNAME=noreply@yourdomain.com
MAIL_PASSWORD=your_mail_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@yourdomain.com

# Sanctum
SANCTUM_STATEFUL_DOMAINS=yourdomain.com,www.yourdomain.com

# CORS
FRONTEND_URL=https://yourdomain.com

# File Storage (AWS S3 recommended for production)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_aws_key
AWS_SECRET_ACCESS_KEY=your_aws_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=lumen-crm-uploads
```

### 4. Generate Application Key

```bash
php artisan key:generate
```

### 5. Set Permissions

```bash
# Set ownership
chown -R www-data:www-data /var/www/lumen-crm-backend

# Set directory permissions
find /var/www/lumen-crm-backend -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/lumen-crm-backend -type f -exec chmod 644 {} \;

# Storage and cache directories need write access
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### 6. Database Setup

```bash
# Create database
mysql -u root -p
CREATE DATABASE lumen_crm_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON lumen_crm_production.* TO 'your_db_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

# Run migrations
php artisan migrate --force

# Seed initial data (optional)
php artisan db:seed --force
```

### 7. Create Storage Link

```bash
php artisan storage:link
```

### 8. Optimize Application

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache

# Optimize autoloader
composer dump-autoload --optimize
```

---

## 🌐 Web Server Configuration

### Nginx Configuration

Create file: `/etc/nginx/sites-available/lumen-crm-backend`

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name api.yourdomain.com;

    root /var/www/lumen-crm-backend/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /etc/ssl/certs/yourdomain.crt;
    ssl_certificate_key /etc/ssl/private/yourdomain.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;

    # Logs
    access_log /var/log/nginx/lumen-crm-access.log;
    error_log /var/log/nginx/lumen-crm-error.log;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # Max upload size
    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

**Enable site:**
```bash
ln -s /etc/nginx/sites-available/lumen-crm-backend /etc/nginx/sites-enabled/
nginx -t
systemctl reload nginx
```

### Apache Configuration

Create file: `/etc/apache2/sites-available/lumen-crm-backend.conf`

```apache
<VirtualHost *:80>
    ServerName api.yourdomain.com
    Redirect permanent / https://api.yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.yourdomain.com
    DocumentRoot /var/www/lumen-crm-backend/public

    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/yourdomain.crt
    SSLCertificateKeyFile /etc/ssl/private/yourdomain.key

    <Directory /var/www/lumen-crm-backend/public>
        AllowOverride All
        Require all granted
        Options -Indexes +FollowSymLinks
    </Directory>

    # Security Headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"

    ErrorLog ${APACHE_LOG_DIR}/lumen-crm-error.log
    CustomLog ${APACHE_LOG_DIR}/lumen-crm-access.log combined
</VirtualHost>
```

**Enable site:**
```bash
a2ensite lumen-crm-backend
a2enmod ssl rewrite headers
systemctl reload apache2
```

---

## 🔄 Queue Workers Setup

### Create Supervisor Configuration

Create file: `/etc/supervisor/conf.d/lumen-crm-worker.conf`

```ini
[program:lumen-crm-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/lumen-crm-backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/lumen-crm-backend/storage/logs/worker.log
stopwaitsecs=3600
```

**Start workers:**
```bash
supervisorctl reread
supervisorctl update
supervisorctl start lumen-crm-worker:*
```

---

## ⏰ Scheduled Tasks Setup

Add to crontab:
```bash
crontab -e
```

Add this line:
```cron
* * * * * cd /var/www/lumen-crm-backend && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🔐 SSL Certificate Setup

### Using Let's Encrypt (Certbot)

```bash
# Install Certbot
apt install certbot python3-certbot-nginx

# Generate certificate
certbot --nginx -d api.yourdomain.com

# Auto-renewal
certbot renew --dry-run
```

---

## 📊 Monitoring & Logging

### Log Rotation

Create file: `/etc/logrotate.d/lumen-crm`

```
/var/www/lumen-crm-backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### Application Monitoring

```bash
# Install Laravel Telescope (development only)
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

---

## 🔄 Deployment Script

Create file: `deploy.sh`

```bash
#!/bin/bash

echo "🚀 Starting deployment..."

# Pull latest code
git pull origin main

# Install/Update dependencies
composer install --optimize-autoloader --no-dev

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart queue workers
supervisorctl restart lumen-crm-worker:*

# Reload PHP-FPM
systemctl reload php8.1-fpm

echo "✅ Deployment completed!"
```

Make executable:
```bash
chmod +x deploy.sh
```

---

## 🔒 Security Hardening

### 1. Firewall Configuration

```bash
# UFW
ufw allow 22/tcp
ufw allow 80/tcp
ufw allow 443/tcp
ufw enable
```

### 2. Fail2Ban Setup

```bash
apt install fail2ban
systemctl enable fail2ban
systemctl start fail2ban
```

### 3. Database Security

```bash
mysql_secure_installation
```

### 4. Disable Directory Listing

Already handled in Nginx/Apache config

### 5. Rate Limiting

Configure in `app/Http/Kernel.php`

---

## 📈 Performance Optimization

### 1. OpCache Configuration

Edit `/etc/php/8.1/fpm/conf.d/10-opcache.ini`:

```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0
opcache.revalidate_freq=0
opcache.save_comments=1
```

### 2. Redis Configuration

```bash
# Install Redis
apt install redis-server

# Configure Redis
systemctl enable redis-server
systemctl start redis-server
```

### 3. PHP-FPM Optimization

Edit `/etc/php/8.1/fpm/pool.d/www.conf`:

```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

---

## 🔙 Backup Strategy

### Database Backup Script

Create file: `backup-db.sh`

```bash
#!/bin/bash

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/lumen-crm"
DB_NAME="lumen_crm_production"
DB_USER="your_db_user"
DB_PASS="your_db_password"

mkdir -p $BACKUP_DIR

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/db_backup_$DATE.sql.gz

# Keep only last 7 days of backups
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +7 -delete

echo "Backup completed: $BACKUP_DIR/db_backup_$DATE.sql.gz"
```

### Add to crontab (daily at 2 AM):
```cron
0 2 * * * /var/www/lumen-crm-backend/backup-db.sh
```

---

## 🧪 Post-Deployment Testing

```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Test cache
php artisan tinker
>>> Cache::put('test', 'value', 60);
>>> Cache::get('test');

# Test queue
php artisan queue:work --once

# API health check
curl https://api.yourdomain.com/api/health
```

---

## 🆘 Troubleshooting

### Permission Issues
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Clear All Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Check Logs
```bash
tail -f storage/logs/laravel.log
tail -f /var/log/nginx/lumen-crm-error.log
```

### Database Connection Issues
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## 📞 Support

For deployment issues, contact the development team.

---

**Last Updated:** June 2026
