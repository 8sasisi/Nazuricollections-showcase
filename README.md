# Grant Fashion

Grant Fashion is a responsive fashion showcase and content-management system. The public website uses HTML, CSS, Bootstrap 5, and vanilla JavaScript; PHP 8.3+ and MySQL 8 provide the public content API and the secured **Usimamizi** administrator dashboard.

The site is not an e-commerce platform: it has no customer accounts, cart, checkout, order processing, or payment features. Product photographs, product details, prices, and business contact information remain empty until an administrator enters verified information.

## Features

- Homepage, Abayas, Magauni / Gowns, About, and Contact pages.
- Public APIs for published products, pages, contact settings, and approved images.
- Usimamizi dashboard with product and collection editing, photograph uploads, homepage/page copy, contact settings, administrator roles, and activity history.
- Administrator login with Argon2id password hashes, session security, CSRF protection, progressive login throttling, optional TOTP 2FA, one-time recovery codes, and password reset codes.
- MySQL schema migration in `database/migrations/001_initial_schema.sql`.

## Requirements

- WampServer with Apache 2.4, PHP 8.3+, and MySQL 8 (or a compatible current MariaDB release).
- PHP extensions: PDO MySQL, GD, Fileinfo, and Sodium.
- Apache `mod_rewrite` and `mod_headers`.
- `AllowOverride All` for the project directory so its `.htaccess` protections are active.

PHP and JavaScript syntax, plus the built-in security-helper checks, can be verified without a database:

```powershell
php tests\run.php
Get-ChildItem -Recurse -File -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets\js\main.js
node --check assets\js\admin.js
```

## Windows / WampServer setup

1. Place the project in a WampServer Apache directory, for example `C:\wamp64\www\grant-fashion`. Configure an Apache virtual host with this directory as its `DocumentRoot` and allow `.htaccess` overrides. Keep the project's `.htaccess`; it denies direct HTTP access to application code, SQL files, storage, documentation, and local configuration files.
2. Enable `pdo_mysql`, `gd`, `fileinfo`, and `sodium` for the PHP version used by Apache. Set `upload_max_filesize=5M` and `post_max_size=6M` in `php.ini`, then restart Apache.
3. In MySQL, create a database and a dedicated, least-privilege application user. For example, connect to MySQL as a database administrator and run the following after choosing a unique password:

   ```sql
   CREATE DATABASE grant_fashion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'grant_fashion_app'@'localhost' IDENTIFIED BY 'REPLACE_WITH_A_UNIQUE_PASSWORD';
   GRANT SELECT, INSERT, UPDATE, DELETE
       ON grant_fashion.* TO 'grant_fashion_app'@'localhost';
   FLUSH PRIVILEGES;
   ```

   Use a unique database password, not the example text. The application account is only for runtime queries; import the initial schema using an administrative MySQL account:

   ```powershell
   mysql -u root -p grant_fashion < database\migrations\001_initial_schema.sql
   ```

   The migration creates roles and permissions, products and media, editable content, administrator accounts, security events, rate-limit data, and reset-token storage. It inserts no administrator credentials.
4. Create a private configuration file **outside the project directory and outside the web root**, for example `C:\wamp64\private\grant-fashion.env`. Start from `.env.example`, replace the database values, and generate a random encryption key:

   ```powershell
   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```

   Use the generated base64 value for `TOTP_ENCRYPTION_KEY`; it must decode to exactly 32 random bytes. Set `APP_ENV=production` only after HTTPS is enabled. Never put real secrets in a file under the project or commit them.
5. Tell Apache where to find that private file in the virtual-host configuration, then restart Apache:

   ```apache
   SetEnv APP_CONFIG_FILE "C:/wamp64/private/grant-fashion.env"
   ```

   For command-line setup, set the same `APP_CONFIG_FILE` environment variable in the PowerShell process that runs the commands below. The application rejects a configuration file located inside the project directory.
