# SHIORI Café - Troubleshooting Guide

This guide addresses common issues encountered when configuring and running the SHIORI Café system locally.

## 1. Database Connection Errors
* **Symptom:** The website shows a "Database connection failure" or a white screen.
* **Solution:** 
  * Ensure the PostgreSQL service is running on your machine.
  * Verify that you are connecting to port **5173** (our custom port), not the PostgreSQL default of 5432.
  * Check your `.env` file to ensure `DB_USER` and `DB_PASSWORD` are correct.
  * Ensure the PHP extensions `pdo_pgsql` and `pgsql` are uncommented in your `php.ini` file and Apache has been restarted.

## 2. Common Login Problems
* **Symptom:** Unable to log into the staff or customer dashboard.
* **Solution:**
  * Verify that you are using the correct credentials from the `seed.sql` file. 
  * For staff, ensure you are navigating to the `/admin/login.php` route, not the customer login page.
  * If sessions are failing, clear your browser cookies or ensure PHP sessions are configured correctly in your `php.ini`.

## 3. Missing Menu Items
* **Symptom:** The digital menu is blank or missing expected food and drinks.
* **Solution:**
  * Confirm that the `seed.sql` file was executed successfully in your PostgreSQL database.
  * Run `SELECT * FROM shioricafe_schema.menu_item;` in your pgAdmin query tool to verify the items exist in the database.
  * Ensure the `is_available` boolean flag for the missing items is set to `TRUE`.

## 4. Blank Pages or 404 Errors for Scripts
* **Symptom:** A page loads, but the styling is broken or functionality fails.
* **Solution:**
  * Check the browser console (F12) for JavaScript path errors. Ensure the application is being accessed via `http://localhost/SHIORIcafe-new/` and not directly from the file explorer.