{{-- resources/views/partials/file-preview.blade.php --}}
@if($filePath)
  @if(Str::endsWith($filePath, '.pdf'))
    <a class="badge text-bg-danger" onclick="showImageModal('{{ url('/stock-opname/image/'.$filePath) }}')">
      <i class="fas fa-file-pdf fa-2x"></i>
      <span class="ms-1">Lihat PDF</span>
    </a>
  @else
    <img src="{{ url('/stock-opname/image/'.$filePath) }}" 
         alt="Preview" 
         class="img-thumbnail cursor-pointer preview-thumbnail">
  @endif
@else
  <span>-</span>
@endif