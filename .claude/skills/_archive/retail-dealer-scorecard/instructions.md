# Retail Dealer Scorecard

Score and rank retail fuel dealers on volume, revenue, and performance metrics.

## When to Use

User asks questions like:
- "Which dealers are underperforming?"
- "How are Esso vs Shell sites doing?"
- "Rank our dealers by volume"
- "Which dealers are growing fastest?"
- "Show me the dealer network scorecard"

## Workflow

1. **Define scope** - All dealers, specific brand, specific region
2. **Set time period** - Current period vs comparison period
3. **Calculate metrics** - Volume, revenue, growth, ranking
4. **Identify patterns** - Brand performance, regional trends
5. **Recommend actions** - Support plans, growth opportunities

## Key Metrics

| Metric | Description |
|--------|-------------|
| Total Litres | Sum of fuel delivered to dealer sites |
| Revenue | Litres × unit price |
| Site Count | Number of retail stations per dealer |
| Avg Litres/Site | Volume efficiency per location |
| YoY Growth % | Volume change vs prior year |
| Brand Mix | Distribution across Esso/Mobil/Shell/Pump |
| Delivery Frequency | Average deliveries per month |

## SQL Patterns

### Dealer Ranking by Volume
```sql
SELECT
  c.id AS dealer_id,
  c.name AS dealer_name,
  c.branch,
  COUNT(DISTINCT s.id) AS site_count,
  ROUND(SUM(d.litres_delivered), 0) AS total_litres,
  ROUND(SUM(d.litres_delivered * d.unit_price), 2) AS total_revenue,
  ROUND(SUM(d.litres_delivered) / COUNT(DISTINCT s.id), 0) AS litres_per_site,
  RANK() OVER (ORDER BY SUM(d.litres_delivered) DESC) AS volume_rank
FROM customers c
JOIN sites s ON s.customer_id = c.id
JOIN deliveries d ON d.site_id = s.id
WHERE c.segment = 'retail_dealer'
  AND s.site_type = 'retail_station'
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY c.id, c.name, c.branch
ORDER BY total_litres DESC;
```

### Performance by Brand
```sql
SELECT
  s.brand,
  COUNT(DISTINCT s.id) AS site_count,
  COUNT(DISTINCT c.id) AS dealer_count,
  ROUND(SUM(d.litres_delivered), 0) AS total_litres,
  ROUND(SUM(d.litres_delivered) / COUNT(DISTINCT s.id), 0) AS avg_litres_per_site,
  ROUND(SUM(d.litres_delivered * d.unit_price), 2) AS total_revenue
FROM sites s
JOIN customers c ON c.id = s.customer_id
JOIN deliveries d ON d.site_id = s.id
WHERE c.segment = 'retail_dealer'
  AND s.site_type = 'retail_station'
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY s.brand
ORDER BY total_litres DESC;
```

### Year-over-Year Growth by Dealer
```sql
WITH current_year AS (
  SELECT
    c.id AS dealer_id,
    c.name AS dealer_name,
    SUM(d.litres_delivered) AS litres
  FROM customers c
  JOIN sites s ON s.customer_id = c.id
  JOIN deliveries d ON d.site_id = s.id
  WHERE c.segment = 'retail_dealer'
    AND d.status = 'completed'
    AND d.delivery_date >= date('now', '-12 months')
  GROUP BY c.id, c.name
),
prior_year AS (
  SELECT
    c.id AS dealer_id,
    SUM(d.litres_delivered) AS litres
  FROM customers c
  JOIN sites s ON s.customer_id = c.id
  JOIN deliveries d ON d.site_id = s.id
  WHERE c.segment = 'retail_dealer'
    AND d.status = 'completed'
    AND d.delivery_date >= date('now', '-24 months')
    AND d.delivery_date < date('now', '-12 months')
  GROUP BY c.id
)
SELECT
  cy.dealer_name,
  cy.litres AS current_litres,
  COALESCE(py.litres, 0) AS prior_litres,
  ROUND(100.0 * (cy.litres - COALESCE(py.litres, 0)) / NULLIF(py.litres, 0), 1) AS growth_pct
FROM current_year cy
LEFT JOIN prior_year py ON py.dealer_id = cy.dealer_id
ORDER BY growth_pct DESC;
```

### Site-Level Detail for a Dealer
```sql
SELECT
  s.name AS site_name,
  s.brand,
  s.city,
  ROUND(SUM(d.litres_delivered), 0) AS litres,
  COUNT(d.id) AS delivery_count,
  ROUND(AVG(d.litres_delivered), 0) AS avg_delivery_size
FROM sites s
JOIN deliveries d ON d.site_id = s.id
WHERE s.customer_id = :dealer_id
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY s.id, s.name, s.brand, s.city
ORDER BY litres DESC;
```

