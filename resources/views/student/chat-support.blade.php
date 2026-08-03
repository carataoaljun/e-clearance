@extends('layouts.portal')
@section('theme-body-class', 'student-portal-theme')
@section('title', 'Messages')
@section('portal-name', 'Student Portal')
@section('portal-subtitle', $student->student_id)
@section('page-title', 'Messages')
@section('user-label', $student->full_name . ' · ' . $student->program . ' ' . $student->year_level . '-' . $student->section)
@section('user-role', 'Student')
@section('nav')
<a class="nav-link" href="{{ route('student.dashboard') }}"><i class="bi bi-grid-1x2"></i> Dashboard</a><a class="nav-link" href="{{ route('student.clearance-updates') }}"><i class="bi bi-clipboard2-check"></i> Clearance Updates</a><a class="nav-link" href="{{ route('student.submission-remark') }}"><i class="bi bi-file-earmark-arrow-up"></i> Submission & Remark</a><a class="nav-link active" href="{{ route('student.chat-support') }}"><i class="bi bi-chat-square-text"></i> Chat Support</a>
@endsection
@section('logout-form')<form method="POST" action="{{ route('student.logout') }}">@csrf<button type="submit" class="sidebar-action"><i class="bi bi-box-arrow-right me-2"></i> Log Out</button></form>@endsection
@push('styles')<link href="{{ asset('css/messenger_interface.css') }}" rel="stylesheet">@endpush
@section('content')
@php
    $departments = $instructors->pluck('department')->filter()->unique()->sort()->values();
    $studentClass = $student->program . ' · Year ' . $student->year_level . ' · Section ' . $student->section;
@endphp
<div class="portal-messenger" id="studentMessenger">
    <aside class="messenger-sidebar">
        <div class="messenger-sidebar-head">
            <div class="messenger-sidebar-title"><h3>Instructor messages</h3><span>{{ $instructors->count() }}</span></div>
            <p>Chat privately with instructors assigned to your class.</p>
            <div class="messenger-context">
                <i class="bi bi-mortarboard"></i>
                <div><strong>{{ $student->full_name }}</strong><small>{{ $studentClass }}</small></div>
            </div>
        </div>
        <div class="messenger-tools">
            <label class="messenger-search" for="studentContactSearch"><i class="bi bi-search"></i><input id="studentContactSearch" type="search" placeholder="Search instructor or department…" autocomplete="off"></label>
            <select class="messenger-filter" id="studentDepartmentFilter" aria-label="Filter instructors by department">
                <option value="">All departments</option>
                @foreach($departments as $department)<option value="{{ strtolower($department) }}">{{ $department }}</option>@endforeach
            </select>
        </div>
        <div class="messenger-contact-list" id="studentContactList">
            @forelse($instructors as $instructor)
                @php($unread = (int) ($unreadCounts[$instructor->instructor_id] ?? 0))
                <button class="messenger-contact" type="button"
                    data-contact data-id="{{ $instructor->instructor_id }}"
                    data-name="{{ $instructor->instructor_name }}"
                    data-department="{{ strtolower($instructor->department ?: 'Instructor') }}"
                    data-department-label="{{ $instructor->department ?: 'Instructor' }}"
                    data-search="{{ strtolower($instructor->instructor_name . ' ' . ($instructor->department ?: 'Instructor')) }}">
                    <span class="messenger-avatar">{{ strtoupper(substr($instructor->instructor_name, 0, 1)) }}</span>
                    <span class="messenger-contact-copy">
                        <span class="messenger-contact-line"><strong>{{ $instructor->instructor_name }}</strong>@if($unread)<span class="messenger-unread">{{ $unread }}</span>@endif</span>
                        <small>{{ $instructor->department ?: 'Instructor' }}</small>
                        <span class="messenger-contact-meta"><span><i class="bi bi-people me-1"></i>{{ $student->year_level }}-{{ $student->section }}</span></span>
                    </span>
                </button>
            @empty
                <div class="messenger-list-empty"><i class="bi bi-person-x"></i>No instructor is assigned to your class yet.</div>
            @endforelse
            <div class="messenger-list-empty d-none" id="studentNoResults"><i class="bi bi-search"></i>No instructors match your search and filter.</div>
        </div>
    </aside>

    <section class="messenger-chat">
        <header class="messenger-chat-head">
            <div class="messenger-chat-person">
                <button class="messenger-back" id="studentMessengerBack" type="button" aria-label="Back to conversations"><i class="bi bi-arrow-left"></i></button>
                <span class="messenger-avatar" id="studentThreadAvatar"><i class="bi bi-chat-dots"></i></span>
                <div><h3 id="studentThreadTitle">Select an instructor</h3><p id="studentThreadSubtitle">{{ $studentClass }}</p></div>
            </div>
            <span class="messenger-online">Messaging available</span>
        </header>
        <div class="messenger-thread" id="studentThread">
            <div class="messenger-empty-thread"><i class="bi bi-chat-square-text"></i>Choose an instructor to start chatting.</div>
        </div>
        <form class="messenger-composer" id="studentComposer">
            <textarea id="studentMessageInput" rows="1" maxlength="2000" placeholder="Write a message…" disabled></textarea>
            <button class="messenger-send" id="studentSendButton" type="submit" disabled aria-label="Send message"><i class="bi bi-send-fill"></i></button>
        </form>
    </section>
