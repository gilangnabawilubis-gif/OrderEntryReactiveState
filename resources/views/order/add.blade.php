@extends('layouts.app')
@section('title', 'New Order')
@section('content')
<div id="orderAddApp" class="container-fluid">
  <div class="d-flex justify-content-between mb-3">
    <h1 class="h3">New Order</h1>
    <a href="{{ route('order.index') }}" class="btn btn-outline-secondary">Kembali</a>
  </div>
  <div v-if="error" class="alert alert-danger">@{{ error }}</div>
  <div class="card mb-3"><div class="card-body row g-3">
    <div class="col-md-3">
      <label class="form-label">Meja</label>
      <select v-model="mejaId" class="form-select"
        :disabled="loadingMeja || !mejaList.length">
        <option value="">Pilih Meja</option>
        <option v-for="m in mejaList" :key="m.id" :value="m.id">
          @{{ m.kode }} — @{{ m.nama }}
        </option>
      </select>
      <small v-if="!loadingMeja && !mejaList.length" class="text-danger">
        Tidak ada meja tersedia.
      </small>
    </div>
    <div class="col-md-4">
      <label class="form-label">Menu Resto</label>
      <select v-model="menuId" class="form-select">
        <option value="">Pilih Menu</option>
        <option v-for="m in menus" :key="m.id" :value="m.id">@{{ m.nama }}</option>
      </select>
    </div>
    <div class="col-md-5">
      <label class="form-label">Product dalam Menu</label>
      <select v-model="picked" class="form-select">
        <option value="">Pilih Product</option>
        <option v-for="d in options" :key="d.id" :value="d.id">
          @{{ d.product.name }} — Rp @{{ rupiah(d.harga) }}
        </option>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Quantity</label>
      <input type="number" min="1" v-model.number="qty" class="form-control">
    </div>
    <div class="col-md-3 d-grid align-items-end">
      <button type="button" @click="addItem" class="btn btn-outline-primary">
        Tambah Item
      </button>
    </div>
  </div></div>
  <div class="card"><div class="card-body">
    <h2 class="h5">Detail Pesanan</h2>
    <table class="table"><thead><tr>
      <th>Item</th><th>Qty</th><th>Harga</th><th>Subtotal</th><th>Aksi</th>
    </tr></thead><tbody>
      <tr v-for="(it,i) in items" :key="it.menu_detail_id">
        <td>@{{ it.nama }}</td><td>@{{ it.qty }}</td>
        <td>Rp @{{ rupiah(it.harga) }}</td>
        <td>Rp @{{ rupiah(it.qty * it.harga) }}</td>
        <td><button class="btn btn-sm btn-outline-danger"
            @click="items.splice(i,1)">Hapus</button></td>
      </tr>
    </tbody></table>
    <div class="text-end fw-bold">Total: Rp @{{ rupiah(total) }}</div>
    <button class="btn btn-primary" :disabled="saving" @click="save">
      @{{ saving ? 'Menyimpan...' : 'Simpan Order (ACTIVE)' }}
    </button>
  </div></div>
</div>
@endsection
@push('scripts')
<script>
const { createApp, ref, computed, onMounted } = Vue;
createApp({ setup() {
  const menus=ref([]), mejaList=ref([]), mejaId=ref(''), menuId=ref(''), picked=ref('');
  const qty=ref(1), items=ref([]), error=ref(''), saving=ref(false);
  const loadingMeja=ref(true);
  const API_MENUS=@json(url('/api/menus'));
  const API_MEJA=@json(url('/api/meja/available'));
  const API_ORDERS=@json(url('/api/orders'));
  const ORDER_PAGE=@json(url('/order'));
  const options=computed(()=>menus.value.find(m=>m.id==menuId.value)?.details||[]);
  const total=computed(()=>items.value.reduce((s,i)=>s+i.qty*i.harga,0));
  const rupiah=n=>new Intl.NumberFormat('id-ID').format(n||0);
  async function loadMenus() {
    try { const r=await fetch(API_MENUS); if(!r.ok) throw Error('Menu gagal dimuat');
      menus.value=await r.json(); }
    catch(e) { error.value=e.message; }
  }
  async function loadMeja() {
    try { const r=await fetch(API_MEJA);
      if(!r.ok) throw Error('Daftar meja gagal dimuat');
      mejaList.value=await r.json(); }
    catch(e) { error.value=e.message; }
    finally { loadingMeja.value=false; }
  }
  function addItem() {
    const d=options.value.find(x=>x.id==picked.value);
    if(!d || !Number.isInteger(qty.value) || qty.value<1) {
      error.value='Pilih Product dan qty minimal 1.'; return;
    }
    const existing=items.value.find(x=>x.menu_detail_id==d.id);
    if(existing) existing.qty+=qty.value;
    else items.value.push({menu_detail_id:d.id,nama:d.product.name,
      harga:Number(d.harga),qty:qty.value});
    picked.value='';qty.value=1;error.value='';
  }
  async function save() {
    if(!mejaId.value||!items.value.length) {
      error.value='Pilih meja yang tersedia dan tambahkan minimal satu item.';return;
    }
    saving.value=true;error.value='';
    try {
      const r=await fetch(API_ORDERS,{method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json'},
        body:JSON.stringify({meja_id:mejaId.value,items:items.value.map(i=>({
          menu_detail_id:i.menu_detail_id,qty:i.qty
        }))})});
      const j=await r.json();if(!r.ok) throw Error(
        j.errors?.meja_id?.[0]||j.errors?.meja?.[0]||j.message||'Gagal simpan Order');
      window.location.href=ORDER_PAGE;
    } catch(e) { error.value=e.message; } finally { saving.value=false; }
  }
  onMounted(()=>{loadMenus();loadMeja();});
  return {menus,mejaList,mejaId,loadingMeja,menuId,picked,qty,items,error,saving,options,
    total,rupiah,addItem,save};
}}).mount('#orderAddApp');
</script>
@endpush
