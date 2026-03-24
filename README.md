# 🦁 Safari Contribution Portal

A simple PHP/MySQL web portal for tracking employee contributions toward a group Safari Trip Fund.

---

## 📁 Folder Structure

```
safari-portal/
│
├── index.php              ← User portal (login by Work ID)
├── dashboard.php          ← Redirect alias → index.php
├── add-payment.php        ← Payment handler (user-facing)
├── config.php             ← DB config + helpers
├── style.css              ← All styles
├── database.sql           ← Database setup script
│
└── admin/
    ├── admin-login.php    ← Admin login
    ├── admin-dashboard.php← Admin overview + settings
    ├── add-user.php       ← Register new employee
    ├── record-payment.php ← Log payment for any user
    ├── user-payments.php  ← View + delete user payments
    └── admin-logout.php   ← Session destroy
```

---

## ⚙️ Setup Instructions (XAMPP / Shared Hosting)

### 1. Install XAMPP
Download from https://www.apachefriends.org and start **Apache** + **MySQL**.

### 2. Copy Files
Place the `safari-portal/` folder into:
```
C:\xampp\htdocs\safari-portal\
```
(or `/var/www/html/safari-portal/` on Linux)

### 3. Create the Database
- Open your browser → http://localhost/phpmyadmin
- Click **New** → create a database named `safari_portal`
- Select `safari_portal` → click **Import**
- Browse to `database.sql` → click **Go**

OR run in the SQL tab:
```sql
source /path/to/safari-portal/database.sql;
```

### 4. Configure Database (config.php)
Open `config.php` and update:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('DB_NAME', 'safari_portal');
```

### 5. Change Admin Credentials (Recommended!)
In `config.php`:
```php
define('ADMIN_USERNAME', 'admin');      // Change this
define('ADMIN_PASSWORD', 'safari2025'); // Change this!
```

### 6. Set Currency (Optional)
```php
define('CURRENCY', 'TSh');  // or 'USD', 'NGN', 'ZAR', etc.
```

### 7. Access the Portal
| Page            | URL                                          |
|-----------------|----------------------------------------------|
| User Portal     | http://localhost/safari-portal/              |
| Admin Login     | http://localhost/safari-portal/admin/admin-login.php |

---

## 🧪 Test Users (included in database.sql)

| Name           | Work ID | Target    |
|----------------|---------|-----------|
| Alice Mwangi   | EMP001  | TSh 5,000 |
| Brian Ochieng  | EMP002  | TSh 5,000 |
| Carol Njeri    | EMP003  | TSh 3,500 |
| David Kamau    | EMP004  | TSh 5,000 |
| Eva Wambui     | EMP005  | TSh 4,000 |

**Default admin credentials:**
- Username: `admin`
- Password: `safari2025`

---

## 🔄 How It Works

### User Flow
1. User visits the portal → enters their **Work ID** → clicks **Fetch**
2. Dashboard loads showing: target, paid, remaining, progress bar
3. User can **Add Payment** via the modal
4. After **first payment**, the **Join WhatsApp Group** button unlocks

### Admin Flow
1. Admin logs in at `/admin/admin-login.php`
2. Dashboard shows: total users, total collected, completion count, overall progress
3. Admin can: **Add Users**, **Record Payments**, **View History**, **Set WhatsApp link**

---

## 🚀 Deploying to Shared Hosting (cPanel)

1. Zip the `safari-portal/` folder
2. Upload via **File Manager** → extract to `public_html/safari-portal/`
3. In **phpMyAdmin** → create DB → import `database.sql`
4. Update `config.php` with your hosting DB credentials
5. **Fix paths**: Replace `/safari-portal/` in `config.php` redirects with your actual subdirectory or root

### If deployed to root domain (e.g. mysite.com):
In `config.php`, change all redirects from:
```php
redirect('/safari-portal/admin/...');
```
to:
```php
redirect('/admin/...');
```

---

## 🛠️ Customization

| Setting          | Location                    | How to Change                    |
|------------------|-----------------------------|----------------------------------|
| Currency symbol  | `config.php`                | `define('CURRENCY', 'USD')`      |
| Admin username   | `config.php`                | `define('ADMIN_USERNAME', 'you')`|
| Admin password   | `config.php`                | `define('ADMIN_PASSWORD', '...')`|
| Trip name        | Admin Dashboard → Settings  | Edit in browser                  |
| WhatsApp link    | Admin Dashboard → Settings  | Paste invite link                |
| Colors / theme   | `style.css`                 | Edit CSS variables at top        |

---

## 📋 Requirements

- PHP 7.4+ (PDO + PDO_MySQL extension enabled)
- MySQL 5.7+ or MariaDB 10.3+
- Apache or Nginx web server

---

## 🔒 Security Notes

- This is a **simple internal tool** — not hardened for public internet exposure
- For production use: add CSRF tokens, rate limiting, HTTPS
- Change the admin password before going live
- Avoid exposing this to the public internet without authentication improvements
