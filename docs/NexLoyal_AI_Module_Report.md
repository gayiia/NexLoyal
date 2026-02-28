# AI-Driven Loyalty Platform (NexLoyal / LoyaltyHub FYP)
## Executive Summary for Non-IT Readers

NexLoyal is a loyalty system connected to Shopify that helps a business treat customers differently based on their real shopping behavior. Instead of giving the same reward to everyone, the system groups customers into meaningful segments and suggests better reward actions for each segment.

In simple terms, the AI looks at customer shopping and loyalty activity, finds patterns, and creates groups such as:

- high-value frequent shoppers
- regular shoppers
- at-risk or inactive shoppers

This helps the business decide who should receive premium benefits, who needs engagement nudges, and who needs win-back offers.  
The goal is better retention, better reward spend efficiency, and improved customer lifetime value.

### What the system produces

- customer clusters (segments)
- dashboard metrics (cluster sizes, average spend, model quality scores)
- cluster-based reward targeting inputs (points/coupon campaigns)

### Why this is useful for business

- reduces guesswork in loyalty decisions
- sends rewards to the right people at the right time
- supports measurable campaign strategy by customer segment

---

## A. Overview (Problem + Outputs)

Traditional loyalty programs are often rule-only and broad. They may over-reward low-potential users and under-reward high-value users.  
This AI module solves that by clustering customers based on behavior.

Core purpose:

1. **Customer segmentation** using data-driven clustering.
2. **Reward personalization support** using segment-level actions.

Actual implementation summary:

- **Data sources**: Shopify-related customer/order/points/coupon data stored in Laravel DB; CSV imports supported; Shopify integrations/webhooks available in platform architecture.
- **Time window**: all available history in DB at run time (current code does not force a hard 12-month cut).
- **Run frequency**: on-demand, or queued immediately after CSV import.
- **Outputs**: selected `K`, cluster assignments, centroids, silhouette/inertia metrics, cluster profiles, dashboard charts, cluster-targeted award operations.
- **Where it runs**: Laravel queued jobs + Python FastAPI AI service.
- **Model type**: K-Means clustering + rule-based business mapping (hybrid operational use).

---

## B. Data Inputs (What fields are used and where they come from)

### 1) Customers dataset (required)

- `shopify_id` (stable key)
- `email`
- `orders_count`
- `total_spent`
- `loyalty_points`
- `points_pending`
- `last_order_at`
- `shopify_created_at` (used if available for tenure)

Typical source: Shopify/customer sync or CSV import.

### 2) Points transactions dataset (strongly recommended)

- `customer_shopify_id`
- `points`
- `type` (`EARN` / `SPEND`)
- `source_type` (`ORDER` / `RULE`)
- `order_id` (optional link to order)
- `event_key` (idempotency/dedup key)
- `created_at`

Typical source: order-triggered loyalty events, rules engine, import history.

### 3) Customer coupon redemption dataset (optional)

- `customer_shopify_id`
- `coupon_code`
- `redeemed_at`

This improves reward-engagement understanding.

---

## C. Feature Engineering (How each feature is computed)

Production feature list in current configuration:

1. `orders_count`
2. `total_spent`
3. `avg_order_value`
4. `redeemed_coupons`
5. `points_earned`
6. `points_spent`
7. `loyalty_points`
8. `days_since_last_order`
9. `tenure_days`

### Mathematical definitions

Recency:

\[
R_i = (\text{today} - \text{last\_order\_date}_i) \text{ in days}
\]

Frequency:

\[
F_i = \text{count of orders for customer } i
\]

Monetary:

\[
M_i = \sum \text{order amounts of customer } i
\]

Average Order Value:

\[
AOV_i = \frac{M_i}{\max(F_i, 1)}
\]

Lifetime / tenure:

\[
L_i = (\text{today} - \text{first/shopify\_created\_date}_i) \text{ in days}
\]

Loyalty points earned/spent from ledger:

\[
\text{PointsEarned}_i = \sum \text{points where type = EARN}
\]

\[
\text{PointsSpent}_i = \sum \text{points where type = SPEND}
\]

### Practical feature rules in current system

- Missing `last_order_at` gets a default recency value (config default: 365 days).
- Outlier capping applied before model training.
- Log-transform applied to `total_spent` and `avg_order_value`.

---

## D. Data Preprocessing (Cleaning, missing values, outliers)

Before model training, the system performs:

1. CSV/schema validation and type conversion.
2. Numeric null handling (fallback to 0).
3. Date parsing and normalization.
4. Optional exclusion of low-signal customers:
   - no-activity customers
   - refund-only customers
5. Outlier capping by quantile (default 99th percentile).

Outlier capping equation:

\[
\tilde{x}_{ij} = \min(x_{ij}, Q_q(X_j))
\]

where \(Q_q\) is the \(q\)-quantile (default \(q=0.99\)).

Log transform used for skewed monetary features:

\[
x^*_{ij} = \log(1 + \max(0, \tilde{x}_{ij}))
\]

---

## E. Scaling / Normalization

Why scaling is needed:  
Features are in different units (money, counts, days). Without scaling, larger-unit features dominate distance calculations.

