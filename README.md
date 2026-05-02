# Drugs4U – Prescription Management System
## Setup Guide (Abyss Web Server + MySQL 9.2 + PHP)

---

### 1. Copy Files
Copy the entire `drugs4u/` folder into your Abyss web server root.
Example path:  `C:\Abyss Web Server\htdocs\drugs4u\`

---

### 2. Create the Database
Open **MySQL Workbench** or the MySQL CLI and run:

```sql
SOURCE /path/to/drugs4u/database.sql;
```

This creates the `drugs4u` database, all tables, and loads the seed data.

---

### 3. Configure Database Connection
Edit `includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // your MySQL username
define('DB_PASS', '');          // your MySQL password
define('DB_NAME', 'drugs4u');
define('DB_PORT', 3306);
```

---

### 4. Initialise Passwords
Open your browser and visit:
```
http://localhost/drugs4u/init_passwords.php
```
This hashes the demo passwords correctly. **Delete `init_passwords.php` after running.**

---

### 5. Login
Visit: `http://localhost/drugs4u/`

| Role        | Email                        | Password  |
|-------------|------------------------------|-----------|
| Admin       | admin@drugs4u.co.uk          | admin123  |
| Pharmacist  | chamath@drugs4u.co.uk        | pharm123  |
| Staff       | nadeeshan@drugs4u.co.uk      | staff123  |

---

### 6. File Structure
```
drugs4u/
├── index.php              ← Login page
├── app.php                ← Main application shell
├── auth.php               ← Login/logout handler
├── init_passwords.php     ← One-time password setup (delete after use)
├── database.sql           ← MySQL schema + seed data
├── includes/
│   ├── config.php         ← DB credentials & constants
│   └── db.php             ← MySQLi wrapper helpers
└── pages/
    ├── dashboard.php      ← Dashboard with stats & chart
    ├── customers.php      ← Customer management (CRUD)
    ├── prescriptions.php  ← Prescription management (CRUD + risk detection)
    ├── inventory.php      ← Medicine/inventory management (CRUD + restock)
    ├── risks.php          ← Risk alerts (resolve/delete/add)
    └── reports.php        ← 6 printable report types
```

---

### Features
- **Dashboard**: Live stats, 7-day prescription chart, active alert sidebar
- **Customers**: Add/Edit/Delete/View with full medical profile & prescription history
- **Prescriptions**: Full CRUD, auto allergy-conflict detection, auto ID-check flagging, auto low-stock risk creation, quick status change
- **Inventory**: Add/Edit/Delete medicines, restock function, stock level indicators, expiry tracking
- **Risk Alerts**: Automatically generated; resolve/unresolve/delete; manual add; filter by level
- **Reports**: Daily summary, prescriptions, customers, inventory, low stock, risk alerts — all printable

---

### Requirements
- PHP 8.1+
- MySQL 9.2 (or 8.x compatible)
- Abyss Web Server (or any PHP-capable server)
- `mysqli` PHP extension enabled
