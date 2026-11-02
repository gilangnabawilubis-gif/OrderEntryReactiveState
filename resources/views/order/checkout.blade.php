@extends('layouts.app')
@section('title', 'Checkout Order')
@section('content')
<div id="checkoutApp" class="container-fluid">
  <h1 class="h3 mb-3">Checkout Order</h1>
  <div v-if="error" class="alert alert-danger">@{{ error }}</div>
  <div v-if="loading">Memuat pesanan...</div>
  <div v-else class="card"><div class="card-body">
    <h2 class="h5">@{{ order.kode_order }} — Meja @{{ order.meja?.nama || order.meja || '—' }}</h2>
    <p>Status: <strong>@{{ order.status }}</strong></p>
    <table class="table"><thead><tr><th>Product</th><th>Qty</th>
      <th>Harga</th><th>Subtotal</th></tr></thead><tbody>
      <tr v-for="d in order.details" :key="d.id">
        <td>@{{ d.menu_detail?.product?.name || d.menu_detail_id }}</td>
        <td>@{{ d.qty }}</td>
        <td>Rp @{{ rupiah(d.harga) }}</td>
        <td>Rp @{{ rupiah(d.subtotal) }}</td>
      </tr>
    </tbody></table>
    <div class="text-end h4">TOTAL: Rp @{{ rupiah(order.total) }}</div>
    <template v-if="order.status==='active'">
      <label class="form-label">Nominal Dibayar (cash)</label>
      <input type="number" min="0" step="1" v-model.number="paid"
        class="form-control mb-2">
      <p :class="change>=0?'text-success':'text-danger'">
        @{{ change>=0?'Kembalian':'Kekurangan' }}:
        Rp @{{ rupiah(Math.abs(change)) }}
      </p>
      <button @click="submitCheckout" :disabled="saving||change<0"
        class="btn btn-success">Proses Checkout</button>
    </template>
    <div v-else class="alert alert-info">Order telah final.</div>
    <a href="{{ route('order.index') }}" class="btn btn-outline-secondary ms-2">
      Kembali
    </a>
  </div></div>
</div>
@endsection
@push('scripts')
<script>
const {createApp,ref,computed,onMounted}=Vue;
createApp({setup(){
  const id=@json($id), API=@json(url('/api/orders'));
  const ORDER_PAGE=@json(url('/order'));
  const order=ref({details:[],total:0,status:''});
  const paid=ref(0),loading=ref(true),saving=ref(false),error=ref('');
  const change=computed(()=>Number(paid.value||0)-Number(order.value.total||0));
  const rupiah=n=>new Intl.NumberFormat('id-ID').format(n||0);
  async function load(){
    try{const r=await fetch(`${API}/${id}`);
      if(!r.ok)throw Error('Gagal memuat checkout');
      order.value=await r.json();
    }catch(e){error.value=e.message;}finally{loading.value=false;}
  }
  async function submitCheckout(){
    if(!Number.isInteger(paid.value)||change.value<0){
      error.value='Nominal bayar harus bilangan bulat dan mencukupi.';return;
    }
    if(!confirm('Selesaikan pembayaran dan tutup order?'))return;
    saving.value=true;error.value='';
    try{const r=await fetch(`${API}/${id}/checkout`,{
      method:'POST',headers:{'Content-Type':'application/json',
        'Accept':'application/json'},
      body:JSON.stringify({paid_amount:paid.value})});
      const j=await r.json();if(!r.ok)throw Error(j.message||'Checkout gagal');
      window.location.href=ORDER_PAGE;
    }catch(e){error.value=e.message;}finally{saving.value=false;}
  }
  onMounted(load);
  return{order,paid,change,loading,saving,error,rupiah,submitCheckout};
}}).mount('#checkoutApp');
</script>
@endpush
