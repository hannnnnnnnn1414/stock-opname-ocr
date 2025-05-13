{{-- resources/views/components/gagal-proses-modal.blade.php --}}
<div class="modal fade" id="gagalProsesModal" tabindex="-1" aria-labelledby="gagalProsesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="gagalProsesModalLabel">Informasi Gagal Proses</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p>Error 400: {{ count(Storage::disk('stock_opname')->allFiles('error_400')) }}</p>
          <p>Error 401: {{ count(Storage::disk('stock_opname')->allFiles('error_401')) }}</p>
          <p>Error 500: {{ count(Storage::disk('stock_opname')->allFiles('error_500')) }}</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>