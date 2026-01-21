# Data Quality Check

Validate data integrity before analysis and identify issues that could affect reporting accuracy.

## When to Use

User asks questions like:
- "Is the data reliable?"
- "Check data quality before the board meeting"
- "Are there any data issues I should know about?"
- "Run a data health check"
- Before any major KPI or dashboard build

## Workflow

1. **Run standard checks** across all core tables
2. **Quantify issues** with counts and percentages
3. **Assess severity** - Critical, Warning, Info
4. **Recommend remediation** for each issue type
5. **Provide confidence rating** for analysis readiness

## Data Quality Dimensions

| Dimension | Description | Examples |
|-----------|-------------|----------|
| Completeness | Missing required values | NULL customer_id, missing dates |
| Validity | Values within expected ranges | Negative litres, future dates |
| Consistency | Cross-table agreement | Invoice totals vs line sums |
| Uniqueness | No unwanted duplicates | Duplicate invoice numbers |
| Timeliness | Data is current | Stale tank readings |

## SQL Patterns

### Completeness Checks

```sql
-- Missing critical fields in deliveries
SELECT
  'deliveries' AS table_name,
  'customer_id' AS field,
  COUNT(*) AS missing_count
FROM deliveries WHERE customer_id IS NULL
UNION ALL
SELECT 'deliveries', 'delivery_date', COUNT(*) FROM deliveries WHERE delivery_date IS NULL
UNION ALL
SELECT 'deliveries', 'litres_delivered', COUNT(*) FROM deliveries WHERE litres_delivered IS NULL
UNION ALL
SELECT 'invoices', 'customer_id', COUNT(*) FROM invoices WHERE customer_id IS NULL
UNION ALL
SELECT 'invoices', 'invoice_date', COUNT(*) FROM invoices WHERE invoice_date IS NULL
UNION ALL
SELECT 'invoices', 'total_amount', COUNT(*) FROM invoices WHERE total_amount IS NULL
UNION ALL
SELECT 'customers', 'segment', COUNT(*) FROM customers WHERE segment IS NULL
UNION ALL
SELECT 'customers', 'branch', COUNT(*) FROM customers WHERE branch IS NULL;
```

### Validity Checks

```sql
-- Invalid values
SELECT
  'deliveries' AS table_name,
  'negative_litres' AS issue,
  COUNT(*) AS count
FROM deliveries WHERE litres_delivered < 0
UNION ALL
SELECT 'deliveries', 'zero_litres', COUNT(*) FROM deliveries WHERE litres_delivered = 0
UNION ALL
SELECT 'deliveries', 'future_date', COUNT(*) FROM deliveries WHERE delivery_date > date('now')
UNION ALL
SELECT 'deliveries', 'negative_price', COUNT(*) FROM deliveries WHERE unit_price < 0
UNION ALL
SELECT 'invoices', 'negative_total', COUNT(*) FROM invoices WHERE total_amount < 0
UNION ALL
SELECT 'invoices', 'future_invoice_date', COUNT(*) FROM invoices WHERE invoice_date > date('now')
UNION ALL
SELECT 'tank_readings', 'pct_over_100', COUNT(*) FROM tank_readings WHERE estimated_pct > 100
UNION ALL
SELECT 'tank_readings', 'negative_pct', COUNT(*) FROM tank_readings WHERE estimated_pct < 0
UNION ALL
SELECT 'cardlock_transactions', 'negative_litres', COUNT(*) FROM cardlock_transactions WHERE litres < 0;
```

### Consistency Checks

```sql
-- Invoice total vs sum of lines
SELECT
  i.id AS invoice_id,
  i.invoice_number,
  i.subtotal AS invoice_subtotal,
  COALESCE(SUM(il.line_total), 0) AS lines_total,
  ABS(i.subtotal - COALESCE(SUM(il.line_total), 0)) AS discrepancy
FROM invoices i
LEFT JOIN invoice_lines il ON il.invoice_id = i.id
GROUP BY i.id, i.invoice_number, i.subtotal
HAVING ABS(i.subtotal - COALESCE(SUM(il.line_total), 0)) > 0.01
ORDER BY discrepancy DESC
LIMIT 20;
```

```sql
-- HST calculation check (should be ~13%)
SELECT
  i.id,
  i.invoice_number,
  i.subtotal,
  i.hst_amount,
  ROUND(100.0 * i.hst_amount / NULLIF(i.subtotal, 0), 2) AS calculated_hst_pct
FROM invoices i
WHERE i.subtotal > 0
  AND ABS(100.0 * i.hst_amount / i.subtotal - 13.0) > 1.0
LIMIT 20;
```

```sql
-- Payments exceeding invoice totals
SELECT
  i.id AS invoice_id,
  i.invoice_number,
  i.total_amount,
  SUM(p.amount) AS total_paid,
  SUM(p.amount) - i.total_amount AS overpayment
FROM invoices i
JOIN payments p ON p.invoice_id = i.id
GROUP BY i.id, i.invoice_number, i.total_amount
HAVING SUM(p.amount) > i.total_amount * 1.01  -- Allow 1% tolerance
ORDER BY overpayment DESC
LIMIT 20;
```

### Uniqueness Checks

```sql
-- Duplicate invoice numbers
SELECT
  invoice_number,
  COUNT(*) AS occurrences
FROM invoices
GROUP BY invoice_number
HAVING COUNT(*) > 1;
```

```sql
-- Duplicate customer numbers
SELECT
  customer_number,
  COUNT(*) AS occurrences
FROM customers
GROUP BY customer_number
HAVING COUNT(*) > 1;
```

### Timeliness Checks