6. Create the first super administrator from PowerShell. Use a password at least 14 characters long with uppercase, lowercase, and a digit. The prompt below does not echo the password:

   ```powershell
   $env:APP_CONFIG_FILE = 'C:\wamp64\private\grant-fashion.env'
   $secret = Read-Host 'Initial super administrator password' -AsSecureString
   $env:ADMIN_PASSWORD = [Net.NetworkCredential]::new('', $secret).Password
   php scripts\admin.php create admin@example.com 'Grant Fashion Administrator' super_admin
   Remove-Item Env:\ADMIN_PASSWORD
   ```

   Replace the placeholder email and display name with the authorized administrator's details. Do not retain, publish, or reuse the initial password. The script refuses to create the first user with any role other than Super Administrator.
7. Open the configured virtual host. Public pages use the site at `/`; sign in to Usimamizi at `/admin/login.php`. Add verified contact information and genuine product details and photographs in the dashboard. Prices remain hidden on the public site unless an ISO 4217 currency code is also configured.
8. Enable HTTPS before production and verify Apache headers, `.htaccess` restrictions, uploads, and backups from outside the server. PHP's built-in development server does not enforce `.htaccess` and must not be used to serve the project as a substitute for the protected Apache setup.

## Administrator recovery

If an administrator loses access, an authorized server operator can issue a short-lived reset code locally. Set `APP_CONFIG_FILE` in the same PowerShell session, then run:

```powershell
php scripts\admin.php issue-reset admin@example.com
```

Deliver the one-time code to the verified administrator through a separate secure channel. It expires after 30 minutes and is stored only as a hash. The administrator enters it at `/admin/reset-password.php`; reset codes must never be placed in URLs or logged.

## Backups and recovery

Schedule encrypted database backups and a separate backup of `storage\media` using a protected backup account and access-controlled destination. Keep backup copies off the web server and define a retention policy appropriate to the business. Use MySQL's `mysqldump` or WampServer's supported database backup tooling; protect dump files because they contain business content and administrator hashes.

Example PowerShell database export (the password is requested interactively by MySQL):

```powershell
mysqldump -u grant_fashion_backup -p --single-transaction --routines --triggers grant_fashion > C:\wamp64\private\backups\grant_fashion.sql
```

Restore into a clean database with an administrative account, then restore `storage\media` from the matching backup and verify file permissions and application configuration. Before deploying a release, retain the previous release and database backup; on a failed deployment, restore the previous release and the matching database/media backup. For suspected administrator compromise, disable the affected account, issue a reset code, revoke its sessions by changing `auth_version` (or disable/re-enable the account), rotate affected secrets, review the audit log, and investigate the host before restoring service.

Application logs are written under `storage\logs` and are not publicly served when Apache protections are active. Configure server-side log rotation and monitor available disk space; do not expose log files or database dumps. Apply OS, Apache, PHP, and MySQL security updates. Use HTTPS, restricted server administration, firewall rules, and reputable WAF/DDoS mitigation at the infrastructure or hosting-provider level. Application code alone cannot guarantee availability or prevent denial-of-service attacks.

## Security notes and limitations

- Configure production Apache with HTTPS, the supplied security headers, private credentials, least-privilege database access, and protected backups. Review the actual deployed virtual host and permissions; repository configuration cannot verify hosting setup.
- TOTP is available to each administrator in **Contact settings** and should be enabled for every account. Encrypted TOTP secret storage depends on keeping `TOTP_ENCRYPTION_KEY` secret and backed up. Recovery codes are displayed once at enrollment.
- Password reset issuance is intentionally a server-operator CLI action because no mail delivery service or verified administrator email domain was supplied.
- The project contains no genuine Grant Fashion product photographs, prices, phone number, WhatsApp number, social profiles, or confirmed currency. Do not invent those details.
- Bootstrap, Bootstrap Icons, and web fonts are loaded from their existing CDNs; a network connection is required for those enhancements.
- This initial setup has not been independently penetration-tested or validated on a production host. Do not describe the deployment as production-ready until infrastructure configuration, MySQL installation, HTTPS, account setup, backup restoration, and operational monitoring are verified.
