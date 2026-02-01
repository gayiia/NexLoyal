<!-- This document explains the system architecture so readers can understand how the project is structured and why key design choices were made. -->
# NexLoyal System Architecture

## 1. Introduction

NexLoyal is a Shopify-focused loyalty platform implemented as a Laravel web application with an embedded storefront widget. The platform serves two primary user groups: store owners who manage loyalty programs in the admin panel, and customers who interact with points, rewards, and engagement features through the embedded widget. The backend aggregates data from Shopify webhooks, manages a points ledger, and issues coupon rewards that are synchronized with Shopify discount codes. The widget provides customers with a compact, high-availability interface that can be embedded directly inside Shopify themes.

The purpose of the platform is to increase repeat purchases and customer engagement by translating purchase behavior and profile actions into loyalty points, and by offering tiered rewards and lightweight engagement channels. The system is designed to be installed with minimal Shopify theme changes, while the administration interface exposes operational controls for point rules, tier definitions, coupon lifecycle management, and engagement content such as exclusive chat and polls.

The high-level business goals reflected in the codebase are to provide a reliable, measurable loyalty layer for Shopify stores, to maintain a strong audit trail of points activity, and to offer configurable incentives that can be linked directly to Shopify discount infrastructure. These goals are expressed in the architecture through a points ledger model, webhook-driven updates, and a coupon system that mirrors Shopify price rule and discount code capabilities.

## 2. Architectural Goals and Principles

Scalability is addressed through a normalized data model and idempotent event processing. The system uses database-level constraints and unique event keys to prevent duplicated point transactions, which allows the platform to accept repeated webhook deliveries without inflating balances. This supports vertical scaling on a single database and makes horizontal scaling feasible because the critical invariants are enforced at the database layer rather than by in-memory logic.

Extensibility is provided by keeping business rules in model-centric data structures and dedicated services. Point rules are stored in a separate table and resolved at runtime; tier definitions are data-driven and can be updated from the admin panel. This approach allows operators to adjust program behavior without code changes and enables future modules to reuse the same points ledger and tier system without restructuring the data model.

Security is enforced through multiple layers. Webhook events are validated using Shopify HMAC headers, preventing spoofed payloads. Widget access is controlled through an encrypted token issued only after a Shopify API validation. Admin access requires authenticated and verified users and uses Fortify's rate limiting and two-factor authentication features. These measures were chosen to align with Shopify integration requirements and to minimize exposure of customer data.

Maintainability is achieved by leveraging Laravel's standard conventions and by keeping controllers focused on specific modules. Migrations capture schema evolution, and tests validate key transactional flows such as pending points creation and poll voting. This convention-first design reduces the cognitive overhead for new contributors and supports long-term evolution without risky refactors.

Performance considerations are reflected in the use of indexing, query filtering, and pagination for list views. The data model includes indexes on the most frequently queried columns and uses paginated results for administrative lists. The widget endpoints are optimized for low payload size and are read-heavy, which suits edge caching in front of the Shopify store if desired.

Multi-tenancy is partially anticipated through the presence of store_id columns on several tables, but the current implementation operates in a single-tenant mode and uses null store identifiers. This explicit schema choice makes a future transition to multi-tenant isolation possible without retrofitting the entire data model, but it is not yet enforced in application logic.

## 3. Technology Stack Overview

Laravel is the core backend framework. It was selected because it provides a mature MVC architecture, a powerful ORM for relational data, and first-class support for security, queues, and migrations. The architecture relies on Laravel's conventions to keep the codebase predictable and to ensure that features such as middleware, authorization, and request validation are consistently applied across modules.

PHP 8.2 is the runtime language. The codebase uses typed arrays and explicit casting in models to handle JSON columns and numeric fields. PHP's ecosystem is particularly well suited to Shopify integrations because of robust HTTP client support and the availability of packages for authentication, queues, and mail.

Blade templates are used for the primary admin interface and for dedicated loyalty pages. Blade was chosen because it integrates naturally with Laravel controllers and allows server-rendered pages with minimal complexity. In places where the interface requires rich client-side behavior, lightweight JavaScript is embedded directly into the views.

