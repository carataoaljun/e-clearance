@extends('layouts.portal')

@section('title', 'Registrar Dashboard')
@section('portal-name', 'Registrar Portal')
@section('portal-subtitle', 'Clearance Management')
@section('page-title', 'Registrar Dashboard')
@section('user-label', $registrar->full_name ?? $registrar->email)
@section('user-role', 'Registrar')

@section('nav')
    <a class="nav-link active" href="{{ route('registrar.dashboard') }}"><i class="bi bi-speedometer2 me-2"></i> Dashboard</a>
    <a class="nav-link" href="{{ route('registrar.student-clearance') }}"><i class="bi bi-bar-chart-line me-2"></i> Student Clearance</a>
    <a class="nav-link" href="{{ route('registrar.qr-scanner') }}"><i class="bi bi-qr-code-scan me-2"></i> QR Code Scanner</a>
@endsection

@section('logout-form')
    <form method="POST" action="{{ route('registrar.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>
@endsection

@push('styles')
<style>
    .registrar-dashboard { display:flex; flex-direction:column; gap:1rem; }
    .registrar-metrics { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:1rem; }
    .registrar-metric { min-height:130px; padding:1.15rem 1.2rem; border-radius:1rem; transition:transform .2s ease; }
    .registrar-metric:hover { transform:translateY(-3px); }
    .metric-top { display:flex; align-items:flex-start; justify-content:space-between; gap:1rem; }
    .metric-copy small { display:block; margin-bottom:.3rem; color:#687b98; font-size:.82rem; }
    .metric-copy strong { display:block; color:#071b50; font-size:1.85rem; line-height:1; }
    .metric-copy span { display:block; margin-top:.55rem; color:#71809a; font-size:.74rem; }
    .metric-symbol { display:grid; width:46px; height:46px; flex:0 0 auto; place-items:center; border-radius:14px; font-size:1.25rem; }
    .symbol-blue { color:#075bea; background:rgba(7,91,234,.13); }
    .symbol-cyan { color:#0783a8; background:rgba(20,180,215,.14); }
    .symbol-green { color:#14865c; background:rgba(25,171,113,.14); }
    .symbol-amber { color:#bd7900; background:rgba(255,178,22,.17); }
    .registrar-grid { display:grid; grid-template-columns:minmax(0,1.1fr) minmax(330px,.9fr); gap:1rem; }
    .dashboard-panel { padding:1.3rem; border-radius:1rem; }
    .panel-heading { display:flex; justify-content:space-between; align-items:flex-start; gap:1rem; margin-bottom:1.15rem; }
    .panel-heading h5 { margin:0 0 .25rem; color:#102a56; font-weight:800; }
    .panel-heading p { margin:0; color:#71809a; font-size:.8rem; }
    .completion-rate { color:#14865c; font-size:1rem; font-weight:800; }
    .status-track { display:flex; height:14px; overflow:hidden; border-radius:99px; background:rgba(211,225,239,.72); }
    .track-cleared { background:linear-gradient(90deg,#149d6b,#49d7a5); }
    .track-pending { background:linear-gradient(90deg,#e99d00,#ffc83d); }
    .status-summary { display:grid; grid-template-columns:1fr 1fr; gap:.75rem; margin-top:1rem; }
    .status-box { display:flex; align-items:center; gap:.7rem; padding:.8rem; border:1px solid rgba(191,214,232,.55); border-radius:.8rem; background:rgba(255,255,255,.34); }
    .status-dot { width:10px; height:10px; flex:0 0 auto; border-radius:50%; }
    .status-box strong { display:block; color:#102a56; }
    .status-box small { color:#71809a; }
    .quick-actions { display:grid; gap:.7rem; }
    .quick-action { display:flex; align-items:center; gap:.75rem; padding:.8rem; color:#173763; border:1px solid rgba(191,214,232,.58); border-radius:.85rem; background:rgba(255,255,255,.4); text-decoration:none; transition:.2s ease; }
    .quick-action:hover { color:#075bea; border-color:rgba(7,91,234,.27); background:rgba(255,255,255,.72); transform:translateX(3px); }
    .quick-icon { display:grid; width:40px; height:40px; flex:0 0 auto; place-items:center; color:#fff; border-radius:12px; background:linear-gradient(145deg,#36aaff,#075bea); box-shadow:0 7px 16px rgba(7,91,234,.2); }
    .quick-action strong { display:block; font-size:.86rem; }
    .quick-action small { display:block; margin-top:.12rem; color:#71809a; font-size:.7rem; }
    .quick-action > .bi-chevron-right { margin-left:auto; color:#7790ae; }
    .program-panel { padding:1.3rem; border-radius:1rem; }
    .program-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:.75rem; }
    .program-item { padding:.85rem; border:1px solid rgba(191,214,232,.55); border-radius:.85rem; background:rgba(255,255,255,.34); }
    .program-head { display:flex; justify-content:space-between; align-items:center; gap:.75rem; margin-bottom:.65rem; }
    .program-name { color:#102a56; font-size:.84rem; font-weight:700; }
    .program-count { color:#075bea; font-size:.82rem; font-weight:800; }
    .program-track { height:7px; overflow:hidden; border-radius:99px; background:rgba(203,219,234,.7); }
    .program-track span { display:block; height:100%; border-radius:inherit; background:linear-gradient(90deg,#21bfe7,#075bea); }
    .empty-programs { padding:2rem; color:#71809a; text-align:center; }
    @media(max-width:1050px){.registrar-metrics{grid-template-columns:repeat(2,1fr)}.registrar-grid{grid-template-columns:1fr}.program-grid{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:600px){.registrar-metrics{gap:.65rem}.registrar-metric{min-height:112px;padding:.85rem}.metric-symbol{width:38px;height:38px}.metric-copy strong{font-size:1.5rem}.program-grid,.status-summary{grid-template-columns:1fr}.dashboard-panel,.program-panel{padding:1rem}}
</style>
@endpush

@section('content')
@php
    $totalStudents = (int) $totalStudents;
    $pendingRequests = (int) $pendingRequests;
    $clearedRequests = (int) $clearedRequests;
    $totalRequests = $pendingRequests + $clearedRequests;
    $clearedPct = $totalRequests ? (int) round(($clearedRequests / $totalRequests) * 100) : 0;
    $pendingPct = $totalRequests ? 100 - $clearedPct : 0;
@endphp
<div class="page-content registrar-dashboard">
    <div class="registrar-metrics">
        <div class="metric-card registrar-metric"><div class="metric-top"><div class="metric-copy"><small>Total Students</small><strong>{{ number_format($totalStudents) }}</strong><span>Registered student accounts</span></div><span class="metric-symbol symbol-blue"><i class="bi bi-people"></i></span></div></div>
        <div class="metric-card registrar-metric"><div class="metric-top"><div class="metric-copy"><small>Total Reviews</small><strong>{{ number_format($totalRequests) }}</strong><span>Clearance records tracked</span></div><span class="metric-symbol symbol-cyan"><i class="bi bi-clipboard-data"></i></span></div></div>
        <div class="metric-card registrar-metric"><div class="metric-top"><div class="metric-copy"><small>Awaiting Review</small><strong>{{ number_format($pendingRequests) }}</strong><span>Requests still pending</span></div><span class="metric-symbol symbol-amber"><i class="bi bi-hourglass-split"></i></span></div></div>
        <div class="metric-card registrar-metric"><div class="metric-top"><div class="metric-copy"><small>Cleared</small><strong>{{ number_format($clearedRequests) }}</strong><span>Approved clearance records</span></div><span class="metric-symbol symbol-green"><i class="bi bi-patch-check"></i></span></div></div>
    </div>

    <div class="registrar-grid">
        <section class="card dashboard-panel">
            <div class="panel-heading"><div><h5>Clearance completion</h5><p>Overall status of clearance records across the college.</p></div><span class="completion-rate">{{ $clearedPct }}% cleared</span></div>
            <div class="status-track"><span class="track-cleared" style="width:{{ $clearedPct }}%"></span><span class="track-pending" style="width:{{ $pendingPct }}%"></span></div>
            <div class="status-summary">
                <div class="status-box"><span class="status-dot bg-success"></span><div><strong>{{ $clearedRequests }} Cleared</strong><small>Final approvals completed</small></div></div>
                <div class="status-box"><span class="status-dot bg-warning"></span><div><strong>{{ $pendingRequests }} Pending</strong><small>Records awaiting action</small></div></div>
            </div>
        </section>

        <section class="card dashboard-panel">
            <div class="panel-heading"><div><h5>Quick actions</h5><p>Open the Registrar’s primary workflows.</p></div></div>
            <div class="quick-actions">
                <a class="quick-action" href="{{ route('registrar.student-clearance') }}"><span class="quick-icon"><i class="bi bi-journal-check"></i></span><span><strong>Student clearances</strong><small>Review and finalize student records</small></span><i class="bi bi-chevron-right"></i></a>
                <a class="quick-action" href="{{ route('registrar.qr-scanner') }}"><span class="quick-icon"><i class="bi bi-qr-code-scan"></i></span><span><strong>Scan clearance QR</strong><small>Verify a generated clearance form</small></span><i class="bi bi-chevron-right"></i></a>
            </div>
        </section>
    </div>

    <section class="card program-panel">
        <div class="panel-heading"><div><h5>Students by program</h5><p>Enrollment distribution across academic programs.</p></div><span class="small text-secondary">{{ $studentsByProgram->count() }} programs</span></div>
        <div class="program-grid">
            @forelse($studentsByProgram as $program)
                @php $programPct = $totalStudents ? (int) round(($program->total / $totalStudents) * 100) : 0; @endphp
                <article class="program-item"><div class="program-head"><span class="program-name"><i class="bi bi-mortarboard text-primary me-1"></i>{{ $program->program ?: 'Unassigned' }}</span><span class="program-count">{{ $program->total }}</span></div><div class="program-track"><span style="width:{{ $programPct }}%"></span></div></article>
            @empty
                <div class="empty-programs"><i class="bi bi-inbox fs-3 d-block mb-2"></i>No student records yet.</div>
            @endforelse
        </div>
    </section>
</div>
@endsection
