@extends('layouts.app')

@section('abn_name') @if(Auth::user()->abn_name) <a class="navbar-brand"
                                                    href="https://dcp.testpoint.io/urn:oasis:names:tc:ebcore:partyid-type:iso6523:0151::{{ Auth::user()->name }}" target="_blank">for
    "{{ Auth::user()->abn_name }}"</a> @endif @endsection

@section('content')
    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">Add new transaction (This is TEST account only)</div>

                    <div class="panel-body" id="filters">
                        @include('transactions.create')

                    </div>

                    <div class="panel-heading">Transactions</div>

                    <div class="panel-body">

                        @foreach($conversations as $conversation)
                            <?php
                                $transactions = \App\Transaction::where('conversation_id', $conversation->conversation_id)->orderby('updated_at', 'desc')->get();
                                $collapsableId = md5($conversation->conversation_id);
                            ?>
                            <div class="panel-group" id="accordion" role="tablist" aria-multiselectable="true">
                                <div class="panel panel-default">
                                    <div class="panel-heading" role="tab" id="heading{{ $collapsableId }}">
                                        <h4 class="panel-title">
                                            <a role="button" data-toggle="collapse" data-parent="#accordion" href="#collapse{{ $collapsableId }}" aria-expanded="true" aria-controls="collapse{{ $collapsableId }}">
                                                <b>{{ $conversation->conversation_id }}</b> - {{ count($transactions) }} message(s)
                                            </a>
                                        </h4>
                                    </div>
                                    <div id="collapse{{ $collapsableId }}" class="panel-collapse collapse" role="tabpanel" aria-labelledby="heading{{ $collapsableId }}">
                                        <div class="panel-body">
                                           <table class="table table-stripped text-center">
                                                <tr>
                                                    <td>Timestamp</td>
                                                    <td>From</td>
                                                    <td>To</td>
                                                    <td>Message Hash</td>
                                                    <td>Notarized Message</td>
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
                                                                <span title="{{ $transaction->message_hash }}"></span>

                                                                <a class="btn" data-toggle="modal" data-target="#messageHashModal{{ $transaction->id }}">
                                                                    {{ substr($transaction->message_hash, 0, 6) }}
                                                                </a>

                                                                <!-- Modal -->
                                                                <div class="modal fade" id="messageHashModal{{ $transaction->id }}" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
                                                                    <div class="modal-dialog" role="document">
                                                                        <div class="modal-content">
                                                                            <div class="modal-header">
                                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span>
                                                                                </button>
                                                                                <h4 class="modal-title" id="myModalLabel">Message hash</h4>
                                                                            </div>
                                                                            <div class="modal-body">
                                                                               {{ $transaction->message_hash }}
                                                                            </div>
                                                                            <div class="modal-footer">
                                                                                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </td>

                                                            <td>
                                                                @if($transaction->validation_status == 'sent' && Storage::disk('s3')->exists(config('app.env') .'/'. $transaction->id . '/message.json'))
                                                                    <a href="/download/{{ $transaction->id . '/message.json' }}" target="_blank">Download</a>
                                                                @endif
                                                            </td>

                                                            <td>
                                                                @if($transaction->encripted_payload)
                                                                    <a href="/download/{{ $transaction->id.'/'.$transaction->encripted_payload }}" target="_blank">Encrypted</a>
                                                                @endif
                                                                <br>
                                                                @if($transaction->decripted_payload && Storage::disk('s3')->exists(config('app.env') .'/'. $transaction->id . '/' . $transaction->decripted_payload))
                                                                    <a href="/download/{{ $transaction->id.'/'.$transaction->decripted_payload }}" target="_blank">Decrypted</a>
                                                                @endif
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
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        @if(false)
                            <div class="pagination-wrapper">
                                {{ $transactions->appends($_GET)->render() }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@section('additional_js')
    <script>
        jQuery(document).ready(function () {
            jQuery('body').on('submit', '#transactionSearchFilterByForm', function (e) {
                e.preventDefault();
                jQuery('#loadingModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $('.has-error').removeClass('has-error');
                $('.error').remove();
                $('#transactionSearchFilterByForm').ajaxSubmit({
                    type: 'post',
                    dataType: 'json',
                    url: '/transactions',
                    success: function (data) {
                        jQuery('#loadingModal').modal('hide');
                        location.reload();
                    },
                    error: function (response) {
                        response = $.parseJSON(response.responseText);
                        $('.error').remove();
                        $.each(response, function (index, elem) {
//                            if(!$('#' + index).length && elem.join('') == "This receiver ABN doesn't have any active public key in DCP"){
//                                index = 'receiver_abn';
//                            }
                            if($('#' + index).length) {
                                $('#' + index).closest('.form-group').addClass('has-error');
                                $('#' + index).closest('.form-group').append(
                                    '<div class="error alert-danger">' + elem.join('<br>') + '</div>'
                                );
                            }
                        })
                        jQuery('#loadingModal').modal('hide');
                        $(window).scrollTop($('.error:first').offset().top);
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
                $('.has-error').removeClass('has-error');
                $('.error').remove();
                $.ajax({
                    type: 'get',
                    dataType: 'json',
                    url: '/transactions/filters',
                    data: $('#transactionSearchFilterByForm').serialize(),
                    success: function (data) {
                        $('#filters').html(data.html);
                        jQuery('#loadingModal').modal('hide');
                    },
                    error: function (response) {
                        response = $.parseJSON(response.responseText);
                        $('.error').remove();
                        $.each(response, function (index, elem) {
                            if($('#' + index).length) {
                                $('#' + index).closest('.form-group').addClass('has-error');
                                $('#' + index).closest('.form-group').append(
                                    '<span class="error alert-danger">' + elem.join('<br>') + '</span>'
                                );
                            }
                        })
                        jQuery('#loadingModal').modal('hide');
                    }
                })
            });
        });

        jQuery(document).ready(function () {
            jQuery(document).on('change', '#template_id', function (e) {
                jQuery('#loadingModal').modal({
                    backdrop: 'static',
                    keyboard: false
                });
                $('.has-error').removeClass('has-error');
                $('.error').remove();
                $.ajax({
                    type: 'get',
                    dataType: 'json',
                    url: '/transactions/get-template',
                    data: {
                        'template_id' : $('#template_id').val(),
                        'receiver_abn' : $('#receiver_abn').val(),
                    },
                    success: function (data) {
                        $('#document').val(data.html);
                        jQuery('#loadingModal').modal('hide');
                    },
                    error: function (response) {
                        jQuery('#loadingModal').modal('hide');
                    }
                })
            });
        });
    </script>
@endsection