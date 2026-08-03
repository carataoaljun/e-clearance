@extends('mainAdmin.layouts.admin')
@section('title', 'Subject Assignments — ClearanceMS')

@push('styles')
<style>
    .section-checklist { display:flex; flex-direction:column; gap:.55rem; background:#f8fafc; overflow-y:auto; }
    .section-dropdown { border:1px solid #dbe4f0; border-radius:.6rem; background:#fff; overflow:hidden; interpolate-size:allow-keywords; }
    .section-dropdown summary { padding:.65rem .8rem; cursor:pointer; color:#334155; font-weight:600; list-style:none; transition:background-color .2s ease; }
    .section-dropdown summary::-webkit-details-marker { display:none; }
    .section-dropdown summary::after { content:'⌄'; float:right; color:#64748b; }
    .section-dropdown[open] summary { border-bottom:1px solid #dbe4f0; background:#f8fafc; }
    .section-dropdown::details-content { block-size:0; opacity:0; overflow:hidden; transition:block-size .25s ease, opacity .2s ease, content-visibility .25s allow-discrete; }
    .section-dropdown[open]::details-content { block-size:auto; opacity:1; }
    .assignment-section-option { position:relative; display:flex; align-items:center; justify-content:center; padding:.6rem 2rem; border:1px solid #dbe4f0; border-radius:.55rem; background:#fff; cursor:pointer; font-size:.9rem; font-weight:600; text-align:center; transition:.15s ease; }
    .assignment-section-option:hover { border-color:#60a5fa; background:#eff6ff; }
    .assignment-section-option:has(input:checked) { border-color:#2563eb; background:#dbeafe; color:#1d4ed8; }
    .assignment-section-option input { position:absolute; left:.7rem; top:50%; width:.85rem; height:.85rem; transform:translateY(-50%); accent-color:#2563eb; }
</style>
@endpush

@section('content')
<x-main-admin.page-header
    title="Subject Assignments"
    description="Connect instructors with subjects, programs, year levels, and sections."
    icon="bi bi-diagram-3-fill"
    eyebrow="Academic setup"
>
    <x-slot:actions>
        <button class="btn-add" onclick="openAddModal()"><i class="bi bi-plus-circle-fill"></i> Add Assignment</button>
        <button class="btn-csv" onclick="openCsvModal('assignments')"><i class="bi bi-filetype-csv"></i> Import CSV</button>
    </x-slot:actions>
</x-main-admin.page-header>

<div class="filter-bar">
    <form method="GET" action="{{ route('assignments.index') }}">
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="program">
                    <option value="">All Programs</option>
                    @foreach(['BSIT','BSBA','BSHM','BSED','BEED'] as $prog)
                    <option value="{{ $prog }}" {{ request('program') === $prog ? 'selected' : '' }}>{{ $prog }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="year_level">
                    <option value="">All Years</option>
                    @foreach([1,2,3,4] as $year)
                    <option value="{{ $year }}" {{ request('year_level') == $year ? 'selected' : '' }}>{{ $year }} Year</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3"><input type="search" name="section" placeholder="Filter section" value="{{ request('section') }}"></div>
            <div class="col-md-2"><button type="submit" class="btn-filter w-100">Filter</button></div>
        </div>
    </form>
</div>

<div class="data-table-wrap">
    <div class="data-table-header">
        <h3><i class="bi bi-table"></i> Assignments</h3>
        <span style="font-size:12px;color:var(--muted);">{{ $assignments->total() }} results</span>
    </div>
    <div class="table-scroll">
        <table class="cms-table">
            <thead>
                <tr><th>Instructor</th><th>Subject</th><th>Program</th><th>Year</th><th>Section</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse($assignments as $row)
                <tr>
                    <td>{{ $row->instructor->firstname ?? '—' }} {{ $row->instructor->lastname ?? '' }}</td>
                    <td>{{ $row->subject->subject_code ?? '—' }} — {{ $row->subject->subject_description ?? '' }}</td>
                    <td>{{ $row->program }}</td>
                    <td>{{ $row->year_level }}</td>
                    <td>{{ $row->section }}</td>
                    <td style="white-space:nowrap;">
                        <button class="act-edit" onclick='openEdit({{ json_encode($row) }})'><i class="bi bi-pencil-fill"></i> Edit</button>
                        <form method="POST" action="{{ route('assignments.destroy', ['id' => $row->assignment_id]) }}" style="display:inline;" data-confirm-title="Confirm Deletion" data-confirm="Are you sure you want to delete this assignment?&#10;This action cannot be undone." data-confirm-button="Yes, Delete">
                            @csrf @method('DELETE')
                            <button type="submit" class="act-delete"><i class="bi bi-trash3-fill"></i> Del</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6"><div class="empty-state">No assignments found.</div></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="padding:16px 20px;">{{ $assignments->links() }}</div>
</div>

<div class="modal-overlay" id="addModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-plus-circle-fill" style="color:var(--success);margin-right:8px;"></i>Add Assignment</h4>
            <button class="close-btn" onclick="closeAddModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" action="{{ route('assignments.store') }}">
                @csrf
                <div class="form-row">
                    <div class="fg"><label>Instructor</label>
                        <select name="instructor_id" required>
                            <option value="">Select instructor</option>
                            @foreach($instructors as $inst)
                            <option value="{{ $inst->instructor_id }}">{{ $inst->firstname }} {{ $inst->lastname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Subject</label>
                        <select name="subject_id" required>
                            <option value="">Select subject</option>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->subject_id }}">{{ $subject->subject_code }} — {{ $subject->subject_description }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" id="add_program" required onchange="filterAssignmentSections('add')">
                            <option value="">Select program</option>
                            @foreach(['BSIT','BSBA','BSHM','BSED','BEED'] as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="add_year_level" required onchange="filterAssignmentSections('add')">
                            <option value="">Select year</option>
                            @foreach([1,2,3,4] as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Sections <small class="text-muted">(select one or more)</small></label>
                    <details class="section-dropdown">
                        <summary>Choose sections</summary>
                        <div id="add_sections" class="section-checklist p-2" style="min-height:110px;max-height:180px;color:#111827;">
                            <span class="text-secondary small">Select program and year first</span>
                        </div>
                    </details>
                </div>
                <button type="submit" class="btn-save" style="display:block;margin:1rem auto 0;"><i class="bi bi-save-fill"></i> Save Assignment</button>
            </form>
        </div>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div class="modal-hdr">
            <h4><i class="bi bi-pencil-square" style="color:var(--warning);margin-right:8px;"></i>Edit Assignment</h4>
            <button class="close-btn" onclick="closeEditModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-body">
            <form method="POST" id="editForm">
                @csrf @method('PUT')
                <div class="form-row">
                    <div class="fg"><label>Instructor</label>
                        <select name="instructor_id" id="e_instructor" required>
                            <option value="">Select instructor</option>
                            @foreach($instructors as $inst)
                            <option value="{{ $inst->instructor_id }}">{{ $inst->firstname }} {{ $inst->lastname }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Subject</label>
                        <select name="subject_id" id="e_subject" required>
                            <option value="">Select subject</option>
                            @foreach($subjects as $subject)
                            <option value="{{ $subject->subject_id }}">{{ $subject->subject_code }} — {{ $subject->subject_description }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="fg"><label>Program</label>
                        <select name="program" id="e_program" required onchange="filterAssignmentSections('edit')">
                            <option value="">Select program</option>
                            @foreach(['BSIT','BSBA','BSHM','BSED','BEED'] as $prog)
                            <option value="{{ $prog }}">{{ $prog }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fg"><label>Year Level</label>
                        <select name="year_level" id="e_year_level" required onchange="filterAssignmentSections('edit')">
                            <option value="">Select year</option>
                            @foreach([1,2,3,4] as $year)
                            <option value="{{ $year }}">{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="fg"><label>Sections <small class="text-muted">(select one or more)</small></label>
                    <details class="section-dropdown">
                        <summary>Choose sections</summary>
                        <div id="e_sections" class="section-checklist p-2" style="min-height:110px;max-height:180px;color:#111827;"><span class="text-secondary small">Select program and year first</span></div>
                    </details>
                </div>
                <button type="submit" class="btn-save" style="display:block;margin:1rem auto 0;"><i class="bi bi-check-circle-fill"></i> Update Assignment</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const assignmentSections = @json($sections);

function filterAssignmentSections(formType, selectedSection = '') {
    const isAdd = formType === 'add';
    const program = document.getElementById(isAdd ? 'add_program' : 'e_program').value;
    const yearLevel = document.getElementById(isAdd ? 'add_year_level' : 'e_year_level').value;
    const select = document.getElementById(isAdd ? 'add_sections' : 'e_sections');
    const matching = assignmentSections.filter(section => section.program === program && String(section.year_level) === String(yearLevel));
    select.innerHTML = '';
    if (!matching.length) {
        select.innerHTML = '<span class="text-secondary small">No managed sections available</span>';
        return;
    }
    const selectedSections = Array.isArray(selectedSection) ? selectedSection : [selectedSection];
    matching.forEach(section => {
        const label = document.createElement('label');
        label.className = 'assignment-section-option';
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.name = 'sections[]';
        checkbox.value = section.section;
        checkbox.checked = selectedSections.includes(section.section);
        label.append(checkbox, document.createTextNode(section.section));
        select.appendChild(label);
    });
}
function openAddModal() { document.getElementById('addModal').classList.add('show'); }
function closeAddModal() { document.getElementById('addModal').classList.remove('show'); }
function openEdit(assignment) {
    const form = document.getElementById('editForm');
    form.action = '{{ url('/mainAdmin/assignments') }}/' + (assignment.assignment_id || assignment.id);
    document.getElementById('e_instructor').value = assignment.instructor_id || '';
    document.getElementById('e_subject').value = assignment.subject_id || '';
    document.getElementById('e_program').value = assignment.program || '';
    document.getElementById('e_year_level').value = assignment.year_level || '';
    filterAssignmentSections('edit', assignment.section || '');
    document.getElementById('editModal').classList.add('show');
}
function closeEditModal() { document.getElementById('editModal').classList.remove('show'); }
</script>
@endpush