```sql
-- Stale tank readings (no reading in 30+ days)
SELECT
  s.id AS site_id,
  s.name AS site_name,
  c.name AS customer_name,
  MAX(tr.reading_at) AS last_reading,
  CAST(julianday('now') - julianday(MAX(tr.reading_at)) AS INTEGER) AS days_since_reading
FROM sites s
JOIN customers c ON c.id = s.customer_id
LEFT JOIN tank_readings tr ON tr.site_id = s.id
WHERE s.tank_capacity_litres IS NOT NULL
GROUP BY s.id, s.name, c.name
HAVING MAX(tr.reading_at) IS NULL
   OR julianday('now') - julianday(MAX(tr.reading_at)) > 30
ORDER BY days_since_reading DESC;
```

```sql
-- Invoices without payments (past due date)
SELECT
  i.id,
  i.invoice_number,
  i.customer_id,
  i.total_amount,
  i.due_date,
  CAST(julianday('now') - julianday(i.due_date) AS INTEGER) AS days_overdue
FROM invoices i
LEFT JOIN payments p ON p.invoice_id = i.id
WHERE p.id IS NULL
  AND i.due_date < date('now')
  AND i.status != 'paid'
ORDER BY days_overdue DESC
LIMIT 20;
```

### Referential Integrity

```sql
-- Orphan records
SELECT 'deliveries with invalid customer_id' AS issue, COUNT(*) AS count
FROM deliveries d
LEFT JOIN customers c ON c.id = d.customer_id
WHERE c.id IS NULL
UNION ALL
SELECT 'deliveries with invalid site_id', COUNT(*)
FROM deliveries d
LEFT JOIN sites s ON s.id = d.site_id
WHERE s.id IS NULL
UNION ALL
SELECT 'invoice_lines with invalid invoice_id', COUNT(*)
FROM invoice_lines il
LEFT JOIN invoices i ON i.id = il.invoice_id
WHERE i.id IS NULL
UNION ALL
SELECT 'payments with invalid invoice_id', COUNT(*)
FROM payments p
LEFT JOIN invoices i ON i.id = p.invoice_id
WHERE i.id IS NULL;
```

### Summary Statistics

```sql
-- Table row counts and date ranges
SELECT
  'customers' AS table_name,
  COUNT(*) AS row_count,
  MIN(created_at) AS earliest,
  MAX(created_at) AS latest
FROM customers
UNION ALL
SELECT 'deliveries', COUNT(*), MIN(delivery_date), MAX(delivery_date) FROM deliveries
UNION ALL
SELECT 'invoices', COUNT(*), MIN(invoice_date), MAX(invoice_date) FROM invoices
UNION ALL
SELECT 'cardlock_transactions', COUNT(*), MIN(txn_datetime), MAX(txn_datetime) FROM cardlock_transactions
UNION ALL
SELECT 'portal_events', COUNT(*), MIN(event_at), MAX(event_at) FROM portal_events;
```

## Laravel Alternative

```php
use Illuminate\Support\Facades\DB;

// Completeness check
$missingFields = DB::select("
    SELECT 'deliveries' as tbl, 'customer_id' as field, COUNT(*) as cnt
    FROM deliveries WHERE customer_id IS NULL
");

// Validity check
$invalidValues = DB::select("
    SELECT COUNT(*) as negative_litres
    FROM deliveries WHERE litres_delivered < 0
");

// Invoice consistency
$inconsistentInvoices = DB::select("
    SELECT i.id, i.subtotal, SUM(il.line_total) as lines_total
    FROM invoices i
    LEFT JOIN invoice_lines il ON il.invoice_id = i.id
    GROUP BY i.id
    HAVING ABS(i.subtotal - COALESCE(SUM(il.line_total), 0)) > 0.01
");
```

## Output Format

### Executive Summary
> "Data quality is **GOOD** overall with a 98.5% completeness score. We found 3 minor issues that should be addressed before the quarterly review, but none affect core KPIs."

### Data Health Scorecard
| Dimension | Score | Status |
|-----------|-------|--------|
| Completeness | 98.5% | Good |
| Validity | 99.2% | Good |
| Consistency | 97.8% | Warning |
| Uniqueness | 100% | Good |
| Timeliness | 95.0% | Warning |
| **Overall** | **98.1%** | **Good** |

### Issues Found

#### Critical (0)
*None found*

#### Warnings (3)
| Issue | Count | Impact | Remediation |
|-------|-------|--------|-------------|
| Invoice/line total mismatch | 12 | Financial reporting | Recalculate or investigate |
| Stale tank readings (>30 days) | 8 sites | Delivery planning | Check monitoring equipment |
| HST calculation variance | 5 | Tax compliance | Review calculation logic |

#### Info (2)
| Issue | Count | Notes |
|-------|-------|-------|
| Zero-litre deliveries | 3 | May be cancellations |
| Future delivery dates | 1 | Likely scheduled delivery |

### Table Statistics
| Table | Records | Date Range | Status |
|-------|---------|------------|--------|
| customers | 500 | 2022-2024 | OK |
| deliveries | 15,234 | 2022-2024 | OK |
| invoices | 12,450 | 2022-2024 | OK |
| cardlock_transactions | 25,678 | 2022-2024 | OK |
| tank_readings | 10,234 | 2022-2024 | OK |

### Recommended Actions
1. **Immediate:** Fix 12 invoice total mismatches before month-end close
2. **This week:** Investigate 8 sites with stale tank readings
3. **Ongoing:** Add validation rule for HST calculation on invoice create

### Confidence Rating
> **Analysis Readiness: HIGH**
> The data is suitable for executive reporting. Minor issues identified do not materially affect aggregate KPIs.

## Follow-up Questions

- "Want me to list the specific invoices with mismatches?"
- "Should I identify the sites with stale readings?"
- "Want a deeper dive into any specific table?"
- "Should I check data quality for a specific time period?"
