<?php

namespace App\Http\Controllers;

use App\Models\StockOpnameResult; // Import the model
use Illuminate\Support\Facades\Storage; // Import Storage facade
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function home(Request $request) // Tambahkan Request di parameter
    {
        $search = $request->input('search');

        $query = StockOpnameResult::query();
        // Fetch all records from the StockOpnameResult model
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('nomor_form', 'like', "%{$search}%")
                  ->orWhere('nama_part', 'like', "%{$search}%")
                  ->orWhere('nomor_part', 'like', "%{$search}%");
            });
        }
        
        $stockOpnameResults = $query->paginate(5)->withQueryString(); 

        // Hitung total dokumen
        $disk = Storage::disk('stock_opname');
        $totalDokumen = count($disk->allFiles('to_process')) +
                        count($disk->allFiles('finished')) +
                        count($disk->allFiles('error_400')) +
                        count($disk->allFiles('error_401')) +
                        count($disk->allFiles('error_500')) +
                        count($disk->allFiles('rejected')); // Include rejected files

        // Hitung dokumen yang sudah diekstrak
        $dokumenTerekstrak = count($disk->allFiles('finished'));

        // Hitung dokumen yang gagal diproses
        $gagalDiproses = count($disk->allFiles('error_400')) +
                        count($disk->allFiles('error_401')) +
                        count($disk->allFiles('error_500'));

        // Hitung dokumen yang ditolak
        $rejectedFiles = count($disk->allFiles('rejected'));

        // Hitung yang berhasil disimpan di database
        $berhasilDisimpan = StockOpnameResult::count();

        // Pass the data to the view
        return view('home', compact('stockOpnameResults', 'totalDokumen', 'dokumenTerekstrak', 'gagalDiproses', 'berhasilDisimpan', 'rejectedFiles'));
}

        public function fetchStockOpnameResults(Request $request)
        {
            $search = $request->input('search');
            
            $query = StockOpnameResult::query();
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('nomor_form', 'like', "%{$search}%")
                    ->orWhere('nama_part', 'like', "%{$search}%")
                    ->orWhere('nomor_part', 'like', "%{$search}%");
                });
            }
            
            $stockOpnameResults = $query->paginate(5);

            if ($request->ajax()) {
                return response()->json($stockOpnameResults);
            }

            return view('home', compact('stockOpnameResults'));
        }

        public function index(Request $request)
{
    $search = $request->input('search', '');
    
    $stockOpnameResults = StockOpnameResult::where(function ($query) use ($search) {
        if (!empty($search)) {
            $query->where('nomor_form', 'like', "%$search%")
                 ->orWhere('nama_part', 'like', "%$search%")
                 ->orWhere('nomor_part', 'like', "%$search%");
        }
    })
    ->orderBy('created_at', 'desc')
    ->paginate(10);

    return view('dashboard', compact('stockOpnameResults'));
}
}