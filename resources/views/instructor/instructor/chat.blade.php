@extends('instructor.layouts.instructor')
@section('title', 'Messages')
@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">@endpush

@section('content')
@php
    $yearLevels = $students->pluck('year_level')->filter()->unique()->sort()->values();
    $sections = $students->pluck('section')->filter()->unique()->sort()->values();
    $instructor = auth('instructor')->user();
@endphp
<div class="portal-messenger" id="instructorMessenger">
    <aside class="messenger-sidebar">
        <div class="messenger-sidebar-head">
            <div class="messenger-sidebar-title"><h3>Student conversations</h3><span>{{ $students->count() }}</span></div>
            <p>Search and message students assigned to your classes.</p>
            <div class="messenger-context">
                <i class="bi bi-person-video3"></i>
                <div><strong>{{ $instructor->full_name ?? 'Instructor' }}</strong><small>{{ $instructor->department ?: 'Instructor account' }}</small></div>
            </div>
        </div>
        <div class="messenger-tools">
            <label class="messenger-search" for="instructorStudentSearch"><i class="bi bi-search"></i><input id="instructorStudentSearch" type="search" placeholder="Search student name or ID…" autocomplete="off"></label>
            <div class="messenger-filter-row">
                <select class="messenger-filter" id="instructorYearFilter" aria-label="Filter students by year level">
                    <option value="">All year levels</option>
                    @foreach($yearLevels as $year)<option value="{{ strtolower($year) }}">Year {{ $year }}</option>@endforeach
                </select>
                <select class="messenger-filter" id="instructorSectionFilter" aria-label="Filter students by section">
                    <option value="">All sections</option>
                    @foreach($sections as $section)<option value="{{ strtolower($section) }}">{{ $section }}</option>@endforeach
                </select>
            </div>
        </div>
        <div class="messenger-contact-list">
            @forelse($students as $student)
                @php($unread = (int) ($unreadCounts[$student->student_id] ?? 0))
                <button class="messenger-contact" type="button"
                    data-contact data-id="{{ $student->student_id }}"
                    data-name="{{ $student->student_name }}"
                    data-program="{{ $student->program }}"
                    data-year="{{ strtolower($student->year_level) }}"
                    data-section="{{ strtolower($student->section) }}"
                    data-search="{{ strtolower($student->student_name . ' ' . $student->student_id . ' ' . $student->program . ' ' . $student->section) }}">
                    <span class="messenger-avatar">{{ strtoupper(substr($student->student_name, 0, 1)) }}</span>
                    <span class="messenger-contact-copy">
                        <span class="messenger-contact-line"><strong>{{ $student->student_name }}</strong>@if($unread)<span class="messenger-unread">{{ $unread }}</span>@endif</span>
                        <small>{{ $student->last_msg ? \Illuminate\Support\Str::limit($student->last_msg, 42) : $student->student_id }}</small>
                        <span class="messenger-contact-meta">
                            <span>{{ $student->program }}</span><span>Year {{ $student->year_level }}</span><span>Section {{ $student->section }}</span>
                        </span>
                    </span>
                </button>
            @empty
                <div class="messenger-list-empty"><i class="bi bi-people"></i>No students are assigned to your classes yet.</div>
            @endforelse
            <div class="messenger-list-empty d-none" id="instructorNoResults"><i class="bi bi-search"></i>No students match the selected filters.</div>
        </div>
    </aside>

    <section class="messenger-chat">
        <header class="messenger-chat-head">
            <div class="messenger-chat-person">
                <button class="messenger-back" id="instructorMessengerBack" type="button" aria-label="Back to conversations"><i class="bi bi-arrow-left"></i></button>
                <span class="messenger-avatar" id="instructorThreadAvatar"><i class="bi bi-chat-dots"></i></span>
                <div><h3 id="instructorThreadTitle">Select a student</h3><p id="instructorThreadSubtitle">Student program, year level, and section will appear here.</p></div>
            </div>
            <span class="messenger-online">Messaging available</span>
        </header>
        <div class="messenger-thread" id="instructorThread">
            <div class="messenger-empty-thread"><i class="bi bi-chat-square-text"></i>Choose a student to open the conversation.</div>
        </div>
        <form class="messenger-composer" id="instructorComposer">
            <textarea id="instructorMessageInput" rows="1" maxlength="2000" placeholder="Write a message…" disabled></textarea>
            <button class="messenger-send" id="instructorSendButton" type="submit" disabled aria-label="Send message"><i class="bi bi-send-fill"></i></button>
        </form>
    </section>
