# Segment KPI Overview

Generate at-a-glance performance reports for business segments (Residential, Commercial, Retail Dealer).

## When to Use

User asks questions like:
- "How is residential doing overall?"
- "Give me a snapshot of commercial performance last quarter"
- "Compare all three segments this year"
- "What's our residential heating oil performance?"

## Workflow

1. **Identify the segment(s)** - residential, commercial, retail_dealer, or all
2. **Determine time range** - default to last 12 months if not specified
3. **Run the analysis queries**
4. **Present results** in business-friendly format

## Key Metrics to Calculate

| Metric | Description |
|--------|-------------|
| Active Customers | Distinct customers with deliveries in period |
| Total Litres | Sum of completed deliveries |
| Total Revenue | Litres × unit price |
| Avg Revenue/Customer | Revenue ÷ active customers |
| Delivery Count | Number of completed deliveries |
| Auto-Delivery % | % of customers on automatic delivery |
| Portal Adoption % | % with portal accounts |
| On-Time Rate % | % deliveries not marked failed |

## SQL Patterns

### Full Segment Overview
```sql
SELECT
  c.segment,
  COUNT(DISTINCT c.id) AS active_customers,
  COUNT(d.id) AS delivery_count,
  ROUND(SUM(d.litres_delivered), 0) AS total_litres,
  ROUND(SUM(d.litres_delivered * d.unit_price), 2) AS total_revenue,
  ROUND(SUM(d.litres_delivered * d.unit_price) / COUNT(DISTINCT c.id), 2) AS avg_revenue_per_customer,
  ROUND(100.0 * SUM(CASE WHEN c.is_automatic_delivery = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT c.id), 1) AS auto_delivery_pct
FROM customers c
JOIN deliveries d ON d.customer_id = c.id
WHERE d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY c.segment
ORDER BY total_revenue DESC;
```

### Single Segment with Product Breakdown
```sql
SELECT
  fp.name AS product,
  COUNT(DISTINCT d.customer_id) AS customers,
  ROUND(SUM(d.litres_delivered), 0) AS litres,
  ROUND(SUM(d.litres_delivered * d.unit_price), 2) AS revenue
FROM deliveries d
JOIN customers c ON c.id = d.customer_id
JOIN fuel_products fp ON fp.id = d.fuel_product_id
WHERE c.segment = :segment
  AND d.status = 'completed'
  AND d.delivery_date BETWEEN :from AND :to
GROUP BY fp.name
ORDER BY revenue DESC;
```

### Year-over-Year Comparison
```sql
WITH current_year AS (
  SELECT c.segment,
         SUM(d.litres_delivered) AS litres,
         SUM(d.litres_delivered * d.unit_price) AS revenue
  FROM deliveries d
  JOIN customers c ON c.id = d.customer_id
  WHERE d.status = 'completed'
    AND d.delivery_date >= date('now', '-12 months')
  GROUP BY c.segment
),
prior_year AS (
  SELECT c.segment,
         SUM(d.litres_delivered) AS litres,
         SUM(d.litres_delivered * d.unit_price) AS revenue
  FROM deliveries d
  JOIN customers c ON c.id = d.customer_id
  WHERE d.status = 'completed'
    AND d.delivery_date >= date('now', '-24 months')
    AND d.delivery_date < date('now', '-12 months')
  GROUP BY c.segment
)
SELECT
  cy.segment,
  cy.litres AS current_litres,
  py.litres AS prior_litres,
  ROUND(100.0 * (cy.litres - py.litres) / py.litres, 1) AS litres_growth_pct,
  cy.revenue AS current_revenue,
  py.revenue AS prior_revenue,
  ROUND(100.0 * (cy.revenue - py.revenue) / py.revenue, 1) AS revenue_growth_pct
FROM current_year cy
LEFT JOIN prior_year py ON py.segment = cy.segment;
```

### Branch Performance within Segment
```sql
SELECT
  c.branch,
  COUNT(DISTINCT c.id) AS customers,
  ROUND(SUM(d.litres_delivered), 0) AS litres,
  ROUND(SUM(d.litres_delivered * d.unit_price), 2) AS revenue
FROM deliveries d
JOIN customers c ON c.id = d.customer_id
WHERE c.segment = :segment
  AND d.status = 'completed'
  AND d.delivery_date >= date('now', '-12 months')
GROUP BY c.branch
ORDER BY revenue DESC;
```

## Laravel Alternative

```php
use App\Models\Customer;
use App\Models\Delivery;

// Get segment metrics
$metrics = Customer::query()
    ->selectRaw("
        segment,
        COUNT(DISTINCT customers.id) as active_customers,
        SUM(deliveries.litres_delivered) as total_litres,
        SUM(deliveries.litres_delivered * deliveries.unit_price) as total_revenue
    ")
    ->join('deliveries', 'deliveries.customer_id', '=', 'customers.id')
    ->where('deliveries.status', 'completed')
    ->where('deliveries.delivery_date', '>=', now()->subYear())
    ->groupBy('segment')
    ->get();
```

## Output Format

### Executive Summary
> "Residential continues to be our largest segment by customer count but Commercial drives the highest revenue per customer. All segments show growth versus last year, with Retail Dealer up 15% in volume."

### KPI Table
| Segment | Customers | Litres | Revenue | Rev/Customer |
|---------|-----------|--------|---------|--------------|
| Residential | 300 | 2.1M | $2.5M | $8,333 |
| Commercial | 150 | 4.5M | $5.2M | $34,667 |
| Retail Dealer | 50 | 8.2M | $9.1M | $182,000 |

### Key Observations
- Commercial customers consume 3x more per account than residential
- Retail dealers have highest volume but thinnest margins
- Branch X is outperforming in residential segment

### Recommended Actions
1. Focus residential growth in underperforming branches
2. Upsell service plans to high-volume residential customers
3. Review dealer pricing in competitive markets

## Follow-up Questions to Offer

- "Would you like to see this broken down by product?"
- "Should I compare this to the same period last year?"
- "Want to drill into a specific branch?"
- "Should I look at the portal adoption for this segment?"
