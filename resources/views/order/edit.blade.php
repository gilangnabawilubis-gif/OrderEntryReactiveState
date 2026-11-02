@extends('layouts.app')
@section('title', 'Edit Order')
@section('content')
<div id="orderEditApp">
  <h1 class="h3 mb-3">Edit Order</h1>
  <div v-if="error" class="alert alert-danger">@{{ error }}</div>
  <div v-if="loading">Memuat order...</div>
  <div v-else-if="status!=='active'" class="alert alert-warning">
    Order sudah final; tidak dapat diedit.
  </div>
  <template v-else>
    <div class="card mb-3"><div class="card-body row g-3">
      <div class="col-md-3"><label class="form-label">Meja</label>
        <select v-model="mejaId" class="form-select"
          :disabled="loadingMeja || !meja.length">
          <option value="">Pilih Meja</option>
          <option v-for="m in meja" :key="m.id" :value="m.id">
            @{{ m.kode }} — @{{ m.nama }}@{{ !m.aktif ? ' (tidak aktif)' : '' }}
          </option>
        </select>
        <small v-if="!loadingMeja && !meja.length" class="text-danger">
          Tidak ada meja tersedia. Silakan coba lagi setelah meja tersedia.
        </small></div>
      <div class="col-md-4"><label class="form-label">Menu</label>
        <select v-model="menuId" class="form-select"><option value="">Pilih</option>
          <option v-for="m in menus" :key="m.id" :value="m.id">@{{ m.nama }}</option>
        </select></div>
      <div class="col-md-5"><label class="form-label">Product</label>
        <select v-model="picked" class="form-select"><option value="">Pilih</option>
          <option v-for="d in options" :key="d.id" :value="d.id">
            @{{ d.product.name }} (Rp @{{ rupiah(d.harga) }})
          </option></select></div>
      <div class="col-md-3"><label class="form-label">Qty item baru</label>
        <input type="number" v-model.number="qty" min="1" class="form-control"></div>
      <div class="col-md-3 d-grid align-items-end">
        <button @click="addItem" class="btn btn-outline-primary">Tambah Item</button>
      </div>
    </div></div>
    <div class="card"><div class="card-body">
      <table class="table"><thead><tr><th>Item</th><th>Qty</th>
        <th>Harga Snapshot</th><th>Subtotal</th><th>Aksi</th>
      </tr></thead><tbody>
        <tr v-for="(it,i) in items" :key="it.menu_detail_id">
          <td>@{{ it.nama }}</td>
          <td><input type="number" min="1" v-model.number="it.qty"
            class="form-control form-control-sm" style="width:90px"></td>
          <td>Rp @{{ rupiah(it.harga) }}</td>
          <td>Rp @{{ rupiah(it.qty*it.harga) }}</td>
          <td><button @click="items.splice(i,1)"
            class="btn btn-sm btn-outline-danger">Hapus</button></td>
        </tr>
      </tbody></table>
      <div class="text-end fw-bold mb-3">Total: Rp @{{ rupiah(total) }}</div>
      <button @click="save" :disabled="saving" class="btn btn-primary">
        Simpan Perubahan
      </button>
      <a href="{{ route('order.index') }}" class="btn btn-outline-secondary ms-2">
        Kembali
      </a>
    </div></div>
  </template>
</div>
@endsection
@push('scripts')
<script>
const {createApp,ref,computed,onMounted}=Vue;
createApp({setup(){
  const orderId=@json($id),API_ORDERS=@json(url('/api/orders'));
  const API_MENUS=@json(url('/api/menus'));
  const API_MEJA=@json(url('/api/meja/available'));
  const ORDER_PAGE=@json(url('/order'));
  const menus=ref([]),meja=ref([]),mejaId=ref(''),status=ref(''),items=ref([]);
  const menuId=ref(''),picked=ref(''),qty=ref(1);
  const loading=ref(true),saving=ref(false),error=ref('');
  const loadingMeja=ref(true);
  const options=computed(()=>menus.value.find(m=>m.id==menuId.value)?.details||[]);
  const total=computed(()=>items.value.reduce((s,i)=>s+i.harga*i.qty,0));
  const rupiah=n=>new Intl.NumberFormat('id-ID').format(n||0);
  async function load(){
    try{const [mr,or,mejar]=await Promise.all([
      fetch(API_MENUS),fetch(`${API_ORDERS}/${orderId}`),
      fetch(`${API_MEJA}?order_id=${orderId}`)]);
      if(!mr.ok||!or.ok||!mejar.ok)throw Error('Gagal memuat data edit');
      menus.value=await mr.json();meja.value=await mejar.json();
      const o=await or.json();
      mejaId.value=o.meja_id;status.value=o.status;
      items.value=o.details.map(d=>({menu_detail_id:d.menu_detail_id,
        nama:d.menu_detail?.product?.name||`Item ${d.menu_detail_id}`,
        harga:Number(d.harga),qty:Number(d.qty)}));
    }catch(e){error.value=e.message;}finally{
      loading.value=false;loadingMeja.value=false;
    }
  }
  function addItem(){
    const d=options.value.find(x=>x.id==picked.value);
    if(!d||!Number.isInteger(qty.value)||qty.value<1){
      error.value='Pilih Product dan qty minimal 1';return;
    }
    const old=items.value.find(i=>i.menu_detail_id==d.id);
    if(old)old.qty+=qty.value;
    else items.value.push({menu_detail_id:d.id,nama:d.product.name,
      harga:Number(d.harga),qty:qty.value});
    picked.value='';qty.value=1;error.value='';
  }
  async function save(){
    if(!mejaId.value||!items.value.length||
       items.value.some(i=>!Number.isInteger(i.qty)||i.qty<1)){
      error.value='Pilih meja yang tersedia; qty harus valid dan minimal satu item.';return;
    }
    saving.value=true;error.value='';
    try{const r=await fetch(`${API_ORDERS}/${orderId}`,{
      method:'PUT',headers:{'Content-Type':'application/json','Accept':'application/json'},
      body:JSON.stringify({meja_id:mejaId.value,items:items.value.map(i=>({
        menu_detail_id:i.menu_detail_id,qty:i.qty
      }))})});
      const j=await r.json();if(!r.ok)throw Error(
        j.errors?.meja_id?.[0]||j.message||'Gagal update');
      window.location.href=ORDER_PAGE;
    }catch(e){error.value=e.message;}finally{saving.value=false;}
  }
  onMounted(load);
  return{menus,meja,mejaId,loadingMeja,status,items,menuId,picked,qty,options,
    total,rupiah,loading,saving,error,addItem,save};
}}).mount('#orderEditApp');
</script>
@endpush
