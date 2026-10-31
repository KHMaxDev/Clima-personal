@extends('layouts.app')

@section('title', 'Pronóstico')

@section('content')
    @php
        $props = [
            'location' => $forecast['location'] ?? null,
            'weatherUrl' => $forecast['weatherUrl'] ?? null,
            'locationsUrl' => $forecast['locationsUrl'] ?? null,
            'weatherLabels' => trans('weather'),
        ];
    @endphp

    <div data-react-component="Forecast" data-react-props="{{ json_encode($props) }}"></div>
@endsection
