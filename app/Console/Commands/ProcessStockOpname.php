<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\StockOpnameResult;
use setasign\Fpdi\Fpdi;

class ProcessStockOpname extends Command
{
    protected $signature = 'stock-opname:process';
    protected $description = 'Process stock opname forms from directory';

    protected $sourceDir = ''; // Nyari file di root C:/OCR_KYBI
    protected $processedDir = 'finished';
    protected $scannedDir = 'scanned'; // Folder buat hasil split
    protected $toProcessDir = 'to_process'; // Folder buat proses OCR

    public function handle()
    {
        $disk = Storage::disk('stock_opname');
        
        // Pastiin semua folder ada
        $disk->makeDirectory($this->sourceDir);
        $disk->makeDirectory($this->processedDir);
        $disk->makeDirectory($this->scannedDir);
        $disk->makeDirectory($this->toProcessDir);
        $disk->makeDirectory('error_400');
        $disk->makeDirectory('error_401');
        $disk->makeDirectory('rejected');

        $files = $disk->files($this->sourceDir);

        $this->info('Starting stock opname processing...');
        $this->info('Verihubs Config: ' . json_encode(config('services.verihubs')));
        $this->info('Storage Root: ' . $disk->path('')); // Debug root path
        $this->info('Storage Path: ' . $disk->path($this->sourceDir));
        $this->info('Files to process: ' . json_encode($files));

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
        $filename = pathinfo($filePath, PATHINFO_BASENAME);
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $this->line("\n<fg=blue>🔍 Processing file:</> {$filePath}");

        // Kalo PDF, split dulu
        if ($extension === 'pdf') {
            $this->processPdfFile($filePath, $fullPath, $filename);
        } else {
            $this->processSingleFile($filePath, $fullPath);
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

            $this->info("Split PDF {$filename} yang punya {$pageCount} halaman...");

            // Split PDF dan simpen ke scanned
            for ($page = 1; $page <= $pageCount; $page++) {
                $newPdf = new Fpdi();
                $newPdf->AddPage();
                $newPdf->setSourceFile($fullPath);
                $tplIdx = $newPdf->importPage($page);
                $newPdf->useTemplate($tplIdx);

                $pageFilename = pathinfo($filename, PATHINFO_FILENAME) . "_hal{$page}.pdf";
                $scannedPath = "{$this->scannedDir}/{$pageFilename}"; // Simpen ke scanned
                $newPdf->Output($disk->path($scannedPath), 'F');

                $this->info("Halaman {$page} disimpen ke: {$scannedPath}");

                // Pindahin dari scanned ke to_process
                $toProcessPath = "{$this->toProcessDir}/{$pageFilename}";
                $disk->move($scannedPath, $toProcessPath);
                $this->info("Halaman {$page} dipindah ke: {$toProcessPath}");

                // Proses file di to_process
                $this->processSingleFile($toProcessPath, $disk->path($toProcessPath));
            }

            // Pindahin PDF asli ke finished
            $this->moveProcessedFileAndGetPath($filePath);
            $disk->deleteDirectory($tempDir);
        } catch (\Exception $e) {
            $this->error("Gagal split PDF {$filename}: " . $e->getMessage());
            $this->moveErrorFile($filePath, 'error_400');
        }
    }

    private function processSingleFile($filePath, $fullPath)
    {
        $disk = Storage::disk('stock_opname');

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
                $newImagePath = $this->moveProcessedFileAndGetPath($filePath);
                $this->saveToDatabase($data['data'], $form, $newImagePath);
            }
        } else {
            $this->error("Result data not found in response for {$filePath}.");
        }
    }

    private function moveProcessedFileAndGetPath($originalPath)
    {
        $filename = pathinfo($originalPath, PATHINFO_BASENAME);
        $newPath = $this->processedDir . '/' . $filename;
        
        Storage::disk('stock_opname')->move($originalPath, $newPath);
        $this->line("<fg=magenta>♻️ File dipindah ke:</> {$newPath}");
        
        return $newPath;
    }

    private function handleApiError($response, $filePath)
    {
        $status = $response->status();
        $error = $response->json();

        $this->error("API Error [{$status}]: " . ($error['message'] ?? 'Unknown error'));
        
        if (in_array($status, [400, 401, 500])) {
            $this->moveErrorFile($filePath, "error_{$status}");
        }
    }

    private function moveErrorFile($originalPath, $errorType)
    {
        $disk = Storage::disk('stock_opname');
        $disk->makeDirectory($errorType);
        $filename = pathinfo($originalPath, PATHINFO_BASENAME);
        $newPath = $errorType . '/' . $filename;
        
        $disk->move($originalPath, $newPath);
        $this->info("Moved error file to: {$newPath}");
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
            'quantity_reject' => $form['quantity']['reject'] === 'N/A' ? null : (int)$form['quantity']['reject'],
            'quantity_repair' => $form['quantity']['repair'] === 'N/A' ? null : (int)$form['quantity']['repair'],
            'image_path' => $imagePath,
        ]);
    }
}