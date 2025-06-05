<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use setasign\Fpdi\Fpdi;
use App\Models\StockOpnameResult;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Request;

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
                    $startTime = microtime(true);
                    $this->processFile($file);
                    $duration = microtime(true) - $startTime;
                    $this->info("Total processing time for file {$file}: " . number_format($duration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Total processing time for file {$file}: " . number_format($duration, 2) . " seconds");
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
                $startTime = microtime(true);
                $scannedPath = $this->moveToScanned($filePath);
                $duration = microtime(true) - $startTime;
                $this->info("Move to scanned time for {$filePath}: " . number_format($duration, 2) . " seconds");
                \Illuminate\Support\Facades\Log::info("Move to scanned time for {$filePath}: " . number_format($duration, 2) . " seconds");

                $startTime = microtime(true);
                $toProcessPath = $this->moveToProcess($filePath);
                $duration = microtime(true) - $startTime;
                $this->info("Move to process time for {$filePath}: " . number_format($duration, 2) . " seconds");
                \Illuminate\Support\Facades\Log::info("Move to process time for {$filePath}: " . number_format($duration, 2) . " seconds");

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
        $startTime = microtime(true);
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($fullPath);
        $initDuration = microtime(true) - $startTime;
        $this->info("PDF initialization time for {$filename}: " . number_format($initDuration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("PDF initialization time for {$filename}: " . number_format($initDuration, 2) . " seconds");

        $this->info("Split PDF {$filename} dengan {$pageCount} halaman...");
        $toProcessFiles = [];
        $totalSplitTime = 0;

        for ($page = 1; $page <= $pageCount; $page++) {
            $startTime = microtime(true);
            $newPdf = new Fpdi();
            $newPdf->AddPage();
            $newPdf->setSourceFile($fullPath);
            $tplIdx = $newPdf->importPage($page);
            $newPdf->useTemplate($tplIdx);

            $pageFilename = pathinfo($filename, PATHINFO_FILENAME) . "_hal{$page}.pdf";
            $toProcessPath = "{$this->toProcessDir}/{$pageFilename}";
            $newPdf->Output($disk->path($toProcessPath), 'F');
            $toProcessFiles[] = ['path' => $toProcessPath, 'fullPath' => $disk->path($toProcessPath)];
            $splitDuration = microtime(true) - $startTime;
            $totalSplitTime += $splitDuration;

            $this->info("Split page {$page} time for {$filename}: " . number_format($splitDuration, 2) . " seconds");
            \Illuminate\Support\Facades\Log::info("Split page {$page} time for {$filename}: " . number_format($splitDuration, 2) . " seconds");
        }

        // Proses paralel
        $client = new Client(['timeout' => 120, 'connect_timeout' => 10]);
        $requests = function ($files) use ($client) {
            foreach ($files as $file) {
                yield function () use ($client, $file) {
                    return $client->requestAsync('POST', 'https://api.verihubs.com/v2/ocr/stock_opname', [
                        'headers' => [
                            'App-ID' => config('services.verihubs.app_id'),
                            'API-Key' => config('services.verihubs.api_key'),
                        ],
                        'multipart' => [
                            [
                                'name' => 'image',
                                'contents' => fopen($file['fullPath'], 'r'),
                                'filename' => basename($file['fullPath']),
                            ],
                        ],
                    ]);
                };
            }
        };

        $pool = new Pool($client, $requests($toProcessFiles), [
            'concurrency' => 5,
            'fulfilled' => function ($response, $index) use ($toProcessFiles, $disk) {
                $filePath = $toProcessFiles[$index]['path'];
                $data = json_decode($response->getBody(), true);
                $this->info('API Response for ' . $filePath . ': ' . json_encode($data));

                if (isset($data['data']['result_data']['forms'])) {
                    $newPath = null;
                    foreach ($data['data']['result_data']['forms'] as $form) {
                        $this->displayFormattedResult($form);
                        $startTime = microtime(true);
                        $this->saveToDatabase($data['data'], $form, $filePath);
                        $dbDuration = microtime(true) - $startTime;
                        $this->info("Store to database time for {$filePath}: " . number_format($dbDuration, 2) . " seconds");
                        \Illuminate\Support\Facades\Log::info("Store to database time for {$filePath}: " . number_format($dbDuration, 2) . " seconds");
                    }
                    $startTime = microtime(true);
                    $newPath = $this->moveProcessedFile($filePath, $this->processedDir);
                    $moveDuration = microtime(true) - $startTime;
                    $this->info("Move to finished time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Move to finished time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");

                    $startTime = microtime(true);
                    StockOpnameResult::where('image_path', $filePath)
                        ->update(['image_path' => $newPath]);
                    $updateDbDuration = microtime(true) - $startTime;
                    $this->info("Update database image path time for {$filePath}: " . number_format($updateDbDuration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Update database image path time for {$filePath}: " . number_format($updateDbDuration, 2) . " seconds");
                } else {
                    $this->error("Result data not found in response for {$filePath}.");
                    \Illuminate\Support\Facades\Log::error("No result data for file {$filePath}");
                    $startTime = microtime(true);
                    $newPath = $this->moveProcessedFile($filePath, $this->rejectedDir);
                    $moveDuration = microtime(true) - $startTime;
                    $this->info("Move to rejected time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Move to rejected time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                }
            },
            'rejected' => function ($reason, $index) use ($toProcessFiles) {
                $filePath = $toProcessFiles[$index]['path'];
                $this->error("Error processing file {$filePath}: " . $reason->getMessage());
                \Illuminate\Support\Facades\Log::error("Error processing file {$filePath}: {$reason->getMessage()}");
                $errorDir = strpos($reason->getMessage(), 'cURL error 28') !== false ? 'error_timeout' : $this->errorDir;
                $startTime = microtime(true);
                $this->moveErrorFile($filePath, $errorDir);
                $moveDuration = microtime(true) - $startTime;
                $this->info("Move to {$errorDir} time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                \Illuminate\Support\Facades\Log::info("Move to {$errorDir} time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
            },
        ]);

        $pool->promise()->wait();

        $this->info("Total split PDF time for {$filename}: " . number_format($totalSplitTime, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Total split PDF time for {$filename}: " . number_format($totalSplitTime, 2) . " seconds");

        $startTime = microtime(true);
        $scannedPath = $this->moveToScanned($filePath);
        $duration = microtime(true) - $startTime;
        $this->info("Move to scanned time for {$filename}: " . number_format($duration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Move to scanned time for {$filename}: " . number_format($duration, 2) . " seconds");

        $startTime = microtime(true);
        $disk->deleteDirectory($tempDir);
        $duration = microtime(true) - $startTime;
        $this->info("Delete temp directory time for {$filename}: " . number_format($duration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Delete temp directory time for {$filename}: " . number_format($duration, 2) . " seconds");
    } catch (\Exception $e) {
        $this->error("Gagal split PDF {$filename}: " . $e->getMessage());
        \Illuminate\Support\Facades\Log::error("PDF Split Error for file {$filename}: {$e->getMessage()}");
        $errorDir = strpos($e->getMessage(), 'cURL error 28') !== false ? 'error_timeout' : $this->errorDir;
        $this->moveErrorFile($filePath, $errorDir);
    }
}

    private function processSingleFile($filePath, $fullPath)
    {
        $disk = Storage::disk('stock_opname');

        try {
            $startTime = microtime(true);
            $response = Http::withHeaders([
                'App-ID' => config('services.verihubs.app_id'),
                'API-Key' => config('services.verihubs.api_key'),
            ])
                ->timeout(120)
                ->attach('image', file_get_contents($fullPath), basename($fullPath))
                ->post('https://api.verihubs.com/v2/ocr/stock_opname');
            $ocrDuration = microtime(true) - $startTime;
            $this->info("OCR extraction time for {$filePath}: " . number_format($ocrDuration, 2) . " seconds");
            \Illuminate\Support\Facades\Log::info("OCR extraction time for {$filePath}: " . number_format($ocrDuration, 2) . " seconds");

            if ($response->failed()) {
                $this->handleApiError($response, $filePath);
                return;
            }

            $data = $response->json();
            $this->info('API Response: ' . json_encode($data));

            if (isset($data['data']['result_data']['forms'])) {
                foreach ($data['data']['result_data']['forms'] as $form) {
                    $this->displayFormattedResult($form);

                    $startTime = microtime(true);
                    $this->saveToDatabase($data['data'], $form, $filePath);
                    $dbDuration = microtime(true) - $startTime;
                    $this->info("Store to database time for {$filePath}: " . number_format($dbDuration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Store to database time for {$filePath}: " . number_format($dbDuration, 2) . " seconds");

                    $startTime = microtime(true);
                    $newPath = $this->moveProcessedFile($filePath, $this->processedDir);
                    $moveDuration = microtime(true) - $startTime;
                    $this->info("Move to finished time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Move to finished time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");

                    $startTime = microtime(true);
                    StockOpnameResult::where('image_path', $filePath)
                        ->update(['image_path' => $newPath]);
                    $updateDbDuration = microtime(true) - $startTime;
                    $this->info("Update database image path time for {$filePath}: " . number_format($updateDbDuration, 2) . " seconds");
                    \Illuminate\Support\Facades\Log::info("Update database image path time for {$filePath}: " . number_format($updateDbDuration, 2) . " seconds");
                }
            } else {
                $this->error("Result data not found in response for {$filePath}.");
                \Illuminate\Support\Facades\Log::error("No result data for file {$filePath}");

                $startTime = microtime(true);
                $newPath = $this->moveProcessedFile($filePath, $this->rejectedDir);
                $moveDuration = microtime(true) - $startTime;
                $this->info("Move to rejected time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                \Illuminate\Support\Facades\Log::info("Move to rejected time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
            }
        } catch (\Exception $e) {
            $this->error("Error processing file {$filePath}: " . $e->getMessage());
            \Illuminate\Support\Facades\Log::error("Error processing file {$filePath}: {$e->getMessage()}");
            if (strpos($e->getMessage(), 'cURL error 28') !== false) {
                $startTime = microtime(true);
                $this->moveErrorFile($filePath, 'error_timeout');
                $moveDuration = microtime(true) - $startTime;
                $this->info("Move to error_timeout time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                \Illuminate\Support\Facades\Log::info("Move to error_timeout time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
            } else {
                $startTime = microtime(true);
                $this->moveErrorFile($filePath, $this->errorDir);
                $moveDuration = microtime(true) - $startTime;
                $this->info("Move to error time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
                \Illuminate\Support\Facades\Log::info("Move to error time for {$filePath}: " . number_format($moveDuration, 2) . " seconds");
            }
        }
    }

    private function moveToScanned($filePath)
    {
        $disk = Storage::disk('stock_opname');
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $newPath = $this->scannedDir . '/' . $filename;

        $startTime = microtime(true);
        $disk->move($filePath, $newPath);
        $duration = microtime(true) - $startTime;
        $this->line("<fg=magenta>♻️ File asli dipindah ke:</> {$newPath}");
        $this->info("Move to scanned time for {$filePath}: " . number_format($duration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Move to scanned time for {$filePath}: " . number_format($duration, 2) . " seconds");
        return $newPath;
    }

    private function moveToProcess($filePath)
    {
        $disk = Storage::disk('stock_opname');
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $newPath = $this->toProcessDir . '/' . $filename;

        $startTime = microtime(true);
        $disk->copy($filePath, $newPath);
        $duration = microtime(true) - $startTime;
        $this->line("<fg=magenta>♻️ File disalin ke:</> {$newPath}");
        $this->info("Move to process time for {$filePath}: " . number_format($duration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Move to process time for {$filePath}: " . number_format($duration, 2) . " seconds");
        return $newPath;
    }

    private function moveProcessedFile($filePath, $destinationDir)
    {
        $disk = Storage::disk('stock_opname');
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $newPath = $destinationDir . '/' . $filename;

        $startTime = microtime(true);
        $disk->move($filePath, $newPath);
        $duration = microtime(true) - $startTime;
        $this->line("<fg=magenta>♻️ File dipindah ke:</> {$newPath}");
        $this->info("Move to {$destinationDir} time for {$filePath}: " . number_format($duration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Move to {$destinationDir} time for {$filePath}: " . number_format($duration, 2) . " seconds");
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

        $errorDir = match ($status) {
            400 => 'error_400',
            401 => 'error_401',
            500 => 'error_500',
            default => $this->errorDir,
        };

        $startTime = microtime(true);
        $this->moveErrorFile($filePath, $errorDir);
        $duration = microtime(true) - $startTime;
        $this->info("Move to {$errorDir} time for {$filePath}: " . number_format($duration, 2) . " seconds");
        \Illuminate\Support\Facades\Log::info("Move to {$errorDir} time for {$filePath}: " . number_format($duration, 2) . " seconds");
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
    $this->line("<fg=cyan>│</> <fg=yellow>🏷️ Tipe:</>         " . ($data['tipe'] ?? 'N/A'));
    $this->line("<fg=cyan>│</> <fg=yellow>🌍 Zone:</>         " . ($data['zone'] ?? 'N/A'));
    $this->line("<fg=cyan>│</> <fg=yellow>🔨 WIP Code:</>     " . ($data['wip_code'] ?? 'N/A'));
    $this->line("<fg=cyan>├──────────────────────────────</>");
    $this->line("<fg=cyan>│</> <fg=yellow>📊 Kuantitas:</>");
    $this->line("<fg=cyan>│</>   - Baik:     {$data['quantity']['good']}");
    $this->line("<fg=cyan>│</>   - Reject:   {$data['quantity']['reject']}");
    $this->line("<fg=cyan>│</>   - Repair:   {$data['quantity']['repair']}");
    $this->line("<fg=cyan>└──────────────────────────────</>\n");
}

    private function saveToDatabase($data, $form, $imagePath)
{
    $startTime = microtime(true);
    
    // Ambil nilai asli dari respon API
    $quantityGoodRaw = $form['quantity']['good'] ?? null;
    $quantityRejectRaw = $form['quantity']['reject'] ?? null;
    $quantityRepairRaw = $form['quantity']['repair'] ?? null;

    // Proses nilai asli menjadi float (seperti sebelumnya)
    $quantityGood = isset($form['quantity']['good']) ? str_replace(',', '.', $form['quantity']['good']) : null;
    $quantityReject = isset($form['quantity']['reject']) ? str_replace(',', '.', $form['quantity']['reject']) : null;
    $quantityRepair = isset($form['quantity']['repair']) ? str_replace(',', '.', $form['quantity']['repair']) : null;

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
        'tipe' => isset($form['tipe']) ? $form['tipe'] : null,
        'zone' => isset($form['zone']) ? $form['zone'] : null,
        'wip_code' => isset($form['wip_code']) ? $form['wip_code'] : null,
        'quantity_good_raw' => $quantityGoodRaw, // Nilai asli
        'quantity_good' => is_numeric($quantityGood) ? (float) $quantityGood : null,
        'quantity_reject_raw' => $quantityRejectRaw, // Nilai asli
        'quantity_reject' => in_array($form['quantity']['reject'], ['N/A', '-']) ? 'N/A' : ($quantityReject && is_numeric($quantityReject) ? (float) $quantityReject : null),
        'quantity_repair_raw' => $quantityRepairRaw, // Nilai asli
        'quantity_repair' => in_array($form['quantity']['repair'], ['N/A', '-']) ? 'N/A' : ($quantityRepair && is_numeric($quantityRepair) ? (float) $quantityRepair : null),
        'image_path' => $imagePath,
    ]);
    
    $duration = microtime(true) - $startTime;
    $this->info("Waktu menyimpan ke database untuk {$imagePath}: " . number_format($duration, 2) . " detik");
    \Illuminate\Support\Facades\Log::info("Waktu menyimpan ke database untuk {$imagePath}: " . number_format($duration, 2) . " detik");
}
}