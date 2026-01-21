# Portal Adoption Report

Analyze myAccount portal adoption, e-billing rates, and the business impact of digital engagement.

## When to Use

User asks questions like:
- "What percent of customers use the portal?"
- "Is e-billing adoption increasing?"
- "Are portal users paying faster?"
- "Show me portal activity trends"
- "Which segment has lowest portal adoption?"

## Workflow

1. **Clarify scope** - All segments or specific? Time range?
2. **Run adoption metrics** - Registration rates, feature usage
3. **Calculate business impact** - Payment speed, engagement
4. **Present insights** with actionable recommendations

## Key Metrics

| Metric | Description |
|--------|-------------|
| Portal Adoption % | Customers with portal accounts ÷ total customers |
| E-Statement % | Portal users with email_statement enabled |
| E-Invoice % | Portal users with email_invoice enabled |
| Autopay % | Portal users with autopay_enabled |
| Avg Days to Pay | Average payment speed (portal vs non-portal) |
| Active Users | Portal users with recent login events |
| Feature Usage | Counts by event type |

## SQL Patterns

### Overall Adoption by Segment
```sql
SELECT
  c.segment,
  COUNT(DISTINCT c.id) AS total_customers,
  COUNT(DISTINCT pu.customer_id) AS portal_customers,
  ROUND(100.0 * COUNT(DISTINCT pu.customer_id) / COUNT(DISTINCT c.id), 1) AS adoption_pct,
  ROUND(100.0 * SUM(CASE WHEN pu.email_statement = 1 THEN 1 ELSE 0 END)
    / NULLIF(COUNT(DISTINCT pu.id), 0), 1) AS e_statement_pct,
  ROUND(100.0 * SUM(CASE WHEN pu.email_invoice = 1 THEN 1 ELSE 0 END)
    / NULLIF(COUNT(DISTINCT pu.id), 0), 1) AS e_invoice_pct,
  ROUND(100.0 * SUM(CASE WHEN pu.autopay_enabled = 1 THEN 1 ELSE 0 END)
    / NULLIF(COUNT(DISTINCT pu.id), 0), 1) AS autopay_pct
FROM customers c
LEFT JOIN portal_users pu ON pu.customer_id = c.id
GROUP BY c.segment
ORDER BY adoption_pct DESC;
```

### Payment Speed Comparison
```sql
WITH portal_payments AS (
  SELECT
    AVG(julianday(p.payment_date) - julianday(i.invoice_date)) AS avg_days
  FROM payments p
  JOIN invoices i ON i.id = p.invoice_id
  JOIN customers c ON c.id = i.customer_id
  JOIN portal_users pu ON pu.customer_id = c.id
  WHERE i.invoice_date >= date('now', '-12 months')
),
non_portal_payments AS (
  SELECT
    AVG(julianday(p.payment_date) - julianday(i.invoice_date)) AS avg_days
  FROM payments p
  JOIN invoices i ON i.id = p.invoice_id
  JOIN customers c ON c.id = i.customer_id
  LEFT JOIN portal_users pu ON pu.customer_id = c.id
  WHERE pu.id IS NULL
    AND i.invoice_date >= date('now', '-12 months')
)
SELECT
  ROUND(pp.avg_days, 1) AS portal_avg_days,
  ROUND(np.avg_days, 1) AS non_portal_avg_days,
  ROUND(np.avg_days - pp.avg_days, 1) AS days_faster
FROM portal_payments pp, non_portal_payments np;
```

### Portal Event Activity Summary
```sql
SELECT
  pe.event_type,
  COUNT(*) AS event_count,
  COUNT(DISTINCT pe.portal_user_id) AS unique_users
FROM portal_events pe
WHERE pe.event_at >= date('now', '-12 months')
GROUP BY pe.event_type
ORDER BY event_count DESC;
```

### Monthly Adoption Trend
```sql
SELECT
  strftime('%Y-%m', pu.registered_at) AS month,
  COUNT(*) AS new_registrations,
  SUM(COUNT(*)) OVER (ORDER BY strftime('%Y-%m', pu.registered_at)) AS cumulative_users
FROM portal_users pu
WHERE pu.registered_at >= date('now', '-24 months')
GROUP BY strftime('%Y-%m', pu.registered_at)
ORDER BY month;
```

