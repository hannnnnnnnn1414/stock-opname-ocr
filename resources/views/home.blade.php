<!DOCTYPE html>
<html lang="en">
  @include('components.head')

  <body class="g-sidenav-show bg-gray-100">
    {{-- @include('components.sidebar') --}}
    
    <main class="main-content position-relative max-height-vh-100 h-100 mt-1 border-radius-lg">
      @include('components.navbar')

      <div class="container-fluid py-4">
        <div class="row">
          <div class="col-xl-3 col-sm-6 mb-4">
            @include('components.stats-card', [
              'title' => 'Total Dokumen',
              'value' => $totalDokumen,
              'icon' => 'ni ni-money-coins'
            ])
          </div>
          
          <div class="col-xl-3 col-sm-6 mb-4">
            @include('components.stats-card', [
              'title' => 'Dokumen Ter-Ekstrak',
              'value' => $dokumenTerekstrak,
              'icon' => 'ni ni-world'
            ])
          </div>

          <div class="col-xl-3 col-sm-6 mb-4">
            <div class="card cursor-pointer" id="gagalProsesCard">
              @include('components.stats-card', [
                'title' => 'Gagal Proses',
                'value' => $gagalDiproses,
                'icon' => 'ni ni-world',
              ])
            </div>
            @include('components.gagal-proses-modal')
          </div>

          <div class="col-xl-3 col-sm-6 mb-4">
            @include('components.stats-card', [
              'title' => 'Dokumen Ditolak',
              'value' => $rejectedFiles,
              'icon' => 'ni ni-world'
            ])
          </div>
        </div>

        <!-- Data Saved Card -->
        <div class="row mt-3">
          <div class="col-xl-3 col-sm-6 mb-4">
            @include('components.stats-card', [
              'title' => 'Data Tersimpan di DB',
              'value' => $berhasilDisimpan,
              'icon' => 'ni ni-paper-diploma'
            ])
          </div>
        </div>      

        <div class="row mt-4">
          <div class="col">
            <div class="card">
              <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                  <h5 class="mb-0">Stock Opname Results</h5>
                
                          <!-- Form pencarian -->
                  <form method="GET" action="{{ url('/home') }}" class="w-50" id="searchForm">
                    <div class="input-group">
                      <input type="text" 
                            class="form-control form-control-sm" 
                            name="search" 
                            placeholder="Search by Nomor Form, Nama Part, or Nomor Part"
                            value="{{ request('search') }}">
                      <button class="btn btn-outline-primary btn-sm" type="submit">
                        <i class="fas fa-search"></i>
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-body">
            <div class="table-responsive">
              
              <table class="table align-items-center mb-0">
                <thead>
                  <tr>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nomor Form</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Tanggal</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Jam</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Location</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Warehouse</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nama Part</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nomor Part</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Satuan</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Quantity Good</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Quantity Reject</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Quantity Repair</th>
                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Gambar</th>
                  </tr>
                </thead>
                <tbody id="stockOpnameTableBody">
                  @foreach($stockOpnameResults as $result)
                  <tr>
                    <td>{{ $result->nomor_form }}</td>
                    <td>{{ $result->tanggal }}</td>
                    <td>{{ $result->jam }}</td>
                    <td>{{ $result->location }}</td>
                    <td>{{ $result->warehouse }}</td>
                    <td>{{ $result->nama_part }}</td>
                    <td>{{ $result->nomor_part }}</td>
                    <td>{{ $result->satuan }}</td>
                    <td>{{ $result->quantity_good }}</td>
                    <td>{{ $result->quantity_reject }}</td>
                    <td>{{ $result->quantity_repair }}</td>
                    <td>
                      @if($result->image_path)
                          @if(Str::endsWith($result->image_path, '.pdf'))
                              <a class="badge text-bg-danger" 
                                 onclick="showImageModal('{{ url('/stock-opname/image/'.$result->image_path) }}')">
                                  <i class="fas fa-file-pdf fa-2x"></i>
                                  <span class="ms-1">Lihat PDF</span>
                              </a>
                          @else
                              <img src="{{ url('/stock-opname/image/'.$result->image_path) }}" 
                                   alt="Preview" 
                                   style="max-width: 100px; cursor: pointer;"
                                   class="img-thumbnail"
                                   onclick="showImageModal('{{ url('/stock-opname/image/'.$result->image_path) }}')">
                          @endif
                      @else
                          <span>-</span>
                      @endif
                  </td>
                  </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
            <nav aria-label="..." id="paginationLinks" class="mt-3">
              <ul class="pagination pagination-sm">
                  {{-- Previous Page Link --}}
                  @if ($stockOpnameResults->onFirstPage())
                      <li class="page-item disabled"><span class="page-link">«</span></li>
                  @else
                      <li class="page-item"><a class="page-link" href="{{ $stockOpnameResults->previousPageUrl() }}">«</a></li>
                  @endif

                  {{-- Pagination Elements --}}
                  @for ($i = 1; $i <= $stockOpnameResults->lastPage(); $i++)
                      <li class="page-item {{ $i == $stockOpnameResults->currentPage() ? 'active' : '' }}">
                          <a class="page-link" href="{{ $stockOpnameResults->url($i) }}">{{ $i }}</a>
                      </li>
                  @endfor

                  {{-- Next Page Link --}}
                  @if ($stockOpnameResults->hasMorePages())
                      <li class="page-item"><a class="page-link" href="{{ $stockOpnameResults->nextPageUrl() }}">»</a></li>
                  @else
                      <li class="page-item disabled"><span class="page-link">»</span></li>
                  @endif
              </ul>
            </nav>
          </div>
        </div>
      </div>

      <footer class="footer pt-3">
        @include('components.footer')
      </footer>
    </div>
  </main>

  <!-- Image Preview Modal -->
  <div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-labelledby="imagePreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imagePreviewModalLabel">Preview Dokumen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="imageContainer">
                    <img id="modalImage" src="" class="img-fluid" style="max-height: 80vh;">
                </div>
                <div id="pdfContainer" class="d-none">
                    <iframe id="pdfViewer" style="width: 100%; height: 80vh; border: none;"></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
  </div>

  <!-- Core JS Files -->
  @include('components.scripts')
  </body>
</html>