@extends('admin.layouts.app')
@section('title','Admin Roles')
@section('content')
<div class="rt-access-page mx-auto max-w-6xl space-y-6"><h1 class="text-2xl font-bold text-[#02143C]">Roles & Permissions</h1>
@if(session('success'))<div class="rounded bg-green-50 p-3">{{session('success')}}</div>@endif
@if($errors->any())<div class="rounded bg-red-50 p-3">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{route('admin.roles.store')}}" class="rounded-xl border bg-white p-5 space-y-3">@csrf<h2 class="font-semibold">Create Admin Role</h2><input class="rounded border p-2" name="code" placeholder="role_code" required><input class="rounded border p-2" name="description" placeholder="Description"><button class="rounded bg-[#02143C] px-4 py-2 text-white">Create</button></form>
@foreach($roles as $role)<form method="POST" action="{{route('admin.roles.update',$role->id)}}" class="rounded-xl border bg-white p-5 space-y-4">@csrf @method('PUT')<h2 class="font-bold">{{ $role->code }}</h2><input class="w-full rounded border p-2" name="description" value="{{ $role->description }}" @disabled($role->code==='super_admin')><div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">@foreach($permissions as $permission)<label class="flex items-start gap-2 text-sm"><input type="checkbox" name="permission_ids[]" value="{{$permission->id}}" @checked(($assigned[$role->id]??collect())->contains('permission_id',$permission->id)) @disabled($role->code==='super_admin')><span>{{$permission->code}}</span></label>@endforeach</div>@if($role->code!=='super_admin')<button class="rounded bg-[#02143C] px-4 py-2 text-white">Save Permissions</button>@else<p class="text-sm text-slate-500">Protected role — changes require a reviewed procedure.</p>@endif</form>@endforeach
</div>
@endsection
