@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">Add new transaction</div>

                    <div class="panel-body" id="filters">
                        @include('transactions.create')

                    </div>

                    <div class="panel-heading">Transactions</div>

                    <div class="panel-body">

                        <table class="table table-stripped text-center">
                            <tr>
                                <td>Timestamp</td>
                                <td>From</td>
                                <td>To</td>
                                <td>Message Hash</td>
                                <td>Payloads</td>
                                <td>Message Type</td>
                                <td>Validation Status</td>
                            </tr>
                            @if(count($transactions))
                                @foreach($transactions as $transaction)
                                    <tr>
                                        <td>{{ $transaction->created_at }}</td>
                                        <td>{{ $transaction->from_party }}</td>
                                        <td>{{ $transaction->to_party }}</td>
                                        <td>
                                            <span title="{{ $transaction->message_hash }}">{{ substr($transaction->message_hash, 0, 10) }}</span>
                                        </td>
                                        <td>
                                            <a href="/download/{{ $transaction->encripted_payload }}" target="_blank">Encrypted</a>
                                            <br>
                                            <a href="/download/{{ $transaction->decripted_payload }}" target="_blank">Decrypted</a>
                                        </td>
                                        <td>{{ $transaction->message_type }}</td>
                                        <td>
                                            <span>{{ $transaction->validation_status }}</span>
                                            @if($transaction->validation_status == 'error')
                                                <br>({{ $transaction->validation_message }})
                                            @endif
                                        </td>
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
@section('additional_js')
    <script>
        jQuery(document).ready(function () {
            jQuery(document).on('submit', '#transactionSearchFilterByForm', function (e) {
                e.preventDefault();
                jQuery('#loadingModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $('.has-error').removeClass('has-error');
                $.ajax({
                    type: 'post',
                    dataType: 'json',
                    url: '/transactions',
                    data: $('#transactionSearchFilterByForm').serialize(),
                    success: function (data) {
                        jQuery('#loadingModal').modal('hide');
                        location.reload();
                    },
                    error: function (response) {
                        response = $.parseJSON(response.responseText);
                        $.each(response, function (index, elem) {
                            $('#' + index).closest('.form-group').addClass('has-error');
                        })
                        jQuery('#loadingModal').modal('hide');
                    }
                })
            });
        });
        jQuery(document).ready(function () {
            jQuery(document).on('change', '#receiver_abn, #document_id', function (e) {
                jQuery('#loadingModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $.ajax({
                    type: 'get',
                    dataType: 'json',
                    url: '/transactions/filters',
                    data: $('#transactionSearchFilterByForm').serialize(),
                    success: function (data) {
                        $('#filters').html(data.html);
                        jQuery('#loadingModal').modal('hide');
                    }
                })
            });
        });
    </script>
@endsection