### Underperforming Sites (Below Network Average)
```sql
WITH network_avg AS (
  SELECT AVG(site_litres) AS avg_litres
  FROM (
    SELECT s.id, SUM(d.litres_delivered) AS site_litres
    FROM sites s
    JOIN customers c ON c.id = s.customer_id
    JOIN deliveries d ON d.site_id = s.id
    WHERE c.segment = 'retail_dealer'
      AND s.site_type = 'retail_station'
      AND d.status = 'completed'
      AND d.delivery_date >= date('now', '-12 months')
    GROUP BY s.id
  )
)
SELECT
  c.name AS dealer_name,
  s.name AS site_name,
  s.brand,
  s.city,
  ROUND(SUM(d.litres_delivered), 0) AS site_litres,
  ROUND(na.avg_litres, 0) AS network_avg,
  ROUND(100.0 * SUM(d.litres_delivered) / na.avg_litres, 1) AS pct_of_avg
FROM sites s
JOIN customers c ON c.id = s.customer_id
JOIN deliveries d ON d.site_id = s.id
CROSS JOIN network_avg na
WHERE c.segment = 'retail_dealer'
  AND s.site_type = 'retail_station'
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY s.id, c.name, s.name, s.brand, s.city, na.avg_litres
HAVING SUM(d.litres_delivered) < na.avg_litres * 0.7
ORDER BY pct_of_avg ASC;
```

### Product Mix by Dealer
```sql
SELECT
  c.name AS dealer_name,
  fp.name AS product,
  ROUND(SUM(d.litres_delivered), 0) AS litres,
  ROUND(100.0 * SUM(d.litres_delivered) / SUM(SUM(d.litres_delivered)) OVER (PARTITION BY c.id), 1) AS pct_of_total
FROM customers c
JOIN sites s ON s.customer_id = c.id
JOIN deliveries d ON d.site_id = s.id
JOIN fuel_products fp ON fp.id = d.fuel_product_id
WHERE c.segment = 'retail_dealer'
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY c.id, c.name, fp.name
ORDER BY c.name, litres DESC;
```

## Laravel Alternative

```php
use App\Models\Customer;
use App\Models\Site;
use App\Models\Delivery;

// Dealer ranking
$dealers = Customer::retailDealer()
    ->select('customers.*')
    ->selectRaw('SUM(deliveries.litres_delivered) as total_litres')
    ->selectRaw('COUNT(DISTINCT sites.id) as site_count')
    ->join('sites', 'sites.customer_id', '=', 'customers.id')
    ->join('deliveries', 'deliveries.site_id', '=', 'sites.id')
    ->where('deliveries.status', 'completed')
    ->where('deliveries.delivery_date', '>=', now()->subYear())
    ->groupBy('customers.id')
    ->orderByDesc('total_litres')
    ->get();

// Brand performance
$brands = Site::query()
    ->select('brand')
    ->selectRaw('COUNT(DISTINCT sites.id) as sites')
    ->selectRaw('SUM(deliveries.litres_delivered) as litres')
    ->join('customers', 'customers.id', '=', 'sites.customer_id')
    ->join('deliveries', 'deliveries.site_id', '=', 'sites.id')
    ->where('customers.segment', 'retail_dealer')
    ->where('site_type', 'retail_station')
    ->where('deliveries.status', 'completed')
    ->groupBy('brand')
    ->get();
```

## Output Format

### Executive Summary
> "Our retail dealer network delivered 8.2M litres across 50 dealers and 85 sites in the past 12 months. Pump-branded sites are growing fastest at +18% YoY, while 12 sites are underperforming the network average by 30% or more."

### Top 10 Dealers by Volume
| Rank | Dealer | Branch | Sites | Litres | Revenue | YoY Growth |
|------|--------|--------|-------|--------|---------|------------|
| 1 | ABC Petroleum | Sudbury | 3 | 1.2M | $1.4M | +12% |
| 2 | Quick Stop Inc | North Bay | 2 | 950K | $1.1M | +8% |
| 3 | Highway Fuels | Timmins | 2 | 820K | $950K | -3% |

### Performance by Brand
| Brand | Sites | Litres | Avg/Site | Growth % |
|-------|-------|--------|----------|----------|
| Esso | 25 | 3.1M | 124K | +5% |
| Pump | 30 | 2.8M | 93K | +18% |
| Shell | 15 | 1.5M | 100K | +2% |
| Mobil | 10 | 650K | 65K | -5% |

### Underperforming Sites
| Dealer | Site | Brand | Litres | % of Avg |
|--------|------|-------|--------|----------|
| XYZ Gas | Station #3 | Mobil | 45K | 47% |
| Highway Fuels | North Outlet | Esso | 52K | 54% |

### Recommended Actions
1. **Support plan** for 5 dealers with declining volumes
2. **Growth investment** in Pump-brand expansion (fastest growing)
3. **Site review** for 12 underperforming locations
4. **Pricing analysis** for Mobil sites (lowest growth)

## Follow-up Questions

- "Should I drill into a specific dealer's sites?"
- "Want to see the monthly trend for top dealers?"
- "Should I compare brand performance by region?"
- "Want to identify dealers ready for expansion?"
