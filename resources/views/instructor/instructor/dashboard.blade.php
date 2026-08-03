@extends('instructor.layouts.instructor')
@section('title', 'Dashboard')

@push('styles')
<style>
    .instructor-dashboard { display:flex; flex-direction:column; gap:1rem; }
    .instructor-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
    .instructor-metric { min-height:132px; padding:1.15rem 1.2rem; border-radius:1rem; transition:transform .2s ease,box-shadow .2s ease; }
    .instructor-metric:hover { transform:translateY(-2px); }
    .metric-top { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
    .metric-copy small { display:block; margin-bottom:.3rem; color:#687b98; font-size:.82rem; }
    .metric-copy strong { display:block; color:#071b50; font-size:1.85rem; line-height:1; }
    .metric-copy span { display:block; margin-top:.55rem; color:#71809a; font-size:.76rem; }
    .metric-symbol { display:grid; width:46px; height:46px; flex:0 0 auto; place-items:center; border-radius:14px; font-size:1.25rem; }
    .symbol-blue { color:#075bea; background:rgba(7,91,234,.13); }
    .symbol-green { color:#14865c; background:rgba(25,171,113,.14); }
    .symbol-amber { color:#c47b00; background:rgba(255,178,22,.17); }
    .symbol-purple { color:#7048cf; background:rgba(112,72,207,.14); }
    .dashboard-panels { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(320px,.75fr); gap:1rem; }
    .dashboard-panel { padding:1.35rem; border-radius:1rem; }
    .panel-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.25rem; }
    .panel-heading h5 { margin:0 0 .25rem; color:#102a56; font-weight:800; }
    .panel-heading p { margin:0; color:#71809a; font-size:.82rem; }
    .approval-rate { color:#075bea; font-weight:800; }
    .progress-track { display:flex; height:14px; overflow:hidden; border-radius:99px; background:rgba(211,225,239,.72); }
    .progress-approved { background:linear-gradient(90deg,#18a771,#45d4a2); }
    .progress-pending { background:linear-gradient(90deg,#f1a900,#ffc83d); }
    .status-legend { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-top:1rem; }
    .legend-item { display:flex; align-items:center; gap:.7rem; padding:.8rem; border:1px solid rgba(191,214,232,.56); border-radius:.8rem; background:rgba(255,255,255,.34); }
    .legend-dot { width:10px; height:10px; border-radius:50%; }
    .legend-item strong { display:block; color:#102a56; }
    .legend-item small { color:#71809a; }
    .quick-actions { display:grid; gap:.7rem; }
    .quick-action { display:flex; align-items:center; gap:.8rem; padding:.85rem; color:#173763; border:1px solid rgba(191,214,232,.58); border-radius:.85rem; background:rgba(255,255,255,.42); text-decoration:none; transition:.2s ease; }
    .quick-action:hover { color:#075bea; border-color:rgba(7,91,234,.28); background:rgba(255,255,255,.72); transform:translateX(3px); }
    .quick-icon { display:grid; width:40px; height:40px; flex:0 0 auto; place-items:center; color:#fff; border-radius:12px; background:linear-gradient(145deg,#36aaff,#075bea); box-shadow:0 7px 16px rgba(7,91,234,.2); }
    .quick-action strong { display:block; font-size:.88rem; }
    .quick-action small { display:block; margin-top:.15rem; color:#71809a; font-size:.72rem; }
    .quick-action > .bi-chevron-right { margin-left:auto; color:#7790ae; }
    @media(max-width:1050px){.instructor-metrics{grid-template-columns:repeat(2,1fr)}.dashboard-panels{grid-template-columns:1fr}}
    @media(max-width:560px){.instructor-metrics{grid-template-columns:1fr 1fr;gap:.65rem}.instructor-metric{min-height:115px;padding:.9rem}.metric-symbol{width:38px;height:38px}.metric-copy strong{font-size:1.5rem}.dashboard-panel{padding:1rem}.status-legend{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $totalStudents = (int) ($stats->total_students ?? 0);
    $approved = (int) ($stats->approved ?? 0);
    $pending = (int) ($stats->pending ?? 0);
    $reviewTotal = $approved + $pending;
    $approvalRate = $reviewTotal ? (int) round(($approved / $reviewTotal) * 100) : 0;
    $pendingRate = $reviewTotal ? 100 - $approvalRate : 0;
@endphp
<div class="page-content-fit instructor-dashboard">
    <div class="instructor-metrics">
        <div class="metric-card instructor-metric"><div class="metric-top"><div class="metric-copy"><small>Total Students</small><strong>{{ $totalStudents }}</strong><span>Assigned to your classes</span></div><span class="metric-symbol symbol-blue"><i class="bi bi-people"></i></span></div></div>
        <div class="metric-card instructor-metric"><div class="metric-top"><div class="metric-copy"><small>Approved</small><strong>{{ $approved }}</strong><span>Completed clearances</span></div><span class="metric-symbol symbol-green"><i class="bi bi-check2-circle"></i></span></div></div>
        <div class="metric-card instructor-metric"><div class="metric-top"><div class="metric-copy"><small>Awaiting Review</small><strong>{{ $pending }}</strong><span>Items needing attention</span></div><span class="metric-symbol symbol-amber"><i class="bi bi-hourglass-split"></i></span></div></div>
        <div class="metric-card instructor-metric"><div class="metric-top"><div class="metric-copy"><small>Submissions</small><strong>{{ $totalSubmissions }}</strong><span>Student files received</span></div><span class="metric-symbol symbol-purple"><i class="bi bi-folder2-open"></i></span></div></div>
    </div>

    <div class="dashboard-panels">
        <section class="card dashboard-panel">
            <div class="panel-heading"><div><h5>Clearance distribution</h5><p>A live summary of your student clearance reviews.</p></div><span class="approval-rate">{{ $approvalRate }}% approved</span></div>
            <div class="progress-track" aria-label="Clearance approval progress">
                <span class="progress-approved" style="width:{{ $approvalRate }}%"></span>
                <span class="progress-pending" style="width:{{ $pendingRate }}%"></span>
            </div>
            <div class="status-legend">
                <div class="legend-item"><span class="legend-dot bg-success"></span><div><strong>{{ $approved }} Approved</strong><small>Clearance reviews completed</small></div></div>
                <div class="legend-item"><span class="legend-dot bg-warning"></span><div><strong>{{ $pending }} Pending</strong><small>Still waiting for review</small></div></div>
            </div>
        </section>

        <section class="card dashboard-panel">
            <div class="panel-heading"><div><h5>Quick actions</h5><p>Jump directly to your daily tasks.</p></div></div>
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('instructor.submissions.index') }}"><span class="quick-icon"><i class="bi bi-file-earmark-check"></i></span><span><strong>Review submissions</strong><small>Open student files and send feedback</small></span><i class="bi bi-chevron-right"></i></a>
                <a class="quick-action" href="{{ route('instructor.clearance') }}"><span class="quick-icon"><i class="bi bi-journal-check"></i></span><span><strong>Manage clearances</strong><small>Approve or return clearance requests</small></span><i class="bi bi-chevron-right"></i></a>
                <a class="quick-action" href="{{ route('instructor.chat') }}"><span class="quick-icon"><i class="bi bi-chat-dots"></i></span><span><strong>Student messages</strong><small>Respond to assigned students</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </section>
    </div>
</div>
@endsection
