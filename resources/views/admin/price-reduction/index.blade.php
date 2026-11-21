@extends('admin.layouts.app')
@section('title','Reduce Price Requests')
@section('styles')
<style>
.rp-wrap{max-width:1480px;margin:auto;color:#081c43}.rp-heading{font-size:30px;font-weight:800;margin:0 0 4px}.rp-muted{color:#67758d}.rp-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin:24px 0}.rp-card{background:#fff;border:1px solid #e7ebf3;border-radius:15px;padding:20px;box-shadow:0 4px 18px #10244808}.rp-number{font-size:27px;font-weight:800;margin-top:7px}.rp-grid{display:grid;grid-template-columns:minmax(0,1fr) 350px;gap:18px}.rp-table{width:100%;border-collapse:collapse;font-size:13px}.rp-table th,.rp-table td{text-align:left;padding:13px 10px;border-bottom:1px solid #e9edf4;vertical-align:middle}.rp-table th{color:#65728a;font-weight:600}.rp-table tr:hover{background:#f8faff}.rp-pill{padding:5px 9px;border-radius:20px;background:#fff1da;color:#a76a06;font-size:12px}.rp-input,.rp-select,.rp-note{border:1px solid #d9dfeb;border-radius:9px;padding:10px;width:100%;background:white;color:#15284b}.rp-btn{border:0;background:#081c43;color:white;border-radius:9px;padding:10px 14px;cursor:pointer}.rp-btn-light{background:#f1f4fa;color:#132448}.rp-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:15px}.rp-detail{position:sticky;top:18px;align-self:start}.rp-detail dl{display:grid;grid-template-columns:110px 1fr;gap:10px;font-size:13px}.rp-detail dt{color:#67758d}.rp-detail dd{margin:0;overflow-wrap:anywhere}.rp-empty{padding:24px;text-align:center;color:#7a879e}@media(max-width:1100px){.rp-grid{grid-template-columns:1fr}.rp-detail{position:static}}@media(max-width:700px){.rp-stats{grid-template-columns:repeat(2,1fr)}.rp-table-wrap{overflow-x:auto}.rp-table{min-width:800px}}
</style>
@endsection
@section('content')
<div class="rp-wrap">
 <h1 class="rp-heading">Reduce Price Requests</h1><div class="rp-muted">Review and manage customer hotel price requests.</div>
 @if(session('success'))<div class="rp-card" role="status" style="margin-top:15px;color:#168348">{{ session('success') }}</div>@endif
 <div class="rp-stats">
  <div class="rp-card"><div class="rp-muted">Total Requests</div><div class="rp-number">{{ collect($counts)->sum() }}</div></div>
  <div class="rp-card"><div class="rp-muted">Pending Review</div><div class="rp-number">{{ ($counts['new']??0)+($counts['in_review']??0) }}</div></div>
  <div class="rp-card"><div class="rp-muted">Approved</div><div class="rp-number">{{ $counts['approved']??0 }}</div></div>
  <div class="rp-card"><div class="rp-muted">Rejected</div><div class="rp-number">{{ $counts['rejected']??0 }}</div></div>
 </div>
 <form method="GET" class="rp-card" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px">
  <input class="rp-input" style="flex:2;min-width:200px" name="q" value="{{ $search }}" placeholder="Search request ID, customer or hotel">
  <select class="rp-select" style="flex:1;min-width:160px" name="status"><option value="">All Requests</option>@foreach(['new'=>'New','in_review'=>'In Review','approved'=>'Approved','rejected'=>'Rejected','replied'=>'Replied','completed'=>'Completed'] as $key=>$label)<option value="{{ $key }}" @selected($selectedStatus===$key)>{{ $label }}</option>@endforeach</select>
  <button class="rp-btn">Filters</button><a class="rp-btn rp-btn-light" href="{{ route('admin.price-reduction.index') }}">Reset</a>
 </form>
 <div class="rp-grid">
  <section class="rp-card rp-table-wrap"><table class="rp-table"><thead><tr><th>Request ID</th><th>Customer</th><th>Hotel / Room</th><th>Current Price</th><th>Requested Price</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>
  @forelse($requests as $item)
  <tr><td>{{ $item->request_no }}</td><td><strong>{{ $item->customer_name }}</strong><div class="rp-muted">{{ $item->customer_email }}</div></td><td><strong>{{ $item->hotel_name }}</strong><div class="rp-muted">{{ $item->room_name }}</div></td><td>{{ $item->displayed_price === null ? '—' : $item->currency.' '.number_format((float)$item->displayed_price,2) }}</td><td><strong>{{ $item->currency }} {{ number_format((float)$item->requested_price,2) }}</strong></td><td><span class="rp-pill">{{ ucwords(str_replace('_',' ',$item->status)) }}</span></td><td>{{ $item->created_at }}</td><td><button type="button" class="rp-btn rp-btn-light" onclick="showPriceRequest({{ \Illuminate\Support\Js::from($item) }})">Review</button></td></tr>
  @empty<tr><td colspan="8" class="rp-empty">No requests found.</td></tr>@endforelse
  </tbody></table><div style="margin-top:16px">{{ $requests->links() }}</div></section>
  <aside class="rp-card rp-detail" id="rp-detail"><h3>Reduce Price Request</h3><div class="rp-muted">Select a request to view details and actions.</div></aside>
 </div>
</div>
@endsection
@push('scripts')
<script>
function showPriceRequest(r){
 const esc=s=>String(s??'—').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const root=document.getElementById('rp-detail');root.replaceChildren();
 const heading=document.createElement('h3');heading.textContent=r.request_no+' · '+r.status.replaceAll('_',' ');root.append(heading);
 const dl=document.createElement('dl');
 for(const [k,v] of Object.entries({'Customer':r.customer_name,'Email':r.customer_email,'Hotel':r.hotel_name,'Room':r.room_name,'Check-in':r.checkin,'Check-out':r.checkout,'Guests':r.adults+' adults, '+r.children+' children','Current price':r.displayed_price===null?'Not provided':r.currency+' '+r.displayed_price,'Requested':r.currency+' '+r.requested_price,'Notes':r.notes,'Admin note':r.admin_note})){
  const dt=document.createElement('dt');dt.textContent=k;const dd=document.createElement('dd');dd.textContent=v??'—';dl.append(dt,dd);
 }root.append(dl);
 if(r.attachment_path){const a=document.createElement('a');a.textContent='Download supporting document';a.href='/admin/price-reduction-requests/'+encodeURIComponent(r.id)+'/attachment';root.append(a);}
 const form=document.createElement('form');form.method='POST';form.action='/admin/price-reduction-requests/'+encodeURIComponent(r.id);form.className='rp-actions';
 const csrf=document.createElement('input');csrf.type='hidden';csrf.name='_token';csrf.value=document.querySelector('meta[name="csrf-token"]').content;form.append(csrf);
 const method=document.createElement('input');method.type='hidden';method.name='_method';method.value='PATCH';form.append(method);
 const select=document.createElement('select');select.className='rp-select';select.name='status';
 const allowed={new:['in_review','approved','rejected'],in_review:['approved','rejected','replied'],approved:['replied','rejected'],replied:['completed'],rejected:[],completed:[]};
 for(const status of allowed[r.status]??[]){const o=document.createElement('option');o.value=status;o.textContent=status.replaceAll('_',' ');select.append(o);}
 if(select.options.length){form.append(select);const note=document.createElement('textarea');note.className='rp-note';note.name='admin_note';note.placeholder='Admin note (optional)';note.maxLength=2000;form.append(note);const button=document.createElement('button');button.className='rp-btn';button.textContent='Save decision';form.append(button);root.append(form);}
 const mail=document.createElement('a');mail.className='rp-btn rp-btn-light';mail.style.display='inline-block';mail.style.marginTop='12px';mail.textContent='Contact customer';mail.href='mailto:'+encodeURIComponent(r.customer_email)+'?subject='+encodeURIComponent('Royal Travel price request '+r.request_no);root.append(mail);
}
</script>
@endpush
