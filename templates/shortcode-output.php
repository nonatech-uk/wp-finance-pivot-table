<?php
/**
 * Shortcode output template
 * Renders the HTML structure for the finance pivot table
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="finance-pivot-wrapper">
    <nav class="year-navigation">
        <div id="year-tabs" class="year-tabs"></div>
    </nav>

    <section class="summary-section">
        <div class="heading-row">
            <h2 id="current-year-heading"></h2>
            <div id="data-currency" class="data-currency"></div>
        </div>
        <div id="summary-stats" class="summary-stats"></div>
    </section>

    <section class="pivot-section">
        <div class="pivot-toolbar">
            <button id="download-data" class="download-btn" title="Download as CSV">
                <span class="download-icon">&#8681;</span> Download Data
            </button>
        </div>
        <div class="pivot-instructions">
            <p>Click on any row to expand or collapse details. Income and Expense categories can be drilled down to individual transactions.</p>
        </div>
        <div id="pivot-container"></div>
    </section>
</div>
