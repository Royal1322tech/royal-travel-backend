@extends('admin.layouts.app')
@section('title','Support Tickets')
@section('content')
<h1 class="text-2xl font-bold mb-4">Support Tickets</h1>
<form method="GET" class="mb-4"><select name="status"><option value="">All statuses</option>@foreach($states as $state)<option @selected(request('status')===$state)>{{ $state }}</option>@endforeach</select><button>Filter</button></form>
<div class="bg-white rounded-xl p-4 overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Ticket</th><th>Booking</th><th>Employee</th><th>Subject</th><th>Status</th></tr></thead><tbody>@foreach($tickets as $t)<tr class="border-t"><td class="p-3"><a class="underline" href="{{ route('admin.support.show',$t->id) }}">{{ $t->ticket_no }}</a></td><td>{{ $t->booking_no }}</td><td>{{ $t->employee_name }}</td><td>{{ $t->subject }}</td><td>{{ $t->status }}</td></tr>@endforeach</tbody></table>{{ $tickets->links() }}</div>
@endsection
