@extends('admin.layouts.app')
@section('title','Admin Users')
@section('content')
<div class="mx-auto max-w-6xl space-y-6 p-6"><div class="flex justify-between items-center"><h1 class="text-2xl font-bold text-[#02143C]">Admin Users</h1><a class="rounded-lg bg-[#02143C] text-white px-4 py-2" href="{{ route('admin.users.create') }}">Create User</a></div>
@if(session('success'))<div class="rounded bg-green-50 p-3">{{session('success')}}</div>@endif
<form method="GET"><input name="q" value="{{request('q')}}" placeholder="Search name or email" class="rounded border p-2"><button class="ml-2 rounded bg-slate-800 text-white px-3 py-2">Search</button></form>
<div class="overflow-x-auto rounded-xl border bg-white"><table class="w-full text-left text-sm"><thead class="bg-slate-50"><tr><th class="p-4">Name</th><th>Email</th><th>Roles</th><th>Status</th><th></th></tr></thead><tbody>@forelse($users as $user)<tr class="border-t"><td class="p-4">{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ ($assigned[$user->id] ?? collect())->pluck('code')->join(', ') }}</td><td>{{ ($access[$user->id] ?? false) ? 'Enabled' : 'Disabled' }}</td><td><a class="text-[#02143C] underline" href="{{route('admin.users.edit',$user)}}">Edit</a></td></tr>@empty<tr><td colspan="5" class="p-5">No users found</td></tr>@endforelse</tbody></table></div>{{ $users->links() }}</div>
@endsection
