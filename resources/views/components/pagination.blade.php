@if($paginator->hasPages())
<nav class="pagination" aria-label="Pagination">
<span class="muted">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }} events</span>
<div class="actions">
@if($paginator->onFirstPage())<span class="btn btn-secondary disabled" aria-disabled="true">Previous</span>
@else<a class="btn btn-secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>@endif
<span class="muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
@if($paginator->hasMorePages())<a class="btn btn-secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
@else<span class="btn btn-secondary disabled" aria-disabled="true">Next</span>@endif
</div></nav>
@endif
