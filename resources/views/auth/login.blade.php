@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-default">
                    <div class="panel-heading">Login via idp.testpoint.io</div>
                    <div class="panel-body text-center">
                        <a href="{{ url('social.redirect', ['provider' => 'idp'], isHttps()) }}" class="btn">idp.testpoint.io</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="row text-center">
            <div class="col-md-8 col-md-offset-2">OR</div>
        </div>
        <div class="row">

            <div class="col-md-8 col-md-offset-2">

                <div class="panel panel-default">
                    <div class="panel-heading">Login via token</div>
                    <div class="panel-body">
                        <form id="login-form" class="form-horizontal" role="form" method="POST" action="{{ url('login', [], isHttps()) }}">
                            {{ csrf_field() }}

                            <div class="form-group{{ $errors->has('token') ? ' has-error' : '' }}">
                                <label for="email" class="col-md-4 control-label">Your token</label>

                                <div class="col-md-6">
                                    <textarea id="token" class="form-control" name="token" required autofocus>{{ old('token') }}</textarea>

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
