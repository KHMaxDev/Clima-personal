@extends('layouts.app')

@section('title', 'ClimaQuest · Planifica según el clima')

@section('content')
    @php
        $props = [
            'authenticated' => auth()->check(),
            'dashboardUrl' => route('dashboard'),
            'registerUrl' => route('register'),
            'loginUrl' => route('login'),
        ];
    @endphp

    <div data-react-component="LandingExperience" data-react-props="{{ json_encode($props) }}"></div>
@endsection
