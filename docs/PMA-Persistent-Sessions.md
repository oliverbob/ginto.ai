# Composer & phpMyAdmin Dependency Management

## Best Practices for Composer Projects

- **Do NOT commit the `vendor/` directory** to git. Only commit `composer.json` and `composer.lock`.
- After cloning or pulling the repository, always run:
  ```sh
  composer install
  ```
  in your project root to install all PHP dependencies.
- If you use phpMyAdmin as a Composer dependency (in `vendor/phpmyadmin/phpmyadmin`), also run:
  ```sh
  cd vendor/phpmyadmin/phpmyadmin
  composer install
  cd ../../../..
  ```
- This ensures all dependencies are installed as specified in the lock files, making the environment consistent across all machines.

## Why Not Commit `vendor/`?

- The `vendor/` directory can be very large and may contain platform-specific files.
- Composer is designed to generate `vendor/` from `composer.json` and `composer.lock`.
- Committing `vendor/` can cause merge conflicts, bloat your repository, and is not recommended by the PHP community.

## Deployment/Remote Setup

- After pulling from git on any machine (including production), always run `composer install` in both the project root and in `vendor/phpmyadmin/phpmyadmin` if needed.
- You can automate this step in deployment scripts or CI/CD pipelines.

**Summary:**
> Commit only `composer.json` and `composer.lock`. Never commit `vendor/`. Always run `composer install` after pulling.
# phpMyAdmin persistent sessions (development only)

What changed
- The project includes a development convenience that makes phpMyAdmin sessions persistent (long cookie and PHP session lifetimes) and provides an optional auto-login mode for local development.

Files touched
- `public/pma/index.php`
- `public/pma/debug_session.php`
- `vendor/phpmyadmin/phpmyadmin/config.inc.php`

Enable auto-login (dev only)
- Set the environment variable `PMA_AUTOCONFIG=1` before starting your PHP server.

  PowerShell (temporary for current session):

  ```powershell
  $env:PMA_AUTOCONFIG = '1'
  php -S 127.0.0.1:8082 -t public
  ```

  WSL / Linux shell:

  ```bash
  export PMA_AUTOCONFIG=1
  php -S 127.0.0.1:8082 -t public
  ```

Security warning
- These persistent-session and auto-login settings are intended for local development only. Do NOT enable them on public or production servers. Persistent cookies increase risk if your machine is shared or accessible remotely.

Testing logout
- Clear browser cookies or open an incognito/private window, visit `/pma`, log in, then visit `/pma?logout=1` — this should clear phpMyAdmin cookies and end the session.

Next steps / recommendations
- Move any development credentials out of the vendor `config.inc.php` and load them from environment variables to avoid modifying vendor files.
- If you want, I can update the top-level `README.md` to reference this document instead of editing the main file directly.
