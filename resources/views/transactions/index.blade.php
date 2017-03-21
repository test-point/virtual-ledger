@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">Add new transaction</div>

                    <div class="panel-body">
                        @include('transactions.create')

                    </div>

                    <div class="panel-heading">Transactions</div>

                    <div class="panel-body">

                        <table class="table table-stripped">
                            <tr>
                                <td>ID</td>
                                <td>From</td>
                                <td>To</td>
                                <td>Message Hash</td>
                                <td>Payloads</td>
                                <td>Message</td>
                                <td>Message Type</td>
                                <td>Validation Status</td>
                            </tr>
                            @if(count($transactions))
                                @foreach($transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->id }}</td>
                                        <td>{{ $transaction->from_party }}</td>
                                        <td>{{ $transaction->to_party }}</td>
                                        <td>{{ $transaction->message_hash }}</td>
                                        <td>
                                            <a href="{{ $transaction->encrypted_payload }}" target="_blank">Encrypted</a>
                                            <br>
                                            <a href="{{ $transaction->decrypted_payload }}" target="_blank">Decrypted</a>
                                        </td>
                                        <td>{{ $transaction->notarized_message }}</td>
                                        <td>{{ $transaction->message_type }}</td>
                                        <td>{{ $transaction->validation_status }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td class="text-center" colspan="8">No transactions yet</td>
                                </tr>
                            @endif
                        </table>
                        <div class="pagination-wrapper">
                            {{ $transactions->appends($_GET)->render() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
