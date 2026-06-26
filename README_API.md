# Lumen CRM - Backend API Documentation

## 📋 Overview

Lumen CRM is a comprehensive Customer Relationship Management system built with Laravel 10+ and MySQL. This backend provides RESTful APIs with JWT authentication for managing leads, users, activities, and analytics.

## 🚀 Quick Start

### Prerequisites

- PHP 8.1 or higher
- MySQL 8.0 or higher
- Composer
- Node.js (optional, for asset compilation)

### Installation

1. **Clone the repository**
```bash
cd lumen-crm-backend
```

2. **Install dependencies**
```bash
composer install
```

3. **Environment setup**
```bash
# Copy .env file (already created)
# Update database credentials in .env file
```

4. **Generate application key**
```bash
php artisan key:generate
```

5. **Create database**
```bash
mysql -u root -p
CREATE DATABASE lumen_crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

6. **Run migrations**
```bash
php artisan migrate
```

7. **Seed database (optional)**
```bash
php artisan db:seed
```

8. **Create storage link**
```bash
php artisan storage:link
```

9. **Start development server**
```bash
php artisan serve
```

API will be available at: `http://localhost:8000`

---

## 🗄️ Database Schema

### Tables

1. **users** - User accounts (admin/agent)
2. **leads** - Customer leads with stages and priorities
3. **notes** - Notes attached to leads
4. **lead_activities** - Activity log for lead changes
5. **user_activities** - System-wide user activity audit trail
6. **attachments** - Files attached to leads/activities

---

## 🔐 Authentication

### Login
```http
POST /api/auth/login
Content-Type: application/json

{
  "email": "admin@lumencrm.com",
  "password": "password"
}
```

**Response:**
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
    "token": "1|xxxxxxxxxxxxxxxxxxxxx"
  },
  "message": "Login successful"
}
```

### Register (Admin Only)
```http
POST /api/auth/register
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "New User",
  "email": "newuser@example.com",
  "password": "password",
  "role": "agent",
  "avatar_color": "#10b981"
}
```

### Get Current User
```http
GET /api/auth/user
Authorization: Bearer {token}
```

### Logout
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

### Update Profile
```http
PUT /api/auth/profile
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "avatar_color": "#ef4444"
}
```

### Change Password
```http
PUT /api/auth/password
Authorization: Bearer {token}
Content-Type: application/json

{
  "current_password": "oldpassword",
  "new_password": "newpassword",
  "new_password_confirmation": "newpassword"
}
```

---

## 👥 Leads Management

### List Leads
```http
GET /api/leads?page=1&per_page=20&stage=new&priority=high&search=ahmed
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20)
- `stage` - Filter by stage (new, attempted, negotiation, followup, won, lost)
- `priority` - Filter by priority (low, medium, high)
- `assignee_id` - Filter by assigned user
- `search` - Search by name or email
- `sort_by` - Sort field (default: created_at)
- `sort_dir` - Sort direction (asc, desc)

### Get Lead Details
```http
GET /api/leads/{id}
Authorization: Bearer {token}
```

### Create Lead
```http
POST /api/leads
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Ahmed Mohamed",
  "email": "ahmed@example.com",
  "phone": "+201234567890",
  "source": "Website",
  "budget": 25000,
  "priority": "high",
  "stage": "new",
  "assignee_id": 2
}
```

### Update Lead
```http
PUT /api/leads/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "priority": "medium",
  "budget": 30000
}
```

### Delete Lead
```http
DELETE /api/leads/{id}
Authorization: Bearer {token}
```

### Assign Lead
```http
PUT /api/leads/{id}/assign
Authorization: Bearer {token}
Content-Type: application/json

{
  "assignee_id": 3
}
```

### Update Lead Stage
```http
PUT /api/leads/{id}/stage
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "stage": "won",
  "comment": "Client agreed to all terms and signed contract",
  "attachments[]": [file1, file2]
}
```

### Bulk Assign Leads
```http
POST /api/leads/bulk-assign
Authorization: Bearer {token}
Content-Type: application/json

{
  "lead_ids": [1, 2, 3, 4],
  "assignee_id": 2
}
```

---

## 📝 Notes Management

### Add Note to Lead
```http
POST /api/leads/{leadId}/notes
Authorization: Bearer {token}
Content-Type: application/json

{
  "content": "Had a great conversation with the client. They are interested in the premium package."
}
```

### Get Note
```http
GET /api/notes/{id}
Authorization: Bearer {token}
```

### Update Note
```http
PUT /api/notes/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "content": "Updated note content"
}
```

### Delete Note
```http
DELETE /api/notes/{id}
Authorization: Bearer {token}
```

---

## 📎 Attachments Management

### Upload Attachments
```http
POST /api/attachments
Authorization: Bearer {token}
Content-Type: multipart/form-data

{
  "lead_id": 1,
  "activity_id": 5,
  "files[]": [file1, file2, file3]
}
```

**Supported file types:**
- Images: jpeg, jpg, png, gif, webp
- Documents: pdf, doc, docx
- Spreadsheets: xls, xlsx

**Max file size:** 10MB per file

### List Attachments
```http
GET /api/attachments?lead_id=1
Authorization: Bearer {token}
```

### Download Attachment
```http
GET /api/attachments/{id}
Authorization: Bearer {token}
```

### Delete Attachment
```http
DELETE /api/attachments/{id}
Authorization: Bearer {token}
```

---

## 📊 Dashboard

### Get Dashboard Stats
```http
GET /api/dashboard
Authorization: Bearer {token}
```

**Response includes:**
- Summary statistics (total leads, conversion rate, revenue)
- Leads by stage
- Leads by priority
- Recent activities
- Top performers (admin only)

### Get Quick Stats
```http
GET /api/dashboard/quick-stats
Authorization: Bearer {token}
```

