@if ($paginator->hasPages())
  <nav class="d-flex justify-content-end mt-3">{{ $paginator->links() }}</nav>
@endif
