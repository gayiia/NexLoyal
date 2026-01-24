<?php

return [
    'k_range' => [
        'min' => (int) env('AI_MIN_K', 2),
        'max' => (int) env('AI_MAX_K', 10),
    ],
    'outlier_cap_quantile' => (float) env('AI_OUTLIER_CAP_QUANTILE', 0.99),
    'log_transforms' => [
        'total_spent',
        'avg_order_value',
    ],
    'min_customers_for_training' => (int) env('AI_MIN_CUSTOMERS', 20),
    'feature_schema_version' => (int) env('AI_FEATURE_SCHEMA_VERSION', 1),
    'algorithm_version' => env('AI_ALGORITHM_VERSION', 'kmeans_v1'),
    'code_version' => env('AI_CODE_VERSION', 'local'),
    'api_key' => env('AI_API_KEY'),
    'ai_timeout_seconds' => (int) env('AI_TIMEOUT_SECONDS', 30),
    'exclude_zero_activity_customers' => (bool) env('AI_EXCLUDE_ZERO_ACTIVITY', true),
    'exclude_refund_only_customers' => (bool) env('AI_EXCLUDE_REFUND_ONLY', true),
    'new_customer_recency_days' => (int) env('AI_NEW_CUSTOMER_RECENCY_DAYS', 365),
    'cleanup_days' => (int) env('AI_CLEANUP_DAYS', 90),
    'feature_keys' => [
        'orders_count',
        'total_spent',
        'avg_order_value',
        'redeemed_coupons',
        'points_earned',
        'points_spent',
        'loyalty_points',
        'days_since_last_order',
        'tenure_days',
    ],
];
