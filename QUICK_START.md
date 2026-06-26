# 🚀 Lumen CRM - دليل البدء السريع

## ⚡ خطوات سريعة (5 دقائق)

### المتطلبات الأساسية
- PHP 8.1+
- MySQL 8.0+
- Composer

---

## 📦 التثبيت

### 1. تثبيت Dependencies
```bash
cd lumen-crm-backend
composer install
```

### 2. إنشاء قاعدة البيانات
```bash
mysql -u root -p
```

```sql
CREATE DATABASE lumen_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### 3. تكوين Environment
```bash
# الملف .env موجود بالفعل
# فقط عدّل بيانات قاعدة البيانات إذا لزم الأمر:
```

افتح `.env` وتأكد من:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lumen_crm
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

### 4. إنشاء Application Key
```bash
php artisan key:generate
```

### 5. تشغيل Migrations
```bash
php artisan migrate
```

### 6. إدخال البيانات التجريبية
```bash
php artisan db:seed
```

### 7. إنشاء Storage Link
```bash
php artisan storage:link
```

### 8. تشغيل الخادم
```bash
php artisan serve
```

✅ **API جاهز على:** http://localhost:8000

---

## 🔑 حسابات التجربة

بعد تشغيل `php artisan db:seed`:

### Admin Account
```
Email: admin@lumencrm.com
Password: password
```

### Agent Accounts
```
Email: priya@lumencrm.com
Password: password

Email: marcus@lumencrm.com
Password: password

Email: sofia@lumencrm.com
Password: password
```

---

## 🧪 اختبار API

### 1. Health Check (بدون مصادقة)
```bash
curl http://localhost:8000/api/health
```

**Expected Response:**
```json
{
  "success": true,
  "message": "Lumen CRM API is running",
  "version": "1.0.0",
  "timestamp": "2026-06-14T..."
}
```

### 2. Login
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@lumencrm.com",
    "password": "password"
  }'
```

**Expected Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "Alex Morgan",
      "email": "admin@lumencrm.com",
      "role": "admin",
      "avatar_color": "#3b82f6",
      "initials": "AM"
    },
    "token": "1|xxxxxxxxxxxxxxxxxxxx"
  },
  "message": "Login successful"
}
```

### 3. Get Leads (مع Token)
```bash
# نسخ token من response السابق
curl http://localhost:8000/api/leads \
  -H "Authorization: Bearer YOUR_TOKEN_HERE"
```

---

## 📮 استخدام Postman

### 1. استيراد Collection
1. افتح Postman
2. File → Import
3. اختر: `Lumen_CRM_API.postman_collection.json`

### 2. تعديل Environment
1. اذهب إلى Collections → Lumen CRM API
2. في Variables، عدّل `base_url` إذا لزم:
   ```
   base_url = http://localhost:8000/api
   ```

### 3. Login وحفظ Token
1. افتح: `Authentication → Login`
2. اضغط Send
3. Token سيُحفظ تلقائياً في المتغير `{{token}}`
4. جميع الطلبات الأخرى ستستخدمه تلقائياً

### 4. جرب Endpoints
- Dashboard
- Leads
- Notes
- Activities
- Analytics (Admin only)

---

## 📁 هيكل المشروع

```
lumen-crm-backend/
├── app/
│   ├── Http/
│   │   ├── Controllers/API/    # 8 Controllers
│   │   └── Middleware/         # CheckRole
│   ├── Models/                 # 6 Models
│   └── Services/               # 4 Services
├── database/
│   ├── migrations/             # 10 Migrations
│   └── seeders/                # DatabaseSeeder
├── routes/
│   └── api.php                 # 38+ API Routes
├── storage/
│   ├── logs/                   # Application logs
│   └── app/public/attachments/ # Uploaded files
├── .env                        # Configuration
├── README_API.md               # Full documentation
├── DEPLOYMENT.md               # Production deployment
└── Lumen_CRM_API.postman_collection.json
```

---

## 🔧 أوامر مفيدة

### Clear Caches
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### View Routes
```bash
php artisan route:list
```

### Reset Database
```bash
php artisan migrate:fresh --seed
```

### Enter Tinker (Console)
```bash
php artisan tinker
```

### Check Database Connection
```bash
php artisan tinker
>>> DB::connection()->getPdo();
```

---

## 📊 أمثلة سريعة

### إنشاء Lead جديد
```bash
curl -X POST http://localhost:8000/api/leads \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Ahmed Mohamed",
    "email": "ahmed@example.com",
    "phone": "+201234567890",
    "source": "Website",
    "budget": 25000,
    "priority": "high",
    "assignee_id": 2
  }'
