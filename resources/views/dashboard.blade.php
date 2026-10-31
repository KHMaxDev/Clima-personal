@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    @php
        $props = [
            'dashboard' => $dashboard,
            'weatherUrl' => $weatherUrl,
            'locationsUrl' => $locationsUrl,
            'alternativesUrlTemplate' => $alternativesUrlTemplate,
            'weatherLabels' => trans('weather'),
            'userName' => auth()->user()->name,
        ];
    @endphp

    <div data-react-component="Dashboard" data-react-props="{{ json_encode($props) }}"></div>
@endsection