---

## 📈 Analytics (Admin Only)

### Analytics Overview
```http
GET /api/analytics/overview?range=30
Authorization: Bearer {token}
```

**Query Parameters:**
- `range` - Number of days to analyze (default: 30)

**Response includes:**
- Leads trend
- Conversion trend
- Revenue trend
- Source analysis
- Stage distribution

### Agent Performance
```http
GET /api/analytics/agents
Authorization: Bearer {token}
```

**Response includes:**
- Agent metrics (leads, conversion rate, revenue)
- Activity scores
- Performance comparison

### Lead Analytics
```http
GET /api/analytics/leads
Authorization: Bearer {token}
```

**Response includes:**
- Time-based analysis
- Priority analysis
- Conversion funnel
- Average budget by stage

---

## 🔔 Activities

### Get Recent Activities
```http
GET /api/activities/recent?days=7&per_page=20
Authorization: Bearer {token}
```

### Get Today's Activities
```http
GET /api/activities/today
Authorization: Bearer {token}
```

### Get Activity Statistics
```http
GET /api/activities/stats?days=30
Authorization: Bearer {token}
```

### Get User Activities
```http
GET /api/activities/user/{userId}?per_page=50
Authorization: Bearer {token}
```

### Get Lead Activities
```http
GET /api/activities/lead/{leadId}?per_page=50
Authorization: Bearer {token}
```

---

## 👤 User Management

### List Users
```http
GET /api/users
Authorization: Bearer {token}
```

### Get User
```http
GET /api/users/{id}
Authorization: Bearer {token}
```

### Create User (Admin Only)
```http
POST /api/users
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "New Agent",
  "email": "agent@example.com",
  "password": "password",
  "role": "agent",
  "avatar_color": "#10b981"
}
```

### Update User (Admin Only)
```http
PUT /api/users/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Updated Name",
  "role": "admin",
  "is_active": true
}
```

### Delete User (Admin Only)
```http
DELETE /api/users/{id}
Authorization: Bearer {token}
```

**Note:** Users are soft-deleted (deactivated) and their leads are unassigned.

### Change User Password (Admin Only)
```http
PUT /api/users/{id}/password
Authorization: Bearer {token}
Content-Type: application/json

{
  "password": "newpassword"
}
```

### Get User Activities
```http
GET /api/users/{id}/activities?per_page=50
Authorization: Bearer {token}
```

---

## 🧪 Testing

### Health Check (Public)
```http
GET /api/health
```

### Test Authenticated Endpoint
```http
GET /api/test
Authorization: Bearer {token}
```

---

## 🔒 Security Features

1. **Laravel Sanctum** - Token-based authentication
2. **Password Hashing** - Bcrypt encryption
3. **CORS** - Configured for frontend domains
4. **Activity Logging** - Complete audit trail
5. **Role-Based Access** - Admin/Agent permissions
6. **Input Validation** - Server-side validation
7. **File Type Validation** - Secure file uploads

---

## 📦 Services Layer

### UserActivityService
```php
use App\Services\UserActivityService;

$service = new UserActivityService();
$service->log($user, 'action', 'description', $lead, ['key' => 'value']);
```

### AnalyticsService
```php
use App\Services\AnalyticsService;

$service = new AnalyticsService();
$performance = $service->getAgentPerformance();
$funnel = $service->getConversionFunnel();
```

### FileUploadService
```php
use App\Services\FileUploadService;

$service = new FileUploadService();
$attachments = $service->uploadAttachments($files, $lead, $activityId);
```

### ReportService
```php
use App\Services\ReportService;

$service = new ReportService();
$report = $service->generateSalesReport($startDate, $endDate);
```

---

## 🎯 Default Credentials

After running `php artisan db:seed`:

**Admin:**
- Email: `admin@lumencrm.com`
- Password: `password`

**Agents:**
- Email: `priya@lumencrm.com` / Password: `password`
- Email: `marcus@lumencrm.com` / Password: `password`
- Email: `sofia@lumencrm.com` / Password: `password`

---

## 📝 Error Responses

### Validation Error (422)
```json
{
  "success": false,
  "error": "Validation failed",
  "details": {
    "email": ["The email field is required"],
    "password": ["The password must be at least 6 characters"]
  }
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "error": "Unauthorized"
}
```

### Forbidden (403)
```json
{
  "success": false,
  "error": "Forbidden - Insufficient permissions"
}
```

### Not Found (404)
```json
{
  "success": false,
  "error": "Resource not found"
}
```

### Server Error (500)
```json
{
  "success": false,
  "error": "Internal server error"
}
```

---

## 🛠️ Development

### Run Migrations
```bash
php artisan migrate
```

### Rollback Migrations
```bash
php artisan migrate:rollback
```

### Refresh Database
```bash
php artisan migrate:fresh --seed
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

### Generate IDE Helper
```bash
composer require --dev barryvdh/laravel-ide-helper
php artisan ide-helper:generate
php artisan ide-helper:models
```

---

## 🚀 Production Deployment

1. **Set environment to production**
```bash
APP_ENV=production
APP_DEBUG=false
```

2. **Optimize application**
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

3. **Set proper permissions**
```bash
chmod -R 755 storage bootstrap/cache
```

4. **Configure web server** (Nginx/Apache)

5. **Setup SSL certificate**

6. **Configure queue workers**
```bash
php artisan queue:work --daemon
```

7. **Setup scheduled tasks**
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Laravel Sanctum](https://laravel.com/docs/sanctum)
- [MySQL Documentation](https://dev.mysql.com/doc/)

---

## 📞 Support

For issues or questions, please contact the development team.

---

## 📄 License

This project is proprietary and confidential.

---

**Version:** 1.0.0  
**Last Updated:** June 2026
