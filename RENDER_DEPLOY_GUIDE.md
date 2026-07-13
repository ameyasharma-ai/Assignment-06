# Render.io Free Deployment Guide for OmniMart

This guide walks you through deploying the OmniMart e-commerce app to **Render.com** for free.

---

> [!CAUTION]
> **CRITICAL DATA STORAGE WARNING:**
> Render's **Free Tier Web Services do NOT support persistent storage disks**.
> If you deploy using the default **SQLite** database (`omnimart.db`), **all your data (users, products, orders) will be completely wiped out** every time the server restarts or spins down due to inactivity (which happens automatically after 15 minutes of no web traffic).
>
> **The Solution:** To keep your data safe, you must toggle the application to **MySQL** mode in `db.php` and connect it to a free external cloud MySQL database.

---

## Prerequisites
1. A Render account. Sign up for free at [render.com](https://render.com/).
2. A free cloud MySQL database. You can get one from:
   *   **Aiven.io** (Recommended: provides a free MySQL service with 5GB storage).
   *   **Clever Cloud** (Free MySQL database up to 10MB).
3. A GitHub account.

---

## Step 1: Configure your Free Cloud MySQL Database
1. Go to [Aiven.io](https://aiven.io/) or [Clever Cloud](https://www.clever-cloud.com/) and create a free account.
2. Spin up a new **MySQL** database instance.
3. Once active, retrieve the connection credentials:
   *   **Host / Server** (e.g., `mysql-xxxxx.aivencloud.com`)
   *   **Port** (e.g., `12345` or `3306`)
   *   **Database Name**
   *   **User**
   *   **Password**

---

## Step 2: Update `db.php`
Open your local [db.php](file:///c:/Users/Ameya%20Sharma/Downloads/Assignment-06/db.php) file and update the configurations to use your new remote MySQL database:

```php
define('DB_TYPE', 'mysql'); // Toggle from 'sqlite' to 'mysql'
define('DB_HOST', 'your-remote-host-address');
define('DB_NAME', 'your-database-name');
define('DB_USER', 'your-database-user');
define('DB_PASS', 'your-database-password');
```
*Note: If your database uses a custom port (e.g., Aiven databases), append the port to your host string in `db.php`:*
`define('DB_HOST', 'mysql-xxxxx.aivencloud.com:12345');`

---

## Step 3: Push Code to GitHub
Because Render deploys directly by pulling from GitHub, you must push your project to a GitHub repository:
1. Create a new repository on GitHub (make it Private if you want to protect your credentials).
2. Initialize git and push your project:
   ```bash
   git init
   git add .
   git commit -m "Initialize OmniMart for Render deployment"
   git branch -M main
   git remote add origin <your-github-repo-url>
   git push -u origin main
   ```

---

## Step 4: Deploy on Render
1. Log in to the [Render Dashboard](https://dashboard.render.com/).
2. Click **New +** and select **Web Service**.
3. Connect your GitHub account and select your `Assignment-06` repository.
4. Configure the Web Service settings:
   *   **Name:** `omnimart`
   *   **Region:** Select one close to you.
   *   **Branch:** `main`
   *   **Runtime:** **Docker** (Render will automatically detect our `Dockerfile` and build it).
   *   **Instance Type:** **Free** ($0/month).
5. Click **Create Web Service**.

---

## Step 5: Initialize the Database
1. Wait for Render to build the Docker image and deploy the container (this takes 2-4 minutes).
2. Once the status shows **Live**, click on your unique Render site URL (e.g., `https://omnimart.onrender.com`).
3. To build the database tables and insert the seed data, visit your URL adding `/init_db.php` to the path:
   `https://<your-app-name>.onrender.com/init_db.php`
4. Once initialized, your shop is ready!

> [!WARNING]
> Delete the `init_db.php` file from your GitHub repository and redeploy once database setup is complete, to prevent public visitors from resetting your database!
