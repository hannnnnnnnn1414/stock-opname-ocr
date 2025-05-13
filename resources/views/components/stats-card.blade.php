{{-- resources/views/components/stats-card.blade.php --}}
<div class="card {{ $class ?? '' }}">
    <div class="card-body p-3">
      <div class="row">
        <div class="col-8">
          <div class="numbers">
            <p class="text-sm mb-0 text-capitalize font-weight-bold">{{ $title }}</p>
            <h5 class="font-weight-bolder mb-0">
              {{ $value }}
              @if(isset($additional)) {{ $additional }} @endif
            </h5>
          </div>
        </div>
        <div class="col-4 text-end">
          <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
            <i class="{{ $icon }} text-lg opacity-10" aria-hidden="true"></i>
          </div>
        </div>
      </div>
    </div>
  </div>