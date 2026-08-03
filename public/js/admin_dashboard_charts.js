(function () {
    'use strict';

    const chartData = window.adminDashboardChartData || {};
    const colors = {
        blue: '#1689df',
        blueFill: 'rgba(22, 137, 223, .2)',
        amber: '#f5a514',
        green: '#18a771',
        grid: 'rgba(107, 139, 166, .2)',
        text: '#61758e',
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function numericValues(values) {
        return Array.isArray(values)
            ? values.map(value => Math.max(0, Number(value) || 0))
            : [];
    }

    function niceMaximum(value) {
        const maximum = Math.max(1, Number(value) || 0);
        const magnitude = 10 ** Math.floor(Math.log10(maximum));
        const normalized = maximum / magnitude;
        const rounded = normalized <= 1 ? 1 : normalized <= 2 ? 2 : normalized <= 5 ? 5 : 10;

        return Math.max(5, rounded * magnitude);
    }

    function emptyState(element, message) {
        if (!element) return;
        element.innerHTML = `
            <div class="admin-chart-empty">
                <div>
                    <i class="bi bi-bar-chart-line"></i>
                    <span>${escapeHtml(message)}</span>
                </div>
            </div>
        `;
    }

    function gridMarkup(maximum, dimensions) {
        const { left, right, top, bottom, width, height } = dimensions;
        const plotHeight = height - top - bottom;
        const plotRight = width - right;
        let output = '';

        for (let index = 0; index <= 4; index += 1) {
            const ratio = index / 4;
            const y = top + plotHeight * ratio;
            const value = Math.round(maximum * (1 - ratio));
            output += `<line class="admin-chart-grid" x1="${left}" y1="${y}" x2="${plotRight}" y2="${y}"></line>`;
            output += `<text class="axis-value" x="${left - 9}" y="${y + 4}" text-anchor="end">${value}</text>`;
        }

        return output;
    }

    function renderTrend() {
        const element = document.getElementById('requestsTrend');
        const labels = Array.isArray(chartData.trend?.labels) ? chartData.trend.labels : [];
        const values = numericValues(chartData.trend?.values);
        if (!element || !labels.length || labels.length !== values.length) {
            emptyState(element, 'No request trend data is available yet.');
            return;
        }

        const dimensions = { width: 720, height: 280, left: 48, right: 18, top: 25, bottom: 48 };
        const { width, height, left, right, top, bottom } = dimensions;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const maximum = niceMaximum(Math.max(...values));
        const denominator = Math.max(1, labels.length - 1);
        const points = values.map((value, index) => ({
            x: left + (plotWidth * index / denominator),
            y: top + plotHeight - (value / maximum * plotHeight),
            value,
            label: labels[index],
        }));
        const pointList = points.map(point => `${point.x},${point.y}`).join(' ');
        const areaPoints = `${left},${top + plotHeight} ${pointList} ${left + plotWidth},${top + plotHeight}`;
        const labelsMarkup = points.map(point => `
            <text x="${point.x}" y="${height - 17}" text-anchor="middle">${escapeHtml(point.label)}</text>
        `).join('');
        const pointsMarkup = points.map(point => `
            <circle cx="${point.x}" cy="${point.y}" r="5" fill="${colors.blue}" stroke="#fff" stroke-width="3">
                <title>${escapeHtml(point.label)}: ${point.value} request${point.value === 1 ? '' : 's'}</title>
            </circle>
        `).join('');
        const emptyNote = values.some(value => value > 0)
            ? ''
            : '<text x="384" y="47" text-anchor="middle">No clearance requests recorded in this period</text>';

        element.innerHTML = `
            <svg class="admin-native-chart" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                <defs>
                    <linearGradient id="adminTrendFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="${colors.blue}" stop-opacity=".28"></stop>
                        <stop offset="100%" stop-color="${colors.blue}" stop-opacity=".03"></stop>
                    </linearGradient>
                </defs>
                ${gridMarkup(maximum, dimensions)}
                ${emptyNote}
                <polygon points="${areaPoints}" fill="url(#adminTrendFill)"></polygon>
                <polyline points="${pointList}" fill="none" stroke="${colors.blue}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></polyline>
                ${pointsMarkup}
                ${labelsMarkup}
            </svg>
        `;
    }

    function renderStatus() {
        const element = document.getElementById('requestStatus');
        if (!element) return;

        const labels = Array.isArray(chartData.status?.labels) ? chartData.status.labels : ['Pending', 'Approved'];
        const values = numericValues(chartData.status?.values);
        const pending = values[0] || 0;
        const approved = values[1] || 0;
        const total = pending + approved;
        const pendingDegrees = total ? pending / total * 360 : 0;
        const background = total
            ? `conic-gradient(${colors.amber} 0deg ${pendingDegrees}deg, ${colors.green} ${pendingDegrees}deg 360deg)`
            : 'conic-gradient(#dceaf4 0deg 360deg)';

        element.innerHTML = `
            <div class="admin-donut-layout">
                <div class="admin-donut" style="background:${background}">
                    <div class="admin-donut-center"><strong>${total.toLocaleString()}</strong><small>Total requests</small></div>
                </div>
                <div class="admin-chart-legend">
                    <div><i style="background:${colors.amber}"></i><span>${escapeHtml(labels[0] || 'Pending')}</span><strong>${pending.toLocaleString()}</strong></div>
                    <div><i style="background:${colors.green}"></i><span>${escapeHtml(labels[1] || 'Approved')}</span><strong>${approved.toLocaleString()}</strong></div>
                </div>
            </div>
        `;
    }

    function renderProgramStatusBars() {
        const element = document.getElementById('programStatus');
        const labels = Array.isArray(chartData.programStatus?.labels) ? chartData.programStatus.labels : [];
        const pending = numericValues(chartData.programStatus?.pending);
        const approved = numericValues(chartData.programStatus?.approved);
        if (!element || !labels.length || labels.length !== pending.length || labels.length !== approved.length) {
            emptyState(element, 'No program clearance data is available yet.');
            return;
        }

        const dimensions = { width: 720, height: 280, left: 48, right: 18, top: 36, bottom: 52 };
        const { width, height, left, right, top, bottom } = dimensions;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const maximum = niceMaximum(Math.max(...pending, ...approved));
        const slotWidth = plotWidth / labels.length;
        const barWidth = Math.min(35, slotWidth * .3);
        const barGap = Math.min(8, slotWidth * .06);
        const bars = labels.map((program, index) => {
            const pendingHeight = pending[index] / maximum * plotHeight;
            const approvedHeight = approved[index] / maximum * plotHeight;
            const groupWidth = barWidth * 2 + barGap;
            const groupX = left + slotWidth * index + (slotWidth - groupWidth) / 2;
            const pendingX = groupX;
            const approvedX = groupX + barWidth + barGap;
            const pendingY = top + plotHeight - pendingHeight;
            const approvedY = top + plotHeight - approvedHeight;
            const label = String(labels[index] ?? '').length > 12
                ? `${String(labels[index]).slice(0, 11)}…`
                : labels[index];

            return `
                <rect x="${pendingX}" y="${pendingY}" width="${barWidth}" height="${Math.max(pendingHeight, pending[index] ? 3 : 0)}" rx="7" fill="${colors.amber}">
                    <title>${escapeHtml(program)} pending: ${pending[index]}</title>
                </rect>
                <rect x="${approvedX}" y="${approvedY}" width="${barWidth}" height="${Math.max(approvedHeight, approved[index] ? 3 : 0)}" rx="7" fill="${colors.green}">
                    <title>${escapeHtml(program)} approved: ${approved[index]}</title>
                </rect>
                <text x="${pendingX + barWidth / 2}" y="${Math.max(top + 12, pendingY - 6)}" text-anchor="middle">${pending[index]}</text>
                <text x="${approvedX + barWidth / 2}" y="${Math.max(top + 12, approvedY - 6)}" text-anchor="middle">${approved[index]}</text>
                <text x="${left + slotWidth * index + slotWidth / 2}" y="${height - 18}" text-anchor="middle">${escapeHtml(label)}</text>
            `;
        }).join('');

        element.innerHTML = `
            <svg class="admin-native-chart" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                ${gridMarkup(maximum, dimensions)}
                <circle cx="525" cy="16" r="5" fill="${colors.amber}"></circle><text x="536" y="20">Pending</text>
                <circle cx="620" cy="16" r="5" fill="${colors.green}"></circle><text x="631" y="20">Approved</text>
                ${bars}
            </svg>
        `;
    }

    function renderStackedBars() {
        const element = document.getElementById('statusStacked');
        const labels = Array.isArray(chartData.stacked?.labels) ? chartData.stacked.labels : [];
        const pending = numericValues(chartData.stacked?.pending);
        const approved = numericValues(chartData.stacked?.approved);
        if (!element || !labels.length || labels.length !== pending.length || labels.length !== approved.length) {
            emptyState(element, 'No monthly clearance status data is available yet.');
            return;
        }

        const totals = labels.map((label, index) => pending[index] + approved[index]);
        const dimensions = { width: 720, height: 280, left: 48, right: 18, top: 34, bottom: 52 };
        const { width, height, left, right, top, bottom } = dimensions;
        const plotWidth = width - left - right;
        const plotHeight = height - top - bottom;
        const maximum = niceMaximum(Math.max(...totals));
        const slotWidth = plotWidth / labels.length;
        const barWidth = Math.min(58, slotWidth * .56);
        const bars = labels.map((label, index) => {
            const pendingHeight = pending[index] / maximum * plotHeight;
            const approvedHeight = approved[index] / maximum * plotHeight;
            const x = left + slotWidth * index + (slotWidth - barWidth) / 2;
            const approvedY = top + plotHeight - approvedHeight;
            const pendingY = approvedY - pendingHeight;

            return `
                <rect x="${x}" y="${pendingY}" width="${barWidth}" height="${Math.max(pendingHeight, pending[index] ? 2 : 0)}" rx="7" fill="${colors.amber}">
                    <title>${escapeHtml(label)} pending: ${pending[index]}</title>
                </rect>
                <rect x="${x}" y="${approvedY}" width="${barWidth}" height="${Math.max(approvedHeight, approved[index] ? 2 : 0)}" rx="7" fill="${colors.green}">
                    <title>${escapeHtml(label)} approved: ${approved[index]}</title>
                </rect>
                <text x="${x + barWidth / 2}" y="${height - 18}" text-anchor="middle">${escapeHtml(label)}</text>
            `;
        }).join('');
        const emptyNote = totals.some(value => value > 0)
            ? ''
            : '<text x="384" y="54" text-anchor="middle">No monthly clearance activity recorded yet</text>';

        element.innerHTML = `
            <svg class="admin-native-chart" viewBox="0 0 ${width} ${height}" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
                ${gridMarkup(maximum, dimensions)}
                <circle cx="525" cy="15" r="5" fill="${colors.amber}"></circle><text x="536" y="19">Pending</text>
                <circle cx="620" cy="15" r="5" fill="${colors.green}"></circle><text x="631" y="19">Approved</text>
                ${emptyNote}
                ${bars}
            </svg>
        `;
    }

    function renderCharts() {
        renderTrend();
        renderStatus();
        renderProgramStatusBars();
        renderStackedBars();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderCharts, { once: true });
    } else {
        renderCharts();
    }
})();