### Active vs Inactive Portal Users
```sql
WITH last_activity AS (
  SELECT
    portal_user_id,
    MAX(event_at) AS last_event
  FROM portal_events
  GROUP BY portal_user_id
)
SELECT
  CASE
    WHEN la.last_event >= date('now', '-30 days') THEN 'Active (30d)'
    WHEN la.last_event >= date('now', '-90 days') THEN 'Recent (90d)'
    WHEN la.last_event >= date('now', '-365 days') THEN 'Dormant (1yr)'
    ELSE 'Inactive (>1yr)'
  END AS activity_status,
  COUNT(*) AS user_count
FROM portal_users pu
LEFT JOIN last_activity la ON la.portal_user_id = pu.id
GROUP BY activity_status
ORDER BY
  CASE activity_status
    WHEN 'Active (30d)' THEN 1
    WHEN 'Recent (90d)' THEN 2
    WHEN 'Dormant (1yr)' THEN 3
    ELSE 4
  END;
```

### Branch Adoption Comparison
```sql
SELECT
  c.branch,
  COUNT(DISTINCT c.id) AS customers,
  COUNT(DISTINCT pu.customer_id) AS portal_users,
  ROUND(100.0 * COUNT(DISTINCT pu.customer_id) / COUNT(DISTINCT c.id), 1) AS adoption_pct
FROM customers c
LEFT JOIN portal_users pu ON pu.customer_id = c.id
GROUP BY c.branch
ORDER BY adoption_pct DESC;
```

## Laravel Alternative

```php
use App\Models\Customer;
use App\Models\PortalUser;
use App\Models\PortalEvent;

// Adoption by segment
$adoption = Customer::query()
    ->selectRaw("
        segment,
        COUNT(DISTINCT customers.id) as total,
        COUNT(DISTINCT portal_users.customer_id) as with_portal
    ")
    ->leftJoin('portal_users', 'portal_users.customer_id', '=', 'customers.id')
    ->groupBy('segment')
    ->get()
    ->map(fn($row) => [
        'segment' => $row->segment,
        'adoption_pct' => round(100 * $row->with_portal / $row->total, 1)
    ]);

// Event summary
$events = PortalEvent::query()
    ->selectRaw('event_type, COUNT(*) as count')
    ->where('event_at', '>=', now()->subYear())
    ->groupBy('event_type')
    ->orderByDesc('count')
    ->get();
```

## Output Format

### Executive Summary
> "Portal adoption stands at 52% overall, with Commercial leading at 65%. E-billing is enabled for 70% of portal users, saving an estimated $X in paper/postage annually. Portal users pay invoices 7 days faster on average."

### Adoption Table
| Segment | Customers | Portal Users | Adoption % | E-Billing % | Autopay % |
|---------|-----------|--------------|------------|-------------|-----------|
| Commercial | 150 | 98 | 65% | 75% | 45% |
| Residential | 300 | 142 | 47% | 68% | 35% |
| Retail Dealer | 50 | 28 | 56% | 82% | 60% |

### Feature Usage (Last 12 Months)
| Event Type | Count | Unique Users |
|------------|-------|--------------|
| login | 12,450 | 245 |
| view_statement | 3,200 | 180 |
| order_fuel | 1,850 | 120 |
| download_invoice | 1,400 | 95 |
| pay_by_card | 980 | 85 |
| export_hst_csv | 450 | 45 |

### Business Impact
- **Payment Speed:** Portal users pay 7 days faster → improved cash flow
- **Paper Savings:** 70% on e-billing → ~$X/year in postage/printing
- **Self-Service:** 1,850 online fuel orders → reduced call center load

### Recommended Actions
1. Target residential customers for portal enrollment campaigns
2. Promote autopay to reduce AR aging
3. Re-engage dormant portal users with email campaigns
4. Add HST export reminder emails during tax season

## Follow-up Questions

- "Want to see the trend over the past 2 years?"
- "Should I identify customers who aren't on the portal yet?"
- "Would you like to see adoption by branch?"
- "Should I calculate the cash flow impact of faster payments?"
