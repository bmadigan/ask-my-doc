# Delivery Risk Report

Identify customers and sites at risk of running out of fuel, and analyze delivery performance patterns.

## When to Use

User asks questions like:
- "Where are we at risk of runouts?"
- "Show me sites that need emergency fills"
- "Which customers often order when tanks are low?"
- "What's our failed delivery rate?"
- "Priority deliveries for this week?"

## Workflow

1. **Identify risk type** - Low tanks, emergency patterns, or delivery failures
2. **Set thresholds** - Tank % cutoff (default 20%), time window
3. **Run analysis** - Find at-risk sites and customers
4. **Prioritize results** - By risk level, segment, branch
5. **Recommend actions** - Routing priorities, auto-delivery conversion

## Key Metrics

| Metric | Description |
|--------|-------------|
| Tank Level % | Current estimated tank percentage |
| Days to Empty | Estimated days until runout |
| Emergency Fill Rate | % of deliveries ordered at <15% tank |
| Failed Delivery % | Deliveries with status = 'failed' |
| Will-Call Risk | Non-auto customers with low tanks |
| Avg Tank at Order | Tank % when customers typically order |

## SQL Patterns

### Current Low Tank Sites
```sql
WITH latest_readings AS (
  SELECT
    site_id,
    MAX(reading_at) AS last_reading
  FROM tank_readings
  GROUP BY site_id
)
SELECT
  s.id AS site_id,
  s.name AS site_name,
  c.name AS customer_name,
  c.segment,
  c.branch,
  c.is_automatic_delivery,
  tr.estimated_pct,
  tr.estimated_litres,
  s.tank_capacity_litres,
  tr.reading_at AS last_reading
FROM tank_readings tr
JOIN latest_readings lr ON lr.site_id = tr.site_id AND lr.last_reading = tr.reading_at
JOIN sites s ON s.id = tr.site_id
JOIN customers c ON c.id = s.customer_id
WHERE tr.estimated_pct < 20
ORDER BY tr.estimated_pct ASC;
```

### Critical Risk (Below 10%)
```sql
WITH latest_readings AS (
  SELECT site_id, MAX(reading_at) AS last_reading
  FROM tank_readings
  GROUP BY site_id
)
SELECT
  c.branch,
  COUNT(*) AS critical_sites,
  GROUP_CONCAT(s.name, ', ') AS site_names
FROM tank_readings tr
JOIN latest_readings lr ON lr.site_id = tr.site_id AND lr.last_reading = tr.reading_at
JOIN sites s ON s.id = tr.site_id
JOIN customers c ON c.id = s.customer_id
WHERE tr.estimated_pct < 10
GROUP BY c.branch
ORDER BY critical_sites DESC;
```

### Emergency Fill Pattern Analysis
```sql
SELECT
  c.segment,
  COUNT(*) AS total_deliveries,
  SUM(CASE WHEN d.estimated_tank_pct_before < 15 THEN 1 ELSE 0 END) AS emergency_fills,
  ROUND(100.0 * SUM(CASE WHEN d.estimated_tank_pct_before < 15 THEN 1 ELSE 0 END)
    / COUNT(*), 1) AS emergency_pct,
  ROUND(AVG(d.estimated_tank_pct_before), 1) AS avg_tank_at_order
FROM deliveries d
JOIN customers c ON c.id = d.customer_id
WHERE d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY c.segment;
```

### Failed Delivery Analysis
```sql
SELECT
  c.segment,
  c.branch,
  COUNT(*) AS total_deliveries,
  SUM(CASE WHEN d.status = 'failed' THEN 1 ELSE 0 END) AS failed,
  SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
  ROUND(100.0 * SUM(CASE WHEN d.status = 'failed' THEN 1 ELSE 0 END) / COUNT(*), 2) AS fail_rate_pct
FROM deliveries d
JOIN customers c ON c.id = d.customer_id
WHERE d.delivery_date >= date('now', '-12 months')
GROUP BY c.segment, c.branch
ORDER BY fail_rate_pct DESC;
```

### Will-Call Customers with Low Tanks
```sql
WITH latest_readings AS (
  SELECT site_id, MAX(reading_at) AS last_reading
  FROM tank_readings
  GROUP BY site_id
)
SELECT
  c.name AS customer_name,
  s.name AS site_name,
  c.branch,
  tr.estimated_pct,
  c.is_automatic_delivery
FROM tank_readings tr
JOIN latest_readings lr ON lr.site_id = tr.site_id AND lr.last_reading = tr.reading_at
JOIN sites s ON s.id = tr.site_id
JOIN customers c ON c.id = s.customer_id
WHERE tr.estimated_pct < 25
  AND c.is_automatic_delivery = 0
ORDER BY tr.estimated_pct ASC;
```

