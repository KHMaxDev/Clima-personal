@extends('layouts.app')

@section('title', 'Editar actividad')

@section('content')
    <h1 class="mb-4 text-xl font-semibold heading">Editar actividad</h1>

    @php
        $activityProps = [
            'id' => $activity->id,
            'title' => $activity->title,
            'description' => $activity->description,
            'category_id' => $activity->category_id,
            'location_name' => $activity->location_name,
            'latitude' => (float) $activity->latitude,
            'longitude' => (float) $activity->longitude,
            'timezone' => $activity->timezone,
            'start_at' => $activity->start_at->setTimezone($activity->timezone)->format('Y-m-d\TH:i'),
            'end_at' => $activity->end_at->setTimezone($activity->timezone)->format('Y-m-d\TH:i'),
        ];

        $props = [
            'action' => route('activities.update', $activity),
            'method' => 'PUT',
            'categories' => $categories,
            'activity' => $activityProps,
            'assessUrl' => route('activities.assess'),
            'locationsUrl' => route('locations.search'),
            'cancelUrl' => route('activities.show', $activity),
        ];
    @endphp

    <div data-react-component="ActivityForm" data-react-props="{{ json_encode($props) }}"></div>
@endsection
