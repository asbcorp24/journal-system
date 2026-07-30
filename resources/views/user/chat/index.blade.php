@extends('user.layouts.app')

@section('title', 'Чат')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="fw-bold mb-1">Чат</h2>
            <div class="text-secondary">
                Внутренний обмен сообщениями между пользователями
            </div>
        </div>
    </div>

    <div class="alert alert-info border-0 mb-3" style="background: rgba(59, 130, 246, 0.14); color: #dbeafe;">
        Подсказки по чату, импорту и другим новым функциям собраны в
        <a href="{{ asset('help.html') }}" target="_blank" rel="noopener" class="alert-link">хелпе</a>.
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-body d-flex flex-column p-3">
                    <div class="mb-2">
                        <input type="text"
                               id="chatSearchInput"
                               class="form-control form-control-sm"
                               placeholder="Поиск по пользователям">
                    </div>

                    <div id="chatContactsList" class="d-flex flex-column gap-1 flex-grow-1">
                        <div class="text-secondary text-center py-5">
                            Загрузка...
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-body d-flex flex-column p-3" style="min-height: 60vh;">
                    <div class="border-bottom border-secondary pb-2 mb-2">
                        <div class="fw-semibold" id="chatCurrentUserName">Выберите собеседника</div>
                        <div class="text-secondary small" id="chatCurrentUserMeta"></div>
                    </div>

                    <div id="chatMessagesBox" class="flex-grow-1 overflow-auto pe-1 small">
                        <div class="text-secondary text-center py-5">
                            Выберите пользователя слева, чтобы открыть диалог
                        </div>
                    </div>

                    <form id="chatMessageForm" class="mt-2">
                        <div class="input-group input-group-sm">
                            <textarea id="chatMessageInput"
                                      class="form-control form-control-sm"
                                      rows="1"
                                      placeholder="Введите сообщение"
                                      disabled></textarea>
                            <button type="submit" class="btn btn-primary btn-sm px-3" id="sendChatMessageBtn" disabled>
                                Отправить
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .chat-contact-card {
            border: 1px solid #334155;
            border-radius: 10px;
            background: #0f172a;
            color: #e5e7eb;
            text-align: left;
            padding: 8px 10px;
            transition: .15s ease;
        }

        .chat-contact-card:hover,
        .chat-contact-card.active {
            border-color: #38bdf8;
            background: #132033;
        }

        .chat-contact-card .fw-semibold {
            font-size: 14px;
            line-height: 1.2;
        }

        .chat-contact-card .small {
            font-size: 11px;
            line-height: 1.25;
        }

        .chat-message-row {
            display: flex;
            margin-bottom: 4px;
        }

        .chat-message-row.mine {
            justify-content: flex-end;
        }

        .chat-message-row.theirs {
            justify-content: flex-start;
        }

        .chat-message {
            display: inline-block;
            width: fit-content;
            max-width: min(66%, 520px);
            border-radius: 12px;
            padding: 6px 8px;
            white-space: pre-wrap;
            word-break: break-word;
            font-size: 13px;
            line-height: 1.25;
        }

        .chat-message.mine {
            background: #2563eb;
            color: #ffffff;
            border-bottom-right-radius: 6px;
        }

        .chat-message.theirs {
            background: #1e293b;
            color: #e5e7eb;
            border-bottom-left-radius: 6px;
        }

        .chat-message-meta {
            font-size: 10px;
            opacity: .72;
            margin-top: 3px;
        }

        #chatMessageInput {
            min-height: 34px;
            max-height: 72px;
            resize: vertical;
        }
    </style>
@endpush

