@extends('mainAdmin.layouts.admin')
@section('title', 'Dashboard — ClearanceMS')

@section('content')
<div class="page-header">
    <div>
        <h2>Dashboard</h2>
        <p class="text-muted">Welcome back to your admin control panel.</p>
    </div>
</div>

<div class="dashboard-shell">
    <div class="metric-grid">
    <div class="metric-card">
        <span class="metric-label">Students</span>
        <strong>{{ number_format($students) }}</strong>
    </div>
    <div class="metric-card">
        <span class="metric-label">Instructors</span>
        <strong>{{ number_format($instructors) }}</strong>
    </div>
    <div class="metric-card">
        <span class="metric-label">Personnel</span>
        <strong>{{ number_format($admins) }}</strong>
    </div>
    <div class="metric-card">
        <span class="metric-label">Registrars</span>
        <strong>{{ number_format($registrars) }}</strong>
    </div>
</div>

<div class="dashboard-grid">
    <div class="chart-card">
        <div class="chart-card-header"><h3>Clearance Requests Trend</h3></div>
        <canvas id="requestsTrend" class="chart-canvas"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-card-header"><h3>Request Status Breakdown</h3></div>
        <canvas id="requestStatus" class="chart-canvas"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-card-header"><h3>Students by Program</h3></div>
        <canvas id="studentsProgram" class="chart-canvas"></canvas>
    </div>
    <div class="chart-card">
        <div class="chart-card-header"><h3>Monthly Status Breakdown</h3></div>
        <canvas id="statusStacked" class="chart-canvas"></canvas>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    const trendLabels = {!! json_encode($monthlyData->pluck('label')) !!};
    const trendValues = {!! json_encode($monthlyData->pluck('count')) !!};
    const statusLabels = ['Pending', 'Approved', 'Cleared', 'Rejected'];
    const statusValues = {!! json_encode([$pending, $approved, $cleared, $rejected]) !!};
    const programLabels = {!! json_encode($byProgram->pluck('program')) !!};
    const programValues = {!! json_encode($byProgram->pluck('c')) !!};
    const stackedLabels = {!! json_encode($stackData->pluck('label')) !!};
    const stackedPending = {!! json_encode($stackData->pluck('pending')) !!};
    const stackedApproved = {!! json_encode($stackData->pluck('approved')) !!};
    const stackedCleared = {!! json_encode($stackData->pluck('cleared')) !!};
    const stackedRejected = {!! json_encode($stackData->pluck('rejected')) !!};

    Chart.defaults.font.family = 'Inter, system-ui, sans-serif';
    Chart.defaults.font.size = 13;

    new Chart(document.getElementById('requestsTrend'), {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'Requests',
                data: trendValues,
                borderColor: '#0ea5e9',
                backgroundColor: 'rgba(14,165,233,0.18)',
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointBackgroundColor: '#0ea5e9',
                pointBorderColor: '#fff',
                borderWidth: 3,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { mode: 'index', intersect: false }
            },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(15,23,42,0.08)' } },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('requestStatus'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: ['#f59e0b', '#10b981', '#0ea5e9', '#ef4444'],
                borderColor: '#fff',
                borderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } }
            }
        }
    });

    new Chart(document.getElementById('studentsProgram'), {
        type: 'bar',
        data: {
            labels: programLabels,
            datasets: [{
                label: 'Students',
                data: programValues,
                backgroundColor: 'rgba(59,130,246,0.68)',
                borderColor: 'rgba(59,130,246,0.9)',
                borderWidth: 1,
                borderRadius: 12,
                maxBarThickness: 28,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: 'rgba(15,23,42,0.08)' } },
                x: { grid: { display: false } }
            }
        }
    });

    new Chart(document.getElementById('statusStacked'), {
        type: 'bar',
        data: {
            labels: stackedLabels,
            datasets: [
                { label: 'Pending', data: stackedPending, backgroundColor: '#f59e0b' },
                { label: 'Approved', data: stackedApproved, backgroundColor: '#10b981' },
                { label: 'Cleared', data: stackedCleared, backgroundColor: '#0ea5e9' },
                { label: 'Rejected', data: stackedRejected, backgroundColor: '#ef4444' }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { tooltip: { mode: 'index', intersect: false } },
            scales: {
                x: { stacked: true, grid: { display: false } },
                y: { stacked: true, beginAtZero: true, grid: { color: 'rgba(15,23,42,0.08)' } }
            }
        }
    });
</script>
@endpush
