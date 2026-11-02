@if (count($rows) === 0)
    <p class="muted">No ledger rows.</p>
@else
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>Wallet ID</th>
                    <th>Booking ID</th>
                    <th>Name / mobile</th>
                    <th>Type</th>
                    <th>Credit/Debit</th>
                    <th>Amount</th>
                    <th>Commission</th>
                    <th>Previous</th>
                    <th>New</th>
                    <th>Payment ref</th>
                    <th>Status</th>
                    <th>Date/time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ $row['transactionId'] }}</td>
                        <td>{{ $row['walletId'] }}</td>
                        <td>{{ $row['bookingId'] ?? '—' }}</td>
                        <td>{{ $row['ownerName'] ?? '—' }}@if (!empty($row['ownerPhone']))<br><span class="muted">{{ $row['ownerPhone'] }}</span>@endif @if (!empty($row['userId']))<br><span class="muted">#{{ $row['userId'] }}</span>@endif</td>
                        <td>{{ $row['transactionType'] }}</td>
                        <td>{{ $row['direction'] }}</td>
                        <td>₹{{ number_format($row['amountRupees'], 2) }}</td>
                        <td>₹{{ number_format($row['commissionRupees'], 2) }}</td>
                        <td>₹{{ number_format(($row['previousBalancePaise'] ?? 0) / 100, 2) }}</td>
                        <td>₹{{ number_format(($row['newBalancePaise'] ?? 0) / 100, 2) }}</td>
                        <td>{{ $row['paymentReference'] ?? '—' }}</td>
                        <td>{{ $row['status'] }}</td>
                        <td>{{ $row['createdAt'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if (is_object($rows) && method_exists($rows, 'links'))
        <div style="margin-top:14px">{{ $rows->links('pagination.admin') }}</div>
    @endif
@endif
