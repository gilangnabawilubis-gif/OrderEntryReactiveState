<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MejaSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 5) as $number) {
            $kode = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            DB::table('tbl_meja')->insertOrIgnore([
                'kode' => $kode,
                'nama' => 'Meja ' . $kode,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
