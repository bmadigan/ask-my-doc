# Commercial Fleet Usage Analysis

Analyze how commercial customers use bulk delivery vs cardlock, and identify optimization opportunities.

## When to Use

User asks questions like:
- "Are commercial customers over-relying on cardlock?"
- "Which fleets would benefit from bulk tanks?"
- "What's the cardlock vs bulk split?"
- "Show me fleet fuel usage patterns"
- "Who are our biggest cardlock users?"

## Workflow

1. **Segment commercial customers** by fuel acquisition method
2. **Calculate usage ratios** - Bulk vs cardlock percentages
3. **Identify patterns** - Transaction sizes, frequency, timing
4. **Find opportunities** - Customers who could optimize their mix
5. **Recommend actions** - Bulk tank installations, fleet cards

## Key Metrics

| Metric | Description |
|--------|-------------|
| Bulk Litres | Volume from scheduled deliveries |
| Cardlock Litres | Volume from cardlock transactions |
| Cardlock Share % | Cardlock ÷ total fuel consumption |
| Avg Transaction Size | Typical cardlock fill amount |
| Transaction Count | Number of cardlock transactions |
| Unique Vehicles | Distinct vehicles using cardlock |
| Peak Hours | Most common fueling times |

## SQL Patterns

### Customer Fuel Mix Overview
```sql
WITH bulk AS (
  SELECT
    customer_id,
    SUM(litres_delivered) AS bulk_litres
  FROM deliveries
  WHERE status = 'completed'
    AND delivery_date >= date('now', '-12 months')
  GROUP BY customer_id
),
card AS (
  SELECT
    customer_id,
    SUM(litres) AS card_litres,
    COUNT(*) AS txn_count,
    COUNT(DISTINCT vehicle_id) AS vehicles
  FROM cardlock_transactions
  WHERE txn_datetime >= date('now', '-12 months')
  GROUP BY customer_id
)
SELECT
  c.name,
  c.branch,
  COALESCE(b.bulk_litres, 0) AS bulk_litres,
  COALESCE(card.card_litres, 0) AS card_litres,
  COALESCE(b.bulk_litres, 0) + COALESCE(card.card_litres, 0) AS total_litres,
  ROUND(100.0 * COALESCE(card.card_litres, 0) /
    NULLIF(COALESCE(b.bulk_litres, 0) + COALESCE(card.card_litres, 0), 0), 1) AS cardlock_share_pct,
  COALESCE(card.txn_count, 0) AS cardlock_txns,
  COALESCE(card.vehicles, 0) AS unique_vehicles
FROM customers c
LEFT JOIN bulk b ON b.customer_id = c.id
LEFT JOIN card card ON card.customer_id = c.id
WHERE c.segment = 'commercial'
  AND (b.bulk_litres > 0 OR card.card_litres > 0)
ORDER BY total_litres DESC;
```

### Categorize by Usage Pattern
```sql
WITH fuel_mix AS (
  SELECT
    c.id,
    c.name,
    COALESCE(SUM(d.litres_delivered), 0) AS bulk,
    COALESCE(SUM(ct.litres), 0) AS card
  FROM customers c
  LEFT JOIN deliveries d ON d.customer_id = c.id
    AND d.status = 'completed'
    AND d.delivery_date >= date('now', '-12 months')
  LEFT JOIN cardlock_transactions ct ON ct.customer_id = c.id
    AND ct.txn_datetime >= date('now', '-12 months')
  WHERE c.segment = 'commercial'
  GROUP BY c.id, c.name
)
SELECT
  CASE
    WHEN card = 0 THEN 'Bulk Only'
    WHEN bulk = 0 THEN 'Cardlock Only'
    WHEN card / (bulk + card) < 0.3 THEN 'Mostly Bulk'
    WHEN card / (bulk + card) > 0.7 THEN 'Mostly Cardlock'
    ELSE 'Balanced'
  END AS usage_category,
  COUNT(*) AS customer_count,
  ROUND(SUM(bulk + card), 0) AS total_litres
FROM fuel_mix
WHERE bulk + card > 0
GROUP BY usage_category
ORDER BY total_litres DESC;
```

