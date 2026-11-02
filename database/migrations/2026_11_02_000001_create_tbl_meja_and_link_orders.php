<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tbl_meja')) {
            Schema::create('tbl_meja', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 50)->unique();
                $table->string('nama', 100);
                $table->boolean('aktif')->default(true);
                $table->timestamps();
            });
        }

        $this->seedDefaultMeja();

        if (!Schema::hasTable('tbl_order')) {
            return;
        }

        if (!Schema::hasColumn('tbl_order', 'meja')) {
            throw new RuntimeException(
                'Cannot link existing orders: tbl_order.meja does not exist.'
            );
        }

        if (!Schema::hasColumn('tbl_order', 'meja_id')) {
            Schema::table('tbl_order', function (Blueprint $table) {
                $table->unsignedBigInteger('meja_id')->nullable()->after('meja');
            });
        }

        if (!Schema::hasColumn('tbl_order', 'active_meja_key')) {
            Schema::table('tbl_order', function (Blueprint $table) {
                $table->string('active_meja_key', 80)->nullable();
            });
        }

        $meja = DB::table('tbl_meja')->get()->keyBy(
            fn ($meja) => $this->normalize($meja->kode)
        );

        foreach (DB::table('tbl_order')->get(['id', 'meja']) as $order) {
            $legacyLabel = trim((string) $order->meja);
            $kode = $this->normalize($legacyLabel);

            if ($kode === '') {
                $kode = 'LEGACY-' . $order->id;
            }

            if (!$meja->has($kode)) {
                $now = now();
                $mejaId = DB::table('tbl_meja')->insertGetId([
                    'kode' => $kode,
                    'nama' => $legacyLabel !== '' ? Str::limit($legacyLabel, 100, '') : 'Meja Lama ' . $order->id,
                    'aktif' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $meja->put($kode, (object) ['id' => $mejaId, 'kode' => $kode]);
            }

            DB::table('tbl_order')->where('id', $order->id)->update([
                'meja_id' => $meja->get($kode)->id,
            ]);
        }

        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('tbl_order', function (Blueprint $table) {
                $table->unsignedBigInteger('meja_id')->nullable(false)->change();
            });
        }

        $conflictingActiveMeja = DB::table('tbl_order')
            ->where('status', 'active')
            ->select('meja_id')
            ->groupBy('meja_id')
            ->havingRaw('COUNT(*) > 1')
            ->value('meja_id');

        if ($conflictingActiveMeja !== null) {
            throw new RuntimeException(
                'Cannot migrate orders: multiple ACTIVE orders resolve to the same meja_id. Resolve the active meja conflict and rerun the migration.'
            );
        }

        DB::table('tbl_order')->update(['active_meja_key' => null]);
        foreach (DB::table('tbl_order')->where('status', 'active')->get(['id', 'meja_id']) as $order) {
            DB::table('tbl_order')->where('id', $order->id)->update([
                'active_meja_key' => 'MEJA:' . $order->meja_id,
            ]);
        }

        $hasMejaForeignKey = collect(Schema::getForeignKeys('tbl_order'))
            ->contains(fn ($foreignKey) => in_array('meja_id', $foreignKey['columns'], true));
        if (!$hasMejaForeignKey) {
            Schema::table('tbl_order', function (Blueprint $table) {
                $table->foreign('meja_id')
                    ->references('id')
                    ->on('tbl_meja')
                    ->restrictOnDelete();
            });
        }

        $hasUniqueActiveMejaKey = collect(Schema::getIndexes('tbl_order'))
            ->contains(fn ($index) => $index['unique']
                && $index['columns'] === ['active_meja_key']);
        if (!$hasUniqueActiveMejaKey) {
            Schema::table('tbl_order', function (Blueprint $table) {
                $table->unique('active_meja_key', 'tbl_order_active_meja_key_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tbl_order')) {
            $foreignKeyExists = collect(Schema::getForeignKeys('tbl_order'))
                ->contains(fn ($foreignKey) => in_array('meja_id', $foreignKey['columns'], true));
            if ($foreignKeyExists) {
                Schema::table('tbl_order', function (Blueprint $table) {
                    $table->dropForeign(['meja_id']);
                });
            }

            $namedIndexExists = collect(Schema::getIndexes('tbl_order'))
                ->contains(fn ($index) => $index['name'] === 'tbl_order_active_meja_key_unique');
            if ($namedIndexExists) {
                Schema::table('tbl_order', function (Blueprint $table) {
                    $table->dropUnique('tbl_order_active_meja_key_unique');
                });
            }

            if (Schema::hasColumn('tbl_order', 'meja_id')) {
                Schema::table('tbl_order', function (Blueprint $table) {
                    $table->dropColumn('meja_id');
                });
            }
        }

        Schema::dropIfExists('tbl_meja');
    }

    private function seedDefaultMeja(): void
    {
        $now = now();
        foreach (range(1, 5) as $number) {
            $kode = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            DB::table('tbl_meja')->insertOrIgnore([
                'kode' => $kode,
                'nama' => 'Meja ' . $kode,
                'aktif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function normalize(string $value): string
    {
        return Str::upper(trim(preg_replace('/\s+/', ' ', $value) ?? ''));
    }
};
