# 360Five Immigration API

## Admin sign-in

The React country admin authenticates against Laravel using its session guard.
Admin access requires a user with `is_admin = true`; newly registered users are
not administrators.

1. Configure the database and `APP_KEY` in `.env`.
2. Set `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD` in `.env`. The admin
   password must be at least 12 characters. Do not commit these values.
3. Run `php artisan migrate` and `php artisan db:seed --class=AdminUserSeeder`.
   The seeder creates or updates the configured admin account. Running it again
   also updates that account's password from `ADMIN_PASSWORD`.
4. For local development, run Laravel at `http://127.0.0.1:8000` and the React
   app with Vite. Vite proxies `/api` requests to Laravel so the browser uses
   one origin for both the session cookie and CSRF token. Do not set
   `VITE_API_BASE_URL` to a different host for local sign-in; if you change the
   Laravel port, set `VITE_API_PROXY_TARGET` in the React app instead.
5. After changing Laravel `.env` or CORS configuration, run
   `php artisan optimize:clear` and restart Laravel. Restart the Vite server
   after changing its proxy configuration.

The country list remains publicly readable. Creating, updating, and deleting
countries requires an authenticated administrator session. Authentication uses
HTTP-only session cookies and Laravel's CSRF protection. In local development,
the Vite proxy keeps frontend requests on the same browser origin. When
frontend and API are on different sites in production, serve both over HTTPS and
configure `SESSION_SAME_SITE=none` and `SESSION_SECURE_COOKIE=true` (and set
`SESSION_DOMAIN` if the cookie must be shared by subdomains).

### Managing users with Postman

- Create a regular account with `POST /api/auth/register` and JSON fields
  `name`, `email`, `password`, and `password_confirmation`. A successful
  response is `201 Created` and includes the new user's safe public fields.
  This endpoint never accepts an admin flag.
- List users with `GET /api/users`. This is administrator-only and returns
  paginated `id`, `name`, `email`, and `created_at` fields; it never returns
  passwords. Sign in as the configured admin first using the session and CSRF
  steps described below.
- Create an admin with `POST /api/admins`, update one with
  `PUT /api/admins/{id}`, and remove one with `DELETE /api/admins/{id}`. These
  endpoints require an existing administrator session. The admin panel at
  `/admin` also lists admin accounts and provides add, edit, and remove actions,
  so using Postman is optional. An admin cannot remove their own account.

To create an admin through Postman, first sign in to the admin account as
described below. Then send `POST /api/admins` with the same session cookie and
CSRF header, using JSON fields `name`, `email`, `password`, and
`password_confirmation`. Passwords must be at least 12 characters.

For session-authenticated requests from Postman, call `GET /api/auth/csrf`
first, retain the cookies, and send the returned `csrf_token` value in the
`X-CSRF-TOKEN` header on `POST /api/auth/login` and other non-GET requests.
Login accepts JSON `email` and `password`; the admin session cookie is then
used for `GET /api/users`.
