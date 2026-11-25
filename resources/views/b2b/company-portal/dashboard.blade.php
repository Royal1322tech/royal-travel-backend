@extends('b2b.company-portal.layout')
@section('title','Company Dashboard')
@section('content')
<h1>Dashboard</h1><p>Overview of your company activity.</p>
<div class="cards">@foreach($stats as $label => $value)<section class="card"><div>{{ $label }}</div><div class="number">{{ number_format($value) }}</div></section>@endforeach</div>
@endsection