</div>

@push('scripts')
<script>
(() => {
    const messenger = document.getElementById('instructorMessenger');
    const contacts = [...messenger.querySelectorAll('[data-contact]')];
    const search = document.getElementById('instructorStudentSearch');
    const yearFilter = document.getElementById('instructorYearFilter');
    const sectionFilter = document.getElementById('instructorSectionFilter');
    const noResults = document.getElementById('instructorNoResults');
    const thread = document.getElementById('instructorThread');
    const title = document.getElementById('instructorThreadTitle');
    const subtitle = document.getElementById('instructorThreadSubtitle');
    const avatar = document.getElementById('instructorThreadAvatar');
    const input = document.getElementById('instructorMessageInput');
    const sendButton = document.getElementById('instructorSendButton');
    let activeStudent = '';
    let lastMessageId = 0;
    let pollTimer = null;
    let loadingMessages = false;
    let renderedMessageIds = new Set();

    const filterContacts = () => {
        const query = search.value.trim().toLowerCase();
        const year = yearFilter.value;
        const section = sectionFilter.value;
        let visible = 0;
        contacts.forEach(contact => {
            const matches = (!query || contact.dataset.search.includes(query))
                && (!year || contact.dataset.year === year)
                && (!section || contact.dataset.section === section);
            contact.hidden = !matches;
            if (matches) visible++;
        });
        noResults.classList.toggle('d-none', visible !== 0 || contacts.length === 0);
    };

    const appendMessage = message => {
        const bubble = document.createElement('div');
        bubble.className = `messenger-bubble ${message.sender_role === 'instructor' ? 'mine' : 'theirs'}`;
        bubble.textContent = message.message;
        const time = document.createElement('div');
        time.className = 'messenger-message-time';
        time.textContent = message.time_fmt;
        bubble.appendChild(time);
        thread.appendChild(bubble);
    };

    const loadMessages = async () => {
        if (!activeStudent || loadingMessages) return;
        loadingMessages = true;
        try {
            const response = await apiFetch(`{{ route('instructor.chat.messages') }}?with=${encodeURIComponent(activeStudent)}&since=${lastMessageId}`);
            if (!response.ok) return;
            const rows = await response.json();
            rows.forEach(message => {
                if (renderedMessageIds.has(message.id)) return;
                renderedMessageIds.add(message.id);
                appendMessage(message);
                lastMessageId = Math.max(lastMessageId, message.id);
            });
            if (rows.length) thread.scrollTop = thread.scrollHeight;
        } finally {
            loadingMessages = false;
        }
    };

    const openThread = contact => {
        contacts.forEach(item => item.classList.remove('active'));
        contact.classList.add('active');
        contact.querySelector('.messenger-unread')?.remove();
        activeStudent = contact.dataset.id;
        lastMessageId = 0;
        renderedMessageIds = new Set();
        title.textContent = contact.dataset.name;
        subtitle.textContent = `${contact.dataset.program} · Year ${contact.dataset.year} · Section ${contact.dataset.section}`;
        avatar.textContent = contact.dataset.name.charAt(0).toUpperCase();
        thread.innerHTML = '';
        input.disabled = false;
        sendButton.disabled = false;
        messenger.classList.add('is-thread-open');
        loadMessages();
        clearInterval(pollTimer);
        pollTimer = setInterval(loadMessages, 3500);
        window.setTimeout(() => input.focus(), 0);
    };

    const sendMessage = async () => {
        const message = input.value.trim();
        if (!message || !activeStudent) return;
        input.value = '';
        const response = await apiFetch(@json(route('instructor.chat.send')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiver_id: activeStudent, message }),
        });
        if (response.ok) loadMessages();
    };

    contacts.forEach(contact => contact.addEventListener('click', () => openThread(contact)));
    search.addEventListener('input', filterContacts);
    yearFilter.addEventListener('change', filterContacts);
    sectionFilter.addEventListener('change', filterContacts);
    document.getElementById('instructorComposer').addEventListener('submit', event => { event.preventDefault(); sendMessage(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }
    });
    document.getElementById('instructorMessengerBack').addEventListener('click', () => messenger.classList.remove('is-thread-open'));
    window.addEventListener('beforeunload', () => clearInterval(pollTimer));
})();
</script>
@endpush
@endsection
