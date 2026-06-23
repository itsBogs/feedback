# 📊 Feedback & Insights Management System

A robust backend-driven system developed to bridge the communication gap between users and administrators. It features a comprehensive feedback loop, automated status tracking, and analytical dashboards.

## 📸 App Preview

![Feedback System Preview](https://github.com/itsBogs/feedback/blob/e335e1f2c1f355a090fdc438df6390c94add4e18/uploads/front.jpg)
![Feedback System Preview](https://github.com/itsBogs/feedback/blob/e335e1f2c1f355a090fdc438df6390c94add4e18/uploads/admin.jpg)
![Feedback System Preview](https://github.com/itsBogs/feedback/blob/e335e1f2c1f355a090fdc438df6390c94add4e18/uploads/feed.jpg)

---

## 💡 Key Highlights
* **Data Integrity:** Implemented structured database relationships to ensure accurate tracking of feedback history.
* **Efficient Admin Workflow:** Centralized dashboard for prioritizing and resolving user concerns.
* **Modern UI/UX:** Clean, accessible interface for seamless feedback submission.

---

## 🛠️ Functional Modules

### 1. User Engagement Module
* **Smart Submission:** Users can categorize their concerns for faster routing to the appropriate department.
* **Activity History:** Users can monitor the status of their previously submitted feedback.

### 2. Administrator Command Center
* **Live Monitoring:** Real-time summary of total feedback, resolved issues, and pending requests.
* **Categorical Management:** Tools for organizing feedback by priority (Low, Medium, High).
* **Audit Trail:** Maintain records of feedback timestamps and resolution logs.

---

## ⚙️ Technical Architecture

* **Development Paradigm:** MVC Pattern (Model-View-Controller) for clean separation of concerns.
* **Server-Side Engine:** PHP (Custom routing and secure session management).
* **Database Layer:** MySQL with optimized indexing for fast data retrieval.
* **Frontend Framework:** JavaScript (AJAX for asynchronous data updates without page reloads).

---

## 🚀 Deployment Instructions

### Prerequisites
1. Download and install [XAMPP](https://www.apachefriends.org/).

### Steps
1. **Set up the Database:**
   * Open XAMPP Control Panel and start **Apache** and **MySQL**.
   * Open your web browser and navigate to `http://localhost/phpmyadmin/`.
   * Create a database, select the **SQL** tab, paste your `database.sql` script, and click **Go**.

2. **Run the Project:**
   * Clone this repository into your `htdocs` folder.
   * Update `config.php` with your database credentials.
   * Open your browser and type the local URL link:
```text
     http://localhost/[YOUR_PROJECT_FOLDER_NAME]/index.php
     ```

---

## 🛡️ Security Features
* **Password Hashing:** Uses BCRYPT for secure user authentication.
* **Data Protection:** Prevention of SQL Injection using Prepared Statements.
* **Session Management:** Enforced session timeouts for administrative accounts.

---

## 🧪 Default Test Accounts

| Role | Email | Password |
| :--- | :--- | :--- |
| **Admin** | `admin123` |
| **User** | `NONE` | `NONE` |

---
*Prepared by BOGS*
