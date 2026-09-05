@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Verify Your Email Address') }}</div>

                <div class="card-body">
                    @if (session('resent'))
                        <div class="alert alert-success" role="alert">
                            {{ __('A fresh verification link has been sent to your email address.') }}
                        </div>
                    @endif

                    {{ __('Before proceeding, please check your email for a verification link.') }}
                    {{ __('If you did not receive the email') }},
                    {{-- A verificação de email está DESLIGADA nesta instalação: o
                         Auth::routes() não a inclui, portanto esta página não é servida
                         e o endereço do reenvio não existe. O botão só aparece se alguém
                         a ligar — sem isto, a página rebentava no dia em que fosse ligada. --}}
                    @if(\Illuminate\Support\Facades\Route::has('verification.resend'))
                    <form class="d-inline" method="POST" action="{{ route('verification.resend') }}">
                        @csrf
                        <button type="submit" class="btn btn-link p-0 m-0 align-baseline">{{ __('click here to request another') }}</button>.
                    </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
