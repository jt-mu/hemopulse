# HemoPulse group demo setup

This package intentionally contains no user accounts, appointments, medical records, messages, newsletter subscriptions, uploaded profile photos, email previews, or test databases.

1. Copy the extracted `hemopulse` folder to `C:\xampp\htdocs`.
2. Start Apache and MySQL in XAMPP.
3. Create an empty `hemopulse_db` database in phpMyAdmin and import `deployment/schema.sql`.
4. In PowerShell, from the project folder, run:

   ```powershell
   & C:\xampp\php\php.exe database\migrate.php
   & C:\xampp\php\php.exe scripts\seed_campaigns.php
   ```

The second command adds only the three clearly labelled fictional `(DEMO)` campaigns. It creates no accounts or registrations. Each group member can register their own donor account and an administrator can create Staff accounts through the workspace.

Do not import a database copied from another group member, because it could include their personal accounts or project records.
