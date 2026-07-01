# 🚀 Deployment Checklist - لما يكون عندك تحديثات على الداتابيز

## 📌 السيناريو: عندك Migration جديدة (Custom Fields للـ Leads)

---

## ✅ الخطوات الصحيحة:

### 1️⃣ **ارفع الكود الجديد على السيرفر**

```bash
# على السيرفر
cd /var/www/lumen-crm-backend
git pull origin main
```

**أو لو بترفع يدوي:**
- ارفع المجلدات: `database/migrations/`, `app/Models/`, `app/Http/Controllers/`
- ارفع الملفات المعدلة فقط

---

### 2️⃣ **شغل الـ Migrations على السيرفر**

```bash
# على السيرفر
cd /var/www/lumen-crm-backend
php artisan migrate --force
```

**هيحصل إيه:**
- الـ Migration هتشتغل على قاعدة البيانات اللي على السيرفر
- هتضيف الـ custom fields الجديدة (tech_support_phone, store_link, auth_status, social_media)
- البيانات القديمة **مش هتتمسح** - هتفضل زي ما هي ✅

---

### 3️⃣ **امسح الـ Cache (مهم جداً!)**

```bash
# على السيرفر
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# كاش الكونفج من جديد
php artisan config:cache
php artisan route:cache
```

---

### 4️⃣ **اعمل Restart للـ PHP-FPM**

```bash
# على السيرفر
sudo systemctl reload php8.1-fpm

# أو
sudo systemctl restart php8.1-fpm
```

---

## ❌ اللي **مش** لازم تعمله:

### 🚫 **لا ترفع ملف الداتابيز** (`database.sqlite`)
- الملف ده local بس
- السيرفر عنده MySQL/MariaDB مختلفة
- لو رفعته هيحصل مشاكل كبيرة!

### 🚫 **لا تعمل Export/Import للداتا**
- مش محتاج تعمل SQL dump
- الـ Migration هتحدث الجداول بس
- البيانات القديمة مش هتتأثر

### 🚫 **لا تعمل Fresh Migration**
```bash
# ❌ لا تعمل ده على السيرفر أبداً!
php artisan migrate:fresh  # هيمسح كل الداتا!
```

---

## 📁 الملفات اللي لازم ترفعها:

### ✅ **Migration Files:**
```
database/migrations/2026_06_24_220000_add_custom_fields_to_leads_table.php
```

### ✅ **Model Files (لو معدلت):**
```
app/Models/Lead.php
```

### ✅ **Controller Files (لو معدلت):**
```
app/Http/Controllers/API/LeadController.php
```

### ✅ **أي ملفات تانية اتعدلت**

---

## 🔄 Deployment Script (اختياري - يسهل عليك)

انشئ ملف `deploy.sh` على السيرفر:

```bash
#!/bin/bash

echo "🚀 Starting deployment..."

# 1. Pull latest code
echo "📥 Pulling latest code..."
git pull origin main

# 2. Install dependencies (لو في تحديثات)
echo "📦 Installing dependencies..."
composer install --optimize-autoloader --no-dev

# 3. Run migrations
echo "🗄️ Running migrations..."
php artisan migrate --force

# 4. Clear all caches
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# 5. Cache configs
echo "⚡ Caching configs..."
php artisan config:cache
php artisan route:cache

# 6. Restart services
echo "🔄 Restarting PHP-FPM..."
sudo systemctl reload php8.1-fpm

# 7. Restart queue workers (لو موجودين)
if command -v supervisorctl &> /dev/null; then
    echo "🔄 Restarting queue workers..."
    supervisorctl restart lumen-crm-worker:*
fi

echo "✅ Deployment completed successfully!"
echo ""
echo "🧪 Test the API:"
echo "curl https://api.yourdomain.com/api/health"
```

**استخدامه:**
```bash
chmod +x deploy.sh
./deploy.sh
```

---

## 🧪 اختبار بعد الـ Deployment:

### 1. تأكد إن الـ Migration اشتغلت:
```bash
# على السيرفر
php artisan migrate:status
```

### 2. جرب تنشئ Lead جديد:
```bash
curl -X POST https://api.yourdomain.com/api/leads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Test Lead",
    "email": "test@example.com",
    "phone": "01012345678",
    "tech_support_phone": "01098765432",
    "store_link": "https://store.com/test",
    "auth_status": "verified",
    "social_media": "@test_shop"
  }'
```

### 3. تأكد إن الـ Custom Fields بتتحفظ:
```bash
curl https://api.yourdomain.com/api/leads/LEAD_ID \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 📊 مقارنة: Local vs Production

| العملية | Local (جهازك) | Production (السيرفر) |
|---------|---------------|---------------------|
| **Database** | SQLite (database.sqlite) | MySQL/MariaDB |
| **Migrations** | `php artisan migrate` | `php artisan migrate --force` |
| **رفع الداتا** | ❌ لا ترفع ملف الداتابيز | ✅ شغل Migration بس |
| **الملفات** | كل الملفات موجودة | ارفع الملفات المعدلة فقط |
| **البيانات القديمة** | تفضل موجودة | تفضل موجودة |

---

## 💡 نصائح مهمة:

### ✅ قبل الـ Deployment:
1. **جرب الـ Migration على Local أول** ✅
2. **اعمل Backup للداتابيز على السيرفر** ✅
   ```bash
   php artisan db:backup  # أو
   mysqldump -u user -p database > backup.sql
   ```
3. **اعمل Test للكود على Local** ✅

### ✅ أثناء الـ Deployment:
1. **استخدم Git** - أسهل وأأمن ✅
2. **شغل Migrations بحذر** ✅
3. **راقب الـ Logs** ✅
   ```bash
   tail -f storage/logs/laravel.log
   ```

### ✅ بعد الـ Deployment:
1. **جرب الـ API Endpoints** ✅
2. **تأكد من الـ Custom Fields** ✅
3. **شوف الـ Error Logs** ✅

---

## 🆘 لو حصلت مشكلة:

### مشكلة: Migration فشلت
```bash
# شوف المشكلة
cat storage/logs/laravel.log

# لو عاوز ترجع Migration
php artisan migrate:rollback --step=1
```

### مشكلة: الـ Custom Fields مش ظاهرة
```bash
# امسح الـ Cache
php artisan cache:clear
php artisan config:clear

# اعمل Restart للـ PHP
sudo systemctl restart php8.1-fpm
```

### مشكلة: 500 Error بعد الـ Deploy
```bash
# تأكد من الـ Permissions
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# شوف الـ Error Log
tail -f /var/log/nginx/lumen-crm-error.log
```

---

## ✅ الخلاصة:

| ✅ **اعمل** | ❌ **لا تعمل** |
|------------|---------------|
| ارفع الملفات المعدلة | لا ترفع database.sqlite |
| شغل `php artisan migrate --force` | لا تعمل `migrate:fresh` |
| امسح الـ Cache | لا تنسخ الداتا يدوي |
| اعمل Restart للـ PHP-FPM | لا تمسح البيانات القديمة |
| جرب الـ API بعد Deploy | لا تنسى الـ Backup قبل Deploy |

---

**الخلاصة في سطر واحد:**
> **ارفع الملفات، شغل migrate على السيرفر، امسح الـ cache، خلاص! 🎉**

---

تاريخ: 30 يونيو 2026
