@extends('b2b.layouts.app')

@section('title', 'Hotel Search')

@section('content')
    @include('hotels.partials.search-results', [
        'hotels' => $hotels,
        'searchPerformed' => $searchPerformed,
        'searchAction' => route('b2b.hotels.index'),
    ])
@endsection