```

### إضافة ملاحظة
```bash
curl -X POST http://localhost:8000/api/leads/1/notes \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Great conversation with client!"
  }'
```

### الحصول على Dashboard
```bash
curl http://localhost:8000/api/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## 🎯 Endpoints الأساسية

| Method | Endpoint | الوصف |
|--------|----------|-------|
| POST | `/api/auth/login` | تسجيل دخول |
| GET | `/api/auth/user` | معلومات المستخدم الحالي |
| GET | `/api/leads` | قائمة العملاء |
| POST | `/api/leads` | إضافة عميل |
| GET | `/api/leads/{id}` | تفاصيل عميل |
| PUT | `/api/leads/{id}` | تحديث عميل |
| POST | `/api/leads/{id}/notes` | إضافة ملاحظة |
| GET | `/api/dashboard` | إحصائيات Dashboard |
| GET | `/api/analytics/overview` | تحليلات شاملة |
| GET | `/api/activities/recent` | الأنشطة الأخيرة |

للقائمة الكاملة، راجع: [README_API.md](README_API.md)

---

## ⚠️ استكشاف الأخطاء

### خطأ: "SQLSTATE[HY000] [2002]"
```bash
# تأكد من تشغيل MySQL
mysql -u root -p

# تأكد من بيانات .env صحيحة
```

### خطأ: "No application encryption key"
```bash
php artisan key:generate
```

### خطأ: "Permission denied" (storage)
```bash
chmod -R 775 storage bootstrap/cache
```

### خطأ: "Class not found"
```bash
composer dump-autoload
```

---

## 📚 المزيد من التوثيق

- **[README_API.md](README_API.md)** - توثيق API الكامل
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - دليل النشر على الإنتاج
- **[PROJECT_COMPLETION_REPORT.md](../PROJECT_COMPLETION_REPORT.md)** - تقرير المشروع

---

## 🎓 نصائح للمطورين

### 1. استخدم Tinker للتجارب
```bash
php artisan tinker
>>> User::count()
>>> Lead::where('stage', 'won')->sum('budget')
>>> DB::table('leads')->get()
```

### 2. راقب Logs
```bash
tail -f storage/logs/laravel.log
```

### 3. استخدم Query Log للتطوير
```php
// في Controller أو Route
DB::enableQueryLog();
// ... your code ...
dd(DB::getQueryLog());
```

---

## 🚀 الخطوات التالية

1. ✅ جرّب جميع Endpoints في Postman
2. ✅ افحص Dashboard
3. ✅ أنشئ Leads وملاحظات
4. ✅ جرّب Analytics (كـ Admin)
5. ✅ اقرأ [README_API.md](README_API.md) للتفاصيل
6. ✅ للنشر: اقرأ [DEPLOYMENT.md](DEPLOYMENT.md)

---

## 💡 هل تحتاج مساعدة؟

- 📖 اقرأ [README_API.md](README_API.md)
- 🐛 تحقق من `storage/logs/laravel.log`
- 🔍 استخدم `php artisan tinker` للتصحيح

---

**نسخة:** 1.0.0  
**آخر تحديث:** يونيو 2026

---

# 🎉 استمتع بالتطوير! 🎉
