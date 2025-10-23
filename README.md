# Renty

Modern Laravel 12 + Vue 3 starter that tracks tenant rent payments, verification reports, and credit score progress. The backend exposes Sanctum-secured APIs with dedicated service, event, and facade layers; the frontend uses Vite and Tailwind for the dashboard experience.

## Requirements
- PHP 8.2+ with required extensions (`pdo`, `mbstring`, `openssl`, `curl`, `json`)
- Composer 2
- Node.js 18+ and npm 9+
- A database connection (MySQL/MariaDB or SQLite are supported out of the box)
- npm-compatible package manager for the frontend (npm is assumed below)

## Local Development
1. **Clone & install dependencies**
   ```bash
   git clone <repo-url>
   cd renty
   composer install
   npm install
   ```
2. **Configure the environment**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update database credentials or set `DB_CONNECTION=sqlite` and create `database/database.sqlite`.

3. **Run database migrations**
   ```bash
   php artisan migrate
   ```

4. **Start the application**
   ```bash
   # Terminal 1 – backend
   php artisan serve

   # Terminal 2 – frontend/Vite dev server
   npm run dev
   ```
   Visit `http://127.0.0.1:8000` (Laravel) and Vite will proxy the SPA assets.

## Available Scripts
- `php artisan migrate:fresh --seed` – rebuild the database from scratch.
- `php artisan test` – run the backend test suite.
- `npm run dev` – start the Vite development server with HMR.
- `npm run build` – generate production assets.
- `npm run lint` / `npm run lint:fix` – ESLint with Vue + Prettier integration.
- `npm run format` / `npm run format:write` – enforce Prettier formatting.

## API Highlights
- All API responses use the `Response::api()` macro, returning `{ data, meta.served_at }`.
- Rent payment flows live behind the `RentPayments` facade (`app/Facades/RentPayments.php`) which delegates to `RentPaymentService`.
  - `POST /api/rent-payments` records a payment and increments on-time totals.
  - `POST /api/rent-payments/{rentPayment}/verify` finalises reporting, emits domain events, and updates stats.
  - `GET /api/dashboard` and `GET /api/rent-payments` share the same payload shape used by the Vue dashboard.
- `RentPaymentCreated` and `RentPaymentVerified` events trigger listeners for logging and future integrations.

## Project Structure Notes
- `app/Services` contains service classes for rent payments and payment stats, centralising transactional logic.
- `app/Events` & `app/Listeners` provide extension points for audit trails, notifications, or async processing.
- `resources/js/views/Dashboard.vue` showcases how the SPA consumes the API and manages verification states.

## Next Steps
- Configure queue workers or schedulers if you add asynchronous event listeners.
- Extend documentation/API specs using the details above as a starting point.
- Consider Docker or Sail definitions if you need reproducible infrastructure.