### High Cardlock Users (Bulk Tank Candidates)
```sql
WITH fuel_mix AS (
  SELECT
    c.id,
    c.name,
    c.branch,
    COALESCE(SUM(d.litres_delivered), 0) AS bulk,
    COALESCE(SUM(ct.litres), 0) AS card,
    COUNT(DISTINCT ct.id) AS txn_count
  FROM customers c
  LEFT JOIN deliveries d ON d.customer_id = c.id
    AND d.status = 'completed'
    AND d.delivery_date >= date('now', '-12 months')
  LEFT JOIN cardlock_transactions ct ON ct.customer_id = c.id
    AND ct.txn_datetime >= date('now', '-12 months')
  WHERE c.segment = 'commercial'
  GROUP BY c.id, c.name, c.branch
)
SELECT
  name,
  branch,
  card AS cardlock_litres,
  txn_count AS transactions,
  ROUND(card / NULLIF(txn_count, 0), 0) AS avg_fill_size,
  ROUND(100.0 * card / (bulk + card), 1) AS cardlock_share_pct
FROM fuel_mix
WHERE card > 50000  -- High cardlock volume
  AND card / (bulk + card) > 0.7  -- Mostly cardlock
ORDER BY card DESC
LIMIT 20;
```

### Cardlock Transaction Patterns
```sql
SELECT
  c.name,
  strftime('%H', ct.txn_datetime) AS hour,
  COUNT(*) AS txn_count,
  ROUND(AVG(ct.litres), 1) AS avg_litres
FROM cardlock_transactions ct
JOIN customers c ON c.id = ct.customer_id
WHERE ct.txn_datetime >= date('now', '-12 months')
GROUP BY c.name, hour
ORDER BY c.name, hour;
```

### Time-of-Day Analysis (Fleet-wide)
```sql
SELECT
  CASE
    WHEN CAST(strftime('%H', txn_datetime) AS INTEGER) BETWEEN 5 AND 8 THEN 'Early Morning (5-8)'
    WHEN CAST(strftime('%H', txn_datetime) AS INTEGER) BETWEEN 9 AND 11 THEN 'Morning (9-11)'
    WHEN CAST(strftime('%H', txn_datetime) AS INTEGER) BETWEEN 12 AND 14 THEN 'Midday (12-2)'
    WHEN CAST(strftime('%H', txn_datetime) AS INTEGER) BETWEEN 15 AND 17 THEN 'Afternoon (3-5)'
    WHEN CAST(strftime('%H', txn_datetime) AS INTEGER) BETWEEN 18 AND 20 THEN 'Evening (6-8)'
    ELSE 'Night (9PM-4AM)'
  END AS time_slot,
  COUNT(*) AS txn_count,
  ROUND(SUM(litres), 0) AS total_litres,
  ROUND(AVG(litres), 1) AS avg_fill
FROM cardlock_transactions
WHERE txn_datetime >= date('now', '-12 months')
GROUP BY time_slot
ORDER BY txn_count DESC;
```

### Vehicle Fleet Analysis
```sql
SELECT
  c.name AS customer,
  COUNT(DISTINCT ct.vehicle_id) AS fleet_size,
  COUNT(ct.id) AS total_txns,
  ROUND(SUM(ct.litres), 0) AS total_litres,
  ROUND(SUM(ct.litres) / COUNT(DISTINCT ct.vehicle_id), 0) AS litres_per_vehicle,
  ROUND(COUNT(ct.id) * 1.0 / COUNT(DISTINCT ct.vehicle_id), 1) AS txns_per_vehicle
FROM cardlock_transactions ct
JOIN customers c ON c.id = ct.customer_id
WHERE ct.txn_datetime >= date('now', '-12 months')
GROUP BY c.id, c.name
HAVING COUNT(DISTINCT ct.vehicle_id) > 1
ORDER BY fleet_size DESC;
```

