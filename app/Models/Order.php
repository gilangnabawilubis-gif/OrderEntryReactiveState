<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Order extends Model {
    protected $table = 'tbl_order';
    protected $fillable = [
        'kode_order','meja','meja_id','active_meja_key','status','total',
        'paid_amount','change_amount','checked_out_at','cancelled_at'
    ];
    protected $casts = [
        'total'=>'integer','paid_amount'=>'integer',
        'change_amount'=>'integer','meja_id'=>'integer','checked_out_at'=>'datetime',
        'cancelled_at'=>'datetime'
    ];
    public function meja()
    {
        return $this->belongsTo(Meja::class, 'meja_id');
    }
    public function details() {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }
}