Inertia.js and React are included for the authentication and settings surfaces. This hybrid approach allows the application to use a modern component model for forms such as password resets and two-factor challenges while keeping the rest of the admin panel in Blade, minimizing overall complexity. shadcn UI and Radix UI components appear in the React layer and provide accessible, composable UI primitives without relying on proprietary UI frameworks.

JavaScript is used extensively inside the Shopify widget and in several Blade pages for dynamic functionality such as chart rendering, poll analytics, and widget state transitions. The widget is a self-contained script that is inserted into Shopify themes, and it communicates with the backend through REST-style endpoints.

The persistence layer is a relational database. The migrations and configuration indicate MySQL for production, with SQLite used for testing and local development. MySQL was chosen due to its compatibility with Shopify app ecosystems and because it supports JSON columns and the indexing strategy required by the points ledger and coupon systems.

Shopify APIs and webhooks are foundational dependencies. Shopify Admin API calls are used to create and manage discount price rules and to verify customers. Shopify webhooks deliver customer and order events that update local records and trigger points logic. This direct integration is necessary to keep the loyalty platform aligned with the canonical Shopify data model.

JWT is not used in this system. Instead, the widget uses encrypted tokens created by Laravel's Crypt facade. This approach was chosen to avoid introducing additional authentication infrastructure while still ensuring token confidentiality and a short expiration window. The encryption mechanism is sufficient because tokens are scoped to a single customer and are validated server-side.

Queue support is configured through Laravel's database queue driver and the default jobs table. AI feature computation and clustering run through queued jobs to keep the admin UI responsive. Storage uses Laravel's local and public disks, with S3 available via configuration when required.

AI clustering is handled by a separate FastAPI service that runs KMeans training and returns model metrics, scaler parameters, and version metadata. The Laravel app stores these artifacts per run to keep predictions consistent and auditable across schema changes.

## 4. High-Level System Architecture

At a logical level, the platform is composed of five major modules: the admin management interface, the loyalty points engine, the coupon and redemption system, the mystery box rewards system, and the exclusive chat and polling module. These modules share a common customer model and a unified points transaction ledger, allowing cross-feature analytics and a single source of truth for point balances.

The physical architecture consists of a Laravel application server, a relational database, and Shopify as an external data source. Shopify webhooks push customer and order events into Laravel endpoints. Store administrators interact with the system through authenticated web pages served by Laravel. Customers interact via a widget script embedded in Shopify themes. The widget calls API endpoints hosted by the same Laravel application but separated logically by URL prefixes and CORS handling.

A typical request lifecycle begins with a Shopify storefront rendering the widget. The widget requests a token from the server using customer and shop metadata. The server validates the customer against Shopify Admin API, creates or updates the local customer record, and returns an encrypted token. Subsequent widget calls send the token as a query parameter to fetch dashboard data, coupons, mystery box status, chat messages, and points history. For admin workflows, the browser sends standard web requests to authenticated routes, and the server renders Blade views with data from the database.

The widget embedding architecture is intentionally minimal. The storefront administrator inserts the provided HTML, CSS, and JavaScript snippet into the Shopify theme. The widget renders a floating button, loads its own UI panel, and communicates only with the dedicated widget endpoints. This design ensures that the widget is isolated from the host theme and can be deployed without modifying Shopify checkout or app blocks.

## 5. Backend Architecture

The backend follows Laravel's standard layered structure, with routing, controller, model, and service layers. Controllers are grouped by feature domain. For example, CouponController encapsulates coupon lifecycle management and Shopify discount synchronization, while LoyaltyWidgetController handles all widget-related endpoints and token validation. This separation reduces coupling between admin and widget logic and ensures that security and validation can be tuned independently.

Services are used to encapsulate external API interactions. ShopifyCustomerService and ShopifyDiscountService are responsible for API calls to Shopify, including customer retrieval, price rule creation, and discount code lifecycle management. This separation isolates the rest of the codebase from external API details and simplifies testing because Shopify interactions are concentrated in a single layer.

Webhook handling is implemented in ShopifyWebhookController. Each webhook request verifies the Shopify HMAC signature and inspects the event topic to decide the appropriate processing path. This approach is chosen to ensure event authenticity and to handle webhook retries without double-processing. Idempotency is enforced by using event keys in the points ledger that are unique per customer, combined with a database-level unique constraint.

