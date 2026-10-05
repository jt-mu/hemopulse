# HemoPulse on Render

HemoPulse uses PHP and MariaDB. Select **Web Service → Docker**, not Node, Python or Static Site. The root Dockerfile runs Apache with PHP 8.2, PDO MySQL and mbstring, and listens on Render's `PORT`.

## Free demo limitations

Render Free sleeps after 15 minutes of inactivity. Its local filesystem is disposable: profile uploads, sessions and email previews disappear on restarts, redeploys and sleep. Do not store MariaDB inside this web container. Render's free PostgreSQL database is incompatible with the current schema and migrations. Supply a separate, durable MariaDB 10.4+ database with remote connectivity.

Standard outbound SMTP ports 25, 465 and 587 are blocked on Free. Default preview mode sends no emails, so public signup OTPs and newsletter confirmations cannot be completed from an inbox. Create demo donor/staff accounts through the administrator until a compatible mail service is configured. An HTTPS email API would require an additional code change; it is not implemented here. Use only fictional records for the demo.

Official references: https://render.com/docs/docker and https://render.com/docs/free.

## Before creating the service

1. Upload the current clean source to the chosen GitHub repository. Exclude local uploads, mail previews, credentials and personal database exports. `.dockerignore` excludes these from Docker builds; it does not prevent publishing them to GitHub. Review GitHub contents separately.
2. Provision an empty dedicated MariaDB database. Keep it reachable from Render, require verified TLS for connections over the public internet, and use the provider's connection information. Do not point Render at your XAMPP database.
3. Import `deployment/schema.sql` using the database provider's import tool, or use the explicitly enabled empty-database initialization below.

## Render settings

| Setting | Value |
| --- | --- |
| Repository | `https://github.com/jt-mu/hemopulse` |
| Language / runtime | Docker |
| Root directory | Blank when Dockerfile is in repository root |
| Dockerfile path | `./Dockerfile` |
| Docker command | Leave blank; the Dockerfile supplies it |
| Build / start commands | Do not use `npm install`, `npm start` or Python commands |
| Instance type | Free for a demo |
| Health check path | `/api/index.php/campaigns` |

Set these **runtime environment variables in Render**, never in GitHub:

| Variable | Value |
| --- | --- |
| `HEMOPULSE_DB_HOST` | MariaDB provider hostname |
| `HEMOPULSE_DB_PORT` | Provider port, usually `3306` |
| `HEMOPULSE_DB_NAME` | Dedicated database name |
| `HEMOPULSE_DB_USER` | Database user |
| `HEMOPULSE_DB_PASS` | Database password |
| `HEMOPULSE_DB_SSL_CA` | `/etc/secrets/mariadb-ca.pem` when provider requires a CA certificate; upload that certificate as a Render secret file |
| `HEMOPULSE_APP_URL` | Exact `https://YOUR-SERVICE.onrender.com`, without `/hemopulse` when deployed at root |
| `MAIL_MODE` | `preview` until real delivery is configured |
| `HEMOPULSE_RUN_SETUP` | `1` to run setup at container startup |
| `HEMOPULSE_INIT_EMPTY_DB` | `1` only to import the clean schema into an empty dedicated database |
| `HEMOPULSE_ADMIN_EMAIL` | First administrator email, set only during first setup |
| `HEMOPULSE_ADMIN_PASSWORD` | Strong temporary setup secret, set only during first setup |

The startup script serializes setup using a MariaDB advisory lock, imports the schema only into a completely empty database when explicitly allowed, then applies repeatable migrations. It preserves an existing active administrator. A failed setup prevents Apache from starting. The first setup user needs schema privileges. After success, remove the administrator credentials and initialization flag, set `HEMOPULSE_RUN_SETUP=0`, and switch to a database user with normal runtime query privileges. Temporarily use migration credentials and setup mode again for future schema upgrades after a backup.

The image stores private files under `/var/lib/hemopulse`, outside the web root. On a paid instance, a persistent disk can mount there. On Free those files remain temporary. Apache honors deny rules and uses Render's forwarded HTTPS header for secure session cookies. This proxy configuration is specifically for Render's trusted ingress.

## Verify after deployment

- `/api/index.php/campaigns` returns JSON and the home page loads images and fonts.
- Config, database, scripts, deployment, vendor and dotfiles return 403/404 over HTTP.
- Administrator login, account creation, donor login, screening, registration, staff confirmation, notifications and cancellation work.
- Session cookies are Secure and HttpOnly over HTTPS.
- Profile uploads work during the running instance; document their temporary nature on Free.
- No claim of inbox delivery is made while mail mode is preview.
- Database records survive a service redeploy because the database is external.

The local Docker image must still be built and checked on a Docker-enabled machine or Render. A successful local PHP test suite does not validate the container, external database or Render configuration.
