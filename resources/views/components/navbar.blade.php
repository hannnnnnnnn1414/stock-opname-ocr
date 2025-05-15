<nav class="navbar navbar-main navbar-expand-lg px-0 mx-4 shadow-none border-radius-xl mb-4" id="navbarBlur" navbar-scroll="true">
  <div class="container-fluid py-1 px-3">
    <nav aria-label="breadcrumb">
      {{-- <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Dashboard</li>
      </ol> --}}
      <div class="row">
        <div class="col-2">
      <img class="me-5" src="{{ url('https://upload.wikimedia.org/wikipedia/commons/thumb/7/7b/KYB_Corporation_company_logo.svg/135px-KYB_Corporation_company_logo.svg.png') }}" alt="">
        </div>
        <div class="col-10 d-flex justify-content-center align-items-center">
          <h6 class="font-weight-bolder mb-0 fs-4">DASHBOARD MONITORING STOCK OPNAME</h6>
        </div>
      </div>
    </nav>
    <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
      <div class="ms-md-auto pe-md-3 d-flex align-items-center">
        <!-- Komponen Jam -->
        <div class="badge bg-gradient-primary ms-3" style="font-size: 1.2rem; padding: 0.5rem 1rem;">
          <i class="fas fa-clock me-1" style="font-size: 1.5rem;"></i>
          <span id="liveClock" style="font-size: 1.2rem;">00:00:00 WIB</span>
        </div>
      </div>
    </div>
  </div>
</nav>