Database transactions are used around critical updates such as point balance adjustments and coupon redemption. In the redeem flow, the customer row is locked for update to prevent race conditions, and the points transaction is created in the same transaction to ensure consistency. This design was chosen because loyalty balances are financial in nature and require strict consistency guarantees.

Error handling is performed by returning error responses with contextual messages. External Shopify failures are caught and returned as user-visible errors, while other exceptions are kept local to avoid partial state updates. Logging relies on Laravel's default logging configuration. The use of structured validation rules across controllers provides a first layer of defense and ensures predictable error responses.

## 6. Database Architecture

The database schema is centered on a Customers table that mirrors Shopify customer data while adding loyalty-specific fields such as loyalty_points, points_pending, and tier_id. Customer records are identified by the Shopify customer ID, which ensures that the platform maintains a one-to-one mapping with Shopify's canonical data.

The PointsTransactions table is the core ledger. Each record includes points, status, source, type, and a unique event_key. The ledger approach was chosen because it provides a complete audit trail, supports reconciliation, and allows the system to reconstruct balances even if derived data is corrupted. The event_key uniqueness ensures idempotency for webhook and rule-driven events.

The Coupons table models reward definitions and includes fields that align with Shopify price rules: value_type, value, product eligibility, and buy-x-get-y parameters. CustomerCoupon records represent actual redemptions and contain the issued code, redemption status, and timestamps. The separation between Coupon and CustomerCoupon mirrors the distinction between reward definitions and issued rewards, enabling the system to track lifecycle states independently.

Mystery boxes are stored in MysteryBox and MysteryBoxItem tables. A mystery box defines eligible tiers, claim rules, and activation periods, while each item links to a coupon with a configurable weight. The weight field is included to support probabilistic reward distribution and fairness constraints.

Exclusive chat uses ChatMessage, ChatAttachment, ChatPoll, ChatPollOption, and ChatPollVote. Messages support attachments stored via the public filesystem. Polls are linked to messages and options, and votes reference the customer to enforce single-vote rules. The schema uses JSON arrays for tier visibility, allowing flexible per-message gating without additional join tables.

Indexing is focused on high-frequency query paths. PointsTransactions indexes include order_id and status; CustomerCoupons indexes include coupon_id, status, and redeemed_at; chat-related tables index message and poll IDs. These indexes were chosen based on the read patterns observed in admin dashboards and widget endpoints.

## 7. Loyalty and Points Engine Architecture

The loyalty engine is rule-driven and event-based. Point rules are stored in the PointRules table and include welcome points, birthday points, profile completion points, social reward points, and a spending rate for purchase-based earning. This design was chosen to allow business owners to update loyalty policy without code changes and to align the point system with marketing goals.

Pending points are used for purchase-based earning. When Shopify sends order creation or payment events, the system computes eligible points based on the configured amount_per_point and records a PENDING transaction while increasing points_pending on the customer record. When a fulfillment webhook arrives, the pending transaction is approved and the points move from points_pending to loyalty_points. This two-phase model mirrors real-world loyalty practices and reduces the risk of crediting points for unfulfilled orders.

Refunds and cancellations trigger reversal logic. The system inspects existing transactions and creates a REVERSED transaction with negative points to offset previously granted points. This approach preserves ledger integrity and keeps balances consistent with actual order outcomes. The reversal logic is scoped to specific order and refund identifiers to avoid double adjustments.

Non-order earning rules are implemented through explicit triggers. The welcome bonus is awarded when a customer is first created or verified, and is enforced as a single transaction by the event_key uniqueness constraint. Profile completion and birthday rewards are awarded when the customer reaches a qualifying state and are recorded as ledger entries with clear source types.

Points history is exposed through a dedicated endpoint that filters transactions by earned and redeemed types. The formatter normalizes direction, labels, and titles so that the widget and admin views can present a consistent narrative. This abstraction was chosen to separate UI wording from raw transaction records, improving maintainability and enabling future localization.

## 8. Coupon and Mystery Box Architecture

