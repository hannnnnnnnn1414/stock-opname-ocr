<!-- Core Scripts -->
<script src="{{ asset('js/core/popper.min.js') }}"></script>
<script src="{{ asset('js/core/bootstrap.min.js') }}"></script>
<script src="{{ asset('js/plugins/perfect-scrollbar.min.js') }}"></script>
<script src="{{ asset('js/plugins/smooth-scrollbar.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom Scripts -->
<script>
  // Handle click event on Gagal Proses card
  document.getElementById('gagalProsesCard')?.addEventListener('click', () => {
    new bootstrap.Modal(document.getElementById('gagalProsesModal')).show();
  });

  // Image Preview Handler
  window.showImageModal = function(fileUrl) {
    const isPDF = fileUrl.toLowerCase().endsWith('.pdf');
    const modal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    
    document.getElementById('pdfContainer').classList.toggle('d-none', !isPDF);
    document.getElementById('imageContainer').classList.toggle('d-none', isPDF);
    
    if(isPDF) {
      document.getElementById('pdfViewer').src = `${fileUrl}#view=FitH`;
    } else {
      document.getElementById('modalImage').src = fileUrl;
    }
    
    modal.show();
  }
</script>

<!-- Sidebar Scrollbar -->
<script>
  var win = navigator.platform.indexOf('Win') > -1;
  if (win && document.querySelector('#sidenav-scrollbar')) {
    var options = {
      damping: '0.5'
    }
    Scrollbar.init(document.querySelector('#sidenav-scrollbar'), options);
  }
</script>

<!-- GitHub Buttons -->
<script async defer src="https://buttons.github.io/buttons.js"></script>

<!-- Control Center for Soft Dashboard -->
<script src="{{asset('assets/js/soft-ui-dashboard.min.js?v=1.0.3')}}"></script>

<!-- AJAX Pagination -->
<script>
  document.getElementById('paginationLinks')?.addEventListener('click', async (e) => {
    e.preventDefault();
    const target = e.target.closest('a.page-link');
    if (!target) return;

    try {
      const response = await fetch(target.href, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');

      document.getElementById('stockOpnameTableBody').innerHTML = 
        doc.getElementById('stockOpnameTableBody').innerHTML;
      
      document.getElementById('paginationLinks').innerHTML = 
        doc.getElementById('paginationLinks').innerHTML;
    } catch (error) {
      console.error('Error:', error);
    }
  });

  // Handle search form submission
    document.getElementById('searchForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const searchTerm = this.querySelector('input[name="search"]').value;
        const url = `${this.action}?search=${encodeURIComponent(searchTerm)}`;
        
        try {
            const response = await fetch(url, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const html = await response.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');

            // Update table body and pagination
            document.getElementById('stockOpnameTableBody').innerHTML = 
                doc.getElementById('stockOpnameTableBody').innerHTML;
            document.getElementById('paginationLinks').innerHTML = 
                doc.getElementById('paginationLinks').innerHTML;
        } catch (error) {
            console.error('Error:', error);
        }
    });
</script>