</div>
@push('scripts')<script>
(() => {
    const messenger = document.getElementById('studentMessenger');
    const contacts = [...messenger.querySelectorAll('[data-contact]')];
    const search = document.getElementById('studentContactSearch');
    const departmentFilter = document.getElementById('studentDepartmentFilter');
    const noResults = document.getElementById('studentNoResults');
    const thread = document.getElementById('studentThread');
    const title = document.getElementById('studentThreadTitle');
    const subtitle = document.getElementById('studentThreadSubtitle');
    const avatar = document.getElementById('studentThreadAvatar');
    const input = document.getElementById('studentMessageInput');
    const sendButton = document.getElementById('studentSendButton');
    let activeInstructor = '';
    let lastMessageId = 0;
    let pollTimer = null;
    let loadingMessages = false;
    let renderedMessageIds = new Set();
    const studentClass = @json($studentClass);

    const chatFetch = (url, options = {}) => {
        options.headers = { ...(options.headers || {}), 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '', Accept: 'application/json' };
        return fetch(url, options);
    };

    const filterContacts = () => {
        const query = search.value.trim().toLowerCase();
        const department = departmentFilter.value;
        let visible = 0;
        contacts.forEach(contact => {
            const matches = (!query || contact.dataset.search.includes(query)) && (!department || contact.dataset.department === department);
            contact.hidden = !matches;
            if (matches) visible++;
        });
        noResults.classList.toggle('d-none', visible !== 0 || contacts.length === 0);
    };

    const appendMessage = message => {
        const bubble = document.createElement('div');
        bubble.className = `messenger-bubble ${message.sender_role === 'student' ? 'mine' : 'theirs'}`;
        bubble.textContent = message.message;
        const time = document.createElement('div');
        time.className = 'messenger-message-time';
        time.textContent = message.time_fmt;
        bubble.appendChild(time);
        thread.appendChild(bubble);
    };

    const loadMessages = async () => {
        if (!activeInstructor || loadingMessages) return;
        loadingMessages = true;
        try {
            const response = await chatFetch(`{{ route('student.chat.messages') }}?with=${encodeURIComponent(activeInstructor)}&since=${lastMessageId}`);
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
        activeInstructor = contact.dataset.id;
        lastMessageId = 0;
        renderedMessageIds = new Set();
        title.textContent = contact.dataset.name;
        subtitle.textContent = `${contact.dataset.departmentLabel} · ${studentClass}`;
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
        if (!message || !activeInstructor) return;
        input.value = '';
        const response = await chatFetch(@json(route('student.chat.send')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ receiver_id: activeInstructor, message }),
        });
        if (response.ok) {
            loadMessages();
        } else if (window.showFeedbackModal) {
            window.showFeedbackModal({ tone: 'danger', title: 'Message not sent', message: 'Please try sending your message again.' });
        }
    };

    contacts.forEach(contact => contact.addEventListener('click', () => openThread(contact)));
    search.addEventListener('input', filterContacts);
    departmentFilter.addEventListener('change', filterContacts);
    document.getElementById('studentComposer').addEventListener('submit', event => { event.preventDefault(); sendMessage(); });
    input.addEventListener('keydown', event => {
        if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(); }
    });
    document.getElementById('studentMessengerBack').addEventListener('click', () => messenger.classList.remove('is-thread-open'));
    window.addEventListener('beforeunload', () => clearInterval(pollTimer));
})();
</script>@endpush
@endsection
