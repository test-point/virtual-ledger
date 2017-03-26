@if (count($errors) > 0)
    <div class="alert alert-danger">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
<form action="/transactions" method="post" id="transactionSearchFilterByForm">
    <div class="row">

        {{ csrf_field() }}

        <div class="form-group col-sm-6 col-md-6">
            <label for="filterProduct">Endpoint:</label>
            <input type="text" class="form-control" name="endpoint" value="d8607a45-b490-42ad-92e0-c35b533c6e9b">
        </div>

        <div class="form-group col-sm-6 col-md-6">
            <label for="filterProduct">Receiver ABN:</label>
            <select class="form-control" id="receiver_abn" name="receiver_abn">
                <option value="">- Choose ABN -</option>
                <option value="67008125522">67008125522</option>
                <option value="15101941940">15101941940</option>
                <option value="11002814548">11002814548</option>
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