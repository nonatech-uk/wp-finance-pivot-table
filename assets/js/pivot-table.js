/**
 * Pivot Table Component
 * Handles data aggregation, hierarchy, and interactive drill-down
 */

class PivotTable {
    constructor(containerId) {
        this.container = document.getElementById(containerId);
        this.data = [];
        this.expandedNodes = new Set();
    }

    /**
     * Set data and render the table
     */
    setData(transactions) {
        this.data = transactions;
        this.expandedNodes.clear();
        this.render();
    }

    /**
     * Aggregate data into hierarchical structure
     * Hierarchy: Type -> Centre Name -> Account Name -> Transactions
     */
    aggregateData() {
        const hierarchy = {};

        this.data.forEach(tx => {
            const type = tx.type || 'Unknown';
            const centreName = tx.centre_name || 'Unknown';
            const accountName = tx.account_name || 'Unknown';

            // Initialize type level
            if (!hierarchy[type]) {
                hierarchy[type] = {
                    name: type,
                    amount: 0,
                    vat: 0,
                    totalAmount: 0,
                    children: {}
                };
            }

            // Initialize centre level
            if (!hierarchy[type].children[centreName]) {
                hierarchy[type].children[centreName] = {
                    name: centreName,
                    amount: 0,
                    vat: 0,
                    totalAmount: 0,
                    children: {}
                };
            }

            // Initialize account level
            if (!hierarchy[type].children[centreName].children[accountName]) {
                hierarchy[type].children[centreName].children[accountName] = {
                    name: accountName,
                    amount: 0,
                    vat: 0,
                    totalAmount: 0,
                    transactions: []
                };
            }

            // Add transaction
            const account = hierarchy[type].children[centreName].children[accountName];
            account.transactions.push(tx);
            account.amount += tx.amount || 0;
            account.vat += tx.vat || 0;
            account.totalAmount += tx.total_amount || 0;

            // Roll up to centre
            const centre = hierarchy[type].children[centreName];
            centre.amount += tx.amount || 0;
            centre.vat += tx.vat || 0;
            centre.totalAmount += tx.total_amount || 0;

            // Roll up to type
            hierarchy[type].amount += tx.amount || 0;
            hierarchy[type].vat += tx.vat || 0;
            hierarchy[type].totalAmount += tx.total_amount || 0;
        });

        return hierarchy;
    }

    /**
     * Calculate grand total
     */
    calculateGrandTotal() {
        return this.data.reduce((acc, tx) => {
            acc.amount += tx.amount || 0;
            acc.vat += tx.vat || 0;
            acc.totalAmount += tx.total_amount || 0;
            return acc;
        }, { amount: 0, vat: 0, totalAmount: 0 });
    }

