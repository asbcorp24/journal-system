@extends('user.layouts.app')

@section('title', 'Мои журналы')

@section('content')

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">Мои журналы</h2>
            <div class="text-secondary">
                Доступные журналы по подразделению и персональным назначениям
            </div>
        </div>

        <div class="text-end text-secondary">
            <div>{{ session('user_name') }}</div>
            <div class="small">
                Роль: {{ session('user_role') }}
            </div>
        </div>
    </div>

    @if($journals->count() === 0)
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-journal-x" style="font-size: 54px;"></i>

                <h5 class="mt-3">Нет доступных журналов</h5>

                <div class="text-secondary">
                    Администратор ещё не назначил вам журналы или персональные доступы.
                </div>
            </div>
        </div>
    @else
        <div class="row g-4">
            @foreach($journals as $journal)
                <div class="col-md-4">
                    <div class="card journal-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="rounded-3 bg-primary p-3">
                                    <i class="bi bi-journal-text fs-4"></i>
                                </div>

                                <div class="d-flex align-items-center gap-2">
                                    <button type="button"
                                            class="btn btn-sm btn-outline-warning toggle-favorite-btn"
                                            data-entity-type="journal"
                                            data-entity-id="{{ $journal->id }}"
                                            title="Избранное">
                                        <i class="bi {{ !empty($journal->is_favorite) ? 'bi-star-fill' : 'bi-star' }}"></i>
                                    </button>

                                    <span class="badge bg-success">Активен</span>
                                </div>
                            </div>

                            <h5 class="fw-bold">{{ $journal->name }}</h5>

                            <div class="text-secondary small mb-3">
                                {{ $journal->description ?: 'Описание не указано' }}
                            </div>

                            <div class="mb-3 d-flex flex-wrap gap-2">
                                <span class="badge bg-secondary">
                                    Полей: {{ is_array($journal->schema) ? count($journal->schema) : 0 }}
                                </span>

                                @if(($journal->user_access_mode ?? 'full') === 'view')
                                    <span class="badge bg-secondary">Только просмотр</span>
                                @else
                                    <span class="badge bg-success">Полный доступ</span>
                                @endif
                            </div>

                            <div class="d-grid">
                                <a href="{{ route('user.journals.show', $journal->id) }}"
                                   class="btn btn-outline-info">
                                    Открыть журнал
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection

@push('scripts')
    <script>
        $(document).on('click', '.toggle-favorite-btn', function () {
            let button = $(this);

            $.ajax({
                url: "{{ route('user.favorites.toggle') }}",
                method: 'POST',
                data: {
                    entity_type: button.data('entity-type'),
                    entity_id: button.data('entity-id')
                },
                success: function (response) {
                    showToast(response.message, 'success');
                    window.location.reload();
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });
    </script>
@endpush
