# SubHub v6.3 - Automated Subscription Platform

Welcome to SubHub, a premium digital platform for selling and managing subscriptions.

## 🚀 Setup Instructions

Follow these steps to get the application running on your cPanel server.

1.  **Upload Files:**
    * Upload the entire `subhub_v6.3` directory to your `public_html` folder.

2.  **Create a Database:**
    * In cPanel, go to "MySQL® Database Wizard".
    * Create a new database (e.g., `user_subhub`).
    * Create a new database user (e.g., `user_subhub_u`) and generate a strong password.
    * Add the user to the database and grant **ALL PRIVILEGES**.
    * Note down the Database Name, Database User, and Password.

3.  **Run the Installer:**
    * Open your browser and navigate to the installer:
        `https://yourdomain.com/subhub_v6.3/install.php`
    * Fill in the form with your `localhost`, Database Name, User, and Password.
    * For "Site URL", enter the full URL: `https://yourdomain.com/subhub_v6.3`
    * Click "Install Now".

4.  **🚨 IMPORTANT: Delete Installer 🚨**
    * After successful installation, go back to cPanel File Manager and **DELETE** the `install.php` file immediately.

5.  **Set Up Cron Job:**
    * This is required for automatically marking subscriptions as "Expired".
    * In cPanel, go to "Cron Jobs".
    * Under "Common Settings", select **"Once per 15 minutes"**.
    * In the "Command" field, enter the following command (replace `YOUR_CPANEL_USER` with your actual cPanel username):

    ```bash
    /usr/local/bin/php /home/YOUR_CPANEL_USER/public_html/subhub_v6.3/includes/cron/expire_subscriptions.php
    ```
    * Click "Add New Cron Job".

6.  **Set Folder Permissions:**
    * Ensure the following folders are writable by the server so screenshots can be uploaded.
    * In File Manager, right-click these folders, select "Change Permissions", and set them to `755`.
        * `/assets/uploads/`
        * `/assets/logs/`

## 🔐 Default Admin Login

* **URL:** `https://yourdomain.com/subhub_v6.3/login.php`
* **Username:** `admin`
* **Password:** `123456`

You will be forced to change this password immediately upon your first login.

## 📂 File Structure

* `/admin/` — All admin modules
* `/user/` — User dashboard and storefront
* `/includes/` — Core logic, DB, classes, and cron jobs
* `/assets/` — CSS, JS, images, and user uploads
* `/install.php` — **(Must be deleted after setup)**