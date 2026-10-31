@extends('layouts.app')

@section('title', 'Nueva actividad')

@section('content')
    <h1 class="mb-4 text-xl font-semibold heading">Nueva actividad</h1>

    @php
        $props = [
            'manual' => [
                'action' => route('activities.store'),
                'method' => 'POST',
                'categories' => $categories,
                'assessUrl' => route('activities.assess'),
                'locationsUrl' => route('locations.search'),
                'cancelUrl' => route('activities.index'),
            ],
            'generator' => $generator,
        ];
    @endphp

    <div data-react-component="ActivityCreate" data-react-props="{{ json_encode($props) }}"></div>
@endsection
