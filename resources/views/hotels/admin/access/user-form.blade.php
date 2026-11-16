@extends('admin.layouts.app')
@section('title',$user ? 'Edit Admin User' : 'Create Admin User')
@section('content')
<div class="rt-access-page mx-auto max-w-3xl"><h1 class="mb-5 text-2xl font-bold text-[#02143C]">{{ $user ? 'Edit Admin User' : 'Create Admin User' }}</h1>
@if($errors->any())<div class="mb-4 rounded bg-red-50 p-4 text-red-700"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form class="space-y-4 rounded-xl border bg-white p-6" method="POST" action="{{ $user ? route('admin.users.update',$user) : route('admin.users.store') }}">@csrf @if($user)@method('PUT')@endif
<label class="block">Full name<input class="mt-1 w-full rounded border p-2" required name="name" value="{{old('name',$user?->name)}}"></label>
<label class="block">Email<input class="mt-1 w-full rounded border p-2" type="email" required name="email" value="{{old('email',$user?->email)}}"></label>
@if(!$user)<label class="block">Initial password (minimum 12 characters)<input class="mt-1 w-full rounded border p-2" type="password" required minlength="12" name="password" autocomplete="new-password"></label>@endif
<fieldset class="rounded border p-4"><legend class="font-semibold">Admin Roles</legend>@foreach($roles as $role)<label class="flex items-center gap-2 py-1"><input type="checkbox" name="role_ids[]" value="{{$role->id}}" @checked(in_array($role->id,old('role_ids',$selected)))><span>{{$role->code}}</span></label>@endforeach</fieldset>
<label class="flex items-center gap-2"><input type="hidden" name="is_enabled" value="0"><input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled',$user ? (bool)DB::table('rt_admin_access')->where('user_id',$user->id)->value('is_enabled') : false))>Enable Admin access</label>
<button class="rounded-lg bg-[#02143C] px-5 py-3 text-white">Save</button></form></div>
@endsection
