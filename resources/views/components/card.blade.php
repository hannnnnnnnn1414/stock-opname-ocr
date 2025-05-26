{{ $slot }}

<div class="card shadow-sm rounded">
    <div class="card-header py-3 bg-danger border-bottom">
        <div class="d-flex flex-column align-items-start">
            <div class="d-flex align-items-center">
                <i class="fa-solid fa-file-invoice fs-4 me-2 text-white me-3"></i>
                <h5 class="mb-0 fw-bold text-white">{{ $title }}
                </h5>
            </div>
        </div>
    </div>
    <div class="card-body p-4">
        {{ $body }}
    </div>
</div>