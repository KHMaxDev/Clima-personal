@extends('layouts.app')

@section('title', 'Calendario')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <div>
            <h1 class="text-xl font-semibold heading">Calendario</h1>
            <p class="text-sm text-muted">
                Vista mensual, semanal y de lista de tus actividades.
            </p>
        </div>
        <a href="{{ $createUrl }}" class="btn-primary">
            Nueva actividad
        </a>
    </div>

    @php
        $props = [
            'events' => $events,
            'fetchUrl' => $calendarUrl,
        ];
    @endphp

    <div data-react-component="ActivityCalendar" data-react-props="{{ json_encode($props) }}"></div>
@endsection
