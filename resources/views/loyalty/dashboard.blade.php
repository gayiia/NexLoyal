{{-- This widget view shows a customer's loyalty summary and quick actions. --}}
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- The title is simple because this page is embedded in the widget. --}}
        <title>Loyalty Dashboard</title>
        <style>
            {{-- These styles are self-contained to avoid Shopify theme conflicts. --}}
            :root {
                color-scheme: light;
            }
            body {
                margin: 0;
                font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                background: #f4f5f7;
                color: #1f2933;
            }
            .page {
                max-width: 720px;
                margin: 48px auto;
                padding: 0 20px;
            }
            .card {
                background: #ffffff;
                border-radius: 16px;
                padding: 28px;
                box-shadow: 0 12px 30px rgba(17, 24, 39, 0.08);
            }
            .header {
                margin-bottom: 24px;
            }
            .header h1 {
                margin: 0 0 8px;
                font-size: 28px;
            }
            .muted {
                color: #5b6777;
                font-size: 14px;
            }
            .stat-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 16px;
            }
            .stat {
                padding: 16px;
                background: #f7f8fa;
                border-radius: 12px;
            }
            .stat h3 {
                margin: 0 0 8px;
                font-size: 14px;
                color: #5b6777;
                text-transform: uppercase;
                letter-spacing: 0.08em;
            }
            .stat .value {
                font-size: 22px;
                font-weight: 600;
            }
            .section-title {
                margin: 28px 0 12px;
                font-size: 16px;
                font-weight: 600;
                color: #1f2933;
            }
            .card-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 14px;
            }
            .mini-card {
                background: #ffffff;
                border-radius: 12px;
                border: 1px solid #e6e8eb;
                padding: 16px;
                min-height: 92px;
            }
            .mini-card h4 {
                margin: 0 0 6px;
                font-size: 14px;
                color: #1f2933;
            }
            .mini-card p {
                margin: 0;
                font-size: 13px;
                color: #6b7280;
            }
            .error {
                padding: 18px;
                border-radius: 12px;
                background: #fff0f0;
                color: #9f1239;
                font-weight: 600;
            }
        </style>
    </head>
    <body>
        <div class="page">
            <div class="card">
                {{-- If data cannot be loaded, show a single error message. --}}
                @if (!empty($error))
                    <div class="error">{{ $error }}</div>
                @else
                    {{-- Greeting uses the customer's name when available. --}}
                    <div class="header">
                        <h1>Welcome back, {{ $customer->full_name ?: 'Customer' }}</h1>
                        <div class="muted">{{ $customer->email }}</div>
                    </div>
                    <div class="stat-grid">
                        <div class="stat">
                            <h3>Points</h3>
                            <div class="value">{{ number_format($points) }}</div>
                        </div>
                        <div class="stat">
                            <h3>Tier</h3>
                            <div class="value">{{ $tier?->title ?? 'No tier yet' }}</div>
                        </div>
                        <div class="stat">
                            <h3>Total spent</h3>
                            {{-- Currency prefix is shown when the store provides one. --}}
                            <div class="value">{{ $customer->currency ? $customer->currency.' ' : '' }}{{ number_format((float) $customer->total_spent, 2) }}</div>
                        </div>
                    </div>
                    <div class="section-title">Your Loyalty Hub</div>
                    {{-- These cards act as navigation hints for the widget experience. --}}
                    <div class="card-grid">
                        <div class="mini-card">
                            <h4>Complete your profile</h4>
                            <p>Unlock profile rewards and personalization.</p>
                        </div>
                        <div class="mini-card">
                            <h4>Redeem points</h4>
                            <p>Use your points for exclusive perks.</p>
                        </div>
                        <div class="mini-card">
                            <h4>Point history</h4>
                            <p>Track every point you earn and spend.</p>
                        </div>
                        <div class="mini-card">
                            <h4>My coupons</h4>
                            <p>View your available reward codes.</p>
                        </div>
                        <div class="mini-card">
                            <h4>Earn points</h4>
                            <p>See ways to earn more points.</p>
                        </div>
                        <div class="mini-card">
                            <h4>Mystery box</h4>
                            <p>Open surprise rewards and bonuses.</p>
                        </div>
                        <div class="mini-card">
                            <h4>Exclusive chat</h4>
                            <p>Get VIP access to our team.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </body>
</html>
