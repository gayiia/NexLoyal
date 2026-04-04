# NexLoyal

NexLoyal is a Shopify-focused loyalty platform built on Laravel. It combines a web-based admin panel with an embeddable storefront widget to help stores reward customers, manage points, and deliver targeted engagement. The system integrates directly with Shopify webhooks and discount APIs so that loyalty activity stays aligned with Shopify data.

## Feature Summary

- Points ledger with pending and approved states
- Coupon rewards tied to Shopify price rules
- Tiered eligibility and mystery box rewards
- Exclusive chat with polls and image attachments
- Configurable rules for welcome, birthday, profile completion, and social engagement
- AI clustering for customer segmentation with admin insights

## Technology Stack

- Backend: Laravel 12 (PHP 8.2)
- Frontend: Blade + Tailwind CSS (with some Inertia + React for settings)
- AI: FastAPI + scikit-learn KMeans
- Data: MySQL (production), SQLite (testing)

## Architecture Justification (FYP Notes)

- Laravel handles webhooks, business logic, and admin UX with strong DB consistency and queue support.
- Inertia/React is used only where more interactive settings are needed, keeping most of the UI simple and server-rendered.
- FastAPI isolates ML workloads, keeping the main app responsive and allowing independent scaling and versioning of models.

## Data Flow Overview

Shopify/CSV -> DB -> Feature engineering -> FastAPI training -> Clusters -> Admin insights/widget

1) Shopify webhooks or CSV import populate customers and transactions.
2) Laravel computes customer features and stores them in `customer_features`.
3) Laravel sends the feature matrix to FastAPI for training.
4) FastAPI returns clusters + metrics + scaler metadata.
5) Laravel stores run metadata, clusters, and customer assignments.
6) Admin UI displays insights; widget uses the same data model.

## Model Versioning

Each AI training run stores a `model_metadata` object:

- `dataset_hash`: hash of raw input used for training
- `feature_schema_version`: increment when feature definitions change
- `algorithm_version`: e.g. `kmeans_v1`
- `code_version`: Git commit or manual string
- `trained_at`: timestamp

Scaler mean/scale, feature order, and outlier caps are stored with the run so predictions remain consistent.

## Interpreting Clusters for Loyalty Tiers

Clusters are ranked by average spend and labeled (e.g., Value Seekers -> VIP Loyalists). Use these labels to:

- tailor rewards and campaigns
- define tier-specific offers
- seed mystery box eligibility

## One-Command Local Setup

From the repo root:

```
composer run setup
```

This installs PHP + JS dependencies, generates the app key, migrates, and builds assets.

## Local Development

```
composer install
npm install
php artisan migrate --seed
php artisan queue:work
npm run dev
php artisan serve
```

For a combined workflow:

```
composer run dev
```

## FastAPI AI Service

1) Create a virtual environment and install dependencies:

```
cd ai_service
python -m venv .venv
.venv\\Scripts\\activate
pip install -r requirements.txt
```

2) Set environment variables:

- `AI_API_KEY` (required)
- `AI_CODE_VERSION` (optional)
- `AI_MODEL_STATE_PATH` (optional path for persisted model state)

3) Run the service:

```
uvicorn main:app --host 0.0.0.0 --port 8001
```

Laravel will call the service using `AI_SERVICE_URL` and `AI_API_KEY`. Keep the AI service private or behind a firewall.

Training response includes:

- `silhouette_scores` and `inertia_scores` across the K range
- `selected_k`, `final_silhouette`, `final_inertia`
- `data_stats` (rows, features, feature names)
- `timing` (started_at, finished_at, duration_ms)
- `scaler` and `model_metadata` for persistence

## AI Sandbox and Feature Preview

- AI Sandbox: `admin/ai/sandbox`
  - Compute features
  - Train model
  - View metrics and errors
- Feature Preview: `admin/ai/features`
  - Inspect computed features before training

By default, customers with no activity or refund-only history are excluded from training. These flags are stored on `customer_features` and shown in the preview table.

## Cleanup and Maintenance

Clean generated artifacts:

```
scripts/clean.ps1
scripts/clean.sh
```

Cleanup old AI runs (keeps latest run):

```
php artisan ai:cleanup-runs --days=90
```

## Configuration

AI settings live in `config/ai.php` and can be overridden via `.env`:

- `AI_MIN_K`, `AI_MAX_K`
- `AI_OUTLIER_CAP_QUANTILE`
- `AI_FEATURE_SCHEMA_VERSION`
- `AI_ALGORITHM_VERSION`
- `AI_CODE_VERSION`
- `AI_TIMEOUT_SECONDS`

## Documentation

Additional architecture and thesis documentation is maintained outside this repository.
Shopify widget snippet is in `docs/shopify-widget.liquid`.