Coupon lifecycle management is handled by CouponController. Coupons can be created as drafts, updated while inactive, and activated to create Shopify price rules and discount codes. The system retains Shopify identifiers for each coupon to allow later updates or deactivation. This approach keeps the local data model synchronized with Shopify without requiring the admin to manage Shopify directly.

Redemption is a controlled workflow. The widget validates coupon eligibility by status, tier, and date range before redemption. When redeemed, the system generates a unique code, creates a Shopify discount code, deducts points from the customer, and records a redemption and ledger transaction. The use of database locking ensures that simultaneous redemptions cannot overspend a customer's balance.

Mystery boxes provide probabilistic rewards. An active mystery box is matched to a customer by tier and activation window. Claim eligibility is enforced based on the box's claim rule, which can restrict claims to once ever, once per day, or once per week. When a claim is allowed, the system selects a reward using weighted random selection and issues a coupon code in the same way as standard redemptions. This design ensures that mystery box claims are consistent with the existing coupon lifecycle and do not bypass Shopify validation.

Fairness and transparency are supported by storing all claims in CustomerCoupons with source set to MYSTERY_BOX. The admin view surfaces claim counts, usage, and expiration status, providing operational visibility into the distribution of mystery box rewards. Because the reward selection is deterministic within a single request and is logged in the database, auditability is preserved.

## 9. Tier System Architecture

Tiers are modeled as a standalone table containing a point range, visual attributes, and an activation status. The resolveTier function selects the first active tier whose range includes the customer's point balance. This range-based lookup was chosen for clarity and allows administrators to manage tiers in a predictable, non-overlapping manner.

Tier membership influences both visibility and eligibility across the platform. Coupon availability can be constrained by tier, and mystery boxes explicitly list eligible tiers. The exclusive chat module uses tier visibility rules in addition to global settings. This layered gating ensures that new tiers can be introduced without modifying the logic in each feature, because tier constraints are consistently represented as IDs in configuration data.

The tier system is designed to be extensible. Additional tier attributes can be added without impacting core logic because tier selection depends only on points and activation status. This makes the tier module a stable foundation for future benefits such as tier-specific multipliers or seasonal promotions.

## 10. Exclusive Chat and Poll System Architecture

Exclusive chat is designed as a one-way communication channel. Store administrators create messages in the admin panel, optionally attaching images and configuring tier visibility. Messages are stored with a sent_at timestamp and retrieved by the widget in reverse chronological order. The one-way model minimizes moderation overhead while allowing stores to deliver announcements and promotions directly to loyal customers.

Polls are modeled as a message subtype. Each poll has a set of options and an optional close time. The system enforces a single vote per customer by applying a unique constraint on chat_poll_id and customer_id. Votes are stored with a voted_at timestamp, enabling time-based analytics and auditability.

Tier-based access control is enforced at two levels. The global chat settings define which tiers can access the chat feature, while each message can specify a tier visibility list. This dual gating ensures that the chat can be enabled for only premium tiers while still allowing individual messages to be targeted.

Analytics are exposed through admin API endpoints that return counts and percentages for poll options. This is implemented as a lightweight JSON response rather than a separate reporting system, which keeps the feature self-contained and suitable for real-time dashboards.

## 11. Frontend Widget Architecture

The widget is delivered as a standalone HTML block with embedded CSS and JavaScript. It is designed to be inserted before the closing body tag in Shopify's theme.liquid file. The widget creates a floating trigger button and a modal panel. All visual assets are defined inline to prevent dependency on external CSS frameworks and to minimize the risk of style collisions with the store theme.

Authentication for widget requests is handled through a token exchange. The widget sends the Shopify customer ID and email to the token endpoint, which verifies the customer via Shopify API and returns an encrypted token. This token is appended to all subsequent API calls. The token has a short expiration window, reducing risk if it is leaked. This approach was chosen over JWT to reduce dependency footprint and to leverage Laravel's built-in encryption and rotation mechanisms.

Widget state management is implemented with plain JavaScript. Each view, such as dashboard, profile form, coupon list, mystery box, and chat, is rendered dynamically by injecting HTML into a single container. This approach keeps the widget lightweight and avoids the overhead of a full front-end framework. It also reduces the bundle size and simplifies Shopify theme installation.

