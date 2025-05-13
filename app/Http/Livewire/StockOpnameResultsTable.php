<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\StockOpnameResult;

class StockOpnameResultsTable extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.stock-opname-results-table', [
            'results' => StockOpnameResult::paginate(5)
        ]);
    }
}