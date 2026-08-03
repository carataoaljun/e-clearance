<form method="GET" action="{{ $action }}" class="clearance-filters">
    <div class="clearance-filter-field search"><i class="bi bi-search"></i><input type="search" name="search" value="{{ request('search') }}" placeholder="Search by name or ID number..."></div>
    @if(($programs ?? collect())->isNotEmpty())
    <div class="clearance-filter-field"><label>Program</label><select name="program"><option value="">All</option>@foreach($programs as $program)<option value="{{ $program }}" @selected(request('program') == $program)>{{ $program }}</option>@endforeach</select></div>
    @endif
    @if(($years ?? collect())->isNotEmpty())
    <div class="clearance-filter-field"><label>Year Level</label><select name="year_level"><option value="">All</option>@foreach($years as $year)<option value="{{ $year }}" @selected((string) request('year_level') === (string) $year)>{{ $year }}</option>@endforeach</select></div>
    @endif
    @if(($sections ?? collect())->isNotEmpty())
    <div class="clearance-filter-field"><label>Section</label><select name="section"><option value="">All</option>@foreach($sections as $section)<option value="{{ $section }}" @selected(request('section') == $section)>{{ $section }}</option>@endforeach</select></div>
    @endif
    <div class="clearance-filter-field"><label>Status</label><select name="status"><option value="">All</option><option value="Pending" @selected(request('status') === 'Pending')>Pending</option><option value="Approved" @selected(request('status') === 'Approved')>Approved</option></select></div>
    <div class="clearance-filter-field sort"><label>Sort By</label><select name="sort"><option value="desc" @selected(request('sort','desc') === 'desc')>Newest First</option><option value="asc" @selected(request('sort') === 'asc')>Oldest First</option></select></div>
    <div class="clearance-filter-actions"><a class="clearance-filter-btn reset" href="{{ $action }}"><i class="bi bi-arrow-clockwise"></i> Reset</a><button class="clearance-filter-btn apply" type="submit"><i class="bi bi-funnel"></i> Apply</button></div>
</form>
