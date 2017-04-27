@if(!empty($abnNotConfigured))
    <div class="alert alert-warning">
        ABN has no DCL entry and so is not able to receive messages.
        Please either create a new virtual ledger account for that ABN or
        hook up your own software using that ABN and tap-gw.testpoint.io services.
    </div>
@endif
<form action="/transactions" method="post" id="transactionSearchFilterByForm">
    <div class="row">

        {{ csrf_field() }}

        <div class="form-group col-sm-6 col-md-6">
            <label for="receiver_abn">Receiver ABN:</label>
            <input class="form-control" id="receiver_abn" name="receiver_abn" type="text" value="{{ $request->get('receiver_abn') }}"/>
        </div>

        @if($request->get('receiver_abn'))
            <div class="form-group col-sm-6 col-md-6">
                <label for="document_id">Document ID:</label>
                <select class="form-control" name="document_id" id="document_id" @if(!$documentIds) disabled="disabled"@endif>
                    <option value="">- Choose document id -</option>
                    @if($documentIds)
                        @foreach($documentIds as $documentId)
                            <option value="{{ $documentId }}" @if($documentId == $request->get('document_id')) selected="selected"@endif>{{ $documentId }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div class="form-group col-sm-12 col-md-12">
                <label for="endpoint">Endpoint:</label>
                <select class="form-control" name="endpoint" id="endpoint" @if(!$endpoints)disabled="disabled"@endif>
                    <option value="">- Choose endpoint -</option>
                    @if($endpoints)
                        @foreach($endpoints as $endpoint)
                            <?php $endpoint1 = explode(' - ', $endpoint);?>
                            <option value="{{ end($endpoint1) }}" @if($endpoint == $request->get('endpoint')) selected="selected"@endif>{{ $endpoint }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            <div class="form-group col-sm-12 col-md-12">
                <label for="filterSuite">Document:</label>
                <textarea class="form-control" id="document" name="document"></textarea>
            </div>
        @endif
    </div>

    <div class="filter-box-footer">
        <button type="submit" class="btn btn-success btn-with-icon btn-confirm">Confirm</button>
    </div>
</form>