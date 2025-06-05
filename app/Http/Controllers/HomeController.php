<?php

namespace App\Http\Controllers;

use App\Models\StockOpnameResult;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function home(Request $request)
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

        $stockOpnameResults = $query->paginate(5)->withQueryString();

        // Hitung status untuk setiap hasil
        $stockOpnameResults->getCollection()->transform(function ($result) {
            $result->status = $this->hitungStatus($result);
            return $result;
        });

        // Hitung jumlah dokumen
        $disk = Storage::disk('stock_opname');
        $totalDokumen = count($disk->allFiles('to_process')) +
                        count($disk->allFiles('finished')) +
                        count($disk->allFiles('error_400')) +
                        count($disk->allFiles('error_401')) +
                        count($disk->allFiles('error_500')) +
                        count($disk->allFiles('rejected'));

        $dokumenTerekstrak = count($disk->allFiles('finished'));
        $gagalDiproses = count($disk->allFiles('error_400')) +
                         count($disk->allFiles('error_401')) +
                         count($disk->allFiles('error_500'));
        $rejectedFiles = count($disk->allFiles('rejected'));
        $berhasilDisimpan = StockOpnameResult::count();

        return view('home', compact('stockOpnameResults', 'totalDokumen', 'dokumenTerekstrak', 'gagalDiproses', 'berhasilDisimpan', 'rejectedFiles'));
    }

    /**
     * Menghitung status berdasarkan perbandingan kuantitas
     */
    private function hitungStatus($result)
{
    // Inisialisasi variabel untuk mengecek kecocokan
    $goodMatch = true;
    $rejectMatch = true;
    $repairMatch = true;

    // Periksa quantity_good_raw dan quantity_good
    if (
        (is_null($result->quantity_good_raw) || $result->quantity_good_raw === 'N/A') &&
        (is_null($result->quantity_good) || $result->quantity_good === 'N/A')
    ) {
        // Kedua nilai kosong atau 'N/A', anggap cocok
        $goodMatch = true;
    } elseif (
        (is_null($result->quantity_good_raw) || $result->quantity_good_raw === 'N/A') ||
        (is_null($result->quantity_good) || $result->quantity_good === 'N/A')
    ) {
        // Salah satu nilai kosong atau 'N/A' tetapi yang lain tidak, anggap tidak valid
        return 'Tidak Valid';
    } else {
        // Bandingkan nilai jika keduanya bukan 'N/A' atau NULL
        $goodMatch = $result->quantity_good_raw == $result->quantity_good;
    }

    // Periksa quantity_reject_raw dan quantity_reject
    if (
        (is_null($result->quantity_reject_raw) || $result->quantity_reject_raw === 'N/A') &&
        (is_null($result->quantity_reject) || $result->quantity_reject === 'N/A')
    ) {
        // Kedua nilai kosong atau 'N/A', anggap cocok
        $rejectMatch = true;
    } elseif (
        (is_null($result->quantity_reject_raw) || $result->quantity_reject_raw === 'N/A') ||
        (is_null($result->quantity_reject) || $result->quantity_reject === 'N/A')
    ) {
        // Salah satu nilai kosong atau 'N/A' tetapi yang lain tidak, anggap tidak valid
        return 'Tidak Valid';
    } else {
        // Bandingkan nilai jika keduanya bukan 'N/A' atau NULL
        $rejectMatch = $result->quantity_reject_raw == $result->quantity_reject;
    }

    // Periksa quantity_repair_raw dan quantity_repair
    if (
        (is_null($result->quantity_repair_raw) || $result->quantity_repair_raw === 'N/A') &&
        (is_null($result->quantity_repair) || $result->quantity_repair === 'N/A')
    ) {
        // Kedua nilai kosong atau 'N/A', anggap cocok
        $repairMatch = true;
    } elseif (
        (is_null($result->quantity_repair_raw) || $result->quantity_repair_raw === 'N/A') ||
        (is_null($result->quantity_repair) || $result->quantity_repair === 'N/A')
    ) {
        // Salah satu nilai kosong atau 'N/A' tetapi yang lain tidak, anggap tidak valid
        return 'Tidak Valid';
    } else {
        // Bandingkan nilai jika keduanya bukan 'N/A' atau NULL
        $repairMatch = $result->quantity_repair_raw == $result->quantity_repair;
    }

    // Tentukan status akhir
    return ($goodMatch && $rejectMatch && $repairMatch) ? 'Cocok' : 'Tidak Cocok';
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

        // Hitung status untuk setiap hasil
        $stockOpnameResults->getCollection()->transform(function ($result) {
            $result->status = $this->hitungStatus($result);
            return $result;
        });

        if ($request->ajax()) {
            return response()->json($stockOpnameResults);
        }

        return view('home', compact('stockOpnameResults'));
    }

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        
        $query = StockOpnameResult::where(function ($query) use ($search) {
            if (!empty($search)) {
                $query->where('nomor_form', 'like', "%$search%")
                      ->orWhere('nama_part', 'like', "%$search%")
                      ->orWhere('nomor_part', 'like', "%$search%");
            }
        })
        ->orderBy('created_at', 'desc');

        $stockOpnameResults = $query->paginate(10);

        // Hitung status untuk setiap hasil
        $stockOpnameResults->getCollection()->transform(function ($result) {
            $result->status = $this->hitungStatus($result);
            return $result;
        });

        return view('dashboard', compact('stockOpnameResults'));
    }
}