    /**
     * Format currency value
     */
    formatCurrency(value) {
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

    /**
     * Format date from DD/MM/YYYY
     */
    formatDate(dateStr) {
        if (!dateStr) return '';
        const parts = dateStr.split('/');
        if (parts.length === 3) {
            return `${parts[0]}/${parts[1]}/${parts[2]}`;
        }
        return dateStr;
    }

    /**
     * Toggle node expansion
     */
    toggleNode(nodeId) {
        if (this.expandedNodes.has(nodeId)) {
            this.expandedNodes.delete(nodeId);
        } else {
            this.expandedNodes.add(nodeId);
        }
        this.render();
    }

    /**
     * Check if node is expanded
     */
    isExpanded(nodeId) {
        return this.expandedNodes.has(nodeId);
    }

    /**
     * Create node ID from path
     */
    createNodeId(...parts) {
        return parts.join('|');
    }

    /**
     * Render the pivot table
     */
    render() {
        const hierarchy = this.aggregateData();
        const grandTotal = this.calculateGrandTotal();

        let html = `
            <table class="pivot-table">
                <thead>
                    <tr>
                        <th class="col-name">Description</th>
                        <th class="col-amount">Amount</th>
                        <th class="col-vat">VAT</th>
                        <th class="col-total">Total Amount</th>
                    </tr>
                </thead>
                <tbody>
        `;

        // Render each type (Income/Expense)
        const types = Object.keys(hierarchy).sort((a, b) => {
            // Income first, then Expense
            if (a === 'Income') return -1;
            if (b === 'Income') return 1;
            return a.localeCompare(b);
        });

        types.forEach(type => {
            const typeData = hierarchy[type];
            const typeId = this.createNodeId(type);
            const typeExpanded = this.isExpanded(typeId);

            // Type row
            html += this.renderRow({
                nodeId: typeId,
                level: 0,
                name: type,
                amount: typeData.amount,
                vat: typeData.vat,
                totalAmount: typeData.totalAmount,
                hasChildren: true,
                isExpanded: typeExpanded,
                rowClass: 'level-0 type-row'
            });

            // If expanded, show centres
            if (typeExpanded) {
                const centres = Object.keys(typeData.children).sort();
                centres.forEach(centreName => {
                    const centreData = typeData.children[centreName];
                    const centreId = this.createNodeId(type, centreName);
                    const centreExpanded = this.isExpanded(centreId);

                    // Centre row
                    html += this.renderRow({
                        nodeId: centreId,
                        level: 1,
                        name: centreName,
                        amount: centreData.amount,
                        vat: centreData.vat,
                        totalAmount: centreData.totalAmount,
                        hasChildren: true,
                        isExpanded: centreExpanded,
                        rowClass: 'level-1 centre-row'
                    });

                    // If expanded, show accounts
                    if (centreExpanded) {
                        const accounts = Object.keys(centreData.children).sort();
                        accounts.forEach(accountName => {
                            const accountData = centreData.children[accountName];
                            const accountId = this.createNodeId(type, centreName, accountName);
                            const accountExpanded = this.isExpanded(accountId);

                            // Account row
                            html += this.renderRow({
                                nodeId: accountId,
                                level: 2,
                                name: accountName,
                                amount: accountData.amount,
                                vat: accountData.vat,
                                totalAmount: accountData.totalAmount,
                                hasChildren: accountData.transactions.length > 0,
                                isExpanded: accountExpanded,
                                rowClass: 'level-2 account-row'
                            });

                            // If expanded, show transactions
                            if (accountExpanded) {
                                accountData.transactions.forEach((tx, idx) => {
                                    html += this.renderTransactionRow(tx, idx);
                                });
                            }
                        });
                    }
                });
            }
        });

        // Grand total row
        html += `
            <tr class="grand-total-row">
                <td class="col-name"><strong>Grand Total</strong></td>
                <td class="col-amount">${this.formatCurrency(grandTotal.amount)}</td>
                <td class="col-vat">${this.formatCurrency(grandTotal.vat)}</td>
                <td class="col-total"><strong>${this.formatCurrency(grandTotal.totalAmount)}</strong></td>
            </tr>
        `;

        html += `
                </tbody>
            </table>
        `;

        this.container.innerHTML = html;

        // Attach click handlers
        this.container.querySelectorAll('[data-node-id]').forEach(row => {
            row.addEventListener('click', (e) => {
                const nodeId = row.getAttribute('data-node-id');
                this.toggleNode(nodeId);
            });
        });
    }

    /**
     * Render a summary row (type, centre, or account level)
     */
    renderRow({ nodeId, level, name, amount, vat, totalAmount, hasChildren, isExpanded, rowClass }) {
        const indent = level * 20;
        const icon = hasChildren ? (isExpanded ? '&#9660;' : '&#9654;') : '&nbsp;&nbsp;';
        const clickable = hasChildren ? 'clickable' : '';

        return `
            <tr class="${rowClass} ${clickable}" data-node-id="${nodeId}">
                <td class="col-name" style="padding-left: ${indent + 10}px">
                    <span class="expand-icon">${icon}</span>
                    ${this.escapeHtml(name)}
                </td>
                <td class="col-amount">${this.formatCurrency(amount)}</td>
                <td class="col-vat">${this.formatCurrency(vat)}</td>
                <td class="col-total">${this.formatCurrency(totalAmount)}</td>
            </tr>
        `;
    }

    /**
     * Render a transaction detail row
     */
    renderTransactionRow(tx, idx) {
        const indent = 3 * 20;
        return `
            <tr class="level-3 transaction-row">
                <td class="col-name" style="padding-left: ${indent + 10}px">
                    <span class="tx-date">${this.formatDate(tx.date)}</span>
                    <span class="tx-payee">${this.escapeHtml(tx.payee || '')}</span>
                    <span class="tx-detail">${this.escapeHtml(tx.detail || '')}</span>
                </td>
                <td class="col-amount">${this.formatCurrency(tx.amount)}</td>
                <td class="col-vat">${this.formatCurrency(tx.vat)}</td>
                <td class="col-total">${this.formatCurrency(tx.total_amount)}</td>
            </tr>
        `;
    }

    /**
     * Escape HTML special characters
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
