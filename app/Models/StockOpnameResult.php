<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StockOpnameResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_id', // Add this line
        'tanggal',
        'jam',
        'location',
        'warehouse',
        'nomor_form',
        'nama_part',
        'nomor_part',
        'satuan',
        'tipe', 
        'zone', 
        'wip_code',
        'quantity_good',
        'quantity_reject',
        'quantity_repair',
        'image_path'
    ];
}