@extends('layouts.public')

@section('title', 'Shunno Art Cafe — Visits by reservation')
@section('description', 'An artist-run studio and evening cafe in Lalmatia, Dhaka. Clay, print and paint sessions, exhibitions and quiet corners for work. Every visit is arranged by prior reservation.')

@section('content')
    @include('public.landing.hero')
    @include('public.landing.atmosphere')
    @include('public.landing.intro')
    @include('public.landing.experiences')
    @include('public.landing.studio')
    @include('public.landing.how')
    @include('public.landing.philosophy')
    @include('public.landing.visiting')
    @include('public.landing.cta')
    @include('public.landing.contact')
@endsection