### Delivery Frequency by Customer
```sql
SELECT
  c.name,
  c.segment,
  COUNT(d.id) AS delivery_count,
  MIN(d.delivery_date) AS first_delivery,
  MAX(d.delivery_date) AS last_delivery,
  ROUND(julianday(MAX(d.delivery_date)) - julianday(MIN(d.delivery_date))) /
    NULLIF(COUNT(d.id) - 1, 0) AS avg_days_between
FROM deliveries d
JOIN customers c ON c.id = d.customer_id
WHERE d.status = 'completed'
  AND d.delivery_date >= date('now', '-24 months')
GROUP BY c.id, c.name, c.segment
HAVING COUNT(d.id) > 1
ORDER BY avg_days_between DESC
LIMIT 20;
```

### Seasonal Risk (Heating Season)
```sql
SELECT
  strftime('%m', d.delivery_date) AS month,
  COUNT(*) AS deliveries,
  SUM(CASE WHEN d.estimated_tank_pct_before < 15 THEN 1 ELSE 0 END) AS emergency_fills,
  ROUND(100.0 * SUM(CASE WHEN d.estimated_tank_pct_before < 15 THEN 1 ELSE 0 END)
    / COUNT(*), 1) AS emergency_pct
FROM deliveries d
JOIN customers c ON c.id = d.customer_id
WHERE c.segment = 'residential'
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-24 months')
GROUP BY strftime('%m', d.delivery_date)
ORDER BY month;
```

## Laravel Alternative

```php
use App\Models\TankReading;
use App\Models\Delivery;

// Low tank sites
$lowTanks = TankReading::query()
    ->select('tank_readings.*', 'sites.name as site_name', 'customers.name as customer_name')
    ->join('sites', 'sites.id', '=', 'tank_readings.site_id')
    ->join('customers', 'customers.id', '=', 'sites.customer_id')
    ->whereIn('tank_readings.id', function($q) {
        $q->selectRaw('MAX(id)')
          ->from('tank_readings')
          ->groupBy('site_id');
    })
    ->where('estimated_pct', '<', 20)
    ->orderBy('estimated_pct')
    ->get();

// Failed deliveries
$failedRate = Delivery::query()
    ->selectRaw("
        status,
        COUNT(*) as count,
        ROUND(100.0 * COUNT(*) / SUM(COUNT(*)) OVER(), 1) as pct
    ")
    ->where('delivery_date', '>=', now()->subYear())
    ->groupBy('status')
    ->get();
```

## Output Format

### Executive Summary
> "We have 12 sites below 20% tank level requiring priority attention this week. 8 of these are will-call customers who haven't ordered yet. Our emergency fill rate is 8% overall, but spikes to 15% during peak heating months."

### Critical Sites (< 20% Tank)
| Site | Customer | Branch | Tank % | Litres Left | Auto-Delivery |
|------|----------|--------|--------|-------------|---------------|
| 123 Maple St | Smith Residence | Sudbury | 8% | 80L | No |
| Main Yard | ABC Trucking | North Bay | 12% | 600L | Yes |
| Station #4 | Quick Stop Gas | Timmins | 15% | 1,500L | Yes |

### Risk by Segment
| Segment | Low Tank Sites | Emergency Fill % | Avg Tank at Order |
|---------|----------------|------------------|-------------------|
| Residential | 8 | 12% | 28% |
| Commercial | 3 | 5% | 35% |
| Retail Dealer | 1 | 3% | 40% |

### Delivery Performance
| Status | Count | % |
|--------|-------|---|
| Completed | 14,250 | 97.2% |
| Failed | 320 | 2.2% |
| Cancelled | 95 | 0.6% |

### Recommended Actions
1. **Immediate:** Schedule deliveries for 8 will-call sites below 15%
2. **This week:** Contact 4 additional sites approaching 20%
3. **Conversion opportunity:** 12 high-emergency customers should switch to auto-delivery
4. **Route optimization:** Cluster Sudbury deliveries (5 low-tank sites)

## Follow-up Questions

- "Should I list the specific customers to contact today?"
- "Want to see the seasonal pattern for emergency fills?"
- "Should I identify candidates for auto-delivery conversion?"
- "Want to analyze failed delivery reasons by branch?"
