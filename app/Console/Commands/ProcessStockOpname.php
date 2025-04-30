<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\StockOpnameResult;

class ProcessStockOpname extends Command
{
    protected $signature = 'stock-opname:process';
    protected $description = 'Process stock opname forms from directory';

    // Folder configuration
    protected $sourceDir = 'to_process';
    protected $processedDir = 'finished';

    public function handle()
    {
        $disk = Storage::disk('stock_opname');
        
        // Buat folder jika belum ada
        $disk->makeDirectory($this->sourceDir);
        $disk->makeDirectory($this->processedDir);
        $disk->makeDirectory('error_400');
        $disk->makeDirectory('error_401');

        // Proses file
        $files = $disk->files($this->sourceDir);

        $this->info('Starting stock opname processing...');

        // Debug 1: Tampilkan konfigurasi Verihubs
        $this->info('Verihubs Config: ' . json_encode(config('services.verihubs')));
        
        // Debug 2: Tampilkan path folder
        $this->info('Storage Path: ' . Storage::path($this->sourceDir));
        
        // Debug 3: List file
        $files = Storage::files($this->sourceDir);
        $this->info('Files to process: ' . json_encode($files));

        // Ensure directories exist
        Storage::makeDirectory($this->sourceDir);
        Storage::makeDirectory($this->processedDir);

        // Get list of files to process
        $files = Storage::files($this->sourceDir);

        foreach ($files as $file) {
            try {
                $this->processFile($file);
            } catch (\Exception $e) {
                $this->error("Error processing file {$file}: " . $e->getMessage());
                continue;
            }
        }

        $this->info('Processing completed.');
    }

    private function processFile($filePath)
{   
    $disk = Storage::disk('stock_opname');
    $fullPath = $disk->path($filePath);

    $this->line("\n<fg=blue>🔍 Processing file:</> {$filePath}");

    // Check if the current time is past a certain hour (e.g., 17:00)
    $currentHour = now()->format('H:i');
    if ($currentHour >= '14:32') { // Change 17 to your desired hour
        $this->moveErrorFile($filePath, 'rejected');
        return;
    }

    // Send to Verihubs OCR API
    $response = Http::withHeaders([
        'App-ID' => config('services.verihubs.app_id'),
        'API-Key' => config('services.verihubs.api_key'),
    ])
        ->timeout(120)
        ->attach('image', file_get_contents($fullPath), basename($fullPath))
        ->post('https://api.verihubs.com/v2/ocr/stock_opname');

    // Handle API response
    if ($response->failed()) {
        $this->handleApiError($response, $filePath);
        return;
    }

    // Process successful response
    $data = $response->json();
    $this->info('API Response: ' . json_encode($data));

    if (isset($data['data']['result_data'])) {
        $this->displayFormattedResult($data['data']['result_data']);
        $newImagePath = $this->moveProcessedFileAndGetPath($filePath);
        $this->saveToDatabase($data['data'], $newImagePath);
    } else {
        $this->error("Result data not found in response.");
    }
    
    // Move processed file
}

    private function moveProcessedFileAndGetPath($originalPath)
    {
        $filename = pathinfo($originalPath, PATHINFO_BASENAME);
        $newPath = $this->processedDir . '/' . $filename;
        
        Storage::disk('stock_opname')->move($originalPath, $newPath);
        
        return $newPath;
    }

    private function handleApiError($response, $filePath)
    {
        $status = $response->status();
        $error = $response->json();

        $this->error("API Error [{$status}]: " . ($error['message'] ?? 'Unknown error'));
        
        // Pindahkan file ke folder error sesuai kode status
        if (in_array($status, [400, 401, 500])) {
            $this->moveErrorFile($filePath, "error_{$status}");
        }
    }

    private function moveProcessedFile($originalPath)
    {
        $filename = pathinfo($originalPath, PATHINFO_BASENAME);
        $newPath = $this->processedDir . '/' . $filename;
        
        Storage::move($originalPath, $newPath);
        $this->line("<fg=magenta>♻️ File dipindahkan ke:</> {$newPath}");
    }

    private function moveErrorFile($originalPath, $errorType)
    {
        Storage::makeDirectory($errorType);
        $filename = pathinfo($originalPath, PATHINFO_BASENAME);
        $newPath = $errorType . '/' . $filename;
        
        Storage::move($originalPath, $newPath);
        $this->info("Moved error file to: {$newPath}");
    }

    private function displayFormattedResult($data)
    {
        $this->line("<fg=green>✅ Successfully processed:</>");

        // Format utama
        $this->line("<fg=cyan>┌──────────────────────────────</>");
        $this->line("<fg=cyan>│</> <fg=yellow>📅 Tanggal:</>      {$data['tanggal']}");
        $this->line("<fg=cyan>│</> <fg=yellow>⏰ Jam:</>          {$data['jam']}");
        $this->line("<fg=cyan>│</> <fg=yellow>📍 Lokasi:</>       {$data['location']}");
        $this->line("<fg=cyan>│</> <fg=yellow>📦 Gudang:</>       {$data['warehouse']}");
        $this->line("<fg=cyan>├──────────────────────────────</>");

        // Detail part
        $this->line("<fg=cyan>│</> <fg=yellow>📝 Nomor Form:</>   {$data['nomor_form']}");
        $this->line("<fg=cyan>│</> <fg=yellow>🔧 Nama Part:</>    {$data['nama_part']}");
        $this->line("<fg=cyan>│</> <fg=yellow>🔢 Nomor Part:</>   {$data['nomor_part']}");
        $this->line("<fg=cyan>│</> <fg=yellow>📏 Satuan:</>       {$data['satuan']}");
        $this->line("<fg=cyan>├──────────────────────────────</>");

        // Quantity
        $this->line("<fg=cyan>│</> <fg=yellow>📊 Kuantitas:</>");
        $this->line("<fg=cyan>│</>   - Baik:     {$data['quantity']['good']}");
        $this->line("<fg=cyan>│</>   - Reject:   {$data['quantity']['reject']}");
        $this->line("<fg=cyan>│</>   - Repair:   {$data['quantity']['repair']}");
        $this->line("<fg=cyan>└──────────────────────────────</>\n");
    }

    private function saveToDatabase($data, $imagePath)
    {
        StockOpnameResult::create([
            'reference_id' => $data['reference_id'], // Use reference_id from the main data
            'tanggal' => \Carbon\Carbon::createFromFormat('d-m-Y', $data['result_data']['tanggal']), // Access tanggal from result_data
            'jam' => $data['result_data']['jam'], // Access jam from result_data
            'location' => $data['result_data']['location'], // Access location from result_data
            'warehouse' => $data['result_data']['warehouse'], // Access warehouse from result_data
            'nomor_form' => $data['result_data']['nomor_form'], // Access nomor_form from result_data
            'nama_part' => $data['result_data']['nama_part'], // Access nama_part from result_data
            'nomor_part' => $data['result_data']['nomor_part'], // Access nomor_part from result_data
            'satuan' => $data['result_data']['satuan'], // Access satuan from result_data
            'quantity_good' => (int)$data['result_data']['quantity']['good'], // Access quantity good from result_data
            'quantity_reject' => $data['result_data']['quantity']['reject'] === 'N/A' ? null : (int)$data['result_data']['quantity']['reject'], // Access quantity reject from result_data
            'quantity_repair' => $data['result_data']['quantity']['repair'] === 'N/A' ? null : (int)$data['result_data']['quantity']['repair'], // Access quantity repair from result_data
            'image_path' => $imagePath,
        ]);
    }

}