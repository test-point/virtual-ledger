<form action="/transactions" method="post" id="transactionSearchFilterByForm">
    <div class="row">

        {{ csrf_field() }}

        <div class="form-group col-sm-6 col-md-6">
            <label for="filterProduct">Receiver ABN:</label>
            <select class="form-control" id="receiver_abn" name="receiver_abn">
                <option value="">- Choose ABN -</option>
                @foreach(\App\Abn::all() as $abnRow)
                    <option value="{{ $abnRow->abn }}" @if($abnRow->abn == $request->get('receiver_abn')) selected="selected"@endif>{{ $abnRow->abn }}</option>
                @endforeach
            </select>
        </div>

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
                        <option value="{{ $endpoint }}" @if($endpoint == $request->get('endpoint')) selected="selected"@endif>{{ $endpoint }}</option>
                    @endforeach
                @endif
            </select>
        </div>

        <div class="form-group col-sm-12 col-md-12">
            <label for="filterSuite">Document:</label>
            <textarea class="form-control" id="document" name="document"></textarea>
        </div>
    </div>

    <div class="filter-box-footer">
        <button type="submit" class="btn btn-success btn-with-icon btn-confirm">Confirm</button>
    </div>
</form>