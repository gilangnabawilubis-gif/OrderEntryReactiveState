@extends('layouts.app')
@section('title', 'Order')
@section('content')
<div id="orderIndexApp">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h3">Order</h1>
    <a href="{{ route('order.create') }}" class="btn btn-primary">New Order</a>
  </div>
  <div v-if="error" class="alert alert-danger">@{{ error }}</div>
  <div class="card"><div class="table-responsive">
    <table class="table table-hover mb-0"><thead class="table-light"><tr>
      <th>Kode</th><th>Meja</th><th>Item</th><th>Total</th>
      <th>Status</th><th>Tanggal &amp; Waktu Checkout</th><th>Aksi</th>
    </tr></thead><tbody>
      <tr v-for="o in orders" :key="o.id">
        <td>@{{ o.kode_order }}</td><td>@{{ o.meja?.nama || o.meja || '—' }}</td>
        <td>@{{ o.details.length }}</td>
        <td>Rp @{{ rupiah(o.total) }}</td>
        <td><span class="badge" :class="o.status==='active'?'bg-primary':
             (o.status==='completed'?'bg-success':'bg-secondary')">
          @{{ o.status }}
        </span></td>
        <td>@{{ o.checked_out_at ? formatDateTime(o.checked_out_at) : '—' }}</td>
        <td>
          <template v-if="o.status==='active'">
            <a :href="`${ORDER_PAGE}/${o.id}/edit`"
               class="btn btn-sm btn-outline-primary me-1">Edit</a>
            <a :href="`${ORDER_PAGE}/${o.id}/checkout`"
               class="btn btn-sm btn-success me-1">Checkout</a>
            <button @click="cancelOrder(o)"
               class="btn btn-sm btn-outline-danger">Cancel</button>
          </template>
          <small v-else class="text-muted">Final</small>
        </td>
      </tr>
    </tbody></table>
  </div></div>
</div>
@endsection
@push('scripts')
<script>
const {createApp,ref,onMounted}=Vue;
createApp({setup(){
  const orders=ref([]),error=ref('');
  const API_ORDERS=@json(url('/api/orders'));
  const ORDER_PAGE=@json(url('/order'));
  const rupiah=n=>new Intl.NumberFormat('id-ID').format(n||0);
  const formatDateTime=value=>new Intl.DateTimeFormat('id-ID',{
    dateStyle:'medium',timeStyle:'short'
  }).format(new Date(value));
  async function load(){
    try{const r=await fetch(API_ORDERS);if(!r.ok) throw Error('Gagal muat order');
      orders.value=await r.json();}
    catch(e){error.value=e.message;}
  }
  async function cancelOrder(o){
    if(!confirm(`Batalkan ${o.kode_order}?`)) return;
    try {const r=await fetch(`${API_ORDERS}/${o.id}/cancel`,{
      method:'POST',headers:{'Accept':'application/json'}});
      const j=await r.json();if(!r.ok)throw Error(j.message||'Gagal cancel');
      await load();}
    catch(e){error.value=e.message;}
  }
  onMounted(load);
  return {orders,error,ORDER_PAGE,rupiah,formatDateTime,cancelOrder};
}}).mount('#orderIndexApp');
</script>
@endpush
