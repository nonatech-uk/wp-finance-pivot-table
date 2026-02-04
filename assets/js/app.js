/**
 * Main Application - WordPress Plugin Version
 * Handles year tab navigation and pivot table initialization
 * Data is passed via wp_localize_script as FinancePivotData object
 */

class FinancialApp {
    constructor() {
        this.pivotTable = null;
        this.currentYear = null;

        // Get data from WordPress localized script
        this.financialData = (typeof FinancePivotData !== 'undefined') ? FinancePivotData.FINANCIAL_DATA : {};
        this.fiscalYears = (typeof FinancePivotData !== 'undefined') ? FinancePivotData.FISCAL_YEARS : [];
        this.yearMetadata = (typeof FinancePivotData !== 'undefined') ? FinancePivotData.YEAR_METADATA : {};

        this.init();
    }

    /**
     * Initialize the application
     */
    init() {
        // Create pivot table instance
        this.pivotTable = new PivotTable('pivot-container');

        // Render year tabs
        this.renderYearTabs();

        // Set up download button
        this.setupDownloadButton();

        // Select first year by default (now most recent due to reversed order)
        if (this.fiscalYears.length > 0) {
            this.selectYear(this.fiscalYears[0]);
        }
    }

    /**
     * Set up download button click handler
     */
    setupDownloadButton() {
        const downloadBtn = document.getElementById('download-data');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', () => this.downloadCurrentYearData());
        }
    }

    /**
     * Download current year's data as CSV
     */
    downloadCurrentYearData() {
        if (!this.currentYear) return;

        const data = this.financialData[this.currentYear] || [];
        if (data.length === 0) return;

        // CSV headers
        const headers = ['type', 'date', 'payee', 'reference', 'vat', 'centre', 'centre_name',
                        'account', 'account_name', 'amount', 'total_amount', 'detail'];

        // Build CSV content
        let csv = headers.join(',') + '\n';

        data.forEach(row => {
            const values = headers.map(header => {
                let value = row[header] || '';
                // Escape quotes and wrap in quotes if contains comma, quote, or newline
                if (typeof value === 'string' && (value.includes(',') || value.includes('"') || value.includes('\n'))) {
                    value = '"' + value.replace(/"/g, '""') + '"';
                }
                return value;
            });
            csv += values.join(',') + '\n';
        });

        // Create and trigger download
        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        const filename = `financial-data-${this.currentYear}.csv`;

        if (navigator.msSaveBlob) {
            // IE 10+
            navigator.msSaveBlob(blob, filename);
        } else {
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(link.href);
        }
    }

    /**
     * Render year navigation tabs
     */
    renderYearTabs() {
        const tabsContainer = document.getElementById('year-tabs');
        if (!tabsContainer) return;

        let html = '';
        this.fiscalYears.forEach(year => {
            const label = this.formatYearLabel(year);
            const meta = this.yearMetadata[year] || {};
            const indicator = meta.isComplete ? '' : '<span class="partial-indicator">*</span>';
            html += `<button class="year-tab" data-year="${year}">${label}${indicator}</button>`;
        });

        tabsContainer.innerHTML = html;

        // Attach click handlers
        tabsContainer.querySelectorAll('.year-tab').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const year = tab.getAttribute('data-year');
                this.selectYear(year);
            });
        });
    }

    /**
     * Format year label for display
     */
    formatYearLabel(year) {
        // Convert "2022-3" to "2022/23"
        const parts = year.split('-');
        if (parts.length === 2) {
            const startYear = parts[0];
            const endDigit = parts[1];
            return `${startYear}/${startYear.substring(0, 2)}${endDigit.padStart(2, '0')}`;
        }
        return year;
    }

    /**
     * Select a fiscal year and update the display
     */
    selectYear(year) {
        this.currentYear = year;

        // Update tab active state
        document.querySelectorAll('.year-tab').forEach(tab => {
            if (tab.getAttribute('data-year') === year) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        // Get metadata for this year
        const meta = this.yearMetadata[year] || {};

        // Update heading with data currency
        const heading = document.getElementById('current-year-heading');
        if (heading) {
            heading.textContent = `Financial Year ${this.formatYearLabel(year)}`;
        }

        // Update data currency notice
        const currencyNotice = document.getElementById('data-currency');
        if (currencyNotice) {
            if (meta.isComplete) {
                currencyNotice.innerHTML = `<span class="currency-complete">Complete year data</span>`;
            } else {
                currencyNotice.innerHTML = `<span class="currency-partial">Data to: <strong>${meta.dataTo}</strong></span>`;
            }
        }

        // Load data for selected year
        const data = this.financialData[year] || [];
        this.pivotTable.setData(data);

        // Update summary stats
        this.updateSummary(data, meta);
    }

    /**
     * Update summary statistics
     */
    updateSummary(data, meta) {
        const income = data
            .filter(tx => tx.type === 'Income')
            .reduce((sum, tx) => sum + (tx.total_amount || 0), 0);

        const expense = data
            .filter(tx => tx.type === 'Expense')
            .reduce((sum, tx) => sum + (tx.total_amount || 0), 0);

        const net = income + expense; // Expenses are negative

        const openingBalance = meta.openingBalance !== null && meta.openingBalance !== undefined
            ? parseFloat(meta.openingBalance)
            : null;
        const closingBalance = openingBalance !== null ? openingBalance + net : null;

        const summaryContainer = document.getElementById('summary-stats');
        if (summaryContainer) {
            let html = '';

            // Balances row (above) with Net Position
            if (openingBalance !== null) {
                html += `
                    <div class="balance-row">
                        <div class="stat-item balance opening ${openingBalance >= 0 ? 'positive' : 'negative'}">
                            <span class="stat-label">Opening Balance</span>
                            <span class="stat-value">&pound;${this.formatNumber(openingBalance)}</span>
                        </div>
                        <div class="stat-item balance closing ${closingBalance >= 0 ? 'positive' : 'negative'}">
                            <span class="stat-label">Closing Balance</span>
                            <span class="stat-value">&pound;${this.formatNumber(closingBalance)}</span>
                        </div>
                        <div class="stat-item net ${net >= 0 ? 'positive' : 'negative'}">
                            <span class="stat-label">Net Position</span>
                            <span class="stat-value">&pound;${this.formatNumber(net)}</span>
                        </div>
                    </div>
                `;
            }

            // Main stats row
            html += `
                <div class="stats-row">
                    <div class="stat-item income">
                        <span class="stat-label">Total Income</span>
                        <span class="stat-value">&pound;${this.formatNumber(income)}</span>
                    </div>
                    <div class="stat-item expense">
                        <span class="stat-label">Total Expenditure</span>
                        <span class="stat-value">&pound;${this.formatNumber(Math.abs(expense))}</span>
                    </div>
                    <div class="stat-item count">
                        <span class="stat-label">Transactions</span>
                        <span class="stat-value">${data.length}</span>
                    </div>
                </div>
            `;

            summaryContainer.innerHTML = html;
        }
    }

    /**
     * Format number with commas and 2 decimal places
     */
    formatNumber(value) {
        const absValue = Math.abs(value);
        const formatted = absValue.toLocaleString('en-GB', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        if (value < 0) {
            return `(${formatted})`;
        }
        return formatted;
    }
}

// Initialize app when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.financialApp = new FinancialApp();
});
