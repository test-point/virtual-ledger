@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">Login</div>
                    <div class="panel-body">
                        <form class="form-horizontal" role="form" method="POST" action="{{ route('login') }}">
                            {{ csrf_field() }}

                            <div class="form-group{{ $errors->has('token') ? ' has-error' : '' }}">
                                <label for="email" class="col-md-4 control-label">Your token</label>

                                <div class="col-md-6">
                                    <textarea id="token" type="token" class="form-control" name="token" value="{{ old('token') }}" required autofocus>eyJhbGciOiJSUzI1NiIsImtpZCI6ImFkZDkwZTg0YjVlY2ZlZWFkZDYwMmU0ZGQ3MTA4M2M4In0.eyJ1cm46b2FzaXM6bmFtZXM6dGM6ZWJjb3JlOnBhcnR5aWQtdHlwZTppc282NTIzIjpbeyIwMTUxIjoiMTIzMTIzMTIzIn1dLCJzdWIiOiIzOCIsImlzcyI6Imh0dHBzOi8vaWRwLnRlc3Rwb2ludC5pbyIsImFibiI6MTIzMTIzMTIzLCJhdF9oYXNoIjoiWGtzQ285V1FnaUhrOUFQS05Bem5EZyIsImV4cCI6MTQ5MDYxMDI1MCwiYXV0aF90aW1lIjoxNDkwMTE1MTg0LCJpYXQiOjE0OTAyNTAyNTAsImF1ZCI6IjQzMDU0NiJ9.k7LaYI65qYHCiUjq_wo_qu5SsqQbfxHKYzuByi7CtvWcOw3r8M3u0WDRNwo8luJFgxvV-KM1M6TbXXen7Aik6ROAHN9nA4NlqqIjGq7luWNiACPvciWEiRwUXpBanvH7C7lmZcFm37amBf5GSO5eaQQWGSGABOVvLbw5Pp4qFT8</textarea>

                                    @if ($errors->has('token'))
                                        <span class="help-block">
                                        <strong>{{ $errors->first('token') }}</strong>
                                    </span>
                                    @endif
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="col-md-8 col-md-offset-4">
                                    <button type="submit" class="btn btn-primary">
                                        Login
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
