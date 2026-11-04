<?php
namespace App\Http\Controllers;
use App\Models\Order;
use App\Models\Meja;
use App\Models\MenuDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
 
class OrderController extends Controller {
    public function index() { return view('order.index'); }
    public function create() { return view('order.add'); }
    public function edit($id) { return view('order.edit', ['id'=>$id]); }
    public function availableMeja(Request $request) {
        $data = $request->validate([
            'order_id'=>['sometimes','integer','exists:tbl_order,id']
        ]);
        $currentOrder = isset($data['order_id'])
            ? Order::findOrFail($data['order_id'])
            : null;
        if ($currentOrder && $currentOrder->status !== 'active') {
            throw ValidationException::withMessages([
                'order_id'=>'Order sudah final dan tidak dapat diedit.'
            ]);
        }

        $occupiedMejaIds = Order::where('status', 'active')
            ->whereNotNull('meja_id')
            ->when($currentOrder, fn ($query) => $query->where('id', '!=', $currentOrder->id))
            ->pluck('meja_id');
        $meja = Meja::where(function ($query) use ($occupiedMejaIds, $currentOrder) {
            $query->where(function ($query) use ($occupiedMejaIds) {
                $query->where('aktif', true)->whereNotIn('id', $occupiedMejaIds);
            });
            if ($currentOrder && $currentOrder->meja_id) {
                $query->orWhere('id', $currentOrder->meja_id);
            }
        })->orderBy('kode')->get(['id', 'kode', 'nama', 'aktif']);

        return response()->json($meja);
    }
    public function data() {
        return response()->json(Order::with('mejaMaster', 'details.menuDetail.product')
            ->orderByDesc('id')->get());
    }
    public function show($id) {
        return response()->json(Order::with('mejaMaster', 'details.menuDetail.product')
            ->findOrFail($id));
    }
    private function rules() {
        return [
            'meja_id'=>['required_without:meja','nullable','integer','exists:tbl_meja,id'],
            'meja'=>['required_without:meja_id','nullable','string','max:100'],
            'items'=>['required','array','min:1'],
            'items.*.menu_detail_id'=>[
                'required','integer','exists:tbl_menu_detail,id','distinct'
            ],
            'items.*.qty'=>['required','integer','min:1']
        ];
    }
    private function calculatedItems(array $items) {
        $ids = array_column($items, 'menu_detail_id');
        $prices = MenuDetail::whereIn('id', $ids)->get()->keyBy('id');
        return array_map(function ($item) use ($prices) {
            $src = $prices->get($item['menu_detail_id']);
            $harga = (int)$src->harga;
            $qty = (int)$item['qty'];
            return ['menu_detail_id'=>$src->id, 'qty'=>$qty,
                'harga'=>$harga, 'subtotal'=>$harga*$qty];
        }, $items);
    }
    private function totalItems(array $items) {
        return array_sum(array_column($items, 'subtotal'));
    }
    private function activeOnly(Order $order) {
        if ($order->status !== 'active') {
            throw ValidationException::withMessages([
                'status'=>'Order sudah final dan tidak dapat diubah.'
            ]);
        }
    }
    private function lockAvailableMeja(array $data, ?Order $order = null) {
        if (!empty($data['meja_id'])) {
            $meja = Meja::whereKey($data['meja_id'])->lockForUpdate()->firstOrFail();
        } else {
            $label = trim($data['meja']);
            $meja = Meja::where(function ($query) use ($label) {
                $query->where('kode', $label)->orWhere('nama', $label);
            })->lockForUpdate()->first();
            if (!$meja) {
                throw ValidationException::withMessages([
                    'meja'=>'Pilih meja yang terdaftar pada master meja.'
                ]);
            }
        }

        if (!$meja->aktif && (!$order || (int)$order->meja_id !== (int)$meja->id)) {
            throw ValidationException::withMessages([
                'meja_id'=>'Meja tidak aktif dan tidak dapat dipilih.'
            ]);
        }
        $occupied = Order::where('meja_id', $meja->id)
            ->where('status', 'active')
            ->when($order, fn ($query) => $query->where('id', '!=', $order->id))
            ->exists();
        if ($occupied) {
            throw ValidationException::withMessages([
                'meja_id'=>'Meja sudah dipakai order aktif. Silakan pilih meja lain.'
            ]);
        }

        return $meja;
    }
    private function withMejaConflictHandling(callable $operation) {
        try { return $operation(); }
        catch (QueryException $e) {
            $driverCode = (string)($e->errorInfo[1] ?? '');
            $sqlState = (string)($e->errorInfo[0] ?? $e->getCode());
            if ($driverCode === '1062' || $sqlState === '23505'
                || $driverCode === '19'
                || str_contains(strtolower($e->getMessage()), 'unique constraint failed')) {
                throw ValidationException::withMessages([
                    'meja_id'=>'Meja sudah dipakai order aktif, atau item duplikat.'
                ]);
            }
            throw $e;
        }
    }
    public function store(Request $request) {
        $data = $request->validate($this->rules());
        return $this->withMejaConflictHandling(function () use ($data) {
            $order = DB::transaction(function () use ($data) {
                $meja = $this->lockAvailableMeja($data);
                $items = $this->calculatedItems($data['items']);
                $order = Order::create([
                    'meja'=>$meja->kode,'meja_id'=>$meja->id,
                    'active_meja_key'=>'MEJA:'.$meja->id,
                    'status'=>'active','total'=>$this->totalItems($items)
                ]);
                $order->update([
                    'kode_order'=>'ORD'.str_pad($order->id, 5, '0', STR_PAD_LEFT)
                ]);
                $order->details()->createMany($items);
                return $order;
            });
            return response()->json(['status'=>'success',
                'message'=>'Order aktif dibuat.',
                'data'=>$order->load('mejaMaster', 'details.menuDetail.product')], 201);
        });
    }

        public function update(Request $request, $id) {
        $data = $request->validate($this->rules());
        return $this->withMejaConflictHandling(function () use ($id,$data) {
            $order = DB::transaction(function () use ($id,$data) {
                $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
                $this->activeOnly($order);
                $meja = $this->lockAvailableMeja($data, $order);
                $old = $order->details()->get()->keyBy('menu_detail_id');
                $items = $this->calculatedItems($data['items']);
                // Saat edit, harga item yang sudah ada tetap harga snapshot.
                foreach ($items as &$item) {
                    if (isset($old[$item['menu_detail_id']])) {
                        $item['harga'] = (int)$old[$item['menu_detail_id']]->harga;
                        $item['subtotal'] = $item['harga']*$item['qty'];
                    }
                }
                unset($item);
                $order->details()->delete();
                $order->details()->createMany($items);
                $order->update(['meja'=>$meja->kode,
                    'meja_id'=>$meja->id,
                    'active_meja_key'=>'MEJA:'.$meja->id,
                    'total'=>$this->totalItems($items)]);
                return $order;
            });
            return response()->json(['status'=>'success',
                'message'=>'Order aktif diperbarui.',
                'data'=>$order->load('mejaMaster', 'details.menuDetail.product')]);
        });
    }
    public function cancel($id) {
        $order = DB::transaction(function () use ($id) {
            $order = Order::whereKey($id)->lockForUpdate()->firstOrFail();
            $this->activeOnly($order);
            $order->update(['status'=>'cancelled',
                'active_meja_key'=>null,'cancelled_at'=>now()]);
            return $order;
        });
        return response()->json(['status'=>'success',
            'message'=>'Order dibatalkan; meja dilepas.', 'data'=>$order]);
    }
}
