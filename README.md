# NexLoyal

NexLoyal is a Shopify-focused loyalty platform built on Laravel. It combines a web-based admin panel with an embeddable storefront widget to help stores reward customers, manage points, and deliver targeted engagement. The system integrates directly with Shopify webhooks and discount APIs so that loyalty activity stays aligned with the authoritative Shopify data model.

## Feature Summary

The platform provides a full loyalty workflow, including a points ledger with pending and approved states, coupon rewards tied to Shopify price rules, a tier system for eligibility control, and a mystery box reward mechanic. It also includes an exclusive chat module with polls and image attachments, and a configurable rule engine for welcome, birthday, profile completion, and social engagement rewards.

## Technology Stack

The backend uses Laravel 12 with PHP 8.2 and a relational database (MySQL in production, SQLite in testing). The admin interface is primarily rendered with Blade templates and Tailwind CSS, while authentication and certain settings views use Inertia.js with React and shadcn UI components. Shopify Admin APIs and webhooks are used for customer synchronization, order-based points, and discount code generation. Widget authentication uses encrypted tokens issued by Laravel rather than JWT.

## High-Level Architecture (Textual Diagram)

Shopify emits customer and order webhooks into Laravel endpoints, where HMAC validation and idempotent ledger updates are applied. Store admins access the web UI to manage points rules, tiers, coupons, mystery boxes, and chat content. Customers interact through a Shopify theme widget that calls token, data, and reward endpoints hosted by the same Laravel app. All modules read and write to a shared relational database that serves as the system of record for loyalty activity.

## Running the Project

1. Configure your environment by copying `.env.example` to `.env` and updating database and Shopify credentials.
2. Install PHP dependencies with `composer install` and JavaScript dependencies with `npm install`.
3. Generate the application key using `php artisan key:generate`.
4. Run migrations with `php artisan migrate`.
5. Link public storage for chat attachments using `php artisan storage:link`.
6. Start the development server with `php artisan serve` and the frontend build with `npm run dev`.

For a combined development workflow, the `composer run dev` script starts the server, queue listener, and Vite in parallel.

## Documentation

The full architecture documentation is available at `docs/ARCHITECTURE.md`. A Word version is provided as `NexLoyal_System_Architecture.docx` in the project root.

## Development and Deployment Notes

- Shopify webhook endpoints require `SHOPIFY_WEBHOOK_SECRET` and a configured `SHOPIFY_SHOP_DOMAIN`.
- Coupon activation relies on `SHOPIFY_ADMIN_TOKEN` and API access to Shopify price rules.
- The widget snippet for Shopify themes is stored in `docs/shopify-widget.liquid`.
- The system uses Laravel Fortify for authentication, email verification, and two-factor support.
