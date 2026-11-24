@extends('admin.layouts.app')
@section('title','Support Ticket')
@section('content')
<a href="{{ route('admin.support.index') }}">Back to tickets</a>
<h1 class="text-2xl font-bold">{{ $ticket->ticket_no }} — {{ $ticket->subject }}</h1>
<p>{{ $ticket->booking_no }} | {{ $ticket->employee_name }} | {{ $ticket->employee_email }} | {{ $ticket->category }} | {{ $ticket->status }}</p>
@foreach($messages as $m)<div class="bg-white rounded-xl p-4 my-3"><strong>{{ $m->actor_type }}</strong> — {{ $m->created_at }}<p class="whitespace-pre-wrap">{{ $m->body }}</p></div>@endforeach
{{ $messages->links() }}
@if($errors->any())<p>{{ $errors->first() }}</p>@endif
@if($ticket->status !== 'closed')<form method="POST" action="{{ route('admin.support.update',$ticket->id) }}" class="space-y-3">@csrf<select name="status">@foreach($states as $state)<option @selected($ticket->status===$state)>{{ $state }}</option>@endforeach</select><textarea class="block w-full border rounded p-3" name="message" maxlength="5000" placeholder="Reply to employee"></textarea><button class="bg-slate-900 text-white rounded p-3">Update ticket</button></form>@endif
@endsection