The module uses **z-score normalization**:

\[
Z_{ij} = \frac{X_{ij} - \mu_j}{\sigma_j}
\]

where:

- \(\mu_j\): mean of feature \(j\)
- \(\sigma_j\): standard deviation of feature \(j\)

This is implemented using `StandardScaler`.

---

## F. Model / Algorithm (K-Means)

NexLoyal AI clustering uses K-Means to group similar customers.

### Objective function

\[
J = \sum_{i=1}^{n} \|x_i - \mu_{c_i}\|^2
\]

The algorithm minimizes total within-cluster variation.

### Distance metric (Euclidean)

\[
d(x, \mu) = \sqrt{\sum_{j=1}^{p}(x_j - \mu_j)^2}
\]

### Centroid update rule

\[
\mu_k = \frac{1}{|C_k|} \sum_{x_i \in C_k} x_i
\]

### Iterative steps

1. choose candidate values for \(K\) (config range, e.g., 2..10)
2. initialize centroids
3. assign each customer to nearest centroid
4. recompute centroids
5. repeat until assignments stabilize

### Choosing K

Current implementation evaluates multiple \(K\) values and stores both:

- inertia (elbow-style indicator)
- silhouette score

Silhouette:

\[
s(i) = \frac{b(i) - a(i)}{\max(a(i), b(i))}
\]

Higher silhouette indicates better cluster separation and cohesion.

---

## G. How Outputs Are Used (Reward mapping)

After clustering:

1. each customer gets a cluster ID
2. each cluster gets profile stats (avg spend, avg orders, points behavior)
3. clusters are ranked and labeled (e.g., from lower to higher value)
4. business users configure rewards per cluster

Example rule mapping:

| Cluster Profile | Typical Action |
|---|---|
| High value + high frequency | premium tier perks, higher points multiplier |
| Medium value + stable engagement | targeted bundle/coupon nudges |
| At-risk / high recency | win-back discount or reminder campaign |

Current implementation supports creating and activating:

- points awards
- coupon awards

with issuance tracking and idempotent safeguards.

---

## H. Worked Example (3 Customers)

Assume today is **2026-02-27**.

### Input table

| Customer | Last Order | Orders \(F\) | Total Spend \(M\) |
|---|---|---:|---:|
| C1 | 2026-02-20 | 8 | 1600 |
| C2 | 2025-12-29 | 3 | 450 |
| C3 | 2025-09-30 | 1 | 120 |

Recency values:

- \(R_1 = 7\)
- \(R_2 = 60\)
- \(R_3 = 150\)

### Feature table (R, F, M)

| Customer | \(R\) | \(F\) | \(M\) |
|---|---:|---:|---:|
| C1 | 7 | 8 | 1600 |
| C2 | 60 | 3 | 450 |
| C3 | 150 | 1 | 120 |

### Normalization (z-score)

Means:

\[
\mu_R = 72.33,\ \mu_F = 4.00,\ \mu_M = 723.33
\]

Standard deviations:

\[
\sigma_R = 59.03,\ \sigma_F = 2.94,\ \sigma_M = 634.36
\]

Example calculation (customer C2 recency):

\[
Z_{R,2} = \frac{60 - 72.33}{59.03} \approx -0.21
\]

Normalized table (approx):

| Customer | \(Z_R\) | \(Z_F\) | \(Z_M\) |
|---|---:|---:|---:|
| C1 | -1.11 | 1.36 | 1.38 |
| C2 | -0.21 | -0.34 | -0.43 |
| C3 | 1.32 | -1.02 | -0.95 |

### K-Means with \(K=2\)

Initial centroids:

- \(\mu_0 = C1 = (-1.11, 1.36, 1.38)\)
- \(\mu_1 = C3 = (1.32, -1.02, -0.95)\)

Distance from C2 to \(\mu_0\):

\[
d(C2,\mu_0)=\sqrt{(-0.21+1.11)^2+(-0.34-1.36)^2+(-0.43-1.38)^2}
\]
\[
=\sqrt{0.90^2+(-1.70)^2+(-1.81)^2}
=\sqrt{0.81+2.89+3.28}
=\sqrt{6.98}
\approx2.64
\]

Distance from C2 to \(\mu_1\):

\[
d(C2,\mu_1)=\sqrt{(-0.21-1.32)^2+(-0.34+1.02)^2+(-0.43+0.95)^2}
\]
\[
=\sqrt{(-1.53)^2+0.68^2+0.52^2}
=\sqrt{2.34+0.46+0.27}
=\sqrt{3.07}
\approx1.75
\]

Since \(1.75 < 2.64\), C2 joins cluster 1.

### Final cluster result (simple example)

| Cluster | Members | Interpretation | Reward Action |
|---|---|---|---|
| Cluster A | C1 | very high value, highly active | VIP benefits + stronger rewards |
| Cluster B | C2, C3 | lower engagement / at risk | win-back coupon + reactivation points |

---

## I. Evaluation & Validation

Because this is unsupervised learning, validation focuses on cluster quality and business usefulness.

### Technical validation