Performance considerations focus on minimizing API calls and payloads. The widget fetches only what it needs for the current screen and uses paginated endpoints for activity history. The widget also handles loading states and error messages to ensure a consistent user experience even when the backend is unavailable.

## 12. Security Architecture

Webhook security relies on Shopify HMAC verification. The server computes a signature for each incoming webhook and compares it with the signature supplied by Shopify. This prevents unauthorized systems from modifying loyalty balances or coupon statuses.

Widget authentication uses encrypted tokens rather than session cookies. Tokens encode the Shopify customer ID and expiry time and are decrypted server-side. This strategy eliminates the need for Shopify customer sessions on the backend and allows the widget to operate in a stateless manner. The CORS logic restricts widget requests to the configured Shopify domain, which reduces the risk of token misuse across unrelated sites.

Admin security is provided by Laravel Fortify and the web authentication stack. The admin routes are protected by auth and verified middleware, with configurable two-factor authentication and rate limiting. These features were chosen because they are battle-tested, integrate cleanly with Laravel, and reduce the need to implement custom authentication logic.

Abuse prevention is addressed by strict validation rules and idempotent event handling. Points are awarded only once per event_key, poll votes are constrained to one per customer, and coupon redemption checks enforce tier eligibility and expiration dates. These safeguards ensure that the loyalty system cannot be inflated by replaying requests.

## 13. Scalability and Performance Considerations

The system can scale horizontally as long as all application nodes share a common database. Because data integrity is enforced at the database layer through constraints and transactions, multiple application instances can process webhook events without duplication. Read-heavy endpoints such as widget data and points history are already paginated, reducing database load.

Queue infrastructure is configured but not yet used by any jobs. This signals an architectural direction where heavy tasks such as bulk imports, analytics aggregation, or email processing can be moved to background jobs without major refactors. The database queue driver is the default, which simplifies deployment in environments without external queue services.

Database performance is supported by indexes on high-traffic columns and by keeping the points ledger normalized. For example, points transactions are filtered by customer and status, coupon redemptions are filtered by coupon and status, and chat polling queries use indexed poll and option references. This indexing strategy aligns with the admin dashboard queries and widget usage patterns.

Caching is not explicitly implemented in the current codebase. This choice keeps the system consistent and avoids cache invalidation complexity. If the workload grows, caching of static configuration data such as point rules and tier definitions could be introduced without changing the public API.

## 14. Maintainability and Extensibility

The codebase is organized by feature and follows Laravel conventions, which promotes maintainability. Each feature has a small set of controllers, models, and views, and the shared patterns for validation and formatting are reusable. The PointsHistoryFormatter is a concrete example of encapsulating presentation logic in a dedicated class, making it easy to update transaction labeling without touching multiple controllers.

Adding new earning rules or reward types can be achieved by extending the point_rules table and adding associated logic in the widget controller and admin settings. Because the points ledger accepts arbitrary source types and metadata, new sources can be integrated without schema changes beyond the rule definition itself.

New customer engagement features can reuse the existing tier gating and token validation mechanisms. The chat module demonstrates how a feature can enforce both global eligibility and per-message visibility using the same data structures. This pattern can be reused for future features such as surveys or referral prompts.

The mix of Blade templates and Inertia-based React pages allows the system to evolve gradually. Admin modules can be migrated to React incrementally while still operating within the same Laravel routing and authentication stack, which supports long-term modernization without disrupting the stable parts of the platform.

## 15. Conclusion

NexLoyal presents a cohesive architecture centered on a reliable points ledger, tight Shopify integration, and a lightweight customer-facing widget. The system balances operational control for store owners with a simple, embedded customer experience. Its use of Laravel's conventions, explicit data modeling, and idempotent event handling provides a robust foundation for loyalty features.

The platform is well positioned for future enhancement. The existing schema anticipates multi-tenant expansion, the queue configuration prepares the system for background processing, and the modular feature structure supports new reward types without architectural upheaval. These characteristics make NexLoyal suitable both for academic evaluation and for real-world client deployment.
