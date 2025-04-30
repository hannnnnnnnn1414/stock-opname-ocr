<?php

namespace App\Http\Controllers;

use App\Models\StockOpnameResult; // Import the model
use Illuminate\Support\Facades\Storage; // Import Storage facade

class HomeController extends Controller
{
    public function home()
    {
        // Fetch all records from the StockOpnameResult model
        $stockOpnameResults = StockOpnameResult::all(); 

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
}