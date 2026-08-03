@extends('layouts.portal')

@section('title', 'Treasurer Dashboard')
@section('portal-name', 'Treasurer Portal')
@section('portal-subtitle', $treasurer->isMainTreasurer() ? 'Main Treasury' : ($treasurer->program . ' ' . $treasurer->year_level . '-' . $treasurer->section))
@section('page-title', 'Financial Clearance Dashboard')
@section('user-label', $treasurer->full_name)

@section('nav')
    <a class="nav-link active" href="{{ route('treasurer.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('treasurer.clearance-updates') }}"><i class="bi bi-bar-chart-line me-2"></i> Clearance Updates</a>
    <a class="nav-link" href="{{ route('treasurer.submission-remark') }}"><i class="bi bi-folder2-open me-2"></i> Submission &amp; Remark</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('treasurer.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')
<style>
    .treasurer-dashboard { display:flex; flex-direction:column; gap:1rem; }
    .treasurer-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1rem; }
    .treasurer-metric { min-height:130px; padding:1.15rem 1.2rem; border-radius:1rem; transition:transform .2s ease; }
    .treasurer-metric:hover { transform:translateY(-3px); }
    .metric-top { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
    .metric-copy small { display:block; margin-bottom:.3rem; color:#687b98; font-size:.82rem; }
    .metric-copy strong { display:block; color:#071b50; font-size:1.85rem; line-height:1; }
    .metric-copy span { display:block; margin-top:.55rem; color:#71809a; font-size:.74rem; }
    .metric-symbol { display:grid; width:46px; height:46px; flex:0 0 auto; place-items:center; border-radius:14px; font-size:1.25rem; }
    .symbol-blue { color:#075bea; background:rgba(7,91,234,.13); }
    .symbol-green { color:#14865c; background:rgba(25,171,113,.14); }
    .symbol-amber { color:#bd7900; background:rgba(255,178,22,.17); }
    .symbol-red { color:#cf4053; background:rgba(221,64,83,.13); }
    .dashboard-grid-treasurer { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(330px,.9fr); gap:1rem; }
    .dashboard-panel { padding:1.3rem; border-radius:1rem; }
    .panel-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.15rem; }
    .panel-heading h5 { margin:0 0 .25rem; color:#102a56; font-weight:800; }
    .panel-heading p { margin:0; color:#71809a; font-size:.8rem; }
    .completion-rate { color:#14865c; font-size:1rem; font-weight:800; }
    .status-track { display:flex; height:14px; overflow:hidden; border-radius:99px; background:rgba(211,225,239,.72); }
    .status-track span { display:block; height:100%; }
    .track-approved { background:linear-gradient(90deg,#149d6b,#49d7a5); }
    .track-pending { background:linear-gradient(90deg,#e99d00,#ffc83d); }
    .status-legend { display:grid; grid-template-columns:repeat(2,1fr); gap:.65rem; margin-top:1rem; }
    .legend-item { padding:.75rem; border:1px solid rgba(191,214,232,.55); border-radius:.8rem; background:rgba(255,255,255,.34); }
    .legend-item span { display:flex; align-items:center; gap:.45rem; color:#667b99; font-size:.74rem; }
    .legend-dot { width:9px; height:9px; border-radius:50%; }
    .legend-item strong { display:block; margin-top:.3rem; color:#102a56; font-size:1.1rem; }
    .quick-actions { display:grid; gap:.7rem; }
    .quick-action { display:flex; align-items:center; gap:.75rem; padding:.8rem; color:#173763; border:1px solid rgba(191,214,232,.58); border-radius:.85rem; background:rgba(255,255,255,.4); text-decoration:none; transition:.2s ease; }
    .quick-action:hover { color:#075bea; border-color:rgba(7,91,234,.27); background:rgba(255,255,255,.72); transform:translateX(3px); }
    .quick-icon { display:grid; width:40px; height:40px; flex:0 0 auto; place-items:center; color:#fff; border-radius:12px; background:linear-gradient(145deg,#36aaff,#075bea); box-shadow:0 7px 16px rgba(7,91,234,.2); }
    .quick-action strong { display:block; font-size:.86rem; }
    .quick-action small { display:block; margin-top:.12rem; color:#71809a; font-size:.7rem; }
    .quick-action > .bi-chevron-right { margin-left:auto; color:#7790ae; }
    .recent-panel { padding:1.3rem; border-radius:1rem; }
    .review-list { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; }
    .review-item { padding:.85rem; border:1px solid rgba(191,214,232,.55); border-radius:.85rem; background:rgba(255,255,255,.34); }
    .review-name { color:#102a56; font-weight:700; font-size:.86rem; }
    .review-meta { margin:.18rem 0 .55rem; color:#71809a; font-size:.72rem; }
    .review-footer { display:flex; justify-content:space-between; align-items:center; gap:.5rem; }
    .review-time { color:#8291a8; font-size:.68rem; }
    .empty-reviews { padding:2rem; color:#71809a; text-align:center; }
    @media(max-width:1050px){.treasurer-metrics{grid-template-columns:repeat(2,1fr)}.dashboard-grid-treasurer{grid-template-columns:1fr}.review-list{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:600px){.treasurer-metrics{gap:.65rem}.treasurer-metric{min-height:112px;padding:.85rem}.metric-symbol{width:38px;height:38px}.metric-copy strong{font-size:1.5rem}.status-legend,.review-list{grid-template-columns:1fr}.dashboard-panel,.recent-panel{padding:1rem}}
</style>
@endpush

@section('content')
@php
    $pendingCount = (int) $pendingCount;
    $approvedCount = (int) $approvedCount;
    $totalCases = $pendingCount + $approvedCount;
    $approvedPct = $totalCases ? (int) round(($approvedCount / $totalCases) * 100) : 0;
    $pendingPct = $totalCases ? (int) round(($pendingCount / $totalCases) * 100) : 0;
@endphp
<div class="page-content treasurer-dashboard">
    <div class="treasurer-metrics">
        <div class="metric-card treasurer-metric"><div class="metric-top"><div class="metric-copy"><small>Total Cases</small><strong>{{ $totalCases }}</strong><span>All financial clearances</span></div><span class="metric-symbol symbol-blue"><i class="bi bi-people"></i></span></div></div>
        <div class="metric-card treasurer-metric"><div class="metric-top"><div class="metric-copy"><small>Awaiting Review</small><strong>{{ $pendingCount }}</strong><span>Cases needing attention</span></div><span class="metric-symbol symbol-amber"><i class="bi bi-hourglass-split"></i></span></div></div>
        <div class="metric-card treasurer-metric"><div class="metric-top"><div class="metric-copy"><small>Approved</small><strong>{{ $approvedCount }}</strong><span>Financially cleared</span></div><span class="metric-symbol symbol-green"><i class="bi bi-check2-circle"></i></span></div></div>
    </div>

    <div class="dashboard-grid-treasurer">
        <section class="card dashboard-panel">
            <div class="panel-heading"><div><h5>Clearance distribution</h5><p>Current financial review status across your assigned students.</p></div><span class="completion-rate">{{ $approvedPct }}% approved</span></div>
            <div class="status-track"><span class="track-approved" style="width:{{ $approvedPct }}%"></span><span class="track-pending" style="width:{{ $pendingPct }}%"></span></div>
            <div class="status-legend">
                <div class="legend-item"><span><i class="legend-dot bg-success"></i>Approved</span><strong>{{ $approvedCount }}</strong></div>
                <div class="legend-item"><span><i class="legend-dot bg-warning"></i>Pending</span><strong>{{ $pendingCount }}</strong></div>
            </div>
        </section>

        <section class="card dashboard-panel">
            <div class="panel-heading"><div><h5>Quick actions</h5><p>Open your most important treasury workflows.</p></div></div>
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('treasurer.clearance-updates') }}"><span class="quick-icon"><i class="bi bi-journal-check"></i></span><span><strong>Review clearances</strong><small>Approve or update student cases</small></span><i class="bi bi-chevron-right"></i></a>
                <a class="quick-action" href="{{ route('treasurer.submission-remark') }}"><span class="quick-icon"><i class="bi bi-folder2-open"></i></span><span><strong>Open submissions</strong><small>Review documents and send remarks</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </section>
    </div>

    <section class="card recent-panel">
        <div class="panel-heading"><div><h5>Recent clearance activity</h5><p>The latest financial clearance records assigned to you.</p></div><a class="small text-decoration-none" href="{{ route('treasurer.clearance-updates') }}">View all</a></div>
        <div class="review-list">
            @forelse($clearanceList->take(6) as $case)
                @php $displayStatus = $case->status === 'Approved' ? 'Approved' : 'Pending'; $badge = $displayStatus === 'Approved' ? 'success' : 'warning'; @endphp
                <article class="review-item">
                    <div class="review-name"><i class="bi bi-person-circle text-primary me-1"></i>{{ trim(($case->firstname ?? '') . ' ' . ($case->lastname ?? '')) ?: $case->student_id }}</div>
                    <div class="review-meta">{{ $case->student_id }} · {{ $case->program }} {{ $case->year_level }}-{{ $case->section }}</div>
                    <div class="review-footer"><span class="badge text-bg-{{ $badge }}">{{ $displayStatus }}</span><span class="review-time">{{ $case->updated_at ? \Illuminate\Support\Carbon::parse($case->updated_at)->diffForHumans() : 'No update yet' }}</span></div>
                </article>
            @empty
                <div class="empty-reviews"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No financial clearance activity yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
