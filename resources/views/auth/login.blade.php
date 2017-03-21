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
                                    <textarea id="token" type="token" class="form-control" name="token" value="{{ old('token') }}" required autofocus>
                                        eyJhbGciOiJSUzI1NiIsImtpZCI6ImFkZDkwZTg0YjVlY2ZlZWFkZDYwMmU0ZGQ3MTA4M2M4In0.eyJ1cm46b2FzaXM6bmFtZXM6dGM6ZWJjb3JlOnBhcnR5aWQtdHlwZTppc282NTIzIjpbeyIwMTUxIjoiMTIzMTIzMTIzIn1dLCJzdWIiOiIzOCIsImlzcyI6Imh0dHBzOi8vaWRwLnRlc3Rwb2ludC5pbyIsImFibiI6MTIzMTIzMTIzLCJhdF9oYXNoIjoiOXdLdUkyV1BDc1lxajRKT2lCWkwyUSIsImV4cCI6MTQ5MDE4NDY4NSwiYXV0aF90aW1lIjoxNDg5NzYwMDE4LCJpYXQiOjE0ODk4MjQ2ODUsImF1ZCI6IjQzMDU0NiJ9.acVAB18StVfGuzzAuC72QWbfZ2vqq9xQ1NYCjrJ6SihtV7BNecHBSQ28JBTxKPaeT51JPKGKXw_c6PfxkEG_lzbEFp-7_7LnyOVeaOE_XpDeumVGRcZZkule34BUz8FWWNNDVG8kkriuuDVQbreukfk9t7Jc-qBoBw3PQriqFuk
                                    </textarea>

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
