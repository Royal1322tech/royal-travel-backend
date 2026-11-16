@extends('admin.layouts.app')

@section('title', 'Hotel Search')

@section('content')
    @include('hotels.partials.search-results', [
        'hotels' => $hotels,
        'searchPerformed' => $searchPerformed,
        'searchAction' => route('admin.hotels.index'),
    ])
@endsection