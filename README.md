# Finance Pivot Table - WordPress Plugin

Interactive pivot table for displaying parish council financial data from CSV files.

**Live URL:** https://alburyparishcouncil.gov.uk/finance/

## Features

- Year tabs (newest first) with current year selected by default
- Drill-down hierarchy: Type → Centre Name → Account Name → Transaction details
- Summary statistics: Income, Expenditure, Net Position, Transaction count
- Data currency indicator showing how up-to-date partial year data is
- Download data as CSV
- Responsive design for mobile/tablet
- Print-friendly styles

## Installation

1. Copy plugin folder to `/wp-content/plugins/finance-pivot-table/`
2. Activate in WordPress Admin → Plugins
3. Configure data directory in Settings → Finance Pivot Table
4. Add `[finance_pivot_table]` shortcode to any page

## Usage

Add the shortcode to any page or post:

```
[finance_pivot_table]
```

## Data Directory

Configure the path to your CSV files in Settings → Finance Pivot Table.

Default: `/var/www/html/wp-content/uploads/public-docs/Finance/data`

## CSV File Format

Expected columns:
```
type, date, payee, reference, vat, centre, centre_name, account, account_name, amount, total_amount, detail
```

### Filename Conventions

The fiscal year and data currency are extracted from filenames:

| Filename Pattern | Result |
|-----------------|--------|
| `Receipts and Payments 2022-3.CSV` | Year 2022/23, Complete |
| `Cashbook Report 30-11-2025.CSV` | Year 2025/26, Data to 30 Nov 2025 |
| `Cashbook Report 2025-6 to 31-12.CSV` | Year 2025/26, Data to 31 Dec |

## File Structure

```
finance-pivot-table/
├── finance-pivot-table.php          # Main plugin file
├── includes/
│   ├── class-finance-pivot-table.php    # Shortcode, admin, assets
│   └── class-finance-data-loader.php    # CSV parsing
├── assets/
│   ├── css/
│   │   └── styles.css
│   └── js/
│       ├── pivot-table.js           # Pivot table component
│       └── app.js                   # Application controller
└── templates/
    └── shortcode-output.php         # Shortcode HTML
```

## Updates

The plugin checks GitHub for new releases automatically. When an update is available:

1. WordPress will show the update notification on the Plugins page
2. Click "Update Now" to update directly from GitHub

To manually check for updates, click "Check for updates" link on the plugin row.

### Creating a New Release

1. Update `FINANCE_PIVOT_TABLE_VERSION` in `finance-pivot-table.php`
2. Commit changes
3. Create a tag: `git tag -a v1.0.1 -m "Release notes"`
4. Push: `git push && git push --tags`

## Development

Repository: https://github.com/nonatech-uk/wordpress-finance-pivot-table
