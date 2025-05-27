<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use setasign\Fpdi\Fpdi;
use App\Models\StockOpnameResult;

class ProcessStockOpname extends Command
{
    protected $signature = 'stock-opname:process';
    protected $description = 'Process stock opname forms from directory';

    protected $sourceDir = '';
    protected $processedDir = 'finished';
    protected $scannedDir = 'scanned';
    protected $toProcessDir = 'to_process';
    protected $rejectedDir = 'rejected';
    protected $errorDir = 'error';

    public function handle()
    {
        $lock = Cache::lock('stock-opname:process', 300);

        if (!$lock->get()) {
            $this->info('Another instance is running, exiting...');
            \Illuminate\Support\Facades\Log::info('Process skipped due to existing lock at ' . now()->toDateTimeString());
            return;
        }

        try {
            $this->info('Process started at ' . now()->toDateTimeString() . ' (PID: ' . getmypid() . ')');
            $disk = Storage::disk('stock_opname');

            $disk->makeDirectory($this->sourceDir);
            $disk->makeDirectory($this->processedDir);
            $disk->makeDirectory($this->scannedDir);
            $disk->makeDirectory($this->toProcessDir);
            $disk->makeDirectory($this->rejectedDir);
            $disk->makeDirectory($this->errorDir);
            $disk->makeDirectory('error_400');
            $disk->makeDirectory('error_401');
            $disk->makeDirectory('error_500');
            $disk->makeDirectory('error_timeout');

            $files = $disk->files($this->sourceDir);

            $this->info('Starting stock opname processing...');
            $this->info('Verihubs Config: ' . json_encode(config('services.verihubs')));
            $this->info('Storage Root: ' . $disk->path(''));
            $this->info('Storage Path: ' . $disk->path($this->sourceDir));
            $this->info('Files to process: ' . json_encode($files));

            foreach ($files as $file) {
                try {
                    $this->processFile($file);
                } catch (\Exception $e) {
                    $this->error("Error processing file {$file}: " . $e->getMessage());
                    \Illuminate\Support\Facades\Log::error("General Error for file {$file}: {$e->getMessage()}");
                    if (strpos($e->getMessage(), 'cURL error 28') !== false) {
                        $this->moveErrorFile($file, 'error_timeout');
                    } else {
                        $this->moveErrorFile($file, $this->errorDir);
                    }
                }
            }

            $this->info('Processing completed at ' . now()->toDateTimeString());
        } finally {
            $lock->release();
        }
    }

    private function processFile(string $filePath)
    {
        $disk = Storage::disk('stock_opname');
        $lock = Cache::lock('stock-opname:file:' . md5($filePath), 300);

        if (!$lock->get()) {
            $this->info("File {$filePath} is being processed by another instance, skipping...");
            \Illuminate\Support\Facades\Log::info("File {$filePath} skipped due to lock at " . now()->toDateTimeString());
            return;
        }

        try {
            $this->line("\n<fg=blue>🔍 Processing file:</> {$filePath}");
            $fullPath = $disk->path($filePath);
            $filename = pathinfo($filePath, PATHINFO_BASENAME);
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($extension === 'pdf') {
                $this->processPdfFile($filePath, $fullPath, $filename);
            } else {
                $scannedPath = $this->moveToScanned($filePath);
                $toProcessPath = $this->moveToProcess($filePath);
                $this->processSingleFile($toProcessPath, $disk->path($toProcessPath));
            }
        } finally {
            $lock->release();
        }
    }

