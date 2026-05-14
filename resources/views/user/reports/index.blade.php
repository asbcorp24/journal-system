@extends('user.layouts.app')

@section('title', 'Отчёты')

@section('content')

    <div class="mb-4">
        <h2 class="fw-bold mb-1">Отчёты</h2>
        <div class="text-secondary">
            Формирование отчётов и экспорт в Excel
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Доступные отчёты</h5>

                    <div class="list-group" id="reportsList">
                        @foreach($reports as $report)
                            <button type="button"
                                    class="list-group-item list-group-item-action bg-dark text-light border-secondary select-report"
                                    data-id="{{ $report->id }}">
                                <div class="fw-semibold">
                                    {{ $report->name }}
                                </div>

                                <div class="text-secondary small">
                                    {{ $report->description ?: 'Описание не указано' }}
                                </div>
                            </button>
                        @endforeach
                    </div>

                    @if($reports->count() === 0)
                        <div class="text-secondary text-center py-4">
                            Нет доступных отчётов
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="fw-bold mb-3" id="selectedReportTitle">
                        Выберите отчёт
                    </h5>

                    <form id="reportParamsForm">
                        <div class="row g-3" id="paramsForm"></div>

                        <div class="mt-4 d-none" id="reportActions">
                            <button type="submit" class="btn btn-primary">
                                Сформировать
                            </button>

                            <button type="button" class="btn btn-success" id="exportBtn">
                                <i class="bi bi-file-earmark-excel"></i>
                                Экспорт в Excel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card d-none" id="resultCard">
                <div class="card-body">
                    <h5 class="fw-bold mb-3">Результат отчёта</h5>

                    <div class="table-responsive">
                        <table class="table table-dark table-hover align-middle">
                            <thead>
                            <tr id="resultHead"></tr>
                            </thead>

                            <tbody id="resultBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        let selectedReportId = null;
        let selectedReport = null;
        let selectedSources = {};

        function renderParamForm(report, sources) {
            let schema = report.params_schema || [];
            let html = '';

            schema.forEach(function (field) {
                let required = field.required ? 'required' : '';
                let requiredMark = field.required ? '<span class="text-danger">*</span>' : '';

                html += `<div class="col-md-6">`;
                html += `<label class="form-label">${escapeHtml(field.label)} ${requiredMark}</label>`;

                if (field.type === 'string') {
                    html += `<input type="text" class="form-control report-param" data-key="${field.key}" ${required}>`;
                }

                else if (field.type === 'number') {
                    html += `<input type="number" class="form-control report-param" data-key="${field.key}" ${required}>`;
                }

                else if (field.type === 'date') {
                    html += `<input type="date" class="form-control report-param" data-key="${field.key}" ${required}>`;
                }

                else if (field.type === 'time') {
                    html += `<input type="time" class="form-control report-param" data-key="${field.key}" ${required}>`;
                }

                else if (field.type === 'list') {
                    html += `<select class="form-select report-param" data-key="${field.key}" ${required}>`;
                    html += `<option value="">Выберите значение</option>`;

                    (field.options || []).forEach(function (option) {
                        html += `<option value="${escapeHtml(option)}">${escapeHtml(option)}</option>`;
                    });

                    html += `</select>`;
                }

                else if (field.type === 'directory' || field.type === 'directory_text') {
                    html += `<select class="form-select report-param" data-key="${field.key}" ${required}>`;
                    html += `<option value="">Выберите значение</option>`;

                    let list = sources[field.key] || [];

                    list.forEach(function (item) {
                        html += `<option value="${item.id}">${escapeHtml(item.name)}</option>`;
                    });

                    html += `</select>`;
                }

                else {
                    html += `<input type="text" class="form-control report-param" data-key="${field.key}" ${required}>`;
                }

                html += `</div>`;
            });

            $('#paramsForm').html(html);
        }

        function collectParams() {
            let params = {};

            $('.report-param').each(function () {
                params[$(this).data('key')] = $(this).val();
            });

            return params;
        }

        function renderResult(columns, rows) {
            $('#resultCard').removeClass('d-none');

            if (!columns || columns.length === 0) {
                $('#resultHead').html('');
                $('#resultBody').html(`
                <tr>
                    <td class="text-center text-secondary py-5">
                        Данных нет
                    </td>
                </tr>
            `);
                return;
            }

            let head = '';

            columns.forEach(function (column) {
                head += `<th>${escapeHtml(column)}</th>`;
            });

            $('#resultHead').html(head);

            let body = '';

            rows.forEach(function (row) {
                body += '<tr>';

                columns.forEach(function (column) {
                    let value = row[column];

                    if (value === null || value === undefined) {
                        value = '';
                    }

                    if (typeof value === 'object') {
                        value = JSON.stringify(value);
                    }

                    body += `<td>${escapeHtml(value)}</td>`;
                });

                body += '</tr>';
            });

            $('#resultBody').html(body);
        }

        $(document).on('click', '.select-report', function () {
            selectedReportId = $(this).data('id');

            $('.select-report').removeClass('active');
            $(this).addClass('active');

            $('#resultCard').addClass('d-none');
            $('#resultHead').html('');
            $('#resultBody').html('');

            $.ajax({
                url: `/reports/${selectedReportId}`,
                method: "GET",
                success: function (response) {
                    selectedReport = response.report;
                    selectedSources = response.sources || {};

                    $('#selectedReportTitle').text(selectedReport.name);
                    $('#reportActions').removeClass('d-none');

                    renderParamForm(selectedReport, selectedSources);
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#reportParamsForm').on('submit', function (e) {
            e.preventDefault();

            if (!selectedReportId) {
                showToast('Выберите отчёт', 'warning');
                return;
            }

            $.ajax({
                url: `/reports/${selectedReportId}/run`,
                method: "POST",
                data: {
                    params: collectParams()
                },
                success: function (response) {
                    renderResult(response.columns, response.rows);
                    showToast('Отчёт сформирован', 'success');
                },
                error: function (xhr) {
                    showAjaxErrors(xhr);
                }
            });
        });

        $('#exportBtn').on('click', function () {
            if (!selectedReportId) {
                showToast('Выберите отчёт', 'warning');
                return;
            }

            let form = $('<form>', {
                method: 'POST',
                action: `/reports/${selectedReportId}/export`
            });

            form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: $('meta[name="csrf-token"]').attr('content')
            }));

            let params = collectParams();

            Object.keys(params).forEach(function (key) {
                form.append($('<input>', {
                    type: 'hidden',
                    name: `params[${key}]`,
                    value: params[key]
                }));
            });

            $('body').append(form);
            form.submit();
            form.remove();
        });
    </script>
@endpush
