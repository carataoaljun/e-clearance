@extends('layouts.portal')

@section('title', 'Office Dashboard')
@section('portal-name', 'Office Portal')
@section('portal-subtitle', ucwords($officeName))
@section('page-title', ucwords($officeName) . ' Dashboard')
@section('user-label', $office->full_name)
@section('user-role', ucwords($officeName))

@section('nav')
    <a class="nav-link active" href="{{ route('office.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('office.submissions') }}"><i class="bi bi-folder2-open me-2"></i> Submissions & Remark</a>
    <a class="nav-link" href="{{ route('office.clearance.requests') }}"><i class="bi bi-clipboard2-check me-2"></i> Student Clearance Requests</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('office.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')
<style>
    .office-dashboard { --office-blue:#0d6efd; --office-navy:#102a56; --office-green:#0f9f6e; --office-amber:#f5a800; }
    .office-metrics { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.85rem; margin-bottom:1rem; }
    .office-metric { min-height:126px; padding:1rem 1.05rem; border-radius:1rem; display:flex; justify-content:space-between; gap:.7rem; }
    .metric-copy { display:flex; flex-direction:column; min-width:0; }
    .metric-copy small { color:#6b7b94; font-weight:600; }
    .metric-copy strong { margin:.22rem 0; color:#071c49; font-size:1.8rem; line-height:1; }
    .metric-copy span { margin-top:auto; color:#75839a; font-size:.75rem; }
    .office-metric-icon { width:46px; height:46px; flex:0 0 46px; display:grid; place-items:center; border-radius:14px; font-size:1.2rem; }
    .icon-amber { color:#b87300; background:rgba(255,190,43,.22); }
    .icon-green { color:#078459; background:rgba(25,190,127,.17); }
    .icon-red { color:#d6334c; background:rgba(235,72,96,.15); }
    .icon-blue { color:#0876d1; background:rgba(13,110,253,.14); }
    .office-main-grid { display:grid; grid-template-columns:minmax(0,1.25fr) minmax(310px,.75fr); gap:1rem; margin-bottom:1rem; }
    .office-panel { border-radius:1.05rem; padding:1.15rem 1.25rem; }
    .office-panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1rem; }
    .office-panel h3 { margin:0 0 .18rem; color:#122d58; font-size:1.05rem; font-weight:800; }
    .office-panel-head p { margin:0; color:#75839a; font-size:.82rem; }
    .completion-value { color:var(--office-green); font-weight:800; white-space:nowrap; }
    .completion-track { height:11px; overflow:hidden; border-radius:999px; background:rgba(148,163,184,.2); }
    .completion-bar { height:100%; border-radius:inherit; background:linear-gradient(90deg,#0e9f6e,#3bd5a1); }
    .status-legend { display:grid; grid-template-columns:repeat(2,1fr); gap:.65rem; margin-top:1rem; }
    .legend-item { padding:.7rem .8rem; border:1px solid rgba(133,166,201,.2); border-radius:.8rem; background:rgba(255,255,255,.34); }
    .legend-item span { display:block; color:#718098; font-size:.72rem; }
    .legend-item strong { color:#17315b; }
    .office-actions { display:grid; gap:.7rem; }
    .office-action { display:flex; align-items:center; gap:.8rem; padding:.78rem .85rem; border:1px solid rgba(116,157,200,.22); border-radius:.85rem; color:#17345f; background:rgba(255,255,255,.42); text-decoration:none; transition:.18s ease; }
    .office-action:hover { transform:translateY(-2px); border-color:rgba(13,110,253,.35); color:#0d6efd; box-shadow:0 9px 22px rgba(35,102,160,.1); }
    .action-icon { width:39px; height:39px; flex:0 0 39px; display:grid; place-items:center; border-radius:11px; color:#fff; background:linear-gradient(135deg,#2b9cf0,#1768eb); }
    .office-action strong,.office-action small { display:block; }
    .office-action small { color:#77869b; font-size:.74rem; }
    .office-action > i { margin-left:auto; color:#7990ac; }
    .office-bottom-grid { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(300px,.9fr); gap:1rem; }
    .queue-list,.submission-list { display:grid; gap:.55rem; }
    .queue-row,.submission-row { display:flex; align-items:center; gap:.75rem; padding:.7rem .75rem; border:1px solid rgba(124,158,194,.18); border-radius:.8rem; background:rgba(255,255,255,.32); }
    .student-avatar { width:38px; height:38px; flex:0 0 38px; display:grid; place-items:center; border-radius:50%; color:#0969c7; background:rgba(13,110,253,.13); font-weight:800; }
    .row-copy { min-width:0; flex:1; }
    .row-copy strong,.row-copy span { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .row-copy strong { color:#18345d; font-size:.85rem; }
    .row-copy span { color:#7b889c; font-size:.73rem; }
    .office-status { padding:.28rem .5rem; border-radius:999px; font-size:.67rem; font-weight:800; }
    .status-pending { color:#9a6700; background:#fff1bd; }
    .status-approved,.status-received { color:#087654; background:#d8f7e9; }
    .status-rejected { color:#bd2940; background:#ffe0e5; }
    .empty-office { padding:1.5rem; text-align:center; color:#7b889c; }
    @media(max-width:1100px){.office-metrics{grid-template-columns:repeat(2,1fr)}.office-main-grid,.office-bottom-grid{grid-template-columns:1fr}}
    @media(max-width:620px){.office-metrics{grid-template-columns:1fr}.status-legend{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $totalReviews = $pendingCount + $approvedCount;
    $completion = $totalReviews > 0 ? (int) round(($approvedCount / $totalReviews) * 100) : 0;
    $initials = fn($first, $last) => strtoupper(substr((string)$first, 0, 1) . substr((string)$last, 0, 1));
@endphp
<div class="office-dashboard">
    <section class="office-metrics">
        <div class="card office-metric"><div class="metric-copy"><small>Awaiting Review</small><strong>{{ $pendingCount }}</strong><span>Clearance requests pending</span></div><div class="office-metric-icon icon-amber"><i class="bi bi-hourglass-split"></i></div></div>
        <div class="card office-metric"><div class="metric-copy"><small>Approved</small><strong>{{ $approvedCount }}</strong><span>Completed office clearances</span></div><div class="office-metric-icon icon-green"><i class="bi bi-patch-check"></i></div></div>
        <div class="card office-metric"><div class="metric-copy"><small>New Submissions</small><strong>{{ $pendingSubmissions }}</strong><span>Documents awaiting review</span></div><div class="office-metric-icon icon-blue"><i class="bi bi-file-earmark-arrow-up"></i></div></div>
    </section>

    <section class="office-main-grid">
        <div class="card office-panel"><div class="office-panel-head"><div><h3>Clearance Completion</h3><p>Overall outcome of requests assigned to this office.</p></div><span class="completion-value">{{ $completion }}% approved</span></div><div class="completion-track"><div class="completion-bar" style="width:{{ $completion }}%"></div></div><div class="status-legend"><div class="legend-item"><span>Approved</span><strong>{{ $approvedCount }}</strong></div><div class="legend-item"><span>Pending</span><strong>{{ $pendingCount }}</strong></div></div></div>
        <div class="card office-panel"><div class="office-panel-head"><div><h3>Quick Actions</h3><p>Open your primary workflows.</p></div></div><div class="office-actions"><a class="office-action" href="{{ route('office.clearance.requests') }}"><span class="action-icon"><i class="bi bi-clipboard2-check"></i></span><span><strong>Clearance Requests</strong><small>Approve or return student requests</small></span><i class="bi bi-chevron-right"></i></a><a class="office-action" href="{{ route('office.submissions') }}"><span class="action-icon"><i class="bi bi-folder2-open"></i></span><span><strong>Student Submissions</strong><small>Review uploaded requirements</small></span><i class="bi bi-chevron-right"></i></a></div></div>
    </section>

    <section class="office-bottom-grid">
        <div class="card office-panel"><div class="office-panel-head"><div><h3>Latest Clearance Requests</h3><p>Most recently updated student records.</p></div><a href="{{ route('office.clearance.requests') }}" class="small text-decoration-none">View all</a></div><div class="queue-list">@forelse($clearanceList->take(5) as $item)<div class="queue-row"><span class="student-avatar">{{ $initials($item->firstname, $item->lastname) }}</span><div class="row-copy"><strong>{{ trim(($item->firstname ?? '') . ' ' . ($item->lastname ?? '')) ?: $item->student_id }}</strong><span>{{ $item->student_id }} · {{ $item->program }} {{ $item->year_level }}-{{ $item->section }}</span></div><span class="office-status status-{{ strtolower($item->status) }}">{{ $item->status }}</span></div>@empty<div class="empty-office"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No clearance requests yet.</div>@endforelse</div></div>
        <div class="card office-panel"><div class="office-panel-head"><div><h3>Recent Submissions</h3><p>Latest files sent to your office.</p></div><a href="{{ route('office.submissions') }}" class="small text-decoration-none">View all</a></div><div class="submission-list">@forelse($recentSubmissions->take(5) as $submission)<div class="submission-row"><span class="action-icon"><i class="bi bi-file-earmark-text"></i></span><div class="row-copy"><strong>{{ $submission->file_name ?: 'Office submission' }}</strong><span>{{ trim(($submission->firstname ?? '') . ' ' . ($submission->lastname ?? '')) ?: $submission->student_id }} · {{ \Illuminate\Support\Carbon::parse($submission->submitted_at)->diffForHumans() }}</span></div><span class="office-status status-{{ strtolower($submission->status) }}">{{ $submission->status }}</span></div>@empty<div class="empty-office"><i class="bi bi-folder2-open fs-3 d-block mb-2"></i>No submissions received yet.</div>@endforelse</div></div>
    </section>
</div>
@endsection