    private function processPdfFile($filePath, $fullPath, $filename)
    {
        $disk = Storage::disk('stock_opname');
        $tempDir = 'temp_pages';
        $disk->makeDirectory($tempDir);

        try {
            $pdf = new Fpdi();
            $pageCount = $pdf->setSourceFile($fullPath);

            $this->info("Split PDF {$filename} dengan {$pageCount} halaman...");

            // Split PDF dan simpan halaman ke to_process
            for ($page = 1; $page <= $pageCount; $page++) {
                $newPdf = new Fpdi();
                $newPdf->AddPage();
                $newPdf->setSourceFile($fullPath);
                $tplIdx = $newPdf->importPage($page);
                $newPdf->useTemplate($tplIdx);

                $pageFilename = pathinfo($filename, PATHINFO_FILENAME) . "_hal{$page}.pdf";
                $toProcessPath = "{$this->toProcessDir}/{$pageFilename}";
                $newPdf->Output($disk->path($toProcessPath), 'F');

                $this->info("Halaman {$page} disimpan ke: {$toProcessPath}");

                // Proses file di to_process
                $this->processSingleFile($toProcessPath, $disk->path($toProcessPath));
            }

            // Pindahkan PDF asli ke scanned
            $this->moveToScanned($filePath);
            $disk->deleteDirectory($tempDir);
        } catch (\Exception $e) {
            $this->error("Gagal split PDF {$filename}: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error("PDF Split Error for file {$filename}: {$e->getMessage()}");
            // Cek apakah error adalah cURL timeout
            if (strpos($e->getMessage(), 'cURL error 28') !== false) {
                $this->moveErrorFile($filePath, 'error_timeout');
            } else {
                $this->moveErrorFile($filePath, $this->errorDir);
            }
        }
    }

    private function processSingleFile($filePath, $fullPath)
{
    $disk = Storage::disk('stock_opname');

    try {
        // Kirim ke Verihubs OCR API
        $response = Http::withHeaders([
            'App-ID' => config('services.verihubs.app_id'),
            'API-Key' => config('services.verihubs.api_key'),
        ])
            ->timeout(120)
            ->attach('image', file_get_contents($fullPath), basename($fullPath))
            ->post('https://api.verihubs.com/v2/ocr/stock_opname');

        if ($response->failed()) {
            $this->handleApiError($response, $filePath);
            return;
        }

        $data = $response->json();
        $this->info('API Response: ' . json_encode($data));

        if (isset($data['data']['result_data']['forms'])) {
            foreach ($data['data']['result_data']['forms'] as $form) {
                $this->displayFormattedResult($form);
                // Simpan ke database dengan path sementara
                $this->saveToDatabase($data['data'], $form, $filePath);
                // Pindahkan ke finished dan perbarui image_path
                $newPath = $this->moveProcessedFile($filePath, $this->processedDir);
                // Perbarui image_path di database
                StockOpnameResult::where('image_path', $filePath)
                    ->update(['image_path' => $newPath]);
            }
        } else {
            $this->error("Result data not found in response for {$filePath}.");
            \Illuminate\Support\Facades\Log::error("No result data for file {$filePath}");
            // Pindahkan ke rejected
            $newPath = $this->moveProcessedFile($filePath, $this->rejectedDir);
        }
    } catch (\Exception $e) {
        $this->error("Error processing file {$filePath}: " . $e->getMessage());
        \Illuminate\Support\Facades\Log::error("Error processing file {$filePath}: {$e->getMessage()}");
        // Cek apakah error adalah cURL timeout
        if (strpos($e->getMessage(), 'cURL error 28') !== false) {
            $this->moveErrorFile($filePath, 'error_timeout');
        } else {
            $this->moveErrorFile($filePath, $this->errorDir);
        }
    }
}

    private function moveToScanned($filePath)
    {
        $disk = Storage::disk('stock_opname');
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $newPath = $this->scannedDir . '/' . $filename;

        $disk->move($filePath, $newPath);
        $this->line("<fg=magenta>♻️ File asli dipindah ke:</> {$newPath}");
        return $newPath;
    }

    private function moveToProcess($filePath)
    {
        $disk = Storage::disk('stock_opname');
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $newPath = $this->toProcessDir . '/' . $filename;

        $disk->copy($filePath, $newPath);
        $this->line("<fg=magenta>♻️ File disalin ke:</> {$newPath}");
        return $newPath;
    }

    private function moveProcessedFile($filePath, $destinationDir)
    {
        $disk = Storage::disk('stock_opname');
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $newPath = $destinationDir . '/' . $filename;

        $disk->move($filePath, $newPath);
        $this->line("<fg=magenta>♻️ File dipindah ke:</> {$newPath}");
        return $newPath;
    }

    private function moveErrorFile($filePath, $errorDir)
    {
        $this->moveProcessedFile($filePath, $errorDir);
    }

    private function handleApiError($response, $filePath)
    {
        $status = $response->status();
        $error = $response->json();

        $this->error("API Error [{$status}]: " . ($error['message'] ?? 'Unknown error'));
        \Illuminate\Support\Facades\Log::error("API Error [{$status}] for file {$filePath}: " . ($error['message'] ?? 'Unknown error'));

        // Tentukan folder error berdasarkan status kode
        $errorDir = match ($status) {
            400 => 'error_400', // Bad Request (misalnya, format file salah)
            401 => 'error_401', // Unauthorized (misalnya, App-ID atau API-Key salah)
            500 => 'error_500', // Internal Server Error
            default => $this->errorDir, // Default ke folder error umum
        };

        $this->moveErrorFile($filePath, $errorDir);
    }

    private function displayFormattedResult($data)
    {
        $this->line("<fg=green>✅ Successfully processed:</>");

        $this->line("<fg=cyan>┌──────────────────────────────</>");
        $this->line("<fg=cyan>│</> <fg=yellow>📅 Tanggal:</>      {$data['tanggal']}");
        $this->line("<fg=cyan>│</> <fg=yellow>⏰ Jam:</>          {$data['jam']}");
        $this->line("<fg=cyan>│</> <fg=yellow>📍 Lokasi:</>       {$data['location']}");
        $this->line("<fg=cyan>│</> <fg=yellow>📦 Gudang:</>       {$data['warehouse']}");
        $this->line("<fg=cyan>├──────────────────────────────</>");

        $this->line("<fg=cyan>│</> <fg=yellow>📝 Nomor Form:</>   {$data['nomor_form']}");
        $this->line("<fg=cyan>│</> <fg=yellow>🔧 Nama Part:</>    {$data['nama_part']}");
        $this->line("<fg=cyan>│</> <fg=yellow>🔢 Nomor Part:</>   {$data['nomor_part']}");
        $this->line("<fg=cyan>│</> <fg=yellow>📏 Satuan:</>       {$data['satuan']}");
        $this->line("<fg=cyan>├──────────────────────────────</>");

        $this->line("<fg=cyan>│</> <fg=yellow>📊 Kuantitas:</>");
        $this->line("<fg=cyan>│</>   - Baik:     {$data['quantity']['good']}");
        $this->line("<fg=cyan>│</>   - Reject:   {$data['quantity']['reject']}");
        $this->line("<fg=cyan>│</>   - Repair:   {$data['quantity']['repair']}");
        $this->line("<fg=cyan>└──────────────────────────────</>\n");
    }

    private function saveToDatabase($data, $form, $imagePath)
{
    StockOpnameResult::create([
        'reference_id' => $data['reference_id'],
        'tanggal' => \Carbon\Carbon::createFromFormat('d-m-Y', $form['tanggal']),
        'jam' => $form['jam'],
        'location' => $form['location'],
        'warehouse' => $form['warehouse'],
        'nomor_form' => $form['nomor_form'],
        'nama_part' => $form['nama_part'],
        'nomor_part' => $form['nomor_part'],
        'satuan' => $form['satuan'],
        'quantity_good' => (int) $form['quantity']['good'],
        'quantity_reject' => in_array($form['quantity']['reject'], ['N/A', '-']) ? null : (int)$form['quantity']['reject'],
        'quantity_repair' => in_array($form['quantity']['repair'], ['N/A', '-']) ? null : (int)$form['quantity']['repair'],
        'image_path' => $imagePath,
    ]);
}
}