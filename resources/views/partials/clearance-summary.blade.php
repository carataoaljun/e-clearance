<div class="clearance-summary">
    <article class="clearance-stat pending">
        <div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-hourglass-split" aria-hidden="true"></i></span><div class="clearance-stat-copy"><small>Pending</small><strong>{{ number_format($pendingCount) }}</strong><span>Requests awaiting action</span></div></div>
    </article>
    <article class="clearance-stat approved">
        <div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-check-circle"></i></span><div class="clearance-stat-copy"><small>Approved</small><strong>{{ number_format($approvedCount) }}</strong><span>Completed clearances</span></div></div>
    </article>
    <article class="clearance-stat total">
        <div class="clearance-stat-main"><span class="clearance-stat-icon"><i class="bi bi-people"></i></span><div class="clearance-stat-copy"><small>Total Students</small><strong>{{ number_format($totalStudents) }}</strong><span>{{ $totalCaption ?? 'Students in this clearance queue' }}</span></div></div>
    </article>
</div>