@push('scripts')
    <script>
        let selectedChatUserId = @json($initialUserId ? (string) $initialUserId : null);
        let chatContacts = [];
        let chatPollTimer = null;

        function renderChatContacts(items) {
            if (!items || items.length === 0) {
                $('#chatContactsList').html(`
                    <div class="text-secondary text-center py-5">
                        Пользователи не найдены
                    </div>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                let active = String(selectedChatUserId) === String(item.id) ? 'active' : '';
                let unreadBadge = item.unread_count > 0
                    ? `<span class="badge bg-danger">${item.unread_count > 99 ? '99+' : item.unread_count}</span>`
                    : '';
                let lastMeta = item.last_message
                    ? `${item.last_message_is_mine ? 'Вы: ' : ''}${escapeHtml(item.last_message)}`
                    : '<span class="text-secondary">Нет сообщений</span>';
                let title = item.is_general
                    ? `<div class="fw-semibold"><i class="bi bi-people-fill me-1"></i>${escapeHtml(item.name)}</div>`
                    : `<div class="fw-semibold">${escapeHtml(item.name)}</div>`;

                html += `
                    <button type="button" class="chat-contact-card ${active}" data-id="${item.id}">
                        <div class="d-flex justify-content-between align-items-start gap-2">
                            <div>
                                ${title}
                                <div class="text-secondary small">
                                    ${escapeHtml(item.division_name || 'Без подразделения')}${item.is_general ? '' : ' / ' + escapeHtml(item.role || '')}
                                </div>
                            </div>
                            ${unreadBadge}
                        </div>
                        <div class="small mt-1">${lastMeta}</div>
                        <div class="text-secondary small mt-1">${escapeHtml(item.last_message_at || '')}</div>
                    </button>
                `;
            });

            $('#chatContactsList').html(html);
        }

        function loadChatContacts(selectFirst = false) {
            $.ajax({
                url: "{{ route('user.chat.contacts') }}",
                method: 'GET',
                data: {
                    search: $('#chatSearchInput').val()
                },
                success: function (response) {
                    chatContacts = response.items || [];

                    if (selectFirst && !selectedChatUserId && chatContacts.length) {
                        selectedChatUserId = String(chatContacts[0].id);
                    }

                    if (selectedChatUserId && !chatContacts.some(function (item) {
                        return String(item.id) === String(selectedChatUserId);
                    })) {
                        selectedChatUserId = null;
                    }

                    renderChatContacts(chatContacts);

                    if (selectedChatUserId) {
                        loadChatMessages(false);
                    } else {
                        $('#chatCurrentUserName').text('Выберите собеседника');
                        $('#chatCurrentUserMeta').text('');
                        $('#chatMessagesBox').html(`
                            <div class="text-secondary text-center py-5">
                                Выберите пользователя слева, чтобы открыть диалог
                            </div>
                        `);
                        $('#chatMessageInput').prop('disabled', true).val('');
                        $('#sendChatMessageBtn').prop('disabled', true);
                    }
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function renderChatMessages(items) {
            if (!items || items.length === 0) {
                $('#chatMessagesBox').html(`
                    <div class="text-secondary text-center py-5">
                        Сообщений пока нет
                    </div>
                `);
                return;
            }

            let html = '';

            items.forEach(function (item) {
                html += `
                    <div class="chat-message-row ${item.is_mine ? 'mine' : 'theirs'}">
                        <div class="chat-message ${item.is_mine ? 'mine' : 'theirs'}">
                            <div>${escapeHtml(item.message)}</div>
                            <div class="chat-message-meta">
                                ${escapeHtml(item.created_at || '')}${item.is_mine && item.is_read ? ' / прочитано' : ''}
                            </div>
                        </div>
                    </div>
                `;
            });

            $('#chatMessagesBox').html(html);
            let box = document.getElementById('chatMessagesBox');
            box.scrollTop = box.scrollHeight;
        }

        function loadChatMessages(refreshContacts = true) {
            if (!selectedChatUserId) {
                return;
            }

            if (String(selectedChatUserId) === 'general') {
                $.ajax({
                    url: "{{ route('user.chat.general-messages') }}",
                    method: 'GET',
                    success: function (response) {
                        $('#chatCurrentUserName').text(response.user.name || '');
                        $('#chatCurrentUserMeta').text(response.user.division_name || '');
                        $('#chatMessageInput').prop('disabled', false);
                        $('#sendChatMessageBtn').prop('disabled', false);
                        renderChatMessages(response.items || []);

                        if (refreshContacts) {
                            loadChatContacts(false);
                        }
                    },
                    error: function (xhr) {
                        showAjaxErrors(xhr);
                    }
                });
                return;
            }

            $.ajax({
                url: `/chat/users/${selectedChatUserId}/messages`,
                method: 'GET',
                success: function (response) {
                    $('#chatCurrentUserName').text(response.user.name || '');
                    $('#chatCurrentUserMeta').text([
                        response.user.division_name || 'Без подразделения',
                        response.user.role || ''
                    ].join(' / '));
                    $('#chatMessageInput').prop('disabled', false);
                    $('#sendChatMessageBtn').prop('disabled', false);
                    renderChatMessages(response.items || []);

                    if (refreshContacts) {
                        loadChatContacts(false);
                    }
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        }

        function startChatPolling() {
            if (chatPollTimer) {
                clearInterval(chatPollTimer);
            }

            chatPollTimer = setInterval(function () {
                loadChatContacts(false);
            }, 10000);
        }

        $(document).on('click', '.chat-contact-card', function () {
            selectedChatUserId = String($(this).data('id'));
            renderChatContacts(chatContacts);
            loadChatMessages();
        });

        $('#chatSearchInput').on('keyup', function (e) {
            if (e.key === 'Enter') {
                loadChatContacts(false);
            }
        });

        $('#chatMessageForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedChatUserId) {
                return;
            }

            let message = ($('#chatMessageInput').val() || '').trim();

            if (!message) {
                showToast('Введите сообщение', 'warning');
                return;
            }

            $.ajax({
                url: String(selectedChatUserId) === 'general'
                    ? "{{ route('user.chat.general-store') }}"
                    : `/chat/users/${selectedChatUserId}/messages`,
                method: 'POST',
                data: {
                    message: message
                },
                success: function (response) {
                    $('#chatMessageInput').val('');
                    showToast(response.message || 'Сообщение отправлено', 'success');
                    loadChatMessages();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        loadChatContacts(true);
        startChatPolling();
    </script>
@endpush
