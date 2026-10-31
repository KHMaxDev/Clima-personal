@extends('layouts.app')

@section('title', 'Asistente IA')

@section('content')
    @php
        $props = [
            'activities' => $assistant['activities'] ?? [],
            'generateUrlTemplate' => $assistant['generateUrlTemplate'] ?? null,
            'aiEnabled' => $assistant['aiEnabled'] ?? false,
            'model' => $assistant['model'] ?? null,
        ];
    @endphp

    <div data-react-component="Assistant" data-react-props="{{ json_encode($props) }}"></div>
@endsection
