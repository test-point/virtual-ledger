<form action="/transactions" method="post" id="transactionSearchFilterByForm">
    <div class="row">

        {{ csrf_field() }}

        <div class="form-group col-sm-6 col-md-3">
            <label for="filterProduct">Receiver ABN:</label>
            <select class="form-control" id="receiver_abn" name="receiver_abn">
                <option value="">- Choose ABN -</option>
                <option value="67008125522">67008125522</option>
            </select>
        </div>

        <div class="form-group col-sm-6 col-md-3">
            <label for="filterSuite">Document:</label>
            <select class="form-control" id="document" name="document">
                <option value="">- Choose Document -</option>
                <option value="invoice">Example Invoice</option>
            </select>
        </div>
    </div>

    <div class="filter-box-footer">
        <button type="submit" class="btn btn-success btn-with-icon btn-confirm">Confirm</button>
        &nbsp;&nbsp;
        <button type="button" class="btn btn-default btn-with-icon btn-clear">Clear</button>
    </div>
</form>