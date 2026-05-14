@if($users->hasPages())
    <div class="d-flex justify-content-between align-items-center">
        <div class="text-secondary">
            Показано {{ $users->firstItem() }}–{{ $users->lastItem() }} из {{ $users->total() }}
        </div>

        {{ $users->links('pagination::bootstrap-5') }}
    </div>
@endif
