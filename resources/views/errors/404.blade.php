@extends('errors.layout', ['seconds' => 12])

@section('title', 'Page not found')
@section('code', '404')
@section('heading', '404 — Page not found')

@section('text')
    The link may be old, or the address may have a typo in it. Nothing has happened to your
    reservation — it is only this page that is missing.
@endsection

{{--
    The address that failed, shown rather than swallowed.

    Two reasons. Somebody who followed a bad link from elsewhere can copy it
    into a message to the studio, which is the only way a broken link ever gets
    fixed; and somebody who mistyped can usually see it at a glance.

    PATH ONLY, never the query string. A payment token, an OTP or a search term
    can all be in there, and this page is exactly the sort of thing people
    screenshot and send to somebody else.
--}}
@section('path', \Illuminate\Support\Str::limit(request()->path() === '/' ? '/' : '/' . request()->path(), 90))