### Cardlock Site Usage
```sql
SELECT
  s.name AS cardlock_site,
  s.city,
  COUNT(ct.id) AS transactions,
  COUNT(DISTINCT ct.customer_id) AS unique_customers,
  ROUND(SUM(ct.litres), 0) AS total_litres,
  ROUND(AVG(ct.litres), 1) AS avg_fill
FROM cardlock_transactions ct
JOIN sites s ON s.id = ct.site_id
WHERE ct.txn_datetime >= date('now', '-12 months')
GROUP BY s.id, s.name, s.city
ORDER BY total_litres DESC;
```

## Laravel Alternative

```php
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\CardlockTransaction;

// Fuel mix by customer
$customers = Customer::commercial()
    ->select('customers.*')
    ->selectRaw('COALESCE(SUM(deliveries.litres_delivered), 0) as bulk_litres')
    ->selectRaw('COALESCE((
        SELECT SUM(litres) FROM cardlock_transactions
        WHERE customer_id = customers.id
        AND txn_datetime >= ?
    ), 0) as card_litres', [now()->subYear()])
    ->leftJoin('deliveries', function($join) {
        $join->on('deliveries.customer_id', '=', 'customers.id')
             ->where('deliveries.status', 'completed')
             ->where('deliveries.delivery_date', '>=', now()->subYear());
    })
    ->groupBy('customers.id')
    ->get();

// High cardlock users
$cardlockHeavy = CardlockTransaction::query()
    ->select('customer_id')
    ->selectRaw('SUM(litres) as total_litres')
    ->selectRaw('COUNT(*) as txn_count')
    ->where('txn_datetime', '>=', now()->subYear())
    ->groupBy('customer_id')
    ->having('total_litres', '>', 50000)
    ->orderByDesc('total_litres')
    ->get();
```

## Output Format

### Executive Summary
> "Commercial customers consumed 4.5M litres in the past 12 months, split 60% bulk delivery and 40% cardlock. We've identified 8 high-volume cardlock users (200K+ litres/year) who could save 10-15% by installing on-site bulk tanks."

### Fuel Mix Categories
| Category | Customers | Total Litres | Avg/Customer |
|----------|-----------|--------------|--------------|
| Mostly Bulk (<30% card) | 45 | 2.1M | 47K |
| Balanced (30-70%) | 35 | 1.2M | 34K |
| Mostly Cardlock (>70%) | 28 | 850K | 30K |
| Cardlock Only | 22 | 350K | 16K |

### Bulk Tank Candidates (High Cardlock Volume)
| Customer | Branch | Cardlock Litres | Transactions | Avg Fill | Vehicles |
|----------|--------|-----------------|--------------|----------|----------|
| ABC Trucking | Sudbury | 285K | 1,420 | 201L | 25 |
| XYZ Construction | North Bay | 210K | 890 | 236L | 18 |
| Highway Logistics | Timmins | 180K | 1,100 | 164L | 22 |

### Usage Patterns
- **Peak fueling:** 5-8 AM (42% of transactions)
- **Avg transaction:** 185 litres
- **Busiest cardlock:** Highway 17 Station (45K litres/month)

### Cost Optimization Opportunities
| Customer | Current Method | Annual Litres | Potential Savings |
|----------|----------------|---------------|-------------------|
| ABC Trucking | 95% Cardlock | 285K | ~$8,500/yr |
| XYZ Construction | 88% Cardlock | 210K | ~$6,300/yr |

### Recommended Actions
1. **Sales outreach** to top 8 cardlock-heavy customers about bulk tanks
2. **ROI analysis** for bulk tank installation (payback typically 18-24 months)
3. **Fleet card review** for customers with high transaction counts
4. **Route optimization** for bulk delivery customers

## Follow-up Questions

- "Want to see detailed analysis for a specific customer?"
- "Should I calculate ROI for bulk tank installation?"
- "Want to see cardlock usage by time of day?"
- "Should I identify underutilized cardlock sites?"
