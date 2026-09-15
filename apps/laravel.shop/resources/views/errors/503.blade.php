@extends('errors::minimal')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('message', __('Service Unavailable'))
@section('description', __('The application is currently undergoing maintenance. Please check back shortly.'))