1. silhouette score monitoring (higher is better)
2. inertia trend across candidate K values
3. schema checks (feature length consistency, finite numeric values)
4. minimum sample-size checks (default min 20 customers)

### Business validation

1. sanity check cluster profiles (spend/order/recency differences)
2. compare campaign outcomes by cluster
3. validate that reward decisions align with business intent

### Operational validation

- run metadata persisted (selected K, scaler params, timestamps, dataset hash)
- idempotent award issuance to prevent duplicate rewards

---

## J. Deployment (How it runs and updates)

### Runtime architecture

```text
[Shopify/CSV Data]
        |
        v
[Laravel DB: customers, points, coupons]
        |
        v
[ComputeCustomerFeaturesJob]
        |
        v
[RunAIClusteringJob] ----HTTP----> [Python FastAPI AI Service]
        |                                 |
        |<--------- labels/metrics -------|
        v
[Persist clusters + assignments + run metrics]
        |
        v
[AI Insights Dashboard + Reward Operations]
```

### Update behavior

- Manual/on-demand run from AI Insights.
- After data import, queue chain can automatically:
  1. compute features
  2. run clustering

Integration stack:

- Laravel jobs/queues (orchestrator)
- Python FastAPI + NumPy + scikit-learn (model service)

---

## K. Limitations & Future Improvements

### Current limitations

1. unsupervised output needs business interpretation
2. no fixed rolling time window in core feature computation
3. reward mapping is mostly admin rule-driven after clustering
4. richer product/category/channel features are not yet core inputs

### Future improvements

1. scheduled weekly retraining
2. rolling window features (30/90/180-day trends)
3. campaign-response features (open/click/redeem)
4. drift monitoring and cluster stability tests
5. uplift/A-B experiments by cluster

---

## L. Glossary

- **Recency**: days since last order.
- **Frequency**: number of orders.
- **Monetary**: total spend.
- **AOV**: average order value.
- **Tenure**: customer age in days.
- **Feature**: numeric input to model.
- **Normalization**: scale features to comparable units.
- **Cluster**: group of similar customers.
- **Centroid**: center point of a cluster.
- **K-Means**: algorithm that partitions data into K groups.
- **Euclidean distance**: straight-line distance in feature space.
- **Inertia**: within-cluster compactness score.
- **Silhouette score**: cluster separation/quality score.
- **Outlier capping**: limiting extreme values.
- **Idempotency**: repeated processing does not duplicate output.

---

## Technical Appendix for Examiner

### A1. Actual production-oriented configuration details

- **Feature keys**:
  - `orders_count`
  - `total_spent`
  - `avg_order_value`
  - `redeemed_coupons`
  - `points_earned`
  - `points_spent`
  - `loyalty_points`
  - `days_since_last_order`
  - `tenure_days`
- **Log transforms**: `total_spent`, `avg_order_value`
- **Outlier cap quantile**: 0.99
- **K range**: configurable, defaults 2 to 10
- **Minimum customers for training**: 20
- **Exclusion flags**:
  - exclude zero-activity customers = true
  - exclude refund-only customers = true
- **Default new-customer recency fallback**: 365 days

### A2. Pseudocode (end-to-end)

```text
INPUT:
  customers, points_transactions, customer_coupons

STEP 1: build features per customer
  F = orders_count
  M = total_spent
  AOV = M / max(F,1)
  points_earned = sum(EARN points)
  points_spent = sum(SPEND points)
  redeemed_coupons = count(customer_coupons)
  R = days_since_last_order (or fallback value)
  L = tenure_days

STEP 2: apply data quality rules
  exclude no-activity and refund-only customers (if enabled)
  cap each feature at q99
  log-transform total_spent and avg_order_value
  z-score standardize all model features

STEP 3: model selection and clustering
  for K in [min_k..max_k]:
    run KMeans(K)
    compute inertia
    compute silhouette
  select K with best silhouette
  return labels, centroids, diagnostics

STEP 4: persist and operationalize
  store run metadata + scaler params + cluster metrics
  store customer-cluster assignments
  expose in AI dashboard
  allow admin to map/activate rewards by cluster
```

### A3. Assumptions

1. imported historical data reasonably represents customer behavior
2. points ledger and order-level summaries are consistent
3. cluster semantics are stable enough for campaign use
4. distance in standardized feature space is a valid similarity proxy

### A4. Limitations in mathematical interpretation

1. K-Means assumes roughly spherical clusters under Euclidean geometry.
2. Segment boundaries are hard partitions (no probability membership).
3. Results can shift if customer behavior distribution changes significantly.
4. Silhouette guides K selection but does not guarantee business-optimal segmentation.

### A5. Notes on points and coupon formulas in current implementation

- **Points rule**: AI module reads actual points behavior from transaction ledger; it does not enforce a single fixed earning formula like “1 point per X currency”.
- **Coupon/discount formula**: no single hard-coded percentage function in the model; rewards are configured through business/admin actions per cluster.

If needed for dissertation narrative, a policy example can be documented separately, such as:

- Cluster VIP: +2x points multiplier
- Cluster Growth: 10% targeted coupon
- Cluster At-Risk: win-back discount with expiry

